<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/approval_helper.php";
include "../../includes/period_lock_helper.php";
include "../../includes/audit_helper.php";

if (!rbac_can_do('approve_records')) {
    header("Location: receipt_voucher_grid.php?denied=1");
    exit;
}

$voucher_no = (int) ($_GET['vno'] ?? 0);
$action = $_GET['action'] ?? 'approve';

if ($voucher_no <= 0) {
    header("Location: receipt_voucher_grid.php");
    exit;
}

$row = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT voucher_no, voucher_date, deleted_at
    FROM receipt_voucher
    WHERE voucher_no = $voucher_no
"));

if (!$row || !empty($row['deleted_at'])) {
    header("Location: receipt_voucher_grid.php");
    exit;
}

if (period_lock_is_locked($conn, $row['voucher_date'] ?? '')) {
    header("Location: receipt_voucher_grid.php?period_locked=1");
    exit;
}

$newStatus = ($action === 'reopen') ? 'Draft' : 'Approved';
approval_update_status($conn, 'receipt_voucher', 'voucher_no', $voucher_no, $newStatus, 'approved_by');
audit_log_event($conn, $newStatus === 'Approved' ? 'approve' : 'reopen', 'receipt_voucher', (string) $voucher_no, 'Receipt voucher ' . strtolower($newStatus));

header("Location: receipt_voucher_grid.php?" . ($newStatus === 'Approved' ? 'approved=1' : 'reopened=1'));
exit;
