<?php

declare(strict_types=1);

require_once __DIR__ . '/storefront.php';

function customer_boot(): void
{
    storefront_boot();
}

function customer_initial_view(): string
{
    $view = trim((string) ($_GET['view'] ?? 'dashboard'));
    $allowed = ['dashboard', 'orders', 'track', 'profile', 'order'];
    if (!in_array($view, $allowed, true)) {
        return 'dashboard';
    }
    return $view;
}

function customer_order_id(): string
{
    return trim((string) ($_GET['id'] ?? ''));
}

function customer_head(string $title, string $active = 'dashboard'): void
{
    $site = storefront_site_name();
    $fullTitle = $title . ' — ' . $site;
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover" />
  <title><?= storefront_escape($fullTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Great+Vibes&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/style.css" />
  <link rel="stylesheet" href="css/checkout.css" />
  <link rel="stylesheet" href="css/account.css" />
  <link rel="stylesheet" href="css/chatbot.css" />
  <link rel="stylesheet" href="css/consent.css" />
</head>
<body class="account-page" data-active-nav="<?= storefront_escape($active) ?>">
  <div class="account-announce">
    <p>Espace client · Suivi & commandes <?= storefront_escape($site) ?></p>
  </div>
  <header class="header is-scrolled account-header">
    <div class="container header__inner header__inner--simple">
      <a href="index.php" class="brand">
        <img src="logo/2608843d-81e3-494b-9808-d60e0ec10072.jpeg" alt="<?= storefront_escape($site) ?>" class="brand__logo" />
      </a>
      <div class="header-links">
        <a href="index.php#produits">Boutique</a>
        <a href="index.php#contact">Aide</a>
        <button type="button" class="btn btn--ghost btn--sm" id="customerLogoutBtn" hidden>Déconnexion</button>
        <a href="index.php" class="btn btn--ghost btn--sm">Accueil</a>
      </div>
    </div>
  </header>
    <?php
}

function customer_shell_start(string $active = 'dashboard'): void
{
    $nav = [
        'dashboard' => ['label' => 'Vue d\'ensemble', 'icon' => '◆', 'href' => 'espace-client.php?view=dashboard'],
        'orders' => ['label' => 'Mes commandes', 'icon' => '▤', 'href' => 'espace-client.php?view=orders'],
        'track' => ['label' => 'Suivi colis', 'icon' => '◎', 'href' => 'espace-client.php?view=track'],
        'profile' => ['label' => 'Mon profil', 'icon' => '◉', 'href' => 'espace-client.php?view=profile'],
    ];
    ?>
  <div class="account-shell container">
    <aside class="account-nav" aria-label="Navigation espace client">
      <div class="account-nav__brand">
        <p class="account-nav__eyebrow">Espace client</p>
        <h2 class="account-nav__title">Mon compte</h2>
      </div>
      <nav class="account-nav__links">
        <?php foreach ($nav as $key => $item): ?>
          <a href="<?= storefront_escape($item['href']) ?>"
             class="account-nav__link<?= $active === $key ? ' is-active' : '' ?>"
             data-nav="<?= storefront_escape($key) ?>">
            <span class="account-nav__icon" aria-hidden="true"><?= $item['icon'] ?></span>
            <?= storefront_escape($item['label']) ?>
          </a>
        <?php endforeach; ?>
      </nav>
      <div class="account-nav__help">
        <p id="accountNavUser" class="account-nav__user"></p>
        <p>Besoin d'aide ?</p>
        <a href="index.php#contact" class="btn btn--ghost btn--sm btn--full">Nous contacter</a>
        <button type="button" class="btn btn--ghost btn--sm btn--full" id="accountNavLogout" style="margin-top:.5rem">Déconnexion</button>
      </div>
    </aside>
    <main class="account-main" id="accountMain">
    <?php
}

function customer_shell_end(): void
{
    ?>
    </main>
  </div>
  </div>
  <div class="toast" id="toast" role="status" aria-live="polite"></div>
  <script src="js/dom-safe.js"></script>
  <script src="js/store.js"></script>
  <script src="js/account.js"></script>
  <script src="js/consent.js"></script>
  <script src="js/chatbot.js"></script>
</body>
</html>
    <?php
}
