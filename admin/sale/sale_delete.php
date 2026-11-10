<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/audit_helper.php";
include "../../includes/approval_helper.php";
include "../../includes/soft_delete_helper.php";
include "../../includes/period_lock_helper.php";
approval_ensure_schema($conn);
soft_delete_ensure_schema($conn);
period_lock_ensure_schema($conn);

if (!rbac_can_do('delete_records')) {
    header("Location: sale_view.php?denied=1");
    exit;
}

if(!isset($_GET['voucher_no'])){
    die("Invalid Request");
}

$voucher_no = intval($_GET['voucher_no']);

if($voucher_no <= 0){
    die("Invalid Voucher");
}

$auditCheck = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT voucher_no, account_no, record_date, sale_or_return, bill_no, approval_status, deleted_at
    FROM sale_issue_return
    WHERE voucher_no = $voucher_no
"));

if(!$auditCheck || !empty($auditCheck['deleted_at'])){
    die("Voucher not found");
}

if (approval_is_locked($auditCheck['approval_status'] ?? 'Draft')) {
    header("Location: sale_view.php?locked=1");
    exit;
}

if (period_lock_is_locked($conn, $auditCheck['record_date'] ?? '')) {
    header("Location: sale_view.php?period_locked=1");
    exit;
}

/* Start Transaction */
mysqli_begin_transaction($conn);

try{

    soft_delete_mark($conn, 'sale_issue_return', 'voucher_no', $voucher_no);

    audit_log_delete(
        $conn,
        'sale_voucher',
        (string) $voucher_no,
        'Sale Voucher #' . $voucher_no,
        [
            'account_no' => $auditCheck['account_no'] ?? '',
            'record_date' => $auditCheck['record_date'] ?? '',
            'sale_or_return' => $auditCheck['sale_or_return'] ?? '',
            'bill_no' => $auditCheck['bill_no'] ?? ''
        ]
    );

    mysqli_commit($conn);

    /* Redirect back to grid */
    header("Location: sale_view.php?deleted=1");
    exit;

}catch(Exception $e){

    mysqli_rollback($conn);

    die("Delete Failed");
}
?>
