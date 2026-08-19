/**
 * Pure Essence Vita — Shared store (cart + orders API)
 */
const PEV = (() => {
  const CART_KEY = "pev_cart_v1";
  const ORDERS_LOCAL_KEY = "pev_orders_v1";
  const API_URL = "api/orders.php";
  const ADMIN_KEY = "pev_admin_2026";
  const SHIPPING_FEE = 2000;
  const FREE_SHIPPING_FROM = 50000;

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

  function genId() {
    const t = Date.now().toString(36).toUpperCase();
    const r = Math.random().toString(36).slice(2, 8).toUpperCase();
    return `PEV-${t}-${r}`;
  }

  function genTracking() {
    return "TRK" + Math.random().toString(36).slice(2, 12).toUpperCase();
  }

  async function api(action, payload = {}, method = "GET") {
    const url = new URL(API_URL, window.location.href);
    if (method === "GET") {
      url.searchParams.set("action", action);
      Object.entries(payload).forEach(([k, v]) => {
        if (v != null && v !== "") url.searchParams.set(k, v);
      });
      const res = await fetch(url.toString(), { headers: { Accept: "application/json" } });
      return res.json();
    }
    url.searchParams.set("action", action);
    const res = await fetch(url.toString(), {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ action, ...payload }),
    });
    return res.json();
  }

  async function createOrder(orderData) {
    try {
      const result = await api("create", orderData, "POST");
      if (result && result.ok && result.order) {
        const local = loadLocalOrders();
        local.unshift(result.order);
        saveLocalOrders(local.slice(0, 50));
        return result.order;
      }
    } catch (err) {
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
      id: genId(),
      trackingNumber: genTracking(),
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
      const result = await api("get", { id });
      if (result?.ok && result.order) return result.order;
    } catch (_) {}
    return loadLocalOrders().find((o) => o.id === id || o.trackingNumber === id) || null;
  }

  async function searchOrders({ phone = "", email = "" } = {}) {
    try {
      const result = await api("search", { phone, email });
      if (result?.ok) return result.orders || [];
    } catch (_) {}
    const local = loadLocalOrders();
    const p = phone.replace(/\D+/g, "");
    const e = email.toLowerCase().trim();
    return local.filter((o) => {
      const op = String(o.customer?.phone || "").replace(/\D+/g, "");
      const oe = String(o.customer?.email || "").toLowerCase();
      return (p && (op.endsWith(p) || p.endsWith(op) || op === p)) || (e && oe === e);
    });
  }

  async function listOrders() {
    try {
      const result = await api("list");
      if (result?.ok) return result.orders || [];
    } catch (_) {}
    return loadLocalOrders();
  }

  async function updateStatus(id, status, note = "") {
    try {
      const result = await api("update_status", { id, status, note, adminKey: ADMIN_KEY }, "POST");
      if (result?.ok && result.order) {
        syncLocal(result.order);
        return result.order;
      }
    } catch (_) {}
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
      const result = await api("confirm_delivery", { id, phone }, "POST");
      if (result?.ok && result.order) {
        syncLocal(result.order);
        return result.order;
      }
      if (result && result.ok === false) throw new Error(result.error || "Erreur");
    } catch (err) {
      if (err.message && !String(err.message).includes("Failed")) throw err;
    }
    const order = await getOrder(id);
    if (!order) throw new Error("Commande introuvable");
    if (!["delivered", "out_for_delivery", "in_transit"].includes(order.status)) {
      throw new Error("La commande n'est pas encore en livraison / livrée");
    }
    return updateStatusLocal(id, "completed", "Le client a confirmé la réception de la commande");
  }

  function syncLocal(order) {
    const orders = loadLocalOrders();
    const idx = orders.findIndex((o) => o.id === order.id);
    if (idx >= 0) orders[idx] = order;
    else orders.unshift(order);
    saveLocalOrders(orders);
  }

  function statusIndex(status) {
    const i = STATUS_FLOW.indexOf(status);
    return i < 0 ? 0 : i;
  }

  function simulatePayment(method) {
    return new Promise((resolve, reject) => {
      const delay = 1600 + Math.random() * 1200;
      setTimeout(() => {
        if (Math.random() < 0.04) {
          reject(new Error("Paiement refusé. Veuillez réessayer ou choisir un autre moyen."));
          return;
        }
        resolve({
          paid: true,
          method,
          reference: "PAY-" + Math.random().toString(36).slice(2, 10).toUpperCase(),
        });
      }, delay);
    });
  }

  return {
    CART_KEY,
    ADMIN_KEY,
    STATUS_FLOW,
    STATUS_LABELS,
    PAYMENT_METHODS,
    SHIPPING_FEE,
    FREE_SHIPPING_FROM,
    formatPrice,
    formatDate,
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
  };
})();
