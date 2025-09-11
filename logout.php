<?php
// Legacy endpoint disabled. Use MVC route /logout via public/index.php
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo "410 Gone - Legacy endpoint. Use /logout (MVC).";
exit;
