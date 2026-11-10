<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('add_records')) { header("Location: receipt_voucher_grid.php?denied=1"); exit; }
include "../../includes/approval_helper.php";
include "../../includes/period_lock_helper.php";
include "../../includes/financial_year_helper.php";

if(!isset($_POST['account_no']) || empty($_POST['account_no'])){
    die("Receipt Account Missing");
}

if(!isset($_POST['d_account_no']) || count($_POST['d_account_no']) == 0){
    die("No Detail Accounts Added");
}

$account_no   = $_POST['account_no'];
$voucher_type = $_POST['voucher_type'];
$voucher_date = $_POST['voucher_date'];

$d_account_no = $_POST['d_account_no'];
$remarks      = $_POST['remarks'];
$credit       = $_POST['credit'];

approval_ensure_schema($conn);
period_lock_ensure_schema($conn);
financial_year_ensure_schema($conn);
voucher_series_ensure_transaction_columns($conn);
if ($voucher_date && period_lock_is_locked($conn, $voucher_date)) {
    header("Location: receipt_voucher.php?period_locked=1");
    exit;
}
mysqli_begin_transaction($conn);

try {
    $series = voucher_series_next($conn, 'rv', $voucher_date);

    /* ===== Generate Next Voucher Number ===== */
    $res = mysqli_query($conn,
        "SELECT IFNULL(MAX(voucher_no),0) AS vno FROM receipt_voucher"
    );

    $row = mysqli_fetch_assoc($res);
    $voucher_no = $row['vno'] + 1;

    /* ===== Insert Master ===== */
    $sql = "INSERT INTO receipt_voucher
            (voucher_no, voucher_type, voucher_date, account_no, approval_status, financial_year_id, series_no, display_voucher_no)
            VALUES
            ('$voucher_no','$voucher_type','$voucher_date','$account_no','Draft'," .
            (($series['financial_year_id'] !== null) ? (int) $series['financial_year_id'] : "NULL") . "," .
            (($series['series_no'] !== null) ? (int) $series['series_no'] : "NULL") . "," .
            (($series['display_no'] !== null) ? ("'" . mysqli_real_escape_string($conn, $series['display_no']) . "'") : "NULL") . ")";

    if(!mysqli_query($conn, $sql)){
        throw new Exception(mysqli_error($conn));
    }

    /* ===== Insert Details ===== */
    $totalCredit = 0;

    for($i=0; $i<count($d_account_no); $i++){

        if($credit[$i] == '' || $credit[$i] <= 0){
            continue;
        }

        $acc = $d_account_no[$i];
        $rem = mysqli_real_escape_string($conn,$remarks[$i]);
        $amt = $credit[$i];

        $totalCredit += $amt;

        $sql_detail = "INSERT INTO receipt_voucher_detail
                       (voucher_no, account_no, remarks, credit)
                       VALUES
                       ('$voucher_no','$acc','$rem','$amt')";

        if(!mysqli_query($conn, $sql_detail)){
            throw new Exception(mysqli_error($conn));
        }
    }

    if($totalCredit <= 0){
        throw new Exception("Total Credit Invalid");
    }

    mysqli_commit($conn);

    header("Location: receipt_voucher.php?vno=".$voucher_no."&saved=1");
    exit;

} catch (Exception $e) {

    mysqli_rollback($conn);
    echo "Error: " . $e->getMessage();
    exit;
}
?>
