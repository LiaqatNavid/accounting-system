<?php
error_reporting(E_ALL);
ini_set('display_errors',1);

session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('add_records')) { echo json_encode(['status' => 'error', 'message' => 'Access denied']); exit; }
include "../../includes/audit_helper.php";
include "../../includes/approval_helper.php";
include "../../includes/period_lock_helper.php";
include "../../includes/financial_year_helper.php";
ensure_purchase_sale_created_at_columns($conn);
approval_ensure_schema($conn);
period_lock_ensure_schema($conn);
financial_year_ensure_schema($conn);
voucher_series_ensure_transaction_columns($conn);

header('Content-Type: application/json');

/* CHECK IF UPDATE OR NEW */
if(isset($_POST['voucher_no']) && $_POST['voucher_no']!=""){
    $voucher_no = $_POST['voucher_no'];
    $isUpdate = true;
}else{
    $result=mysqli_query($conn,"
    SELECT IFNULL(MAX(voucher_no),0)+1 AS new_vno
    FROM sale_issue_return
    ");
    $row=mysqli_fetch_assoc($result);
    $voucher_no=$row['new_vno'];
    $isUpdate = false;
}

/* MASTER DATA */
$account_no=$_POST['account_no'];
$record_date=$_POST['record_date'];
$sale_or_return=$_POST['sale_or_return'];
$sale_voucher_no=$_POST['sale_voucher_no']??NULL;
$bill_no=$_POST['bill_no'];
$discount=$_POST['discount'];
$cash_received=$_POST['cash_received'];
$net_amount=$_POST['net_amount'];
$remarks=$_POST['remarks'];

if ($record_date && period_lock_is_locked($conn, $record_date)) {
    echo json_encode(['status' => 'error', 'message' => 'Closed accounting period']);
    exit;
}

$balance=($net_amount-$discount)-$cash_received;

/* ================= UPDATE MASTER ================= */
if($isUpdate){
    $current = mysqli_fetch_assoc(mysqli_query($conn, "SELECT approval_status, deleted_at FROM sale_issue_return WHERE voucher_no='$voucher_no'"));
    if (!$current || !empty($current['deleted_at']) || approval_is_locked($current['approval_status'] ?? 'Draft')) {
        echo json_encode(['status' => 'error', 'message' => 'Approved or deleted voucher cannot be changed']);
        exit;
    }

    mysqli_query($conn,"
    UPDATE sale_issue_return SET
    account_no='$account_no',
    net_amount='$net_amount',
    sale_or_return='$sale_or_return',
    record_date='$record_date',
    sale_voucher_no=".($sale_voucher_no?"'$sale_voucher_no'":"NULL").",
    bill_no='$bill_no',
    discount='$discount',
    cash_received='$cash_received',
    balance='$balance',
    remarks='$remarks'
    WHERE voucher_no='$voucher_no'
    ");

    // Delete old detail rows
    mysqli_query($conn,"
    DELETE FROM sale_issue_bill
    WHERE voucher_no='$voucher_no'
    ");

}else{
    $series = voucher_series_next($conn, 'sale', $record_date);

    mysqli_query($conn,"
    INSERT INTO sale_issue_return
    (voucher_no,account_no,net_amount,sale_or_return,
    record_date,sale_voucher_no,bill_no,
    discount,cash_received,balance,remarks,created_at,approval_status,financial_year_id,series_no,display_voucher_no)
    VALUES
    ('$voucher_no','$account_no','$net_amount',
    '$sale_or_return','$record_date',
    ".($sale_voucher_no?"'$sale_voucher_no'":"NULL").",
    '$bill_no','$discount','$cash_received',
    '$balance','$remarks',NOW(),'Draft',
    " . (($series['financial_year_id'] !== null) ? (int) $series['financial_year_id'] : "NULL") . ",
    " . (($series['series_no'] !== null) ? (int) $series['series_no'] : "NULL") . ",
    " . (($series['display_no'] !== null) ? ("'" . mysqli_real_escape_string($conn, $series['display_no']) . "'") : "NULL") . ")
    ");

}

/* ================= INSERT DETAIL ================= */
$item_code=$_POST['item_code'];
$item_name=$_POST['item_name'];
$qty=$_POST['qty'];
$rate=$_POST['rate'];

for($i=0;$i<count($item_code);$i++){

    $item_total=$qty[$i]*$rate[$i];

    mysqli_query($conn,"
    INSERT INTO sale_issue_bill
    (voucher_no,qty,net_amount,item_total,
    record_date,item_code,item_name)
    VALUES
    ('$voucher_no','$qty[$i]','$rate[$i]',
    '$item_total',CURDATE(),
    '{$item_code[$i]}','{$item_name[$i]}')
    ");

}

echo json_encode([
'status'=>'success',
'voucher_no'=>$voucher_no
]);
