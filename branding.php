<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_login();
redirect('settings.php');
