<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$user = require_member();

layout_start('Need Help?', $user);
render_desk_need_help(['page' => true]);
layout_end();
