<?php
require_once __DIR__ . '/../includes/auth.php';
logoutPartner();
header('Location: /parceiro/index.php');
exit;
