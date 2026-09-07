<?php
declare(strict_types=1);

require __DIR__ . '/includes/customer.php';

customer_boot();
$view = customer_initial_view();
$orderId = customer_order_id();

if ($view === 'order' && $orderId === '') {
    $view = 'track';
}

customer_head('Espace client', $view === 'order' ? 'orders' : $view);
$siteName = storefront_site_name();
?>

<section class="account-auth" id="authGate">
  <div class="container account-auth__wrap">
    <header class="account-auth__intro">
      <p class="account-auth__brand"><?= storefront_escape($siteName) ?></p>
      <h1 class="account-auth__title">Espace client</h1>
      <p class="account-auth__lead">Connectez-vous avec votre téléphone pour suivre vos commandes et livraisons.</p>
    </header>

    <div class="account-auth__panel">
      <div class="account-auth__tabs" role="tablist">
        <button type="button" class="account-auth__tab is-active" data-auth-tab="login" role="tab" aria-selected="true">Connexion</button>
        <button type="button" class="account-auth__tab" data-auth-tab="register" role="tab" aria-selected="false">Créer un compte</button>
      </div>

      <form id="loginForm" class="account-auth__form" autocomplete="on">
        <div class="account-field">
          <label for="loginPhone">Téléphone</label>
          <input id="loginPhone" name="phone" type="tel" placeholder="77 123 45 67" required autocomplete="tel" />
        </div>
        <div class="account-field">
          <label for="loginPassword">Mot de passe</label>
          <input id="loginPassword" name="password" type="password" required minlength="8" autocomplete="current-password" />
        </div>
        <button type="submit" class="btn btn--gold btn--full">Se connecter</button>
      </form>

      <form id="registerForm" class="account-auth__form" hidden autocomplete="on">
        <div class="account-field">
          <label for="regName">Nom complet</label>
          <input id="regName" name="name" required autocomplete="name" />
        </div>
        <div class="account-field">
          <label for="regPhone">Téléphone</label>
          <input id="regPhone" name="phone" type="tel" placeholder="77 123 45 67" required autocomplete="tel" />
        </div>
        <div class="account-field">
          <label for="regEmail">Email (optionnel)</label>
          <input id="regEmail" name="email" type="email" autocomplete="email" />
        </div>
        <div class="account-field">
          <label for="regPassword">Mot de passe</label>
          <input id="regPassword" name="password" type="password" required minlength="8" autocomplete="new-password" />
        </div>
        <div class="account-field">
          <label for="regPassword2">Confirmer le mot de passe</label>
          <input id="regPassword2" name="password2" type="password" required minlength="8" autocomplete="new-password" />
        </div>
        <p class="account-auth__hint">Minimum 8 caractères. Les commandes passées avec ce numéro seront rattachées automatiquement.</p>
        <button type="submit" class="btn btn--gold btn--full">Créer mon compte</button>
      </form>
    </div>
  </div>
</section>

<div id="accountApp" hidden>
<?php
customer_shell_start($view === 'order' ? 'orders' : $view);
?>

<!-- Dashboard -->
<section class="account-panel<?= $view === 'dashboard' ? ' is-active' : '' ?>" id="panelDashboard" data-panel="dashboard">
  <div class="account-hero" id="dashboardHero">
    <div class="account-hero__inner container">
      <p class="account-hero__eyebrow">Bienvenue</p>
      <h1 class="account-hero__title" id="dashboardGreeting">Votre espace client</h1>
      <p class="account-hero__desc" id="dashboardDesc">Retrouvez vos commandes et suivez vos livraisons.</p>
      <div class="account-hero__actions">
        <a href="espace-client.php?view=orders" class="btn btn--gold">Mes commandes</a>
        <a href="espace-client.php?view=track" class="btn btn--ghost">Suivre un colis</a>
      </div>
    </div>
  </div>

  <div class="container account-panel__body">
    <div class="account-stats" id="dashboardStats">
      <div class="account-stat">
        <span class="account-stat__value" id="statActive">—</span>
        <span class="account-stat__label">En cours</span>
      </div>
      <div class="account-stat">
        <span class="account-stat__value" id="statCompleted">—</span>
        <span class="account-stat__label">Livrées</span>
      </div>
      <div class="account-stat">
        <span class="account-stat__value" id="statSpent">—</span>
        <span class="account-stat__label">Total dépensé</span>
      </div>
    </div>

    <div class="account-section-head">
      <div>
        <h2>Dernières commandes</h2>
        <p>Vos achats les plus récents</p>
      </div>
      <a href="espace-client.php?view=orders" class="account-text-link">Tout voir</a>
    </div>
    <div id="dashboardOrders" class="account-orders account-loading">
      <div class="account-skeleton"></div>
      <div class="account-skeleton"></div>
    </div>
  </div>
