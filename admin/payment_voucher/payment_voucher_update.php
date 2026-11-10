<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('edit_records')) { header("Location: payment_voucher_grid.php?denied=1"); exit; }
include "../../includes/approval_helper.php";
include "../../includes/period_lock_helper.php";
include "../../includes/audit_helper.php";

/* ================= VALIDATION ================= */

if(!isset($_POST['voucher_no'])){
    die("Voucher Missing");
}

$voucher_no   = intval($_POST['voucher_no']);
$account_no   = $_POST['account_no'];
$voucher_date = $_POST['voucher_date'];
$voucher_type = $_POST['voucher_type'];

$d_account_no = $_POST['d_account_no'] ?? [];
$remarks      = $_POST['remarks'] ?? [];
$debit        = $_POST['debit'] ?? [];

if(empty($account_no)){
    die("Main Account Missing");
}

if(count($d_account_no) == 0){
    die("No Detail Accounts Found");
}

/* ================= START TRANSACTION ================= */
approval_ensure_schema($conn);
period_lock_ensure_schema($conn);

$currentStatus = approval_get_status($conn, 'payment_voucher', 'voucher_no', $voucher_no);
if (approval_is_locked($currentStatus)) {
    header("Location: payment_voucher_grid.php?locked=1");
    exit;
}
if ($voucher_date && period_lock_is_locked($conn, $voucher_date)) {
    header("Location: payment_voucher_grid.php?period_locked=1");
    exit;
}

$before = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT voucher_no, account_no, voucher_date, voucher_type
    FROM payment_voucher
    WHERE voucher_no = '$voucher_no'
"));

mysqli_begin_transaction($conn);

try{

    /* ===== UPDATE MASTER ===== */
    mysqli_query($conn,"
        UPDATE payment_voucher
        SET 
            account_no   = '$account_no',
            voucher_date = '$voucher_date',
            voucher_type = '$voucher_type'
        WHERE voucher_no = '$voucher_no'
    ");

    /* ===== DELETE OLD DETAILS ===== */
    mysqli_query($conn,"
        DELETE FROM payment_voucher_detail
        WHERE voucher_no = '$voucher_no'
    ");

    /* ===== INSERT NEW DETAILS ===== */
    $total = 0;

    for($i=0; $i<count($d_account_no); $i++){

        $acc = $d_account_no[$i];
        $rem = mysqli_real_escape_string($conn,$remarks[$i]);
        $amt = floatval($debit[$i]);

        if($amt <= 0){
            continue;
        }

        $total += $amt;

        mysqli_query($conn,"
            INSERT INTO payment_voucher_detail
            (voucher_no, account_no, remarks, debit)
            VALUES
            ('$voucher_no','$acc','$rem','$amt')
        ");
    }

    if($total <= 0){
        throw new Exception("Invalid Total");
    }

    mysqli_commit($conn);
    $after = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT voucher_no, account_no, voucher_date, voucher_type
        FROM payment_voucher
        WHERE voucher_no = '$voucher_no'
    "));
    audit_log_event($conn, 'update', 'payment_voucher', (string) $voucher_no, 'Payment voucher updated', $before ?: [], $after ?: [], ['detail_rows' => count($d_account_no)]);

    header("Location: payment_voucher_grid.php?updated=1");
    exit;

}catch(Exception $e){

    mysqli_rollback($conn);
    die("Error Updating Voucher");

}
?>
