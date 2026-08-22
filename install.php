<?php
/**
 * Installation Pure Essence Vita
 * Ouvrez : http://localhost/Ecom/install.php
 */
declare(strict_types=1);

require __DIR__ . '/database/seed.php';

$step = $_POST['step'] ?? 'form';
$error = '';
$success = '';
$installed = is_file(__DIR__ . '/config/database.php');

if ($installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/includes/Env.php';
    Env::load();
    if (!Env::bool('INSTALL_ALLOW_REINSTALL', false)) {
        http_response_code(403);
        exit('Installation déjà effectuée. Supprimez config/database.php ou définissez INSTALL_ALLOW_REINSTALL=true dans .env pour réinstaller.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'install') {
    $host = trim($_POST['host'] ?? '127.0.0.1');
    $port = (int) ($_POST['port'] ?? 3306);
    $database = trim($_POST['database'] ?? 'pure_essence_vita');
    $username = trim($_POST['username'] ?? 'root');
    $password = (string) ($_POST['password'] ?? '');
    $adminUser = trim($_POST['admin_user'] ?? 'admin');
    $adminPass = (string) ($_POST['admin_pass'] ?? 'pev_admin_2026');

    try {
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('Extension PDO MySQL requise.');
        }

        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $host, $port),
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$database`");

        $schema = file_get_contents(__DIR__ . '/database/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('schema.sql introuvable');
        }

        foreach (array_filter(array_map('trim', explode(';', $schema))) as $query) {
            if ($query !== '') {
                $pdo->exec($query);
            }
        }

        $products = load_products_from_js(__DIR__ . '/js/products.js');
        $productCount = seed_products($pdo, $products);

        $hash = password_hash($adminPass, PASSWORD_DEFAULT);
        $pdo->prepare(
            'INSERT INTO admin_users (username, password_hash) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash)'
        )->execute([$adminUser, $hash]);

        $ordersImported = migrate_orders_json($pdo, __DIR__ . '/data/orders.json');

        $config = "<?php\nreturn [\n"
            . "    'host' => " . var_export($host, true) . ",\n"
            . "    'port' => $port,\n"
            . "    'database' => " . var_export($database, true) . ",\n"
            . "    'username' => " . var_export($username, true) . ",\n"
            . "    'password' => " . var_export($password, true) . ",\n"
            . "    'charset' => 'utf8mb4',\n"
            . "];\n";

        if (file_put_contents(__DIR__ . '/config/database.php', $config) === false) {
            throw new RuntimeException('Impossible d\'écrire config/database.php');
        }

        $envPath = __DIR__ . '/.env';
        $envExample = __DIR__ . '/.env.example';
        if (!is_file($envPath) && is_file($envExample)) {
            copy($envExample, $envPath);
        }

        $installed = true;
        $success = "Installation réussie ! $productCount produits importés"
            . ($ordersImported ? ", $ordersImported commande(s) migrée(s)" : '')
            . (is_file($envPath) ? ' Fichier .env prêt (Wave, Orange Money, chatbot).' : '');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Installation — Pure Essence Vita</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <style>
    * { box-sizing: border-box; }
    body { font-family: Outfit, sans-serif; background: #00441b; color: #faf8f4; margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 2rem; }
    .card { width: min(520px, 100%); background: #faf8f4; border: 1px solid rgba(212,175,55,.35); border-radius: 16px; padding: 2rem; color: #0a2018; }
    h1 { margin: 0 0 .5rem; font-size: 1.6rem; font-family: "Cormorant Garamond", serif; color: #00441b; }
    p { color: #4a5c52; line-height: 1.5; }
    label { display: block; margin: 1rem 0 .35rem; font-size: .9rem; color: #0d3d28; }
    input { width: 100%; padding: .75rem 1rem; border-radius: 10px; border: 1px solid rgba(13,61,40,.15); background: #fff; color: #0a2018; }
    .row { display: grid; grid-template-columns: 1fr 120px; gap: .75rem; }
    button, .btn { display: inline-block; margin-top: 1.25rem; padding: .85rem 1.25rem; border: 0; border-radius: 999px; background: linear-gradient(135deg,#f5e6a8,#d4af37,#b8860b); color: #00441b; font-weight: 600; cursor: pointer; text-decoration: none; }
    .alert { margin-top: 1rem; padding: .85rem 1rem; border-radius: 10px; }
    .alert-error { background: rgba(220,53,69,.15); color: #ffb4bc; }
    .alert-success { background: rgba(40,167,69,.15); color: #b8f5c6; }
    .links { margin-top: 1.5rem; display: flex; gap: .75rem; flex-wrap: wrap; }
  </style>
</head>
<body>
  <div class="card">
    <h1>Installation PHP</h1>
    <p>Configurez MySQL, importez les produits et créez le compte administrateur. Le fichier <code>.env</code> (Wave, Orange Money, chatbot) sera créé automatiquement si absent.</p>

    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (!$installed || $success === ''): ?>
    <form method="post">
      <input type="hidden" name="step" value="install" />
      <div class="row">
        <div>
          <label for="host">Hôte MySQL</label>
          <input id="host" name="host" value="<?= htmlspecialchars($_POST['host'] ?? '127.0.0.1') ?>" required />
        </div>
        <div>
          <label for="port">Port</label>
          <input id="port" name="port" type="number" value="<?= htmlspecialchars((string)($_POST['port'] ?? '3306')) ?>" required />
        </div>
      </div>
      <label for="database">Base de données</label>
      <input id="database" name="database" value="<?= htmlspecialchars($_POST['database'] ?? 'pure_essence_vita') ?>" required />
      <label for="username">Utilisateur MySQL</label>
      <input id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? 'root') ?>" required />
      <label for="password">Mot de passe MySQL</label>
      <input id="password" name="password" type="password" value="" placeholder="Vide sur WAMP par défaut" />
      <label for="admin_user">Admin — identifiant</label>
      <input id="admin_user" name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? 'admin') ?>" required />
      <label for="admin_pass">Admin — mot de passe</label>
      <input id="admin_pass" name="admin_pass" type="password" value="<?= htmlspecialchars($_POST['admin_pass'] ?? 'pev_admin_2026') ?>" required />
      <button type="submit"><?= $installed ? 'Réinstaller' : 'Installer' ?></button>
    </form>
    <?php endif; ?>

    <?php if ($installed): ?>
    <div class="links">
      <a class="btn" href="index.php">Voir la boutique</a>
      <a class="btn" href="admin/">Panneau admin</a>
    </div>
    <?php endif; ?>
  </div>
</body>
</html>
