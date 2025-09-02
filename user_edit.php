<?php
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$to = $id > 0 ? "/users/$id/edit" : '/users';
header('Location: ' . $to, true, 302);
exit;
