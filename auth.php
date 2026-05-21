<?php
/**
 * FinTrack ET - Safe Authentication Hub
 * Implements session controls, secure user redirects, and credential verifications.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Enable session cookies safety
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

/**
 * Checks if a user session is active
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Enforces session log checks, redirecting unauthenticated users to the portal
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit;
    }
}

/**
 * Logs out the active user and clears session state
 */
function logout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
