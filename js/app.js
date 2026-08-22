/* ============================================
   Pure Essence Vita — Interactive App
   ============================================ */

const WHATSAPP_NUMBER = "221000000000";

const state = {
  filter: "all",
  search: "",
  cart: typeof PEV !== "undefined" ? PEV.loadCart() : [],
  activeProductId: null,
};

/* ---------- Utils ---------- */
function formatPrice(n) {
  return typeof PEV !== "undefined" ? PEV.formatPrice(n) : new Intl.NumberFormat("fr-FR").format(n) + " FCFA";
}

function saveCart() {
  if (typeof PEV !== "undefined") PEV.saveCart(state.cart);
  else localStorage.setItem("pev_cart_v1", JSON.stringify(state.cart));
}

function $(sel, root = document) {
  return root.querySelector(sel);
}

function $$(sel, root = document) {
  return [...root.querySelectorAll(sel)];
}

function getProduct(id) {
  return PRODUCTS.find((p) => p.id === id);
}

/* ---------- Toast ---------- */
let toastTimer;
function showToast(message) {
  const toast = $("#toast");
  toast.textContent = message;
  toast.classList.add("is-show");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove("is-show"), 2400);
}

/* ---------- Products render ---------- */
function filteredProducts() {
  const q = state.search.trim().toLowerCase();
  return PRODUCTS.filter((p) => {
    const matchFilter = state.filter === "all" || p.category === state.filter;
    const matchSearch =
      !q ||
      p.name.toLowerCase().includes(q) ||
      p.short.toLowerCase().includes(q) ||
      p.categoryLabel.toLowerCase().includes(q);
    return matchFilter && matchSearch;
  });
}

function renderProducts() {
  const grid = $("#productsGrid");
  const empty = $("#productsEmpty");
  const items = filteredProducts();

  if (!items.length) {
    grid.innerHTML = "";
    empty.hidden = false;
    return;
  }

  empty.hidden = true;
  grid.innerHTML = items
    .map(
      (p, i) => `
    <article class="product-card" style="animation-delay:${i * 0.05}s" data-id="${p.id}">
      <div class="product-card__media" data-open="${p.id}">
        ${p.badge ? `<span class="product-card__badge">${p.badge}</span>` : ""}
        <img src="${encodeURI(p.image)}" alt="${p.name}" loading="lazy" />
      </div>
      <div class="product-card__body">
        <span class="product-card__cat">${p.categoryLabel}</span>
        <h3>${p.name}</h3>
        <p class="product-card__desc">${p.short}</p>
        <div class="product-card__footer">
          <span class="price">${formatPrice(p.price)}</span>
          <div class="product-card__actions">
            <button class="btn-icon" data-open="${p.id}" aria-label="Voir ${p.name}" title="Détails">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="btn-icon btn-icon--gold" data-add="${p.id}" aria-label="Ajouter ${p.name}" title="Ajouter">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6L5 3H2"/><path d="M12 11v4M10 13h4"/></svg>
            </button>
          </div>
        </div>
      </div>
    </article>`
    )
    .join("");
}

/* ---------- Cart ---------- */
function cartCount() {
  return state.cart.reduce((sum, i) => sum + i.qty, 0);
}

function cartTotal() {
  return state.cart.reduce((sum, i) => {
    const p = getProduct(i.id);
    return sum + (p ? p.price * i.qty : 0);
  }, 0);
}

function updateCartUI() {
  const count = cartCount();
  const badge = $("#cartCount");
  badge.textContent = count;
  badge.classList.toggle("has-items", count > 0);
  $("#cartTotal").textContent = formatPrice(cartTotal());

  const box = $("#cartItems");
  if (!state.cart.length) {
    box.innerHTML = `<p class="cart-empty">Votre panier est vide.<br/>Explorez la collection pour commencer.</p>`;
    return;
  }

  box.innerHTML = state.cart
    .map((item) => {
      const p = getProduct(item.id);
      if (!p) return "";
      return `
      <div class="cart-item" data-id="${p.id}">
        <img src="${encodeURI(p.image)}" alt="${p.name}" />
        <div>
          <h4>${p.name}</h4>
          <p class="meta">${item.qty} × ${formatPrice(p.price)}</p>
          <p class="meta"><strong>${formatPrice(p.price * item.qty)}</strong></p>
        </div>
        <button class="cart-item__remove" data-remove="${p.id}" aria-label="Retirer">×</button>
      </div>`;
    })
    .join("");
}

