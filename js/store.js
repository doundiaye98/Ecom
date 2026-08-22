/**
 * Pure Essence Vita — Shared store (cart + orders API)
 */
const PEV = (() => {
  const CART_KEY = "pev_cart_v1";
  const ORDERS_LOCAL_KEY = "pev_orders_v1";
  const ORDERS_API = "api/orders.php";
  const SETTINGS_API = "api/settings.php";
  const AUTH_API = "api/auth.php";
  const CUSTOMER_API = "api/customer.php";
  const PAYMENTS_API = "api/payments.php";

  let SHIPPING_FEE = 2000;
  let FREE_SHIPPING_FROM = 50000;
  let SITE_SETTINGS = {};
  let csrfToken = null;

  function setCsrfToken(token) {
    csrfToken = token || null;
  }

  const STATUS_FLOW = [
    "pending_payment",
    "paid",
    "processing",
    "shipped",
    "in_transit",
    "out_for_delivery",
    "delivered",
    "completed",
  ];

  const STATUS_LABELS = {
    pending_payment: "En attente de paiement",
    paid: "Paiement confirmé",
    processing: "Préparation de la commande",
    shipped: "Commande expédiée",
    in_transit: "En transit",
    out_for_delivery: "En cours de livraison",
    delivered: "Livrée — en attente de validation",
    completed: "Livraison validée",
    cancelled: "Annulée",
  };

  const PAYMENT_METHODS = {
    wave: { label: "Wave", icon: "W" },
    orange: { label: "Orange Money", icon: "OM" },
    card: { label: "Carte bancaire", icon: "CB" },
    cod: { label: "Paiement à la livraison", icon: "COD" },
  };

  function formatPrice(n) {
    return new Intl.NumberFormat("fr-FR").format(n) + " FCFA";
  }

  function formatDate(iso) {
    try {
      return new Intl.DateTimeFormat("fr-FR", {
        dateStyle: "medium",
        timeStyle: "short",
      }).format(new Date(iso));
    } catch {
      return iso;
    }
  }

  async function loadSettings() {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000);
    try {
      const res = await fetch(`${SETTINGS_API}?action=public`, {
        headers: { Accept: "application/json" },
        signal: controller.signal,
      });
      const data = await res.json();
      if (data.ok && data.settings) {
        SITE_SETTINGS = data.settings;
        SHIPPING_FEE = Number(data.settings.shippingFee) || SHIPPING_FEE;
        FREE_SHIPPING_FROM = Number(data.settings.freeShippingFrom) || FREE_SHIPPING_FROM;
      }
    } catch (err) {
      console.warn("Paramètres indisponibles, valeurs par défaut.", err);
    } finally {
      clearTimeout(timeoutId);
    }
    return SITE_SETTINGS;
  }

  function loadCart() {
    try {
      return JSON.parse(localStorage.getItem(CART_KEY)) || [];
    } catch {
      return [];
    }
  }

  function saveCart(cart) {
    localStorage.setItem(CART_KEY, JSON.stringify(cart));
  }

  function clearCart() {
    saveCart([]);
  }

  function shippingFee(subtotal) {
    return subtotal >= FREE_SHIPPING_FROM ? 0 : SHIPPING_FEE;
  }

  function loadLocalOrders() {
    try {
      return JSON.parse(localStorage.getItem(ORDERS_LOCAL_KEY)) || [];
    } catch {
      return [];
    }
  }

  function saveLocalOrders(orders) {
    localStorage.setItem(ORDERS_LOCAL_KEY, JSON.stringify(orders));
  }

  async function api(url, action, payload = {}, method = "GET", useCredentials = false, retries = 3) {
    let lastError = null;
    for (let attempt = 0; attempt < retries; attempt++) {
      try {
        const endpoint = new URL(url, window.location.href);
        if (method === "GET") {
          endpoint.searchParams.set("action", action);
          Object.entries(payload).forEach(([k, v]) => {
            if (v != null && v !== "") endpoint.searchParams.set(k, v);
          });
          const res = await fetch(endpoint.toString(), {
            headers: { Accept: "application/json" },
            credentials: useCredentials ? "same-origin" : "same-origin",
          });
          if (res.status === 429) {
            await new Promise((r) => setTimeout(r, 1500 * (attempt + 1)));
            continue;
          }
          if (res.status >= 500 && attempt < retries - 1) {
            await new Promise((r) => setTimeout(r, 800 * (attempt + 1)));
            continue;
          }
          return res.json();
        }
        endpoint.searchParams.set("action", action);
        const headers = { "Content-Type": "application/json", Accept: "application/json" };
        if (useCredentials && csrfToken) {
          headers["X-CSRF-Token"] = csrfToken;
        }
        const body = { action, ...payload };
        if (useCredentials && csrfToken) {
          body._csrf = csrfToken;
        }
        const res = await fetch(endpoint.toString(), {
          method: "POST",
          headers,
          credentials: useCredentials ? "same-origin" : "same-origin",
          body: JSON.stringify(body),
        });
        if (res.status === 429) {
          await new Promise((r) => setTimeout(r, 1500 * (attempt + 1)));
          continue;
        }
        if (res.status >= 500 && attempt < retries - 1) {
          await new Promise((r) => setTimeout(r, 800 * (attempt + 1)));
          continue;
        }
        return res.json();
      } catch (err) {
        lastError = err;
        if (attempt < retries - 1) {
          await new Promise((r) => setTimeout(r, 800 * (attempt + 1)));
        }
      }
    }
    throw lastError || new Error("Connexion au serveur impossible");
  }

  function isNetworkError(err) {
    const msg = String(err?.message || err || "");
    return (
      err instanceof TypeError ||
      msg.includes("Failed to fetch") ||
      msg.includes("NetworkError") ||
      msg.includes("Load failed")
    );
  }

  async function createOrder(orderData) {
    const payload = {
      ...orderData,
      items: (orderData.items || []).map((item) => ({
        id: item.id,
        qty: item.qty,
      })),
    };

    try {
      const result = await api(ORDERS_API, "create", payload, "POST");
      if (result && result.ok && result.order) {
        syncLocal(result.order);
        return result.order;
      }
      if (result && result.error) {
        throw new Error(result.error);
      }
      throw new Error("Réponse serveur invalide");
    } catch (err) {
      if (!isNetworkError(err)) {
        throw err;
      }
      console.warn("API indisponible, sauvegarde locale", err);
    }
    return createOrderLocal(orderData);
  }

  function createOrderLocal(orderData) {
    const now = new Date().toISOString();
    const method = orderData.payment?.method || "card";
    const paid = !!orderData.payment?.paid || method === "cod";
    let status = paid ? (method === "cod" ? "processing" : "paid") : "pending_payment";

    const timeline = [
      {
        status: "pending_payment",
        label: STATUS_LABELS.pending_payment,
        at: now,
        note: "Commande créée",
      },
    ];
    if (status === "paid" || status === "processing") {
      timeline.push({
        status: "paid",
        label: STATUS_LABELS.paid,
        at: now,
        note:
          method === "cod"
            ? "Paiement à la livraison sélectionné"
            : `Paiement reçu via ${method.toUpperCase()}`,
      });
    }
    if (status === "processing") {
      timeline.push({
        status: "processing",
        label: STATUS_LABELS.processing,
        at: now,
        note: "Commande en cours de préparation",
      });
    }

    const order = {
      id: "PEV-LOCAL-" + Date.now().toString(36).toUpperCase(),
      trackingNumber: "TRK" + Math.random().toString(36).slice(2, 12).toUpperCase(),
      createdAt: now,
      updatedAt: now,
      status,
      items: orderData.items,
      subtotal: orderData.subtotal,
      shippingFee: orderData.shipping?.fee || 0,
      total: orderData.total,
      currency: "XOF",
      customer: orderData.customer,
      payment: {
        method,
        paid,
        reference: orderData.payment?.reference || "PAY-" + Math.random().toString(36).slice(2, 10).toUpperCase(),
        paidAt: paid ? now : null,
      },
      shipping: {
        carrier: "Pure Essence Express",
        estimatedDays: 3,
        fee: orderData.shipping?.fee || 0,
      },
      timeline,
      deliveryConfirmedAt: null,
    };

    const local = loadLocalOrders();
    local.unshift(order);
    saveLocalOrders(local);
    return order;
  }

  async function getOrder(id) {
    try {
      const result = await api(ORDERS_API, "get", { id }, "GET", true);
      if (result?.ok && result.order) return result.order;
      if (result?.error) throw new Error(result.error);
    } catch (err) {
      if (err.message && !String(err.message).includes("Failed")) throw err;
    }
    return null;
  }

  async function searchOrders() {
    return listOrders();
  }

  async function listOrders() {
    try {
      const result = await api(ORDERS_API, "mine", {}, "GET", true);
      if (result?.ok) return result.orders || [];
      if (result?.error && result.error.includes("Connexion")) return [];
    } catch (_) {}
    return [];
  }

  async function updateStatus(id, status, note = "") {
    try {
      const result = await api(ORDERS_API, "update_status", { id, status, note }, "POST", true);
      if (result?.ok && result.order) {
        syncLocal(result.order);
        return result.order;
      }
      if (result?.error) throw new Error(result.error);
    } catch (err) {
      if (err.message && !String(err.message).includes("Failed")) throw err;
    }
    return updateStatusLocal(id, status, note);
  }

  function updateStatusLocal(id, status, note = "") {
    const orders = loadLocalOrders();
    const idx = orders.findIndex((o) => o.id === id);
    if (idx < 0) return null;
    const now = new Date().toISOString();
    const order = orders[idx];
    order.status = status;
    order.updatedAt = now;
    if (status === "paid") {
      order.payment.paid = true;
      order.payment.paidAt = now;
    }
    if (status === "completed") order.deliveryConfirmedAt = now;
    order.timeline = order.timeline || [];
    order.timeline.push({
      status,
      label: STATUS_LABELS[status] || status,
      at: now,
      note: note || STATUS_LABELS[status] || status,
    });
    orders[idx] = order;
    saveLocalOrders(orders);
    return order;
  }

  async function confirmDelivery(id, phone = "") {
    try {
      const result = await api(ORDERS_API, "confirm_delivery", { id, phone }, "POST", true);
      if (result?.ok && result.order) {
        syncLocal(result.order);
        return result.order;
      }
      if (result && result.ok === false) throw new Error(result.error || "Erreur");
    } catch (err) {
      if (err.message && !String(err.message).includes("Failed")) throw err;
    }
    throw new Error("Connexion requise ou commande introuvable");
  }

  function syncLocal(order) {
    const orders = loadLocalOrders();
    const idx = orders.findIndex((o) => o.id === order.id);
    if (idx >= 0) orders[idx] = order;
    else orders.unshift(order);
    saveLocalOrders(orders.slice(0, 50));
  }

  function statusIndex(status) {
    const i = STATUS_FLOW.indexOf(status);
    return i < 0 ? 0 : i;
  }

  async function adminLogin(username, password) {
    const result = await api(AUTH_API, "login", { username, password }, "POST", true);
    if (result?.csrfToken) setCsrfToken(result.csrfToken);
    return result;
  }

  async function adminLogout() {
    const result = await api(AUTH_API, "logout", {}, "POST", true);
    setCsrfToken(null);
    return result;
  }

  async function checkAdmin() {
    const result = await api(AUTH_API, "check", {}, "GET", true);
    if (result?.csrfToken) setCsrfToken(result.csrfToken);
    return result;
  }

  async function customerRegister(payload) {
    const result = await api(CUSTOMER_API, "register", payload, "POST", true);
    if (result?.csrfToken) setCsrfToken(result.csrfToken);
    return result;
  }

  async function customerLogin(phone, password) {
    const result = await api(CUSTOMER_API, "login", { phone, password }, "POST", true);
    if (result?.csrfToken) setCsrfToken(result.csrfToken);
    return result;
  }

  async function customerLogout() {
    const result = await api(CUSTOMER_API, "logout", {}, "POST", true);
    setCsrfToken(null);
    return result;
  }

  async function customerCheck() {
    const result = await api(CUSTOMER_API, "check", {}, "GET", true);
    if (result?.csrfToken) setCsrfToken(result.csrfToken);
    return result;
  }

  async function customerUpdateProfile(payload) {
    return api(CUSTOMER_API, "profile", payload, "POST", true);
  }

  function simulatePayment(method) {
    return processPayment({ method, amount: 1, phone: "221770000000", customerName: "Test" });
  }

  async function processPayment({ method, amount, phone = "", customerName = "", orderRef = "" }) {
    const payload = { method, amount, phone, customerName, orderRef };
    const res = await fetch(`${PAYMENTS_API}?action=initiate`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify(payload),
    });
    const data = await res.json();
    if (!data.ok) {
      throw new Error(data.error || "Paiement échoué");
    }
    return data.payment;
  }

  async function verifyPayment(method, reference) {
    const res = await fetch(
      `${PAYMENTS_API}?action=verify&method=${encodeURIComponent(method)}&reference=${encodeURIComponent(reference)}`,
      { headers: { Accept: "application/json" } }
    );
    const data = await res.json();
    if (!data.ok) {
      throw new Error(data.error || "Vérification impossible");
    }
    return data.payment;
  }

  async function pollPayment(method, reference, { attempts = 12, delayMs = 2500 } = {}) {
    for (let i = 0; i < attempts; i++) {
      const result = await verifyPayment(method, reference);
      if (result.paid) {
        return result;
      }
      await new Promise((r) => setTimeout(r, delayMs));
    }
    throw new Error("Paiement non confirmé. Réessayez ou contactez le support.");
  }

  async function checkPaymentDeployment() {
    const res = await fetch(`${PAYMENTS_API}?action=check`, { headers: { Accept: "application/json" } });
    const data = await res.json();
    return data.deployment || null;
  }

  return {
    CART_KEY,
    STATUS_FLOW,
    STATUS_LABELS,
    PAYMENT_METHODS,
    get SHIPPING_FEE() {
      return SHIPPING_FEE;
    },
    get FREE_SHIPPING_FROM() {
      return FREE_SHIPPING_FROM;
    },
    get settings() {
      return SITE_SETTINGS;
    },
    formatPrice,
    formatDate,
    loadSettings,
    loadCart,
    saveCart,
    clearCart,
    shippingFee,
    createOrder,
    getOrder,
    searchOrders,
    listOrders,
    updateStatus,
    confirmDelivery,
    statusIndex,
    simulatePayment,
    processPayment,
    verifyPayment,
    pollPayment,
    checkPaymentDeployment,
    adminLogin,
    adminLogout,
    checkAdmin,
    customerRegister,
    customerLogin,
    customerLogout,
    customerCheck,
    customerUpdateProfile,
  };
})();
