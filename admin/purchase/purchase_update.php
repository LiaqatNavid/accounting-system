<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('edit_records')) { header("Location: purchase_grid_view.php?denied=1"); exit; }
include "../../includes/approval_helper.php";
include "../../includes/period_lock_helper.php";
include "../../includes/audit_helper.php";
approval_ensure_schema($conn);
period_lock_ensure_schema($conn);

if(!isset($_POST['voucher_no'])){
    die("Invalid Request");
}

$voucher_no = intval($_POST['voucher_no']);
$account_no = $_POST['account_no'];

$account_description = mysqli_real_escape_string(
    $conn,
    $_POST['account_description']
);

$record_date = $_POST['record_date'];
$issue_or_return = $_POST['issue_or_return'];

$purchase_remarks = mysqli_real_escape_string(
    $conn,
    $_POST['purchase_remarks']
);

$net_amount = $_POST['net_amount'];

if ($record_date && period_lock_is_locked($conn, $record_date)) {
    header("Location: purchase_edit.php?voucher_no=".$voucher_no."&period_locked=1");
    exit;
}

$current = mysqli_fetch_assoc(mysqli_query($conn, "SELECT approval_status, deleted_at FROM purchase_issue_return WHERE voucher_no = $voucher_no"));
if (!$current || !empty($current['deleted_at']) || approval_is_locked($current['approval_status'] ?? 'Draft')) {
    header("Location: purchase_grid_view.php?locked=1");
    exit;
}

$before = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT voucher_no, account_no, record_date, issue_or_return, purchase_remarks, net_amount
    FROM purchase_issue_return
    WHERE voucher_no=$voucher_no
"));

/* ================= START TRANSACTION ================= */
mysqli_begin_transaction($conn);

/* ================= UPDATE MASTER ================= */
$updateMaster = mysqli_query($conn,"
    UPDATE purchase_issue_return
    SET account_no='$account_no',
    record_date='$record_date',
    issue_or_return='$issue_or_return',
    purchase_remarks='$purchase_remarks',
    net_amount='$net_amount'
    WHERE voucher_no=$voucher_no

");

if(!$updateMaster){
    mysqli_rollback($conn);
    die("Master update failed: " . mysqli_error($conn));
}

/* ================= DELETE OLD DETAIL ================= */
$deleteDetail = mysqli_query($conn,"
    DELETE FROM purchase_issue_bill
    WHERE voucher_no=$voucher_no
");

if(!$deleteDetail){
    mysqli_rollback($conn);
    die("Delete detail failed: " . mysqli_error($conn));
}

/* ================= INSERT NEW DETAIL ================= */
if(isset($_POST['item_code']) && count($_POST['item_code']) > 0){

    foreach($_POST['item_code'] as $k => $code){

        $item_code = mysqli_real_escape_string($conn,$code);
        $item_name = mysqli_real_escape_string($conn,$_POST['item_name'][$k]);
        $qty = $_POST['qty'][$k];
        $rate = $_POST['rate'][$k];
        $total = $_POST['item_total'][$k];

        $insertDetail = mysqli_query($conn,"
            INSERT INTO purchase_issue_bill
            (voucher_no,
             qty,
             net_amount,
             item_total,
             record_date,
             item_code,
             item_name)
            VALUES
            ('$voucher_no',
             '$qty',
             '$rate',
             '$total',
             NOW(),
             '$item_code',
             '$item_name')
        ");

        if(!$insertDetail){
            mysqli_rollback($conn);
            die("Insert detail failed: " . mysqli_error($conn));
        }
    }
}

/* ================= COMMIT ================= */
mysqli_commit($conn);
    $after = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT voucher_no, account_no, record_date, issue_or_return, purchase_remarks, net_amount
        FROM purchase_issue_return
        WHERE voucher_no=$voucher_no
    "));
    audit_log_event($conn, 'update', 'purchase_voucher', (string) $voucher_no, 'Purchase voucher updated', $before ?: [], $after ?: [], ['detail_rows' => isset($_POST['item_code']) ? count($_POST['item_code']) : 0]);

/* ================= REDIRECT BACK TO EDIT ================= */
header("Location: purchase_edit.php?voucher_no=".$voucher_no."&updated=1");
exit;
