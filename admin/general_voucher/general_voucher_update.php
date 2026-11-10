<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('edit_records')) { header("Location: general_voucher_list.php?denied=1"); exit; }
include "../../includes/approval_helper.php";
include "../../includes/period_lock_helper.php";
include "../../includes/audit_helper.php";

/* ================= VALID REQUEST ================= */
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    die("Invalid Request");
}

$voucher_no   = intval($_POST['voucher_no'] ?? 0);
$voucher_date = $_POST['voucher_date'] ?? '';
$voucher_type = $_POST['voucher_type'] ?? '';

if($voucher_no <= 0){
    die("Invalid Voucher");
}

/* ================= VALIDATE DETAIL EXISTS ================= */
if(empty($_POST['account_no'])){
    echo "<script>
        alert('Please add at least one account line.');
        window.history.back();
    </script>";
    exit;
}

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
approval_ensure_schema($conn);
period_lock_ensure_schema($conn);

$currentStatus = approval_get_status($conn, 'ONLINE_GENERAL_VOUCHER', 'VOUCHER_NO', $voucher_no);
if (approval_is_locked($currentStatus)) {
    header("Location: general_voucher_list.php?locked=1");
    exit;
}
if ($voucher_date && period_lock_is_locked($conn, $voucher_date)) {
    header("Location: general_voucher_list.php?period_locked=1");
    exit;
}

$before = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT VOUCHER_NO, VOUCHER_DATE, VOUCHER_TYPE, TOTAL_DEBIT, TOTAL_CREDIT
    FROM ONLINE_GENERAL_VOUCHER
    WHERE VOUCHER_NO = $voucher_no
"));

mysqli_begin_transaction($conn);

try {

    /* ================= UPDATE HEADER ================= */
    $stmt = $conn->prepare("
        UPDATE ONLINE_GENERAL_VOUCHER
        SET VOUCHER_DATE = ?, 
            VOUCHER_TYPE = ?, 
            TOTAL_DEBIT  = ?, 
            TOTAL_CREDIT = ?
        WHERE VOUCHER_NO = ?
    ");

    $stmt->bind_param("ssddi",
        $voucher_date,
        $voucher_type,
        $total_debit,
        $total_credit,
        $voucher_no
    );

    $stmt->execute();

    /* ================= DELETE OLD DETAILS ================= */
    $deleteStmt = $conn->prepare("
        DELETE FROM ONLINE_GENERAL_VOUCHER_DETAIL
        WHERE VOUCHER_NO = ?
    ");

    $deleteStmt->bind_param("i", $voucher_no);
    $deleteStmt->execute();

    /* ================= INSERT NEW DETAILS ================= */
    $insertStmt = $conn->prepare("
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

        $insertStmt->bind_param("issdd",
            $voucher_no,
            $acc,
            $rem,
            $db,
            $cr
        );

        $insertStmt->execute();
    }

    /* ================= COMMIT ================= */
    mysqli_commit($conn);
    $after = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT VOUCHER_NO, VOUCHER_DATE, VOUCHER_TYPE, TOTAL_DEBIT, TOTAL_CREDIT
        FROM ONLINE_GENERAL_VOUCHER
        WHERE VOUCHER_NO = $voucher_no
    "));
    audit_log_event($conn, 'update', 'general_voucher', (string) $voucher_no, 'General voucher updated', $before ?: [], $after ?: [], ['detail_rows' => count($account_no)]);

    echo "<script>
        alert('Voucher Updated Successfully!');
        window.location.href='general_voucher_list.php?updated=1';
    </script>";

} catch (Exception $e) {

    mysqli_rollback($conn);

    echo "<script>
        alert('Error updating voucher.');
        window.history.back();
    </script>";
}
