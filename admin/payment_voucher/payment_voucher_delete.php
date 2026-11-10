<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/audit_helper.php";
include "../../includes/approval_helper.php";
include "../../includes/soft_delete_helper.php";
include "../../includes/period_lock_helper.php";
soft_delete_ensure_schema($conn);
period_lock_ensure_schema($conn);

if (!rbac_can_do('delete_records')) {
    header("Location: payment_voucher_grid.php?denied=1");
    exit;
}

if (!isset($_GET['vno'])) {
    die("Voucher Number Missing");
}

$voucher_no = (int) $_GET['vno'];

if ($voucher_no <= 0) {
    die("Invalid Voucher");
}

$check = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT voucher_no, voucher_type, voucher_date, account_no, approval_status
    FROM payment_voucher
    WHERE voucher_no = $voucher_no
"));

if (!$check) {
    die("Voucher Not Found");
}

approval_ensure_schema($conn);
if (approval_is_locked($check['approval_status'] ?? 'Draft')) {
    header("Location: payment_voucher_grid.php?locked=1");
    exit;
}
if (period_lock_is_locked($conn, $check['voucher_date'] ?? '')) {
    header("Location: payment_voucher_grid.php?period_locked=1");
    exit;
}

mysqli_begin_transaction($conn);

try {
    soft_delete_mark($conn, 'payment_voucher', 'voucher_no', $voucher_no);

    if (mysqli_error($conn)) {
        throw new Exception(mysqli_error($conn));
    }

    audit_log_delete(
        $conn,
        'payment_voucher',
        (string) $voucher_no,
        'Payment Voucher #' . $voucher_no,
        [
            'voucher_type' => $check['voucher_type'] ?? '',
            'voucher_date' => $check['voucher_date'] ?? '',
            'account_no' => $check['account_no'] ?? ''
        ]
    );

    mysqli_commit($conn);
    header("Location: payment_voucher_grid.php?deleted=1");
    exit;
} catch (Exception $e) {
    mysqli_rollback($conn);
    die("Error deleting voucher");
}
