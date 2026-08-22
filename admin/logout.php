<?php
declare(strict_types=1);
require __DIR__ . '/_init.php';
Auth::logout();
admin_redirect('login.php');