</section>

<!-- Orders list -->
<section class="account-panel<?= $view === 'orders' ? ' is-active' : '' ?>" id="panelOrders" data-panel="orders">
  <div class="container account-panel__body account-panel__body--pad">
    <div class="account-section-head">
      <div>
        <h2>Mes commandes</h2>
        <p>Historique de vos achats</p>
      </div>
    </div>

    <div class="account-filters" id="ordersFilters" hidden>
      <button type="button" class="account-filter is-active" data-filter="all">Toutes</button>
      <button type="button" class="account-filter" data-filter="active">En cours</button>
      <button type="button" class="account-filter" data-filter="completed">Terminées</button>
    </div>

    <div id="ordersList" class="account-orders account-loading">
      <div class="account-skeleton"></div>
      <div class="account-skeleton"></div>
      <div class="account-skeleton"></div>
    </div>
  </div>
</section>

<!-- Order detail -->
<section class="account-panel<?= $view === 'order' ? ' is-active' : '' ?>" id="panelOrder" data-panel="order">
  <div class="container account-panel__body account-panel__body--pad">
    <button type="button" class="account-back" id="orderBack">← Retour aux commandes</button>
    <div id="orderDetail"></div>
  </div>
</section>

<!-- Track -->
<section class="account-panel<?= $view === 'track' ? ' is-active' : '' ?>" id="panelTrack" data-panel="track">
  <div class="container account-panel__body account-panel__body--pad">
    <div class="account-section-head">
      <div>
        <h2>Suivi de colis</h2>
        <p>Entrez votre numéro de commande ou de suivi</p>
      </div>
    </div>

    <div class="account-track-box">
      <form class="account-track-form" id="trackForm">
        <label class="visually-hidden" for="trackQuery">Numéro de suivi</label>
        <input id="trackQuery" type="search" placeholder="Ex. PEV-XXXX ou TRKXXXXXXXXXX" required />
        <button class="btn btn--gold" type="submit">Suivre</button>
      </form>
      <p class="account-track-box__hint">Utilisez le numéro reçu par email ou SMS.</p>
    </div>

    <div id="trackResult" hidden></div>
  </div>
</section>

<!-- Profile -->
<section class="account-panel<?= $view === 'profile' ? ' is-active' : '' ?>" id="panelProfile" data-panel="profile">
  <div class="container account-panel__body account-panel__body--pad">
    <div class="account-section-head">
      <div>
        <h2>Mon profil</h2>
        <p>Informations personnelles et de livraison</p>
      </div>
    </div>

    <div class="account-profile-grid">
      <form id="profileForm" class="account-profile-form">
        <div class="account-field">
          <label for="profileName">Nom complet</label>
          <input id="profileName" name="name" autocomplete="name" />
        </div>
        <div class="account-field">
          <label for="profilePhone">Téléphone (identifiant)</label>
          <input id="profilePhone" name="phone" type="tel" readonly disabled />
        </div>
        <div class="account-field">
          <label for="profileEmail">Email</label>
          <input id="profileEmail" name="email" type="email" autocomplete="email" />
        </div>
        <div class="account-field account-field--full">
          <label for="profileAddress">Adresse</label>
          <input id="profileAddress" name="address" autocomplete="street-address" />
        </div>
        <div class="account-field">
          <label for="profileCity">Ville</label>
          <input id="profileCity" name="city" autocomplete="address-level2" />
        </div>
        <div class="account-field account-field--full">
          <label for="profilePassword">Nouveau mot de passe (optionnel)</label>
          <input id="profilePassword" name="password" type="password" minlength="8" autocomplete="new-password" />
        </div>
        <div class="account-profile-actions">
          <button type="submit" class="btn btn--gold">Enregistrer</button>
        </div>
      </form>

      <aside class="account-profile-aside">
        <h3>Comment ça marche</h3>
        <ul class="account-info-list">
          <li>
            <strong>Compte personnel</strong>
            Protégé par mot de passe et lié à votre numéro de téléphone.
          </li>
          <li>
            <strong>Suivi en temps réel</strong>
            Du paiement jusqu’à la validation de livraison.
          </li>
          <li>
            <strong>Validation client</strong>
            Confirmez la réception dès que vous avez votre colis.
          </li>
        </ul>
      </aside>
    </div>
  </div>
</section>

<script>
  window.PEV_ACCOUNT = {
    initialView: <?= json_encode($view, JSON_THROW_ON_ERROR) ?>,
    orderId: <?= json_encode($orderId, JSON_THROW_ON_ERROR) ?>
  };
</script>

<?php
customer_shell_end();
