/**
 * Pure Essence Vita — Bannière confidentialité & cookies
 */
(function () {
  const STORAGE_KEY = "pev_consent_v1";
  const BRAND = "Pure Essence Vita";

  function getConsent() {
    try {
      return JSON.parse(localStorage.getItem(STORAGE_KEY) || "null");
    } catch {
      return null;
    }
  }

  function saveConsent(data) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    window.dispatchEvent(new CustomEvent("pev:consent", { detail: data }));
  }

  function buildModal() {
    const modal = document.createElement("div");
    modal.className = "pev-consent-modal";
    modal.id = "pevConsentModal";
    modal.setAttribute("role", "dialog");
    modal.setAttribute("aria-modal", "true");
    modal.setAttribute("aria-labelledby", "pevConsentModalTitle");
    modal.innerHTML = `
      <div class="pev-consent-modal__box">
        <button type="button" class="pev-consent-modal__close" id="pevConsentModalClose" aria-label="Fermer">×</button>
        <h2 id="pevConsentModalTitle">Politique de confidentialité</h2>
        <p><strong>${BRAND}</strong> respecte votre vie privée. Cette politique explique comment nous utilisons vos données lors de votre visite sur notre boutique en ligne.</p>

        <h3>Données collectées</h3>
        <ul>
          <li>Informations de commande : nom, téléphone, adresse, email</li>
          <li>Données de paiement : traitées par Wave ou Orange Money (nous ne stockons pas vos codes PIN)</li>
          <li>Panier et préférences : stockés localement sur votre appareil</li>
          <li>Messages contact : via le formulaire ou WhatsApp</li>
        </ul>

        <h3>Cookies & stockage local</h3>
        <ul>
          <li><strong>Essentiels</strong> — panier, session, préférences de consentement (obligatoires au fonctionnement)</li>
          <li><strong>Fonctionnels</strong> — mémorisation de vos informations client pour faciliter le checkout</li>
        </ul>

        <h3>Finalités</h3>
        <p>Traitement des commandes, livraison au Sénégal, suivi colis, support client et amélioration de nos services.</p>

        <h3>Vos droits</h3>
        <p>Vous pouvez demander l'accès, la rectification ou la suppression de vos données en nous contactant à <a href="mailto:contact@pureessencevita.com">contact@pureessencevita.com</a> ou via WhatsApp.</p>

        <h3>Conservation</h3>
        <p>Les données de commande sont conservées le temps nécessaire à la gestion commerciale et aux obligations légales.</p>

        <div class="pev-consent-modal__footer">
          <button type="button" class="pev-consent__btn pev-consent__btn--ghost" id="pevConsentModalReject">Refuser les optionnels</button>
          <button type="button" class="pev-consent__btn pev-consent__btn--gold" id="pevConsentModalAccept">Accepter tout</button>
        </div>
      </div>`;
    document.body.appendChild(modal);
    return modal;
  }

  function buildBanner() {
    const wrap = document.createElement("div");
    wrap.className = "pev-consent";
    wrap.id = "pevConsent";
    wrap.setAttribute("role", "region");
    wrap.setAttribute("aria-label", "Confidentialité et cookies");
    wrap.innerHTML = `
      <div class="pev-consent__panel">
        <div class="pev-consent__content">
          <strong>🍪 Confidentialité & cookies</strong>
          <p>
            Nous utilisons des cookies essentiels pour le panier, le checkout et la sécurité de votre navigation.
            En continuant, vous acceptez notre utilisation des données conformément à notre
            <a href="#" id="pevConsentPolicyLink">politique de confidentialité</a>.
          </p>
        </div>
        <div class="pev-consent__actions">
          <button type="button" class="pev-consent__btn pev-consent__btn--ghost" id="pevConsentCustomize">Personnaliser</button>
          <button type="button" class="pev-consent__btn pev-consent__btn--outline" id="pevConsentReject">Refuser</button>
          <button type="button" class="pev-consent__btn pev-consent__btn--gold" id="pevConsentAccept">Tout accepter</button>
        </div>
      </div>`;
    document.body.appendChild(wrap);
    return wrap;
  }

  function hideBanner(banner) {
    banner.classList.remove("is-visible");
    document.body.classList.remove("pev-consent-banner-visible");
    setTimeout(() => banner.remove(), 400);
  }

  function openModal(modal) {
    modal.classList.add("is-open");
    document.body.classList.add("pev-consent-modal-open");
  }

  function closeModal(modal) {
    modal.classList.remove("is-open");
    document.body.classList.remove("pev-consent-modal-open");
  }

  function acceptAll(banner, modal) {
    saveConsent({
      essential: true,
      functional: true,
      analytics: false,
      marketing: false,
      acceptedAt: new Date().toISOString(),
      choice: "accept_all",
    });
    closeModal(modal);
    hideBanner(banner);
  }

  function rejectOptional(banner, modal) {
    saveConsent({
      essential: true,
      functional: false,
      analytics: false,
      marketing: false,
      acceptedAt: new Date().toISOString(),
      choice: "essential_only",
    });
    closeModal(modal);
    hideBanner(banner);
  }

  function init() {
    const banner = getConsent() ? null : buildBanner();
    const modal = buildModal();

    if (banner) {
      document.body.classList.add("pev-consent-banner-visible");
      const show = () => requestAnimationFrame(() => banner.classList.add("is-visible"));
      setTimeout(show, 800);

      document.getElementById("pevConsentAccept")?.addEventListener("click", () => acceptAll(banner, modal));
      document.getElementById("pevConsentReject")?.addEventListener("click", () => rejectOptional(banner, modal));
      document.getElementById("pevConsentCustomize")?.addEventListener("click", () => openModal(modal));
      document.getElementById("pevConsentPolicyLink")?.addEventListener("click", (e) => {
        e.preventDefault();
        openModal(modal);
      });
    }

    document.getElementById("pevConsentModalAccept")?.addEventListener("click", () => {
      if (banner) acceptAll(banner, modal);
      else {
        saveConsent({ essential: true, functional: true, analytics: false, marketing: false, acceptedAt: new Date().toISOString(), choice: "accept_all" });
        closeModal(modal);
      }
    });
    document.getElementById("pevConsentModalReject")?.addEventListener("click", () => {
      if (banner) rejectOptional(banner, modal);
      else {
        saveConsent({ essential: true, functional: false, analytics: false, marketing: false, acceptedAt: new Date().toISOString(), choice: "essential_only" });
        closeModal(modal);
      }
    });
    document.getElementById("pevConsentModalClose")?.addEventListener("click", () => closeModal(modal));
    modal.addEventListener("click", (e) => {
      if (e.target === modal) closeModal(modal);
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape" && modal.classList.contains("is-open")) closeModal(modal);
    });

    document.getElementById("footerPrivacyLink")?.addEventListener("click", (e) => {
      e.preventDefault();
      openModal(modal);
    });
  }

  window.PEVConsent = {
    get: getConsent,
    reset() {
      localStorage.removeItem(STORAGE_KEY);
      location.reload();
    },
  };

  document.addEventListener("DOMContentLoaded", init);
})();
