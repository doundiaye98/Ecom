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
?>

<section class="account-auth" id="authGate">
  <div class="container account-auth__wrap">
    <div class="account-auth__intro">
      <p class="account-hero__eyebrow">Espace personnel</p>
      <h1 class="account-auth__title">Votre espace client privé</h1>
      <p class="account-auth__lead">Connectez-vous ou créez un compte avec votre numéro de téléphone pour accéder uniquement à vos commandes.</p>
      <ul class="account-auth__features">
        <li>Suivi de livraison en temps réel</li>
        <li>Historique de vos achats</li>
        <li>Validation de réception sécurisée</li>
      </ul>
    </div>
    <div class="account-auth__card">
      <div class="account-auth__tabs">
        <button type="button" class="account-auth__tab is-active" data-auth-tab="login">Connexion</button>
        <button type="button" class="account-auth__tab" data-auth-tab="register">Créer un compte</button>
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
        <p class="account-auth__hint">Minimum 8 caractères. Vos commandes passées avec ce numéro seront automatiquement rattachées.</p>
        <button type="submit" class="btn btn--gold btn--full">Créer mon compte</button>
      </form>
    </div>
  </div>
</section>

<div id="accountApp" hidden>
<?php
customer_shell_start($view === 'order' ? 'orders' : $view);
?>

<div class="account-mobile-nav" aria-label="Navigation mobile">
  <div class="account-mobile-nav__inner">
    <a href="espace-client.php?view=dashboard" class="<?= $view === 'dashboard' ? 'is-active' : '' ?>">Accueil</a>
    <a href="espace-client.php?view=orders" class="<?= $view === 'orders' || $view === 'order' ? 'is-active' : '' ?>">Commandes</a>
    <a href="espace-client.php?view=track" class="<?= $view === 'track' ? 'is-active' : '' ?>">Suivi</a>
    <a href="espace-client.php?view=profile" class="<?= $view === 'profile' ? 'is-active' : '' ?>">Profil</a>
  </div>
</div>

<!-- Dashboard -->
<section class="account-panel<?= $view === 'dashboard' ? ' is-active' : '' ?>" id="panelDashboard" data-panel="dashboard">
  <div class="account-hero" id="dashboardHero">
    <p class="account-hero__eyebrow">Bienvenue</p>
    <h1 class="account-hero__title" id="dashboardGreeting">Votre espace client</h1>
    <p class="account-hero__desc" id="dashboardDesc">Retrouvez vos commandes, suivez vos livraisons et gérez vos informations en un seul endroit.</p>
    <div class="account-hero__actions">
      <a href="espace-client.php?view=orders" class="btn btn--gold">Mes commandes</a>
      <a href="espace-client.php?view=track" class="btn btn--ghost">Suivre un colis</a>
    </div>
  </div>

  <div class="account-stats" id="dashboardStats">
    <div class="account-stat">
      <span class="account-stat__label">En cours</span>
      <span class="account-stat__value" id="statActive">—</span>
      <span class="account-stat__hint">Préparation & livraison</span>
    </div>
    <div class="account-stat">
      <span class="account-stat__label">Livrées</span>
      <span class="account-stat__value" id="statCompleted">—</span>
      <span class="account-stat__hint">Commandes finalisées</span>
    </div>
    <div class="account-stat">
      <span class="account-stat__label">Total dépensé</span>
      <span class="account-stat__value" id="statSpent">—</span>
      <span class="account-stat__hint">Toutes commandes confondues</span>
    </div>
  </div>

  <div class="account-section-head">
    <div>
      <h2>Dernières commandes</h2>
      <p>Vos achats les plus récents</p>
    </div>
    <a href="espace-client.php?view=orders" class="btn btn--ghost btn--sm">Tout voir</a>
  </div>
  <div id="dashboardOrders" class="account-orders account-loading">
    <div class="account-skeleton"></div>
    <div class="account-skeleton"></div>
  </div>
</section>

<!-- Orders list -->
<section class="account-panel<?= $view === 'orders' ? ' is-active' : '' ?>" id="panelOrders" data-panel="orders">
  <div class="account-section-head">
    <div>
      <h2>Mes commandes</h2>
      <p>Historique complet de vos achats</p>
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
</section>

<!-- Order detail -->
<section class="account-panel<?= $view === 'order' ? ' is-active' : '' ?>" id="panelOrder" data-panel="order">
  <button type="button" class="account-back" id="orderBack">← Retour aux commandes</button>
  <div id="orderDetail"></div>
</section>

<!-- Track -->
<section class="account-panel<?= $view === 'track' ? ' is-active' : '' ?>" id="panelTrack" data-panel="track">
  <div class="account-section-head">
    <div>
      <h2>Suivi de colis</h2>
      <p>Entrez votre numéro de commande ou de suivi</p>
    </div>
  </div>

  <div class="account-track-box">
    <h3>Où en est ma commande ?</h3>
    <p>Utilisez le numéro reçu par email ou SMS (ex. PEV-… ou TRK…).</p>
    <form class="account-track-form" id="trackForm">
      <input id="trackQuery" type="search" placeholder="Ex. PEV-XXXX ou TRKXXXXXXXXXX" required />
      <button class="btn btn--gold" type="submit">Suivre</button>
    </form>
  </div>

  <div id="trackResult" hidden></div>
</section>

<!-- Profile -->
<section class="account-panel<?= $view === 'profile' ? ' is-active' : '' ?>" id="panelProfile" data-panel="profile">
  <div class="account-section-head">
    <div>
      <h2>Mon profil</h2>
      <p>Vos informations personnelles et de livraison</p>
    </div>
  </div>

  <div class="account-profile-grid">
    <div class="account-profile-card">
      <h3>Informations personnelles</h3>
      <form id="profileForm">
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
    </div>

    <div class="account-profile-card">
      <h3>Comment ça marche</h3>
      <ul class="account-info-list">
        <li>
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 3l7 3v5c0 5-3 8-7 10-4-2-7-5-7-10V6l7-3z"/></svg>
          <div>
            <strong>Compte personnel</strong>
            Votre espace est protégé par mot de passe et lié à votre numéro de téléphone.
          </div>
        </li>
        <li>
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
          <div>
            <strong>Suivi en temps réel</strong>
            De la confirmation du paiement jusqu'à la validation de livraison.
          </div>
        </li>
        <li>
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M20 6L9 17l-5-5"/></svg>
          <div>
            <strong>Validation client</strong>
            Confirmez la réception dès que vous avez votre colis.
          </div>
        </li>
      </ul>
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
