/* Suivi de commande + validation livraison */
(function () {
  const $ = (s) => document.querySelector(s);
  const $$ = (s) => [...document.querySelectorAll(s)];
  let currentOrder = null;

  function toast(msg) {
    const el = $("#toast");
    if (!el) return;
    el.textContent = msg;
    el.classList.add("is-show");
    setTimeout(() => el.classList.remove("is-show"), 2600);
  }

  function renderTimeline(order) {
    const currentIdx = PEV.statusIndex(order.status);
    const flow = PEV.STATUS_FLOW.filter((s) => s !== "cancelled");

    return `
      <ol class="track-timeline">
        ${flow
          .map((status, i) => {
            const done = i < currentIdx || order.status === "completed";
            const active = status === order.status || (order.status === "completed" && status === "completed");
            const event = [...(order.timeline || [])].reverse().find((t) => t.status === status);
            return `
            <li class="track-step ${done || active ? "is-done" : ""} ${active ? "is-active" : ""}">
              <div class="track-step__dot"></div>
              <div class="track-step__body">
                <strong>${escapeHtml(PEV.STATUS_LABELS[status])}</strong>
                <span>${event ? PEV.formatDate(event.at) : i <= currentIdx ? "—" : "À venir"}</span>
                ${event?.note ? `<p>${escapeHtml(event.note)}</p>` : ""}
              </div>
            </li>`;
          })
          .join("")}
      </ol>`;
  }

  function renderOrder(order) {
    currentOrder = order;
    const panel = $("#trackResult");
    const payLabel = PEV.PAYMENT_METHODS[order.payment?.method]?.label || order.payment?.method || "—";
    const canConfirm = ["delivered", "out_for_delivery", "in_transit"].includes(order.status);

    panel.hidden = false;
    panel.innerHTML = `
      <div class="track-card">
        <div class="track-card__head">
          <div>
            <p class="eyebrow">Commande</p>
            <h2>${escapeHtml(order.id)}</h2>
            <p class="muted">Suivi : <strong>${escapeHtml(order.trackingNumber)}</strong></p>
          </div>
          <div class="track-status-pill">${escapeHtml(PEV.STATUS_LABELS[order.status] || order.status)}</div>
        </div>

        <div class="track-meta-grid">
          <div><span>Client</span><strong>${escapeHtml(order.customer?.name || "—")}</strong></div>
          <div><span>Téléphone</span><strong>${escapeHtml(order.customer?.phone || "—")}</strong></div>
          <div><span>Adresse</span><strong>${escapeHtml(order.customer?.address || "")}${order.customer?.city ? ", " + escapeHtml(order.customer.city) : ""}</strong></div>
          <div><span>Paiement</span><strong>${escapeHtml(payLabel)}${order.payment?.paid ? " · Payé" : ""}</strong></div>
          <div><span>Transporteur</span><strong>${escapeHtml(order.shipping?.carrier || "Pure Essence Express")}</strong></div>
          <div><span>Total</span><strong>${PEV.formatPrice(order.total)}</strong></div>
        </div>

        <h3 class="track-subtitle">Progression</h3>
        ${renderTimeline(order)}

        <h3 class="track-subtitle">Articles</h3>
        <div class="track-items">
          ${(order.items || [])
            .map(
              (i) => `
            <div class="track-item">
              <img src="${encodeURI(i.image || "")}" alt="" />
              <div>
                <strong>${escapeHtml(i.name)}</strong>
                <span>${i.qty} × ${PEV.formatPrice(i.price)}</span>
              </div>
              <em>${PEV.formatPrice(i.price * i.qty)}</em>
            </div>`
            )
            .join("")}
        </div>

        <div class="track-actions">
          ${
            canConfirm
              ? `<button class="btn btn--gold" id="confirmDeliveryBtn">J'ai bien reçu ma commande</button>`
              : ""
          }
          ${
            order.status === "completed"
              ? `<p class="success-inline">Livraison validée le ${PEV.formatDate(order.deliveryConfirmedAt || order.updatedAt)}</p>`
              : ""
          }
          <a class="btn btn--ghost" href="commandes.php">Toutes mes commandes</a>
        </div>
      </div>`;

    $("#confirmDeliveryBtn")?.addEventListener("click", async () => {
      try {
        const updated = await PEV.confirmDelivery(order.id, order.customer?.phone || "");
        toast("Merci ! Livraison validée");
        renderOrder(updated);
      } catch (err) {
        toast(err.message || "Impossible de valider");
      }
    });
  }

  async function lookup(id) {
    const order = await PEV.getOrder(id.trim());
    if (!order) {
      $("#trackResult").hidden = false;
      $("#trackResult").innerHTML = `<div class="track-card"><p>Aucune commande trouvée pour <strong>${escapeHtml(id)}</strong>.</p></div>`;
      return;
    }
    renderOrder(order);
  }

  function init() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get("id") || params.get("tracking") || "";

    $("#trackForm")?.addEventListener("submit", (e) => {
      e.preventDefault();
      const q = $("#trackQuery").value.trim();
      if (!q) return toast("Entrez un N° de commande ou de suivi");
      history.replaceState(null, "", `suivi.php?id=${encodeURIComponent(q)}`);
      lookup(q);
    });

    if (id) {
      $("#trackQuery").value = id;
      lookup(id);
    }
  }

  document.addEventListener("DOMContentLoaded", init);
})();
