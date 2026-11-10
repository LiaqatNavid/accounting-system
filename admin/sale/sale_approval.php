<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/approval_helper.php";
include "../../includes/period_lock_helper.php";
include "../../includes/audit_helper.php";

if (!rbac_can_do('approve_records')) {
    header("Location: sale_view.php?denied=1");
    exit;
}

$voucher_no = (int) ($_GET['voucher_no'] ?? 0);
$action = $_GET['action'] ?? 'approve';

$row = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT voucher_no, record_date, deleted_at
    FROM sale_issue_return
    WHERE voucher_no = $voucher_no
"));

if (!$row || !empty($row['deleted_at'])) {
    header("Location: sale_view.php");
    exit;
}

if (period_lock_is_locked($conn, $row['record_date'] ?? '')) {
    header("Location: sale_view.php?period_locked=1");
    exit;
}

$newStatus = $action === 'reopen' ? 'Draft' : 'Approved';
approval_update_status($conn, 'sale_issue_return', 'voucher_no', $voucher_no, $newStatus, 'approved_by');
audit_log_event($conn, $newStatus === 'Approved' ? 'approve' : 'reopen', 'sale_voucher', (string) $voucher_no, 'Sale voucher ' . strtolower($newStatus));
header("Location: sale_view.php?" . ($newStatus === 'Approved' ? 'approved=1' : 'reopened=1'));
exit;
