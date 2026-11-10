<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('edit_records')) { header("Location: receipt_voucher_grid.php?denied=1"); exit; }
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
$credit       = $_POST['credit'] ?? [];

if(empty($account_no)){
    die("Main Account Missing");
}

if(count($d_account_no) == 0){
    die("No Detail Accounts Found");
}

/* ================= START TRANSACTION ================= */
approval_ensure_schema($conn);
period_lock_ensure_schema($conn);

$currentStatus = approval_get_status($conn, 'receipt_voucher', 'voucher_no', $voucher_no);
if (approval_is_locked($currentStatus)) {
    header("Location: receipt_voucher_grid.php?locked=1");
    exit;
}
if ($voucher_date && period_lock_is_locked($conn, $voucher_date)) {
    header("Location: receipt_voucher_grid.php?period_locked=1");
    exit;
}

$before = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT voucher_no, account_no, voucher_date, voucher_type
    FROM receipt_voucher
    WHERE voucher_no = '$voucher_no'
"));

mysqli_begin_transaction($conn);

try{

    /* ===== UPDATE MASTER ===== */
    $updateMaster = "
        UPDATE receipt_voucher
        SET 
            account_no   = '$account_no',
            voucher_date = '$voucher_date',
            voucher_type = '$voucher_type'
        WHERE voucher_no = '$voucher_no'
    ";

    if(!mysqli_query($conn,$updateMaster)){
        throw new Exception(mysqli_error($conn));
    }

    /* ===== DELETE OLD DETAILS ===== */
    if(!mysqli_query($conn,"
        DELETE FROM receipt_voucher_detail
        WHERE voucher_no = '$voucher_no'
    ")){
        throw new Exception(mysqli_error($conn));
    }

    /* ===== INSERT NEW DETAILS ===== */
    $total = 0;

    for($i=0; $i<count($d_account_no); $i++){

        $acc = $d_account_no[$i];
        $rem = mysqli_real_escape_string($conn,$remarks[$i]);
        $amt = floatval($credit[$i]);

        if($amt <= 0){
            continue;
        }

        $total += $amt;

        $insertDetail = "
            INSERT INTO receipt_voucher_detail
            (voucher_no, account_no, remarks, credit)
            VALUES
            ('$voucher_no','$acc','$rem','$amt')
        ";

        if(!mysqli_query($conn,$insertDetail)){
            throw new Exception(mysqli_error($conn));
        }
    }

    if($total <= 0){
        throw new Exception("Invalid Total Credit");
    }

    mysqli_commit($conn);
    $after = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT voucher_no, account_no, voucher_date, voucher_type
        FROM receipt_voucher
        WHERE voucher_no = '$voucher_no'
    "));
    audit_log_event($conn, 'update', 'receipt_voucher', (string) $voucher_no, 'Receipt voucher updated', $before ?: [], $after ?: [], ['detail_rows' => count($d_account_no)]);

    header("Location: receipt_voucher_grid.php?updated=1");
    exit;

}catch(Exception $e){

    mysqli_rollback($conn);
    echo "Error Updating Receipt Voucher: " . $e->getMessage();
    exit;

}
?>