function addToCart(id, qty = 1) {
  const existing = state.cart.find((i) => i.id === id);
  if (existing) existing.qty += qty;
  else state.cart.push({ id, qty });
  saveCart();
  updateCartUI();
  showToast("Ajouté au panier");
}

function removeFromCart(id) {
  state.cart = state.cart.filter((i) => i.id !== id);
  saveCart();
  updateCartUI();
  showToast("Produit retiré");
}

function clearCart() {
  state.cart = [];
  saveCart();
  updateCartUI();
  showToast("Panier vidé");
}

function openCart() {
  const drawer = $("#cartDrawer");
  const overlay = $("#overlay");
  if (!drawer) return;
  drawer.classList.add("is-open");
  drawer.setAttribute("aria-hidden", "false");
  if (overlay) overlay.hidden = false;
  lockScroll();
}

function closeCart() {
  const drawer = $("#cartDrawer");
  const overlay = $("#overlay");
  if (drawer) {
    drawer.classList.remove("is-open");
    drawer.setAttribute("aria-hidden", "true");
  }
  if ($("#productModal")?.hidden !== false) {
    if (overlay) overlay.hidden = true;
    unlockScroll();
  }
}

let scrollLockY = 0;
function lockScroll() {
  scrollLockY = window.scrollY || window.pageYOffset;
  document.body.classList.add("is-locked");
  document.body.style.top = `-${scrollLockY}px`;
}

function unlockScroll() {
  if (!document.body.classList.contains("is-locked")) return;
  document.body.classList.remove("is-locked");
  document.body.style.top = "";
  window.scrollTo(0, scrollLockY);
}

/* ---------- Modal ---------- */
function openModal(id) {
  const p = getProduct(id);
  if (!p) return;
  state.activeProductId = id;

  $("#modalImage").src = encodeURI(p.image);
  $("#modalImage").alt = p.name;
  $("#modalCategory").textContent = p.categoryLabel;
  $("#modalTitle").textContent = p.name;
  $("#modalPrice").textContent = formatPrice(p.price);
  $("#modalDesc").textContent = p.desc;
  $("#modalBenefits").innerHTML = p.benefits.map((b) => `<li>${b}</li>`).join("");
  $("#modalQty").value = 1;

  $("#productModal").hidden = false;
  lockScroll();
}

function closeModal() {
  const modal = $("#productModal");
  if (modal) modal.hidden = true;
  state.activeProductId = null;
  if (!$("#cartDrawer")?.classList.contains("is-open")) {
    unlockScroll();
    const overlay = $("#overlay");
    if (overlay) overlay.hidden = true;
  }
}

/* ---------- Featured ---------- */
function setupFeatured() {
  const featured = PRODUCTS[0];
  $("#featuredAdd").dataset.id = featured.id;
}

/* ---------- Filters & Search ---------- */
function setupFilters() {
  $$(".filter-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      $$(".filter-btn").forEach((b) => b.classList.remove("is-active"));
      btn.classList.add("is-active");
      state.filter = btn.dataset.filter;
      renderProducts();
    });
  });

  $$("[data-jump]").forEach((link) => {
    link.addEventListener("click", (e) => {
      e.preventDefault();
      const cat = link.dataset.jump;
      const btn = $(`.filter-btn[data-filter="${cat}"]`);
      if (btn) btn.click();
      document.querySelector("#produits").scrollIntoView({ behavior: "smooth" });
    });
  });
}

