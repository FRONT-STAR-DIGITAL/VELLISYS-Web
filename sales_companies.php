<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require_sales_agent();
// Renamed to On Testing — keep old URL working.
redirect('sales_testing.php');
