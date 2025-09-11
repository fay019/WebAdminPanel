<?php
// Legacy root entry disabled. Use public/index.php (front controller)
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo "410 Gone - Use public/index.php entrypoint.";
exit;
