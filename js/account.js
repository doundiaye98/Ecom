/**
 * Pure Essence Vita — Espace client (compte personnel)
 */
(function () {
  const CHECKOUT_CUSTOMER_KEY = "pev_last_customer";
  const ACTIVE_STATUSES = new Set([
    "pending_payment",
    "paid",
    "processing",
    "shipped",
    "in_transit",
    "out_for_delivery",
    "delivered",
  ]);
  const COMPLETED_STATUSES = new Set(["completed"]);

  let orders = [];
  let customer = null;
  let orderFilter = "all";

  const cfg = window.PEV_ACCOUNT || { initialView: "dashboard", orderId: "" };
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  function toast(msg) {
    const el = $("#toast");
    if (!el) return;
    el.textContent = msg;
    el.classList.add("is-show");
    setTimeout(() => el.classList.remove("is-show"), 2800);
  }

  function firstName(name) {
    const n = String(name || "").trim();
    return n ? n.split(/\s+/)[0] : "";
  }

  function prefillRegisterFromCheckout() {
    try {
      const saved = JSON.parse(localStorage.getItem(CHECKOUT_CUSTOMER_KEY) || "null");
      if (!saved) return;
      if (saved.name) $("#regName").value = saved.name;
      if (saved.phone) {
        $("#regPhone").value = saved.phone;
        $("#loginPhone").value = saved.phone;
      }
      if (saved.email) $("#regEmail").value = saved.email;
    } catch (_) {}
  }

  function showAuth() {
    $("#authGate").hidden = false;
    $("#accountApp").hidden = true;
    $("#customerLogoutBtn").hidden = true;
  }

  function showApp() {
    $("#authGate").hidden = true;
    $("#accountApp").hidden = false;
    $("#customerLogoutBtn").hidden = false;
    const userLine = $("#accountNavUser");
    if (userLine && customer) {
      userLine.textContent = customer.name ? `${customer.name} · ${customer.phone}` : customer.phone;
    }
  }

  async function handleLogout() {
    try {
      await PEV.customerLogout();
    } catch (_) {}
    customer = null;
    orders = [];
    showAuth();
    toast("Déconnecté");
    window.location.href = "espace-client.php";
  }

  function setupAuthTabs() {
    $$("[data-auth-tab]").forEach((tab) => {
      tab.addEventListener("click", () => {
        $$("[data-auth-tab]").forEach((t) => t.classList.remove("is-active"));
        tab.classList.add("is-active");
        const isLogin = tab.dataset.authTab === "login";
        $("#loginForm").hidden = !isLogin;
        $("#registerForm").hidden = isLogin;
      });
    });
  }

  function setupAuthForms() {
    $("#loginForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        const phone = $("#loginPhone").value.trim();
        const password = $("#loginPassword").value;
        const res = await PEV.customerLogin(phone, password);
        if (!res?.ok) throw new Error(res?.error || "Connexion impossible");
        customer = res.customer;
        showApp();
        await refreshOrders();
        toast("Bienvenue !");
        if (cfg.initialView === "order" && cfg.orderId) {
          await showOrderDetail(cfg.orderId);
        }
      } catch (err) {
        toast(err.message || "Identifiants incorrects");
      }
    });

    $("#registerForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      const password = $("#regPassword").value;
      const password2 = $("#regPassword2").value;
      if (password !== password2) return toast("Les mots de passe ne correspondent pas");
      try {
        const res = await PEV.customerRegister({
          name: $("#regName").value.trim(),
          phone: $("#regPhone").value.trim(),
          email: $("#regEmail").value.trim(),
          password,
        });
        if (!res?.ok) throw new Error(res?.error || "Inscription impossible");
        customer = res.customer;
        showApp();
        await refreshOrders();
        toast("Compte créé — vos commandes sont associées");
        window.location.href = "espace-client.php?view=dashboard";
      } catch (err) {
        toast(err.message || "Impossible de créer le compte");
      }
    });
  }

  async function fetchOrders() {
    return PEV.listOrders();
  }

  function statusClass(status) {
    if (status === "pending_payment") return "is-pending";
    if (["paid", "processing"].includes(status)) return "is-active-status";
    if (["shipped", "in_transit", "out_for_delivery"].includes(status)) return "is-transit";
    if (status === "delivered") return "is-delivered";
    if (status === "completed") return "is-completed";
    if (status === "cancelled") return "is-cancelled";
    return "";
  }

  function progressPercent(status) {
    const flow = PEV.STATUS_FLOW.filter((s) => s !== "cancelled");
    const idx = flow.indexOf(status);
    if (idx < 0) return 0;
    if (status === "completed") return 100;
    return Math.round(((idx + 1) / flow.length) * 100);
  }

  function renderOrderCard(order) {
    const items = order.items || [];
    const thumbs = items.slice(0, 3);
    const extra = items.length - thumbs.length;
    const statusLabel = PEV.STATUS_LABELS[order.status] || order.status;
    const pct = progressPercent(order.status);

    return `
      <article class="account-order" data-order-id="${escapeAttr(order.id)}" tabindex="0" role="button">
        <div class="account-order__top">
          <div>
            <p class="account-order__id">${escapeHtml(order.id)}</p>
            <p class="account-order__meta">${PEV.formatDate(order.createdAt)} · ${items.length} article(s)</p>
          </div>
          <div class="account-order__right">
            <span class="track-status-pill ${statusClass(order.status)}">${escapeHtml(statusLabel)}</span>
            <p class="account-order__total">${PEV.formatPrice(order.total)}</p>
          </div>
        </div>
        ${
          thumbs.length
            ? `<div class="account-order__thumbs">
            ${thumbs.map((i) => `<img class="account-order__thumb" src="${encodeURI(i.image || "")}" alt="" loading="lazy" />`).join("")}
            ${extra > 0 ? `<span class="account-order__thumb account-order__thumb--more">+${extra}</span>` : ""}
          </div>`
            : ""
        }
        <div class="account-order__progress" aria-hidden="true">
          <div class="account-order__progress-bar" style="width:${pct}%"></div>
        </div>
        <div class="account-order__footer">
          <span class="account-order__meta">Suivi : ${escapeHtml(order.trackingNumber || "—")}</span>
          <span class="account-order__link">Voir le détail →</span>
        </div>
      </article>`;
  }

  function bindOrderCards(container) {
    container.querySelectorAll(".account-order").forEach((el) => {
      const open = () => openOrder(el.dataset.orderId);
      el.addEventListener("click", open);
      el.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          open();
        }
      });
    });
  }

  function renderOrdersList(containerId, list, emptyMessage) {
    const box = document.getElementById(containerId);
    if (!box) return;
    if (!list.length) {
      box.className = "account-empty";
      box.innerHTML = `
        <div class="account-empty__icon">📦</div>
        <h3>Aucune commande</h3>
        <p>${escapeHtml(emptyMessage)}</p>
        <a href="index.php#produits" class="btn btn--gold">Découvrir la boutique</a>`;
      return;
    }
    box.className = "account-orders";
    box.innerHTML = list.map(renderOrderCard).join("");
    bindOrderCards(box);
  }

  function filterOrders(list) {
    if (orderFilter === "active") return list.filter((o) => ACTIVE_STATUSES.has(o.status));
    if (orderFilter === "completed") return list.filter((o) => COMPLETED_STATUSES.has(o.status));
    return list;
  }

  function updateDashboard() {
    const name = firstName(customer?.name);
    $("#dashboardGreeting").textContent = name ? `Bonjour, ${name}` : "Votre espace client";
    $("#dashboardDesc").textContent = "Consultez l'état de vos commandes et suivez vos livraisons en temps réel.";

    const active = orders.filter((o) => ACTIVE_STATUSES.has(o.status)).length;
    const completed = orders.filter((o) => COMPLETED_STATUSES.has(o.status)).length;
    const spent = orders.reduce((s, o) => s + (Number(o.total) || 0), 0);

    $("#statActive").textContent = String(active);
    $("#statCompleted").textContent = String(completed);
    $("#statSpent").textContent = orders.length ? PEV.formatPrice(spent) : "0 FCFA";

    renderOrdersList("dashboardOrders", orders.slice(0, 3), "Vous n'avez pas encore passé de commande.");
  }

  function updateOrdersPanel() {
    const filters = $("#ordersFilters");
    if (filters) filters.hidden = orders.length === 0;
    renderOrdersList(
      "ordersList",
      filterOrders(orders),
      "Aucune commande dans cette catégorie."
    );
  }

  async function refreshOrders() {
    orders = await fetchOrders();
    updateDashboard();
    updateOrdersPanel();
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

  function renderOrderDetail(order) {
    const payLabel = PEV.PAYMENT_METHODS[order.payment?.method]?.label || order.payment?.method || "—";
    const canConfirm = ["delivered", "out_for_delivery", "in_transit"].includes(order.status);

    return `
      <div class="track-card">
        <div class="track-card__head">
          <div>
            <p class="eyebrow">Commande</p>
            <h2>${escapeHtml(order.id)}</h2>
            <p class="muted">Suivi : <strong>${escapeHtml(order.trackingNumber)}</strong></p>
          </div>
          <div class="track-status-pill ${statusClass(order.status)}">${escapeHtml(PEV.STATUS_LABELS[order.status] || order.status)}</div>
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
              <img src="${encodeURI(i.image || "")}" alt="" loading="lazy" />
              <div><strong>${escapeHtml(i.name)}</strong><span>${i.qty} × ${PEV.formatPrice(i.price)}</span></div>
              <em>${PEV.formatPrice(i.price * i.qty)}</em>
            </div>`
            )
            .join("")}
        </div>
        <div class="track-actions">
          ${canConfirm ? `<button class="btn btn--gold" id="confirmDeliveryBtn">J'ai bien reçu ma commande</button>` : ""}
          ${order.status === "completed" ? `<p class="success-inline">Livraison validée le ${PEV.formatDate(order.deliveryConfirmedAt || order.updatedAt)}</p>` : ""}
          <a class="btn btn--ghost" href="index.php#produits">Commander à nouveau</a>
        </div>
      </div>`;
  }

  function bindConfirmDelivery(container, order) {
    container.querySelector("#confirmDeliveryBtn")?.addEventListener("click", async () => {
      try {
        const updated = await PEV.confirmDelivery(order.id, customer?.phone || order.customer?.phone || "");
        toast("Merci ! Livraison validée");
        container.innerHTML = renderOrderDetail(updated);
        bindConfirmDelivery(container, updated);
        await refreshOrders();
      } catch (err) {
        toast(err.message || "Impossible de valider");
      }
    });
  }

  function navigate(view, orderId = "") {
    let url = `espace-client.php?view=${encodeURIComponent(view)}`;
    if (orderId) url += `&id=${encodeURIComponent(orderId)}`;
    window.location.href = url;
  }

  function openOrder(id) {
    navigate("order", id);
  }

  async function showOrderDetail(id) {
    const box = $("#orderDetail");
    if (!box) return;
    box.innerHTML = `<div class="account-loading"><div class="account-skeleton"></div></div>`;
    try {
      const order = await PEV.getOrder(id.trim());
      if (!order) throw new Error("Commande introuvable");
      box.innerHTML = renderOrderDetail(order);
      bindConfirmDelivery(box, order);
    } catch (err) {
      box.innerHTML = `
        <div class="account-empty">
          <h3>Commande introuvable</h3>
          <p>${escapeHtml(err.message || "Accès refusé")}</p>
          <a href="espace-client.php?view=orders" class="btn btn--ghost">Retour</a>
        </div>`;
    }
  }

  async function lookupTrack(id) {
    const panel = $("#trackResult");
    if (!panel) return;
    panel.hidden = false;
    panel.innerHTML = `<div class="account-loading"><div class="account-skeleton"></div></div>`;
    try {
      const order = await PEV.getOrder(id.trim());
      if (!order) throw new Error("Commande introuvable ou accès refusé");
      panel.innerHTML = renderOrderDetail(order);
      bindConfirmDelivery(panel, order);
    } catch (err) {
      panel.innerHTML = `<div class="track-card"><p>${escapeHtml(err.message || "Commande introuvable")}</p></div>`;
    }
  }

  function fillProfile() {
    if (!customer) return;
    $("#profileName").value = customer.name || "";
    $("#profilePhone").value = customer.phone || "";
    $("#profileEmail").value = customer.email || "";
    $("#profileAddress").value = customer.address || "";
    $("#profileCity").value = customer.city || "";
  }

  function setupProfile() {
    $("#profileForm")?.addEventListener("submit", async (e) => {
      e.preventDefault();
      try {
        const payload = {
          name: $("#profileName").value.trim(),
          email: $("#profileEmail").value.trim(),
          address: $("#profileAddress").value.trim(),
          city: $("#profileCity").value.trim(),
        };
        const pwd = $("#profilePassword").value;
        if (pwd) payload.password = pwd;
        const res = await PEV.customerUpdateProfile(payload);
        if (!res?.ok) throw new Error(res?.error || "Erreur");
        customer = res.customer;
        fillProfile();
        $("#profilePassword").value = "";
        toast("Profil mis à jour");
      } catch (err) {
        toast(err.message || "Impossible d'enregistrer");
      }
    });
  }

  function setupFilters() {
    $$(".account-filter").forEach((btn) => {
      btn.addEventListener("click", () => {
        $$(".account-filter").forEach((b) => b.classList.remove("is-active"));
        btn.classList.add("is-active");
        orderFilter = btn.dataset.filter || "all";
        updateOrdersPanel();
      });
    });
  }

  function setupTrack() {
    $("#trackForm")?.addEventListener("submit", (e) => {
      e.preventDefault();
      const q = $("#trackQuery")?.value.trim();
      if (!q) return toast("Entrez un numéro de commande ou de suivi");
      history.replaceState(null, "", `espace-client.php?view=track&id=${encodeURIComponent(q)}`);
      lookupTrack(q);
    });

    const id = cfg.orderId || new URLSearchParams(location.search).get("id") || "";
    if (id && cfg.initialView === "track") {
      if ($("#trackQuery")) $("#trackQuery").value = id;
      lookupTrack(id);
    }
  }

  async function initApp() {
    setupProfile();
    setupFilters();
    setupTrack();
    $("#orderBack")?.addEventListener("click", () => navigate("orders"));
    $("#customerLogoutBtn")?.addEventListener("click", handleLogout);
    $("#accountNavLogout")?.addEventListener("click", handleLogout);

    fillProfile();
    await refreshOrders();

    if (cfg.initialView === "order" && cfg.orderId) {
      await showOrderDetail(cfg.orderId);
    }
  }

  async function init() {
    prefillRegisterFromCheckout();
    setupAuthTabs();
    setupAuthForms();

    try {
      const session = await PEV.customerCheck();
      if (session?.authenticated && session.customer) {
        customer = session.customer;
        showApp();
        await initApp();
        return;
      }
    } catch (_) {}

    showAuth();
  }

  document.addEventListener("DOMContentLoaded", init);
})();