/* ---------- Navigation ---------- */
function setupNav() {
  const toggle = $("#navToggle");
  const nav = $("#nav");
  if (!toggle || !nav) return;

  toggle.addEventListener("click", () => {
    const open = toggle.classList.toggle("is-open");
    nav.classList.toggle("is-open", open);
    toggle.setAttribute("aria-expanded", String(open));
  });

  $$(".nav__link").forEach((link) => {
    link.addEventListener("click", () => {
      toggle.classList.remove("is-open");
      nav.classList.remove("is-open");
      toggle.setAttribute("aria-expanded", "false");
    });
  });

  const header = $("#header");
  window.addEventListener(
    "scroll",
    () => {
      header?.classList.toggle("is-scrolled", window.scrollY > 20);
    },
    { passive: true }
  );

  const sections = $$("main section[id]");
  const links = $$(".nav__link");
  if (!sections.length) return;
  const spy = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        const id = entry.target.id;
        links.forEach((l) => l.classList.toggle("is-active", l.getAttribute("href") === `#${id}`));
      });
    },
    { rootMargin: "-40% 0px -50% 0px", threshold: 0 }
  );
  sections.forEach((s) => spy.observe(s));
}

/* ---------- Search ---------- */
function setupSearch() {
  const bar = $("#searchBar");
  const input = $("#searchInput");
  const toggle = $("#searchToggle");
  if (!bar || !input || !toggle) return;
  toggle.addEventListener("click", () => {
    bar.classList.toggle("is-open");
    if (bar.classList.contains("is-open")) input.focus();
  });

  input.addEventListener("input", () => {
    state.search = input.value;
    renderProducts();
    if (state.search) {
      document.querySelector("#produits").scrollIntoView({ behavior: "smooth" });
    }
  });
}

/* ---------- Reveal & counters ---------- */
function setupReveal() {
  const io = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          io.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.15, rootMargin: "0px 0px -40px 0px" }
  );
  $$(".reveal").forEach((el) => io.observe(el));
}

function animateCounters() {
  const nums = $$("[data-count]");
  nums.forEach((el) => {
    const label = el.closest(".stat")?.querySelector(".stat__label")?.textContent || "";
    if (label.includes("Produits") && Array.isArray(PRODUCTS)) {
      el.dataset.count = String(PRODUCTS.length);
    }
  });
  const io = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        const el = entry.target;
        const target = Number(el.dataset.count);
        const duration = 1400;
        const start = performance.now();

        function tick(now) {
          const t = Math.min(1, (now - start) / duration);
          const eased = 1 - Math.pow(1 - t, 3);
          el.textContent = Math.round(target * eased);
          if (t < 1) requestAnimationFrame(tick);
        }
        requestAnimationFrame(tick);
        io.unobserve(el);
      });
    },
    { threshold: 0.5 }
  );
  nums.forEach((n) => io.observe(n));
}

/* ---------- Contact ---------- */
function setupContact() {
  const form = $("#contactForm");
  if (!form) return;
  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const fd = new FormData(form);
    const payload = {
      name: String(fd.get("name") || "").trim(),
      email: String(fd.get("email") || "").trim(),
      message: String(fd.get("message") || "").trim(),
    };
    try {
      const res = await fetch("api/contact.php?action=send", {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      if (!data.ok) throw new Error(data.error || "Erreur d'envoi");
      const success = $("#formSuccess");
      success.hidden = false;
      e.target.reset();
      showToast("Message envoyé");
      setTimeout(() => {
        success.hidden = true;
      }, 4000);
    } catch (err) {
      showToast(err.message || "Impossible d'envoyer le message");
    }
  });
}

/* ---------- Checkout ---------- */
function goToCheckout() {
  if (!state.cart.length) {
    showToast("Votre panier est vide");
    return;
  }
  saveCart();
  window.location.href = "checkout.html";
}

function checkoutWhatsApp() {
  if (!state.cart.length) {
    showToast("Votre panier est vide");
    return;
  }
  const lines = state.cart.map((item) => {
    const p = getProduct(item.id);
    return `• ${p.name} × ${item.qty} — ${formatPrice(p.price * item.qty)}`;
  });
  const text = ["Bonjour Pure Essence Vita", "Je souhaite commander :", "", ...lines, "", `Total : ${formatPrice(cartTotal())}`].join("\n");
  window.open(`https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(text)}`, "_blank", "noopener");
}

