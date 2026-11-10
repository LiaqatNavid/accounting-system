
<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('add_records')) { header("Location: payment_voucher_grid.php?denied=1"); exit; }
include "../../includes/approval_helper.php";
include "../../includes/period_lock_helper.php";
include "../../includes/financial_year_helper.php";

/* ================= VALIDATION ================= */

if(!isset($_POST['account_no']) || empty($_POST['account_no'])){
    die("Payment Account Missing");
}

if(!isset($_POST['d_account_no']) || count($_POST['d_account_no']) == 0){
    die("No Detail Accounts Added");
}

$account_no   = $_POST['account_no'];
$voucher_type = $_POST['voucher_type'];
$voucher_date = $_POST['voucher_date'];

$d_account_no = $_POST['d_account_no'];
$remarks      = $_POST['remarks'];
$debit        = $_POST['debit'];

/* ================= START TRANSACTION ================= */
approval_ensure_schema($conn);
period_lock_ensure_schema($conn);
financial_year_ensure_schema($conn);
voucher_series_ensure_transaction_columns($conn);
if ($voucher_date && period_lock_is_locked($conn, $voucher_date)) {
    header("Location: payment_voucher.php?period_locked=1");
    exit;
}
mysqli_begin_transaction($conn);

try {
    $series = voucher_series_next($conn, 'pv', $voucher_date);

    /* ================= INSERT MASTER ================= */
    $sql = "INSERT INTO payment_voucher
            (voucher_type, voucher_date, account_no, approval_status, financial_year_id, series_no, display_voucher_no)
            VALUES ('$voucher_type','$voucher_date','$account_no','Draft'," .
            (($series['financial_year_id'] !== null) ? (int) $series['financial_year_id'] : "NULL") . "," .
            (($series['series_no'] !== null) ? (int) $series['series_no'] : "NULL") . "," .
            (($series['display_no'] !== null) ? ("'" . mysqli_real_escape_string($conn, $series['display_no']) . "'") : "NULL") . ")";
    
    mysqli_query($conn, $sql);

    $voucher_no = mysqli_insert_id($conn);

    /* ================= INSERT DETAILS ================= */
    $totalDebit = 0;

    for($i=0; $i<count($d_account_no); $i++){

        if($debit[$i] == '' || $debit[$i] <= 0){
            continue;
        }

        $acc   = $d_account_no[$i];
        $rem   = mysqli_real_escape_string($conn,$remarks[$i]);
        $amt   = $debit[$i];

        $totalDebit += $amt;

        $sql_detail = "INSERT INTO payment_voucher_detail
                       (voucher_no, account_no, remarks, debit)
                       VALUES
                       ('$voucher_no','$acc','$rem','$amt')";

        mysqli_query($conn, $sql_detail);
    }

    if($totalDebit <= 0){
        throw new Exception("Total Debit Invalid");
    }

    mysqli_commit($conn);

    header("Location: payment_voucher.php?vno=".$voucher_no."&saved=1");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conn);
    die("Error Saving Voucher");
}
?>
