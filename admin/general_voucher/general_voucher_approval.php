<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/approval_helper.php";
include "../../includes/period_lock_helper.php";
include "../../includes/audit_helper.php";

if (!rbac_can_do('approve_records')) {
    header("Location: general_voucher_list.php?denied=1");
    exit;
}

$voucher_no = (int) ($_GET['vno'] ?? 0);
$action = $_GET['action'] ?? 'approve';

if ($voucher_no <= 0) {
    header("Location: general_voucher_list.php");
    exit;
}

$row = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT VOUCHER_NO, VOUCHER_DATE, deleted_at
    FROM ONLINE_GENERAL_VOUCHER
    WHERE VOUCHER_NO = $voucher_no
"));

if (!$row || !empty($row['deleted_at'])) {
    header("Location: general_voucher_list.php");
    exit;
}

if (period_lock_is_locked($conn, $row['VOUCHER_DATE'] ?? '')) {
    header("Location: general_voucher_list.php?period_locked=1");
    exit;
}

$newStatus = ($action === 'reopen') ? 'Draft' : 'Approved';
approval_update_status($conn, 'ONLINE_GENERAL_VOUCHER', 'VOUCHER_NO', $voucher_no, $newStatus, 'APPROVED_BY');
audit_log_event($conn, $newStatus === 'Approved' ? 'approve' : 'reopen', 'general_voucher', (string) $voucher_no, 'General voucher ' . strtolower($newStatus));

header("Location: general_voucher_list.php?" . ($newStatus === 'Approved' ? 'approved=1' : 'reopened=1'));
exit;
