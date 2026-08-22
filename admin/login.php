<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';

if (Auth::check()) {
    admin_redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Csrf::requireValid($_POST['_csrf'] ?? null);
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if (Auth::login($username, $password)) {
        admin_redirect('index.php');
    }
    $error = 'Identifiants incorrects';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Connexion admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../css/checkout.css" />
  <style>
    body { min-height: 100vh; display: grid; place-items: center; background: linear-gradient(160deg, #00441b, #063318); font-family: Outfit, sans-serif; padding: 1rem; }
    .card { width: min(420px,100%); background: #faf8f4; border: 1px solid rgba(212,175,55,.3); border-radius: 16px; padding: 2rem; }
    h1 { margin: 0 0 .5rem; font-size: 1.4rem; font-family: "Cormorant Garamond", serif; color: #00441b; }
    label { display: block; margin-top: 1rem; margin-bottom: .35rem; color: #0d3d28; }
    input { width: 100%; padding: .75rem 1rem; border: 1px solid rgba(13,61,40,.15); border-radius: 10px; }
    button { margin-top: 1.25rem; width: 100%; padding: .85rem; border: 0; border-radius: 999px; background: linear-gradient(135deg,#f5e6a8,#d4af37,#b8860b); color: #00441b; font-weight: 600; cursor: pointer; }
    .error { color: #b42318; margin-top: .75rem; }
  </style>
</head>
<body>
  <form class="card" method="post">
    <?= Csrf::field() ?>
    <h1>Administration</h1>
    <p>Connectez-vous pour gérer commandes et produits.</p>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <label for="username">Identifiant</label>
    <input id="username" name="username" required autofocus />
    <label for="password">Mot de passe</label>
    <input id="password" name="password" type="password" required />
    <button type="submit">Se connecter</button>
  </form>
</body>
</html>
