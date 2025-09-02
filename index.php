<?php
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/db.php';
migrate();
if (!is_logged_in()) { header('Location: /login'); exit; }
header('Location: /dashboard');
