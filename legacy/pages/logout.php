<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/bootstrap.php';

legacy_session_start();
session_destroy();
header('Location: login.php');
exit;




