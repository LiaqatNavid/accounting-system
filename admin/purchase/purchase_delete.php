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
    header("Location: purchase_grid_view.php?denied=1");
    exit;
}

if(!isset($_GET['voucher_no'])){
    die("Invalid request.");
}

$voucher_no = intval($_GET['voucher_no']);

/* =========================
   CHECK VOUCHER EXISTS
========================= */
$check = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT * FROM purchase_issue_return
    WHERE voucher_no = $voucher_no
      AND deleted_at IS NULL
"));

if(!$check){
    die("Voucher not found.");
}

if (approval_is_locked($check['approval_status'] ?? 'Draft')) {
    header("Location: purchase_grid_view.php?locked=1");
    exit;
}

if (period_lock_is_locked($conn, $check['record_date'] ?? '')) {
    header("Location: purchase_grid_view.php?period_locked=1");
    exit;
}

$auditRow = $check;

/* =========================
   OPTIONAL: PREVENT DELETE IF POSTED
   (Only if you have is_posted column)
========================= */
if(isset($check['is_posted']) && $check['is_posted']==1){
    die("Posted voucher cannot be deleted.");
}

/* =========================
   START TRANSACTION
========================= */
mysqli_begin_transaction($conn);

try {

    soft_delete_mark($conn, 'purchase_issue_return', 'voucher_no', $voucher_no);

    if(mysqli_error($conn)){
        throw new Exception(mysqli_error($conn));
    }

    audit_log_delete(
        $conn,
        'purchase_voucher',
        (string) $voucher_no,
        'Purchase Voucher #' . $voucher_no,
        [
            'account_no' => $auditRow['account_no'] ?? '',
            'record_date' => $auditRow['record_date'] ?? '',
            'issue_or_return' => $auditRow['issue_or_return'] ?? ''
        ]
    );

    mysqli_commit($conn);

    header("Location: purchase_grid_view.php?msg=deleted");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conn);

    echo "<h3 style='color:red;'>Error Deleting Voucher</h3>";
    echo "<p>".$e->getMessage()."</p>";
}
