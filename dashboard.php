<?php
// Legacy dashboard neutralized: redirect to MVC route
header("Location: /dashboard", true, 302);
exit;