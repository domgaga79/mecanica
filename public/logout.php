<?php
require_once __DIR__ . '/../core/auth.php';

auth_destroy_session();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Location: /login.php?logout=1', true, 302);
exit;
