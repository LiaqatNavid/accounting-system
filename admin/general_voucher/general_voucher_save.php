<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('add_records')) { header("Location: general_voucher_list.php?denied=1"); exit; }
include "../../includes/approval_helper.php";
include "../../includes/period_lock_helper.php";
include "../../includes/financial_year_helper.php";

/* ================= VALID REQUEST ================= */
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    die("Invalid Request");
}

/* ================= BASIC VALIDATION ================= */
if(empty($_POST['account_no'])){
    echo "<script>
        alert('Please add at least one account line.');
        window.history.back();
    </script>";
    exit;
}

$voucher_date  = $_POST['voucher_date'] ?? '';
$voucher_type  = $_POST['voucher_type'] ?? 'JV';

$account_no = $_POST['account_no'];
$remarks    = $_POST['remarks'] ?? [];
$debit      = $_POST['debit'] ?? [];
$credit     = $_POST['credit'] ?? [];

/* ================= CALCULATE TOTAL ================= */
$total_debit  = 0;
$total_credit = 0;

for($i=0; $i<count($account_no); $i++){

    $db = floatval($debit[$i] ?? 0);
    $cr = floatval($credit[$i] ?? 0);

    if($db == 0 && $cr == 0){
        continue;
    }

    $total_debit  += $db;
    $total_credit += $cr;
}

/* ================= BALANCE CHECK ================= */
if($total_debit <= 0 || $total_debit != $total_credit){

    echo "<script>
        alert('Debit and Credit must be equal and greater than 0.');
        window.history.back();
    </script>";
    exit;
}

/* ================= START TRANSACTION ================= */
period_lock_ensure_schema($conn);
if ($voucher_date && period_lock_is_locked($conn, $voucher_date)) {
    echo "<script>alert('Selected period is closed.');window.location.href='general_voucher.php?period_locked=1';</script>";
    exit;
}
approval_ensure_schema($conn);
financial_year_ensure_schema($conn);
voucher_series_ensure_transaction_columns($conn);
mysqli_begin_transaction($conn);

try {
    $series = voucher_series_next($conn, 'gv', $voucher_date);

    /* ================= INSERT HEADER ================= */
    $stmt = $conn->prepare("
        INSERT INTO ONLINE_GENERAL_VOUCHER
        (VOUCHER_TYPE, VOUCHER_DATE, TOTAL_DEBIT, TOTAL_CREDIT, approval_status, financial_year_id, series_no, display_voucher_no)
        VALUES (?, ?, ?, ?, 'Draft', ?, ?, ?)
    ");

    $fyId = $series['financial_year_id'];
    $seriesNo = $series['series_no'];
    $displayNo = $series['display_no'];
    $stmt->bind_param("ssddiis",
        $voucher_type,
        $voucher_date,
        $total_debit,
        $total_credit,
        $fyId,
        $seriesNo,
        $displayNo
    );

    $stmt->execute();

    $voucher_no = $stmt->insert_id;

    /* ================= INSERT DETAILS ================= */
    $stmt_detail = $conn->prepare("
        INSERT INTO ONLINE_GENERAL_VOUCHER_DETAIL
        (VOUCHER_NO, ACCOUNT_NO, REMARKS, DEBIT, CREDIT)
        VALUES (?, ?, ?, ?, ?)
    ");

    for($i=0; $i<count($account_no); $i++){

        $acc = $account_no[$i];
        $rem = $remarks[$i] ?? '';
        $db  = floatval($debit[$i] ?? 0);
        $cr  = floatval($credit[$i] ?? 0);

        if($db == 0 && $cr == 0){
            continue;
        }

        $stmt_detail->bind_param("issdd",
            $voucher_no,
            $acc,
            $rem,
            $db,
            $cr
        );

        $stmt_detail->execute();
    }

    /* ================= COMMIT ================= */
    mysqli_commit($conn);

    echo "<script>
        alert('Voucher Saved Successfully! Voucher No: $voucher_no');
        window.location.href='general_voucher.php?saved=1&vno=$voucher_no';
    </script>";

} catch (Exception $e) {

    mysqli_rollback($conn);

    echo "<script>
        alert('Error saving voucher.');
        window.history.back();
    </script>";
}
