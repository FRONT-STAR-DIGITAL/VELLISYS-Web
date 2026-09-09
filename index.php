<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if (current_user()) {
    redirect('dashboard.php');
}
redirect('login.php');
