<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
startChatSession();
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;