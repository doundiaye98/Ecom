<?php
declare(strict_types=1);
require __DIR__ . '/includes/storefront.php';
storefront_boot();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover" />
  <title>Paiement sécurisé — Native Vita</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Great+Vibes&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
  <link rel="stylesheet" href="css/checkout.css" />
  <link rel="stylesheet" href="css/chatbot.css" />
  <link rel="stylesheet" href="css/consent.css" />
</head>
<body class="page-checkout">
  <div class="announcement">
    <p>Paiement sécurisé · Suivi de commande · Validation de livraison</p>
  </div>

  <header class="header is-scrolled">
    <div class="container header__inner header__inner--simple">
      <a href="index.php" class="brand">
        <img src="logo/native-vita.jpeg" alt="Native Vita" class="brand__logo" />
      </a>
      <nav class="checkout-steps" id="checkoutSteps" aria-label="Étapes">
        <span class="step is-active" data-step="1">1. Livraison</span>
        <span class="step" data-step="2">2. Paiement</span>
        <span class="step" data-step="3">3. Confirmation</span>
      </nav>
      <a href="espace-client.php" class="btn btn--ghost btn--sm">Espace client</a>
    </div>
  </header>

  <main class="checkout">
    <div class="container checkout__grid">
      <section class="checkout__main">
        <!-- Step 1 -->
        <div class="checkout-panel is-active" id="step1">
          <h1>Adresse de livraison</h1>
          <p class="muted">Indiquez où nous devons livrer votre commande.</p>
          <form id="shippingForm" class="form-grid" novalidate>
            <div class="form-row">
              <label for="cName">Nom complet *</label>
              <input id="cName" name="name" required placeholder="Ex. Aïssatou Diop" />
            </div>
            <div class="form-row form-row--half">
              <label for="cPhone">Téléphone *</label>
              <input id="cPhone" name="phone" required placeholder="77 000 00 00" />
            </div>
            <div class="form-row form-row--half">
              <label for="cEmail">Email</label>
              <input id="cEmail" name="email" type="email" placeholder="vous@email.com" />
            </div>
            <div class="form-row">
              <label for="cAddress">Adresse *</label>
              <input id="cAddress" name="address" required placeholder="Quartier, rue, villa / appartement" />
            </div>
            <div class="form-row form-row--half">
              <label for="cCity">Ville *</label>
              <input id="cCity" name="city" required placeholder="Dakar" />
            </div>
            <div class="form-row form-row--half">
              <label for="cNotes">Instructions</label>
              <input id="cNotes" name="notes" placeholder="Code, repère, horaires…" />
            </div>
            <button type="submit" class="btn btn--gold btn--full">Continuer vers le paiement</button>
          </form>
        </div>

        <!-- Step 2 -->
        <div class="checkout-panel" id="step2" hidden>
          <h1>Paiement mobile — Sénégal</h1>
          <p class="muted">Wave, Orange Money ou paiement à la livraison. Transactions sécurisées en FCFA.</p>
          <div class="payment-mode-banner is-sandbox" id="paymentModeBanner" hidden></div>

          <div class="pay-methods" id="payMethods" role="radiogroup" aria-label="Moyens de paiement">
            <label class="pay-card is-selected" data-method="wave">
              <input type="radio" name="payMethod" value="wave" checked />
              <img class="pay-card__logo" src="img/wave.jpg" alt="Wave" width="48" height="48" loading="lazy" />
              <span class="pay-card__text">
                <strong>Wave</strong>
                <small>Paiement mobile instantané</small>
              </span>
            </label>
            <label class="pay-card" data-method="orange">
              <input type="radio" name="payMethod" value="orange" />
              <img class="pay-card__logo" src="img/orangemoney.jpg" alt="Orange Money" width="48" height="48" loading="lazy" />
              <span class="pay-card__text">
                <strong>Orange Money</strong>
                <small>Paiement mobile sécurisé</small>
              </span>
            </label>
            <label class="pay-card" data-method="cod">
              <input type="radio" name="payMethod" value="cod" />
              <span class="pay-card__badge pay-card__badge--cod">COD</span>
              <span class="pay-card__text">
                <strong>À la livraison</strong>
                <small>Payez en espèces au livreur</small>
              </span>
            </label>
          </div>

          <div class="pay-details" id="payDetails">
            <div class="pay-box" data-for="wave">
              <figure class="pay-qr">
                <img src="img/wave.jpg" alt="QR code Wave — Payez avec Wave" loading="lazy" />
                <figcaption>Scannez avec l'application Wave ou saisissez votre numéro ci-dessous.</figcaption>
              </figure>
              <label for="wavePhone">Numéro Wave</label>
              <input id="wavePhone" placeholder="77 000 00 00" inputmode="tel" />
              <p class="info-banner pay-sandbox-note" id="waveSandboxNote" hidden>Mode sandbox — paiement simulé sans clés Wave dans .env</p>
            </div>
            <div class="pay-box" data-for="orange" hidden>
              <figure class="pay-qr">
                <img src="img/orangemoney.jpg" alt="QR code Orange Money — Code marchand" loading="lazy" />
                <figcaption>Scannez avec Orange Money ou saisissez votre numéro ci-dessous.</figcaption>
              </figure>
              <label for="orangePhone">Numéro Orange Money</label>
              <input id="orangePhone" placeholder="77 000 00 00" inputmode="tel" />
              <p class="info-banner pay-sandbox-note" id="orangeSandboxNote" hidden>Mode sandbox — paiement simulé sans clés Orange Money dans .env</p>
            </div>
            <div class="pay-box" data-for="cod" hidden>
              <p class="info-banner">Vous paierez le montant total au livreur lors de la réception. Préparez l'appoint si possible.</p>
            </div>
          </div>

          <div class="checkout-actions">
            <button type="button" class="btn btn--ghost" id="backToShipping">Retour</button>
            <button type="button" class="btn btn--gold" id="payBtn">Payer maintenant</button>
          </div>
        </div>

        <!-- Step 3 -->
        <div class="checkout-panel" id="step3" hidden>
          <div class="success-block">
            <div class="success-icon" aria-hidden="true">✓</div>
            <h1>Commande confirmée</h1>
            <p class="muted">Merci ! Votre commande est enregistrée. Suivez-la en temps réel jusqu'à la livraison.</p>
            <div class="success-meta">
              <div>
                <span>N° commande</span>
                <strong id="okOrderId">—</strong>
              </div>
              <div>
                <span>Suivi</span>
                <strong id="okTracking">—</strong>
              </div>
            </div>
            <div class="checkout-actions">
              <a class="btn btn--gold" id="okTrackLink" href="espace-client.php">Suivre ma commande</a>
              <a class="btn btn--ghost" href="espace-client.php">Créer mon espace client</a>
              <a class="btn btn--ghost" href="index.php">Retour à la boutique</a>
            </div>
          </div>
        </div>
      </section>

      <aside class="checkout__summary">
        <h2>Récapitulatif</h2>
        <div id="summaryItems" class="summary-items"></div>
        <div class="summary-lines">
          <div><span>Sous-total</span><strong id="sumSubtotal">0 FCFA</strong></div>
          <div><span>Livraison</span><strong id="sumShipping">0 FCFA</strong></div>
          <div class="summary-total"><span>Total</span><strong id="sumTotal">0 FCFA</strong></div>
        </div>
        <p class="summary-note" id="shippingNote">Livraison gratuite dès 50 000 FCFA</p>
        <div class="secure-note">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6l7-3z"/></svg>
          Paiement chiffré · Suivi bout en bout · Validation client
        </div>
      </aside>
    </div>
  </main>

  <div class="pay-overlay" id="payOverlay" hidden>
    <div class="pay-overlay__card">
      <div class="spinner"></div>
      <h3 id="payOverlayTitle">Traitement du paiement…</h3>
      <p id="payOverlayText">Veuillez patienter, ne fermez pas cette fenêtre.</p>
    </div>
  </div>

  <div class="toast" id="toast" role="status" aria-live="polite"></div>

  <script src="js/dom-safe.js"></script>
  <script src="js/products.js"></script>
  <script src="js/store.js"></script>
  <script src="js/checkout.js"></script>
  <script src="js/consent.js"></script>
  <script src="js/chatbot.js"></script>
</body>
</html>
