/* Checkout flow — shipping → payment → confirmation */
(function () {
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
    setTimeout(() => el.classList.remove("is-show"), 2600);
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
      box.innerHTML = `<p class="muted">Panier vide. <a href="index.html#produits">Retour boutique</a></p>`;
      $("#payBtn") && ($("#payBtn").disabled = true);
    } else {
      box.innerHTML = items
        .map(
          (i) => `
        <div class="summary-item">
          <img src="${encodeURI(i.image)}" alt="" />
          <div>
            <strong>${i.name}</strong>
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

    if (method === "wave" && !$("#wavePhone").value.trim()) {
      toast("Entrez votre numéro Wave");
      return;
    }
    if (method === "orange" && !$("#orangePhone").value.trim()) {
      toast("Entrez votre numéro Orange Money");
      return;
    }
    if (method === "card") {
      const num = $("#cardNumber").value.replace(/\s+/g, "");
      if (num.length < 12) {
        toast("Numéro de carte invalide");
        return;
      }
    }

    try {
      let payment = { method, paid: false, reference: "" };

      if (method === "cod") {
        showPayOverlay("Création de la commande…", "Paiement à la livraison sélectionné.");
        await new Promise((r) => setTimeout(r, 900));
        payment = { method, paid: false, reference: "COD-" + Date.now().toString(36).toUpperCase() };
      } else {
        showPayOverlay(
          "Paiement en cours…",
          method === "card"
            ? "Connexion sécurisée à votre banque…"
            : `Confirmation ${PEV.PAYMENT_METHODS[method]?.label || method}…`
        );
        const result = await PEV.simulatePayment(method);
        payment = { method, paid: true, reference: result.reference };
      }

      showPayOverlay("Finalisation…", "Enregistrement de votre commande et génération du suivi.");

      const order = await PEV.createOrder({
        items,
        subtotal,
        total,
        customer,
        payment,
        shipping: { fee: shipping, estimatedDays: 3, carrier: "Pure Essence Express" },
      });

      createdOrder = order;
      PEV.clearCart();
      hidePayOverlay();

      $("#okOrderId").textContent = order.id;
      $("#okTracking").textContent = order.trackingNumber;
      $("#okTrackLink").href = `suivi.html?id=${encodeURIComponent(order.id)}`;
      setStep(3);
      toast("Commande confirmée");
    } catch (err) {
      hidePayOverlay();
      toast(err.message || "Échec du paiement");
    }
  }

  function init() {
    if (!cart.length) {
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

    // Prefill customer
    try {
      const saved = JSON.parse(localStorage.getItem("pev_last_customer") || "null");
      if (saved) {
        $("#cName").value = saved.name || "";
        $("#cPhone").value = saved.phone || "";
        $("#cEmail").value = saved.email || "";
        $("#cAddress").value = saved.address || "";
        $("#cCity").value = saved.city || "";
        $("#cNotes").value = saved.notes || "";
      }
    } catch (_) {}

    // Card formatting helpers
    $("#cardNumber")?.addEventListener("input", (e) => {
      let v = e.target.value.replace(/\D/g, "").slice(0, 16);
      e.target.value = v.replace(/(\d{4})(?=\d)/g, "$1 ").trim();
    });
    $("#cardExp")?.addEventListener("input", (e) => {
      let v = e.target.value.replace(/\D/g, "").slice(0, 4);
      if (v.length >= 3) v = v.slice(0, 2) + "/" + v.slice(2);
      e.target.value = v;
    });
  }

  document.addEventListener("DOMContentLoaded", init);
})();
