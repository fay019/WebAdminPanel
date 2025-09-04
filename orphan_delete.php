<?php
declare(strict_types=1);
// Legacy endpoint moved to MVC. Keep bookmarks working with a 302 redirect.
header('Location: /sites', true, 302);
exit;