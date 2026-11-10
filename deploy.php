<?php
// Automatic GitHub Webhook Deployment Endpoint
// Triggered on every GitHub push to update http://account-system.liaqatnavid.com

$secretKey = "Accounting2026!";
$incomingSecret = $_GET['secret'] ?? '';

// Check header token as well (if configured in GitHub webhook secret)
if (empty($incomingSecret) && isset($_SERVER['HTTP_X_HUB_SIGNATURE_256'])) {
    $payload = file_get_contents('php://input');
    $hash = 'sha256=' . hash_hmac('sha256', $payload, $secretKey);
    if (hash_equals($hash, $_SERVER['HTTP_X_HUB_SIGNATURE_256'])) {
        $incomingSecret = $secretKey;
    }
}

if ($incomingSecret !== $secretKey) {
    http_response_code(403);
    die("Access denied. Invalid webhook secret.");
}

$dir = __DIR__;
chdir($dir);

// Fetch and apply the latest commit from GitHub main branch
$output = shell_exec("git fetch origin main 2>&1 && git reset --hard origin/main 2>&1");

header('Content-Type: text/plain');
echo "=== GITHUB AUTO-DEPLOYMENT SUCCESSFUL ===\n";
echo "Date: " . date("Y-m-d H:i:s") . "\n";
echo "Directory: " . $dir . "\n";
echo "Output:\n" . $output;
?>
