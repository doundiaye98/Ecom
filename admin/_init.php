<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/Auth.php';

if (!is_file(dirname(__DIR__) . '/config/database.php')) {
    header('Location: ../install.php');
    exit;
}

Database::connect(db_config());
Auth::startSession();

function admin_redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function admin_require_login(): void
{
    if (!Auth::check()) {
        admin_redirect('login.php');
    }
}

function admin_layout(string $title, string $content, string $active = ''): void
{
    $user = Auth::user();
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($title) ?> — Admin PEV</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../css/style.css" />
  <link rel="stylesheet" href="../css/checkout.css" />
  <style>
    .admin-shell { min-height: 100vh; background: #f3efe6; }
    .admin-top { background: linear-gradient(135deg, #00441b, #0d3d28); color: #fff; padding: 1rem 0; border-bottom: 1px solid rgba(212,175,55,.25); }
    .admin-top .container { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; }
    .admin-nav { display: flex; gap: .5rem; flex-wrap: wrap; }
    .admin-nav a { color: #d8cdb8; text-decoration: none; padding: .45rem .85rem; border-radius: 999px; font-size: .9rem; }
    .admin-nav a.is-active, .admin-nav a:hover { background: rgba(201,169,98,.18); color: #fff; }
    .admin-main { padding: 2rem 0 3rem; }
    .admin-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .stat-card { background: #fff; border-radius: 14px; padding: 1.25rem; box-shadow: 0 8px 24px rgba(20,32,24,.06); }
    .stat-card strong { display: block; font-size: 1.6rem; color: #142018; }
    .stat-card span { color: #66756b; font-size: .9rem; }
    .admin-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 14px; overflow: hidden; }
    .admin-table th, .admin-table td { padding: .85rem 1rem; border-bottom: 1px solid #ece7de; text-align: left; vertical-align: top; font-size: .92rem; }
    .admin-table th { background: #faf7f2; color: #536057; font-weight: 600; }
    .pill { display: inline-block; padding: .25rem .65rem; border-radius: 999px; background: #eef5ef; color: #1f4d32; font-size: .78rem; }
  </style>
</head>
<body class="admin-shell">
  <header class="admin-top">
    <div class="container" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
      <div>
        <strong>Pure Essence Vita</strong>
        <div style="opacity:.75;font-size:.85rem">Administration · <?= htmlspecialchars($user['username'] ?? '') ?></div>
      </div>
      <nav class="admin-nav">
        <a href="index.php" class="<?= $active === 'dashboard' ? 'is-active' : '' ?>">Tableau de bord</a>
        <a href="orders.php" class="<?= $active === 'orders' ? 'is-active' : '' ?>">Commandes</a>
        <a href="products.php" class="<?= $active === 'products' ? 'is-active' : '' ?>">Produits</a>
        <a href="messages.php" class="<?= $active === 'messages' ? 'is-active' : '' ?>">Messages</a>
        <a href="../index.html">Boutique</a>
        <a href="logout.php">Déconnexion</a>
      </nav>
    </div>
  </header>
  <main class="admin-main">
    <div class="container">
      <?= $content ?>
    </div>
  </main>
</body>
</html>
    <?php
}
