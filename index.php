<?php
declare(strict_types=1);

if (!is_file(__DIR__ . '/config/database.php')) {
    header('Location: install.php');
    exit;
}

header('Location: index.html');
exit;