/* ---------- Events ---------- */
function setupEvents() {
  document.addEventListener("click", (e) => {
    const openBtn = e.target.closest("[data-open]");
    if (openBtn) {
      openModal(openBtn.dataset.open);
      return;
    }

    const addBtn = e.target.closest("[data-add]");
    if (addBtn) {
      addToCart(addBtn.dataset.add, 1);
      return;
    }

    const removeBtn = e.target.closest("[data-remove]");
    if (removeBtn) {
      removeFromCart(removeBtn.dataset.remove);
      return;
    }

    if (e.target.closest("[data-close-modal]")) {
      closeModal();
    }
  });

  $("#cartToggle")?.addEventListener("click", openCart);
  $("#cartClose")?.addEventListener("click", closeCart);
  $("#overlay")?.addEventListener("click", () => {
    closeCart();
    closeModal();
  });

  $("#clearCart")?.addEventListener("click", clearCart);
  $("#checkoutBtn")?.addEventListener("click", goToCheckout);
  $("#whatsappCheckout")?.addEventListener("click", checkoutWhatsApp);

  $("#featuredAdd")?.addEventListener("click", () => {
    addToCart($("#featuredAdd").dataset.id, 1);
  });

  $("#qtyMinus")?.addEventListener("click", () => {
    const input = $("#modalQty");
    input.value = Math.max(1, Number(input.value) - 1);
  });
  $("#qtyPlus")?.addEventListener("click", () => {
    const input = $("#modalQty");
    input.value = Math.min(20, Number(input.value) + 1);
  });
  $("#modalAdd")?.addEventListener("click", () => {
    if (!state.activeProductId) return;
    addToCart(state.activeProductId, Number($("#modalQty").value) || 1);
    closeModal();
    openCart();
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      closeModal();
      closeCart();
    }
  });
}

/* ---------- Loader ---------- */
function hideLoader() {
  const loader = $("#pageLoader");
  if (loader && !loader.classList.contains("is-done")) {
    loader.classList.add("is-done");
  }
}

function setupLoader() {
  const started = performance.now();
  const minDisplay = 350;

  function finish() {
    const wait = Math.max(0, minDisplay - (performance.now() - started));
    setTimeout(hideLoader, wait);
  }

  if (document.readyState === "complete") {
    finish();
  } else {
    window.addEventListener("load", finish, { once: true });
  }

  // Ne jamais bloquer l'écran plus de 2,5 s
  setTimeout(hideLoader, 2500);
}

function withTimeout(promise, ms = 5000) {
  return Promise.race([
    promise,
    new Promise((resolve) => setTimeout(resolve, ms)),
  ]);
}

/* ---------- Init ---------- */
function init() {
  if (!$("#productsGrid")) return;

  const year = $("#year");
  if (year) year.textContent = new Date().getFullYear();
  setupNav();
  setupSearch();
  setupFilters();
  setupFeatured();
  setupEvents();
  setupContact();
  setupReveal();
  animateCounters();
  renderProducts();
  updateCartUI();

  const leaf1 = $(".hero__leaf--1");
  const leaf2 = $(".hero__leaf--2");
  const canParallax =
    leaf1 &&
    leaf2 &&
    !window.matchMedia("(prefers-reduced-motion: reduce)").matches &&
    !window.matchMedia("(max-width: 768px)").matches;
  if (canParallax) {
    window.addEventListener(
      "scroll",
      () => {
        const y = window.scrollY;
        if (y > window.innerHeight) return;
        leaf1.style.transform = `translateY(${y * 0.12}px)`;
        leaf2.style.transform = `translateY(${y * -0.08}px)`;
      },
      { passive: true }
    );
  }
}

document.addEventListener("DOMContentLoaded", async () => {
  setupLoader();

  if (location.protocol === "file:") {
    console.warn("Ouvrez le site via http://localhost/Ecom/ (pas en file://)");
  }

  try {
    const tasks = [];
    if (typeof loadProducts === "function") tasks.push(withTimeout(loadProducts(), 5000));
    if (typeof PEV !== "undefined" && PEV.loadSettings) tasks.push(withTimeout(PEV.loadSettings(), 5000));
    await Promise.all(tasks);
  } catch (err) {
    console.warn("Chargement partiel du site", err);
  }

  if (PEV?.settings?.whatsapp) {
    window.WHATSAPP_NUMBER = PEV.settings.whatsapp;
  }

  init();
});
