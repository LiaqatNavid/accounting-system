<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('edit_records')) { echo json_encode(['status'=>'error','message'=>'Access denied']); exit; }
include "../../includes/approval_helper.php";
include "../../includes/period_lock_helper.php";
include "../../includes/audit_helper.php";
approval_ensure_schema($conn);
period_lock_ensure_schema($conn);

header('Content-Type: application/json');

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo json_encode(['status'=>'error','message'=>'Invalid Request']);
    exit;
}

if(!isset($_POST['voucher_no']) || $_POST['voucher_no']==''){
    echo json_encode(['status'=>'error','message'=>'Voucher Missing']);
    exit;
}

mysqli_begin_transaction($conn);

try{

$voucher_no     = intval($_POST['voucher_no']);
$account_no     = mysqli_real_escape_string($conn, trim($_POST['account_no'] ?? ''));
$record_date    = mysqli_real_escape_string($conn, trim($_POST['record_date'] ?? ''));
$sale_or_return = mysqli_real_escape_string($conn, trim($_POST['sale_or_return'] ?? 'SALE VOUCHER'));
$sale_voucher_no= isset($_POST['sale_voucher_no']) && $_POST['sale_voucher_no']!=''
                  ? intval($_POST['sale_voucher_no']) : "NULL";
$bill_no        = mysqli_real_escape_string($conn, trim($_POST['bill_no'] ?? ''));
$discount       = floatval($_POST['discount'] ?? 0);
$cash_received  = floatval($_POST['cash_received'] ?? 0);
$net_amount     = floatval($_POST['net_amount'] ?? 0);
$remarks        = mysqli_real_escape_string($conn, trim($_POST['remarks'] ?? ''));

$balance = ($net_amount - $discount) - $cash_received;

if ($record_date && period_lock_is_locked($conn, $record_date)) {
    echo json_encode(['status'=>'error','message'=>'Closed accounting period']);
    exit;
}

$current = mysqli_fetch_assoc(mysqli_query($conn, "SELECT approval_status, deleted_at FROM sale_issue_return WHERE voucher_no = $voucher_no"));
if (!$current || !empty($current['deleted_at']) || approval_is_locked($current['approval_status'] ?? 'Draft')) {
    echo json_encode(['status'=>'error','message'=>'Approved voucher cannot be updated']);
    exit;
}

$before = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT voucher_no, account_no, record_date, sale_or_return, bill_no, discount, cash_received, net_amount, balance, remarks
    FROM sale_issue_return
    WHERE voucher_no = $voucher_no
"));

/* UPDATE MASTER */
mysqli_query($conn,"
UPDATE sale_issue_return SET
    record_date = '$record_date',
    sale_or_return = '$sale_or_return',
    sale_voucher_no = ".($sale_voucher_no==="NULL"?"NULL":"'".$sale_voucher_no."'").",
    bill_no = '$bill_no',
    discount = '$discount',
    cash_received = '$cash_received',
    net_amount = '$net_amount',
    balance = '$balance',
    remarks = '$remarks'
WHERE voucher_no = $voucher_no
");

/* DELETE OLD DETAIL */
mysqli_query($conn,"
DELETE FROM sale_issue_bill
WHERE voucher_no = $voucher_no
");

/* INSERT NEW DETAIL */
if(!empty($_POST['item_code'])){

    $item_code = $_POST['item_code'];
    $item_name = $_POST['item_name'];
    $qty       = $_POST['qty'];
    $rate      = $_POST['rate'];

    for($i=0; $i<count($item_code); $i++){

        $code = mysqli_real_escape_string($conn,$item_code[$i]);
        $name = mysqli_real_escape_string($conn,$item_name[$i]);
        $q    = floatval($qty[$i]);
        $r    = floatval($rate[$i]);

        $item_total = $q * $r;

        mysqli_query($conn,"
        INSERT INTO sale_issue_bill
        (
            voucher_no,
            qty,
            net_amount,
            item_total,
            record_date,
            item_code,
            item_name
        )
        VALUES
        (
            '$voucher_no',
            '$q',
            '$r',
            '$item_total',
            CURDATE(),
            '$code',
            '$name'
        )
        ");
    }
}

mysqli_commit($conn);
audit_log_event($conn, 'update', 'sale_voucher', (string) $voucher_no, 'Sale voucher updated', $before ?: [], [
    'voucher_no' => $voucher_no,
    'account_no' => $account_no,
    'record_date' => $record_date,
    'sale_or_return' => $sale_or_return,
    'bill_no' => $bill_no,
    'discount' => $discount,
    'cash_received' => $cash_received,
    'net_amount' => $net_amount,
    'balance' => $balance,
    'remarks' => $remarks,
], ['detail_rows' => !empty($_POST['item_code']) ? count($_POST['item_code']) : 0]);

echo json_encode([
    'status'=>'success',
    'voucher_no'=>$voucher_no
]);

}catch(Exception $e){

    mysqli_rollback($conn);

    echo json_encode([
        'status'=>'error',
        'message'=>'Update Failed'
    ]);
}
?>
