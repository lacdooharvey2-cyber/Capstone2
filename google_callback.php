<?php

session_start();

require_once __DIR__ . '/googleconfig.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/schema_helpers.php';

if (!columnExists($conn, 'users', 'google_id')) {
    $conn->query("ALTER TABLE users ADD COLUMN google_id VARCHAR(255) NULL AFTER email");
}

// =====================================================
// CHECK FOR GOOGLE ERROR
// =====================================================

if (isset($_GET['error'])) {
    header('Location: login.php?error=google_cancelled');
    exit;
}

// =====================================================
// CHECK STATE
// =====================================================

if (!isset($_GET['state']) || !isset($_SESSION['oauth2_state'])) {
    header('Location: login.php?error=invalid_state');
    exit;
}

if (!hash_equals($_SESSION['oauth2_state'], $_GET['state'])) {

    unset($_SESSION['oauth2_state']);

    header('Location: login.php?error=invalid_state');
    exit;
}

// State has been used
unset($_SESSION['oauth2_state']);

// =====================================================
// CHECK AUTHORIZATION CODE
// =====================================================

if (!isset($_GET['code'])) {
    header('Location: login.php?error=no_code');
    exit;
}

try {

    // Exchange authorization code for access token
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {

        error_log(
            'Google OAuth Error: ' .
            ($token['error_description'] ?? $token['error'])
        );

        header('Location: login.php?error=google_auth_failed');
        exit;
    }

    $client->setAccessToken($token);

    // =================================================
    // GET GOOGLE USER INFORMATION
    // =================================================

    $oauth2 = new Google\Service\Oauth2($client);

    $googleUser = $oauth2->userinfo->get();

    $googleId = $googleUser->getId();
    $email    = $googleUser->getEmail();
    $name     = $googleUser->getName();

    // =================================================
    // BASIC VALIDATION
    // =================================================

    if (empty($googleId) || empty($email)) {
        header('Location: login.php?error=google_data_missing');
        exit;
    }

    // =================================================
    // CHECK IF GOOGLE ACCOUNT ALREADY EXISTS
    // =================================================

    $sql = "SELECT * FROM users WHERE google_id = ? LIMIT 1";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("s", $googleId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        // =============================================
        // EXISTING GOOGLE ACCOUNT
        // =============================================

        $_SESSION['user_id']   = $row['user_id'];
        $_SESSION['custom_id'] = $row['custom_id'];
        $_SESSION['role']      = $row['role'];

        // Regenerate session ID for security
        session_regenerate_id(true);

        // Customer dashboard
        switch ($row['role']) {

            case 'Admin':
                header("Location: admindashboard.php");
                break;

            case 'Technician':
                header("Location: techniciandashboard.php");
                break;

            case 'Customer':
                header("Location: customerdashboard.php");
                break;

            case 'Cashier':
                header("Location: cashierdashboard.php");
                break;

            default:
                header("Location: login.php?error=invalidrole");
        }

        exit;
    }

    $stmt->close();

    // =================================================
    // CHECK IF EMAIL ALREADY EXISTS
    // =================================================

    $sql = "SELECT * FROM users WHERE email = ? LIMIT 1";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {

        // =============================================
        // EMAIL EXISTS
        // =============================================

        // If the existing account doesn't have a Google ID,
        // connect this Google account to it.
        if (empty($row['google_id'])) {

            $updateSql = "UPDATE users
                          SET google_id = ?
                          WHERE user_id = ?";

            $updateStmt = $conn->prepare($updateSql);

            if (!$updateStmt) {
                throw new Exception($conn->error);
            }

            $updateStmt->bind_param(
                "si",
                $googleId,
                $row['user_id']
            );

            $updateStmt->execute();
            $updateStmt->close();
        }

        // Login existing account
        $_SESSION['user_id']   = $row['user_id'];
        $_SESSION['custom_id'] = $row['custom_id'];
        $_SESSION['role']      = $row['role'];

        session_regenerate_id(true);

        switch ($row['role']) {

            case 'Admin':
                header("Location: admindashboard.php");
                break;

            case 'Technician':
                header("Location: techniciandashboard.php");
                break;

            case 'Customer':
                header("Location: customerdashboard.php");
                break;

            case 'Cashier':
                header("Location: cashierdashboard.php");
                break;

            default:
                header("Location: login.php?error=invalidrole");
        }

        exit;
    }

    $stmt->close();

    // =================================================
    // CREATE NEW CUSTOMER ACCOUNT
    // =================================================

    /*
     * Generate a custom User ID.
     *
     * Example:
     * CUST-2026-12345
     */

    do {

        $customId = 'CUST-' . date('Y') . '-' . random_int(10000, 99999);

        $checkSql = "SELECT user_id
                     FROM users
                     WHERE custom_id = ?
                     LIMIT 1";

        $checkStmt = $conn->prepare($checkSql);

        if (!$checkStmt) {
            throw new Exception($conn->error);
        }

        $checkStmt->bind_param("s", $customId);
        $checkStmt->execute();

        $checkResult = $checkStmt->get_result();

        $exists = $checkResult->num_rows > 0;

        $checkStmt->close();

    } while ($exists);

    // =================================================
    // PASSWORD
    // =================================================

    /*
     * Google users don't need to know this password.
     * We still provide a random password hash because
     * your existing users table expects password_hash.
     */

    $randomPassword = bin2hex(random_bytes(32));

    $passwordHash = password_hash(
        $randomPassword,
        PASSWORD_DEFAULT
    );

    // =================================================
    // CREATE USER
    // =================================================

    $role = 'Customer';

    $insertSql = "INSERT INTO users
                  (
                      custom_id,
                      name,
                      email,
                      google_id,
                      password_hash,
                      role
                  )
                  VALUES
                  (?, ?, ?, ?, ?, ?)";

    $insertStmt = $conn->prepare($insertSql);

    if (!$insertStmt) {
        throw new Exception($conn->error);
    }

    $insertStmt->bind_param(
        "ssssss",
        $customId,
        $name,
        $email,
        $googleId,
        $passwordHash,
        $role
    );

    if (!$insertStmt->execute()) {
        throw new Exception($insertStmt->error);
    }

    $newUserId = $conn->insert_id;

    $insertStmt->close();

    // =================================================
    // LOGIN NEW USER
    // =================================================

    session_regenerate_id(true);

    $_SESSION['user_id']   = $newUserId;
    $_SESSION['custom_id'] = $customId;
    $_SESSION['role']      = 'Customer';

    $_SESSION['google_login'] = true;

    // Redirect customer
    header("Location: customerdashboard.php");
    exit;

} catch (Exception $e) {

    error_log(
        'Google Login Error: ' . $e->getMessage()
    );

    header(
        'Location: login.php?error=google_login_failed'
    );

    exit;
}
