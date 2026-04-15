<?php
require_once 'db.php';
logoutUser();
header('Location: index');
exit;
?>