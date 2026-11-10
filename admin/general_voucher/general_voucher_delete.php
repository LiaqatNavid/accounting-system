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
    header("Location: general_voucher_list.php?denied=1");
    exit;
}

/* ================= CHECK VOUCHER ================= */
if(!isset($_GET['vno'])){
    die("Voucher Number Missing");
}

$voucher_no = intval($_GET['vno']);

if($voucher_no <= 0){
    die("Invalid Voucher");
}

/* ================= CHECK IF EXISTS ================= */
$check = mysqli_query($conn,"
    SELECT VOUCHER_NO, VOUCHER_TYPE, VOUCHER_DATE, TOTAL_DEBIT, TOTAL_CREDIT, approval_status
    FROM ONLINE_GENERAL_VOUCHER
    WHERE VOUCHER_NO = $voucher_no
");

if(mysqli_num_rows($check) == 0){
    die("Voucher Not Found");
}

$auditRow = mysqli_fetch_assoc($check);

approval_ensure_schema($conn);
if (approval_is_locked($auditRow['approval_status'] ?? 'Draft')) {
    header("Location: general_voucher_list.php?locked=1");
    exit;
}
if (period_lock_is_locked($conn, $auditRow['VOUCHER_DATE'] ?? '')) {
    header("Location: general_voucher_list.php?period_locked=1");
    exit;
}

/* ================= START TRANSACTION ================= */
mysqli_begin_transaction($conn);

try {

    soft_delete_mark($conn, 'ONLINE_GENERAL_VOUCHER', 'VOUCHER_NO', $voucher_no);

    audit_log_delete(
        $conn,
        'general_voucher',
        (string) $voucher_no,
        'General Voucher #' . $voucher_no,
        [
            'voucher_type' => $auditRow['VOUCHER_TYPE'] ?? '',
            'voucher_date' => $auditRow['VOUCHER_DATE'] ?? '',
            'total_debit' => $auditRow['TOTAL_DEBIT'] ?? 0,
            'total_credit' => $auditRow['TOTAL_CREDIT'] ?? 0
        ]
    );

    /* COMMIT */
    mysqli_commit($conn);

    echo "<script>
        alert('Voucher Deleted Successfully!');
        window.location.href='general_voucher_list.php?deleted=1';
    </script>";

} catch (Exception $e) {

    mysqli_rollback($conn);

    echo "<script>
        alert('Error deleting voucher.');
        window.location.href='general_voucher_list.php';
    </script>";
}
