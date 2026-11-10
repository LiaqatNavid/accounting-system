<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/approval_helper.php";
include "../../includes/period_lock_helper.php";
include "../../includes/audit_helper.php";

if (!rbac_can_do('approve_records')) {
    header("Location: stock_adjustment_list.php?denied=1");
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
$action = $_GET['action'] ?? 'approve';

$row = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT id, adjustment_date, deleted_at
    FROM stock_adjustment_master
    WHERE id = $id
"));

if (!$row || !empty($row['deleted_at'])) {
    header("Location: stock_adjustment_list.php");
    exit;
}

if (period_lock_is_locked($conn, $row['adjustment_date'] ?? '')) {
    header("Location: stock_adjustment_list.php?period_locked=1");
    exit;
}

$newStatus = $action === 'reopen' ? 'Draft' : 'Approved';
approval_update_status($conn, 'stock_adjustment_master', 'id', $id, $newStatus, 'approved_by');
audit_log_event($conn, $newStatus === 'Approved' ? 'approve' : 'reopen', 'stock_adjustment', (string) $id, 'Stock adjustment ' . strtolower($newStatus));
header("Location: stock_adjustment_list.php?" . ($newStatus === 'Approved' ? 'approved=1' : 'reopened=1'));
exit;
