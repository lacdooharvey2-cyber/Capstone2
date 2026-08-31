<?php
session_start();
include("db.php");

if (!isset($_SESSION['user_id'])) {
    // kung walang session, balik login
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sender_id   = $_SESSION['user_id'];
    $receiver_id = intval($_POST['receiver_id']);
    $content     = trim($_POST['content']);

    if (!empty($content)) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, content, status) VALUES (?, ?, ?, 'Unread')");
        $stmt->bind_param("iis", $sender_id, $receiver_id, $content);
        $stmt->execute();
        $stmt->close();
    }

    $allowed_redirects = [
        'adminmessages.php',
        'cashiermessage.php',
        'customermessage.php',
        'technicianmessage.php'
    ];
    $redirect = $_POST['redirect'] ?? '';
    if (!in_array($redirect, $allowed_redirects, true)) {
        $redirect = match ($_SESSION['role'] ?? '') {
            'Admin' => 'adminmessages.php',
            'Cashier' => 'cashiermessage.php',
            'Technician' => 'technicianmessage.php',
            default => 'customermessage.php',
        };
    }

    header("Location: ".$redirect."?contact=".$receiver_id);
    exit();
}
?>
