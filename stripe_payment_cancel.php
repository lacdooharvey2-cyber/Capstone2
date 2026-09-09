<?php
declare(strict_types=1);

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Customer') {
    header('Location: login.php');
    exit();
}

header('Location: customerdashboard.php?payment=cancelled');
exit();
?>
