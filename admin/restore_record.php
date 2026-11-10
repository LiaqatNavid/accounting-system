<?php
session_start();
include "../includes/auth_check.php";
include "../config/db.php";
include "../includes/soft_delete_helper.php";
include "../includes/audit_helper.php";

$module = $_GET['module'] ?? '';
$id = $_GET['id'] ?? '';

$map = [
    'payment_voucher' => ['table' => 'payment_voucher', 'key' => 'voucher_no'],
    'receipt_voucher' => ['table' => 'receipt_voucher', 'key' => 'voucher_no'],
    'general_voucher' => ['table' => 'ONLINE_GENERAL_VOUCHER', 'key' => 'VOUCHER_NO'],
    'purchase_voucher' => ['table' => 'purchase_issue_return', 'key' => 'voucher_no'],
    'sale_voucher' => ['table' => 'sale_issue_return', 'key' => 'voucher_no'],
    'stock_adjustment' => ['table' => 'stock_adjustment_master', 'key' => 'id'],
];

if (!isset($map[$module])) {
    header("Location: recycle_bin.php");
    exit;
}

soft_delete_restore($conn, $map[$module]['table'], $map[$module]['key'], $id);
audit_log_event($conn, 'restore_record', $module, (string) $id, 'Soft deleted record restored');
header("Location: recycle_bin.php?restored=1");
exit;
