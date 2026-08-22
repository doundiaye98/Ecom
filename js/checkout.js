/* Checkout flow — shipping → payment → confirmation (Wave / OM / COD — Sénégal) */
(function () {
  const PENDING_KEY = "pev_pending_checkout";
  const cart = PEV.loadCart();
  let step = 1;
  let customer = null;
  let createdOrder = null;

  const $ = (s) => document.querySelector(s);
  const $$ = (s) => [...document.querySelectorAll(s)];

  function toast(msg) {
    const el = $("#toast");
    el.textContent = msg;
    el.classList.add("is-show");
    setTimeout(() => el.classList.remove("is-show"), 3200);
  }

  function isValidSnPhone(phone) {
    const d = String(phone).replace(/\D/g, "");
    if (/^2217\d{8}$/.test(d)) return true;
    if (/^7\d{8}$/.test(d)) return true;
    if (/^0\d{9}$/.test(d)) return true;
    return false;
  }

  function buildLines() {
    return cart
      .map((item) => {
        const p = PRODUCTS.find((x) => x.id === item.id);
        if (!p) return null;
        return {
          id: p.id,
          name: p.name,
          price: p.price,
          qty: item.qty,
          image: p.image,
        };
      })
      .filter(Boolean);
  }

  function totals() {
    const items = buildLines();
    const subtotal = items.reduce((s, i) => s + i.price * i.qty, 0);
    const shipping = PEV.shippingFee(subtotal);
    return { items, subtotal, shipping, total: subtotal + shipping };
  }

  function renderSummary() {
    const { items, subtotal, shipping, total } = totals();
    const box = $("#summaryItems");
    if (!items.length) {
      box.innerHTML = `<p class="muted">Panier vide. <a href="index.php#produits">Retour boutique</a></p>`;
      $("#payBtn") && ($("#payBtn").disabled = true);
    } else {
      box.innerHTML = items
        .map(
          (i) => `
        <div class="summary-item">
          <img src="${encodeURI(i.image)}" alt="" />
          <div>
            <strong>${escapeHtml(i.name)}</strong>
            <span>× ${i.qty}</span>
          </div>
          <em>${PEV.formatPrice(i.price * i.qty)}</em>
        </div>`
        )
        .join("");
    }
    $("#sumSubtotal").textContent = PEV.formatPrice(subtotal);
    $("#sumShipping").textContent = shipping === 0 ? "Gratuite" : PEV.formatPrice(shipping);
    $("#sumTotal").textContent = PEV.formatPrice(total);
    $("#shippingNote").textContent =
      shipping === 0
        ? "Livraison offerte sur cette commande"
        : `Livraison gratuite dès ${PEV.formatPrice(PEV.FREE_SHIPPING_FROM)}`;
  }

  function setStep(n) {
    step = n;
    $$(".checkout-panel").forEach((p) => {
      p.hidden = p.id !== `step${n}`;
      p.classList.toggle("is-active", p.id === `step${n}`);
    });
    $$(".checkout-steps .step").forEach((s) => {
      const sn = Number(s.dataset.step);
      s.classList.toggle("is-active", sn === n);
      s.classList.toggle("is-done", sn < n);
    });
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  function selectedMethod() {
    return document.querySelector('input[name="payMethod"]:checked')?.value || "wave";
  }

  function syncPayUI() {
    const method = selectedMethod();
    $$(".pay-card").forEach((c) => c.classList.toggle("is-selected", c.querySelector("input").checked));
    $$(".pay-box").forEach((b) => {
      b.hidden = b.dataset.for !== method;
    });
    $("#payBtn").textContent = method === "cod" ? "Confirmer la commande" : "Payer maintenant";
  }

  function showPayOverlay(title, text) {
    $("#payOverlay").hidden = false;
    $("#payOverlayTitle").textContent = title;
    $("#payOverlayText").textContent = text;
  }

  function hidePayOverlay() {
    $("#payOverlay").hidden = true;
  }

  function savePendingCheckout(data) {
    sessionStorage.setItem(PENDING_KEY, JSON.stringify(data));
  }

  function loadPendingCheckout() {
    try {
      return JSON.parse(sessionStorage.getItem(PENDING_KEY) || "null");
    } catch {
      return null;
    }
  }

  function clearPendingCheckout() {
    sessionStorage.removeItem(PENDING_KEY);
  }

  function applyPaymentMethods() {
    const cfg = PEV.settings?.payments || {};
    const methods = cfg.methods || ["wave", "orange", "cod"];
    $$(".pay-card[data-method]").forEach((card) => {
      card.hidden = !methods.includes(card.dataset.method);
    });
    const visible = $$('.pay-card:not([hidden]) input[type="radio"]');
    if (visible.length && !visible.some((r) => r.checked)) {
      visible[0].checked = true;
    }
    syncPayUI();

    const banner = $("#paymentModeBanner");
    if (banner) {
      const mode = cfg.mode || "sandbox";
      const isSandbox = mode === "sandbox";
      banner.hidden = false;
      banner.className = "payment-mode-banner " + (isSandbox ? "is-sandbox" : "is-production");
      banner.innerHTML = isSandbox
        ? "<strong>Mode test</strong> — Paiements simulés. Passez <code>PAYMENT_MODE=production</code> dans .env avant le déploiement."
        : "<strong>Mode production</strong> — Paiements réels Wave / Orange Money (FCFA).";
    }

    if (cfg.wave?.sandbox && $("#waveSandboxNote")) {
      $("#waveSandboxNote").hidden = false;
    }
    if (cfg.orange?.sandbox && $("#orangeSandboxNote")) {
      $("#orangeSandboxNote").hidden = false;
    }
  }

  async function finalizeOrder(orderPayload, payment) {
    showPayOverlay("Finalisation…", "Enregistrement de votre commande et génération du suivi.");
    const order = await PEV.createOrder({
      ...orderPayload,
      payment,
    });
    createdOrder = order;
    PEV.clearCart();
    clearPendingCheckout();
    hidePayOverlay();
    $("#okOrderId").textContent = order.id;
    $("#okTracking").textContent = order.trackingNumber;
    $("#okTrackLink").href = `espace-client.php?view=order&id=${encodeURIComponent(order.id)}`;
    setStep(3);
    history.replaceState({}, "", "checkout.php");
    toast(order.status === "pending_payment" ? "Commande enregistrée — paiement en attente" : "Commande confirmée");
  }

  async function processPayment() {
    const { items, subtotal, shipping, total } = totals();
    if (!items.length) {
      toast("Panier vide");
      return;
    }
    if (!customer) {
      toast("Complétez d'abord l'adresse");
      setStep(1);
      return;
    }

    const method = selectedMethod();
    const orderRef = "PEV-" + Date.now().toString(36).toUpperCase();
    const isProduction = PEV.settings?.payments?.mode === "production";

    const phone =
      method === "wave"
        ? $("#wavePhone").value.trim()
        : method === "orange"
          ? $("#orangePhone").value.trim()
          : customer.phone;

    if (method === "wave" && !phone) {
      toast("Entrez votre numéro Wave");
      return;
    }
    if (method === "orange" && !phone) {
      toast("Entrez votre numéro Orange Money");
      return;
    }
    if ((method === "wave" || method === "orange") && !isValidSnPhone(phone)) {
      toast("Numéro sénégalais invalide (ex. 77 123 45 67)");
      return;
    }

    const orderPayload = {
      items,
      subtotal,
      total,
      customer,
      shipping: { fee: shipping, estimatedDays: 3, carrier: "Pure Essence Express" },
    };

    try {
      if (method === "cod") {
        showPayOverlay("Création de la commande…", "Paiement à la livraison sélectionné.");
        await finalizeOrder(orderPayload, {
          method: "cod",
          paid: false,
          reference: "COD-" + Date.now().toString(36).toUpperCase(),
          status: "pending",
        });
        return;
      }

      showPayOverlay(
        "Paiement en cours…",
        `Connexion ${PEV.PAYMENT_METHODS[method]?.label || method}…`
      );

      const result = await PEV.processPayment({
        method,
        amount: total,
        phone,
        customerName: customer.name,
        orderRef,
      });

      // Redirection Wave / Orange Money (production)
      if (result.checkoutUrl && !result.paid) {
        savePendingCheckout({
          ...orderPayload,
          method,
          orderRef,
          paymentReference: result.reference,
          phone,
        });
        showPayOverlay("Redirection…", "Ouverture de Wave / Orange Money pour valider le paiement.");
        window.location.href = result.checkoutUrl;
        return;
      }

      let paid = Boolean(result.paid);
      let reference = result.reference || orderRef;
      let status = result.status || (paid ? "succeeded" : "pending");

      if (!paid && isProduction) {
        showPayOverlay("Vérification…", "Confirmation du paiement auprès de l'agrégateur…");
        const verified = await PEV.pollPayment(method, reference, { attempts: 8, delayMs: 2000 });
        paid = Boolean(verified.paid);
        status = verified.status || status;
      }

      if (isProduction && !paid) {
        throw new Error("Paiement non confirmé. Réessayez ou choisissez un autre moyen.");
      }

      await finalizeOrder(orderPayload, {
        method,
        paid,
        reference,
        status,
        sandbox: Boolean(result.sandbox),
      });
    } catch (err) {
      hidePayOverlay();
      toast(err.message || "Échec du paiement");
    }
  }

  async function resumeAfterRedirect() {
    const params = new URLSearchParams(location.search);
    const paymentMethod = params.get("payment");
    const status = params.get("status");
    const ref = params.get("ref");

    if (!paymentMethod || !ref) return;

    const pending = loadPendingCheckout();
    if (!pending || pending.orderRef !== ref) {
      if (status === "error" || status === "cancel") {
        toast("Paiement annulé");
        history.replaceState({}, "", "checkout.php");
      }
      return;
    }

    customer = pending.customer;

    if (status === "error" || status === "cancel") {
      clearPendingCheckout();
      toast("Paiement annulé ou échoué");
      history.replaceState({}, "", "checkout.php");
      setStep(2);
      return;
    }

    try {
      showPayOverlay("Vérification du paiement…", "Confirmation auprès de Wave / Orange Money…");
      const verified = await PEV.pollPayment(paymentMethod, pending.paymentReference, {
        attempts: 15,
        delayMs: 2000,
      });

      if (!verified.paid) {
        throw new Error("Paiement non confirmé");
      }

      await finalizeOrder(
        {
          items: pending.items,
          subtotal: pending.subtotal,
          total: pending.total,
          customer: pending.customer,
          shipping: pending.shipping,
        },
        {
          method: paymentMethod,
          paid: true,
          reference: pending.paymentReference,
          status: verified.status || "succeeded",
        }
      );
    } catch (err) {
      hidePayOverlay();
      clearPendingCheckout();
      toast(err.message || "Paiement non confirmé");
      history.replaceState({}, "", "checkout.php");
      setStep(2);
    }
  }

  function init() {
    if (!cart.length && !loadPendingCheckout()) {
      renderSummary();
      toast("Votre panier est vide");
      return;
    }

    renderSummary();
    syncPayUI();

    $("#shippingForm").addEventListener("submit", (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);
      customer = {
        name: String(fd.get("name") || "").trim(),
        phone: String(fd.get("phone") || "").trim(),
        email: String(fd.get("email") || "").trim(),
        address: String(fd.get("address") || "").trim(),
        city: String(fd.get("city") || "").trim(),
        notes: String(fd.get("notes") || "").trim(),
      };
      if (!customer.name || !customer.phone || !customer.address || !customer.city) {
        toast("Veuillez remplir les champs obligatoires");
        return;
      }
      if (!isValidSnPhone(customer.phone)) {
        toast("Téléphone sénégalais invalide (ex. 77 123 45 67)");
        return;
      }
      localStorage.setItem("pev_last_customer", JSON.stringify(customer));
      setStep(2);
    });

    $$('input[name="payMethod"]').forEach((r) => r.addEventListener("change", syncPayUI));
    $$(".pay-card").forEach((card) => {
      card.addEventListener("click", () => {
        card.querySelector("input").checked = true;
        syncPayUI();
      });
    });

    $("#backToShipping").addEventListener("click", () => setStep(1));
    $("#payBtn").addEventListener("click", processPayment);

    try {
      const saved = JSON.parse(localStorage.getItem("pev_last_customer") || "null");
      if (saved) {
        $("#cName").value = saved.name || "";
        $("#cPhone").value = saved.phone || "";
        $("#cEmail").value = saved.email || "";
        $("#cAddress").value = saved.address || "";
        $("#cCity").value = saved.city || "";
        $("#cNotes").value = saved.notes || "";
        customer = saved;
      }
    } catch (_) {}
  }

  document.addEventListener("DOMContentLoaded", async () => {
    if (typeof loadProducts === "function") await loadProducts();
    if (typeof PEV !== "undefined" && PEV.loadSettings) await PEV.loadSettings();
    applyPaymentMethods();

    const returning = new URLSearchParams(location.search).get("ref");
    if (returning) {
      await resumeAfterRedirect();
    } else {
      init();
    }
  });
})();
