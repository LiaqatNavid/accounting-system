<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('add_records')) { echo json_encode(["status" => "error", "message" => "Access denied"]); exit; }
include "../../includes/audit_helper.php";
include "../../includes/approval_helper.php";
include "../../includes/period_lock_helper.php";
include "../../includes/financial_year_helper.php";
ensure_purchase_sale_created_at_columns($conn);
approval_ensure_schema($conn);
period_lock_ensure_schema($conn);
financial_year_ensure_schema($conn);
voucher_series_ensure_transaction_columns($conn);

/* ================= GET VALUES ================= */

$voucher_no = isset($_POST['voucher_no']) 
              ? intval($_POST['voucher_no']) 
              : 0;

$account_no = $_POST['account_no'];

$record_date = $_POST['record_date'];

$issue_or_return = $_POST['issue_or_return'];

$purchase_remarks = mysqli_real_escape_string(
    $conn,
    $_POST['purchase_remarks']
);

$net_amount = $_POST['net_amount'];


$lockedRedirect = false;
if ($record_date && period_lock_is_locked($conn, $record_date)) {
    echo json_encode(["status" => "error", "message" => "Closed accounting period"]);
    exit;
}

/* ================= START TRANSACTION ================= */
mysqli_begin_transaction($conn);

try{

    /* ==================================================
       FIRST SAVE  → INSERT MASTER
       ================================================== */
    if($voucher_no == 0){
        $series = voucher_series_next($conn, 'pur', $record_date);

        mysqli_query($conn,"
        INSERT INTO purchase_issue_return
        (account_no,
         record_date,
         issue_or_return,
         purchase_remarks,
         net_amount,
         created_at,
         approval_status,
         financial_year_id,
         series_no,
         display_voucher_no)
        VALUES
        ('$account_no',
         '$record_date',
         '$issue_or_return',
         '$purchase_remarks',
         '$net_amount',
         NOW(),
         'Draft',
         " . (($series['financial_year_id'] !== null) ? (int) $series['financial_year_id'] : "NULL") . ",
         " . (($series['series_no'] !== null) ? (int) $series['series_no'] : "NULL") . ",
         " . (($series['display_no'] !== null) ? ("'" . mysqli_real_escape_string($conn, $series['display_no']) . "'") : "NULL") . ")
        ");

        $voucher_no = mysqli_insert_id($conn);
    }

    /* ==================================================
       SECOND SAVE → UPDATE MASTER
       ================================================== */
    else{
        $current = mysqli_fetch_assoc(mysqli_query($conn, "SELECT approval_status, deleted_at FROM purchase_issue_return WHERE voucher_no = $voucher_no"));
        if (!$current || !empty($current['deleted_at']) || approval_is_locked($current['approval_status'] ?? 'Draft')) {
            throw new Exception("Voucher locked");
        }

        mysqli_query($conn,"
        UPDATE purchase_issue_return
        SET account_no='$account_no',
            record_date='$record_date',
            issue_or_return='$issue_or_return',
            purchase_remarks='$purchase_remarks',
            net_amount='$net_amount'
        WHERE voucher_no=$voucher_no
        ");

        /* Delete old detail */
        mysqli_query($conn,"
        DELETE FROM purchase_issue_bill
        WHERE voucher_no=$voucher_no
        ");
    }


    /* ==================================================
       INSERT DETAIL (Always fresh insert)
       ================================================== */
    if(isset($_POST['item_code'])){

        foreach($_POST['item_code'] as $k=>$code){

            $name  = $_POST['item_name'][$k];
            $qty   = $_POST['qty'][$k];
            $rate  = $_POST['rate'][$k];
            $total = $_POST['item_total'][$k];

            mysqli_query($conn,"
            INSERT INTO purchase_issue_bill
            (voucher_no,qty,net_amount,
             item_total,record_date,
             item_code,item_name)
            VALUES
            ('$voucher_no',
             '$qty',
             '$rate',
             '$total',
             NOW(),
             '$code',
             '$name')
            ");
        }
    }

    mysqli_commit($conn);

    echo json_encode([
        "status"=>"success",
        "voucher_no"=>$voucher_no
    ]);
    exit;

}catch(Exception $e){

    mysqli_rollback($conn);

    echo json_encode([
        "status"=>"error"
    ]);
    exit;
}
