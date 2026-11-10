<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!ob_get_level()) {
    ob_start();
}

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/rbac.php";

$sessionTimeoutSeconds = 600;

if (!isset($_SESSION['user_id'])) {
    header("Location: " . rbac_redirect_path('/login.php'));
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - (int) $_SESSION['last_activity']) > $sessionTimeoutSeconds) {
    session_unset();
    session_destroy();
    header("Location: " . rbac_redirect_path('/logout.php?timeout=1'));
    exit();
}

$_SESSION['last_activity'] = time();
rbac_require_current_route($conn);
?>
