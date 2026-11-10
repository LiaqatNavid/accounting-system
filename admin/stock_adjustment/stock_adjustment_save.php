<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('add_records')) { header("Location: stock_adjustment_list.php?denied=1"); exit; }
include "../../includes/approval_helper.php";
include "../../includes/period_lock_helper.php";
include "../../includes/financial_year_helper.php";
approval_ensure_schema($conn);
period_lock_ensure_schema($conn);
financial_year_ensure_schema($conn);
voucher_series_ensure_transaction_columns($conn);

if($_SERVER['REQUEST_METHOD'] == 'POST'){

mysqli_begin_transaction($conn);

try{

    /* ================= MASTER ================= */

    $adjustment_no   = time(); // auto number
    $adjustment_date = mysqli_real_escape_string($conn,$_POST['adjustment_date']);
    $adjustment_type = mysqli_real_escape_string($conn,$_POST['adjustment_type']);
    $master_remarks  = mysqli_real_escape_string($conn,$_POST['master_remarks']);
    $created_by      = $_SESSION['user_id'] ?? 1;

    if ($adjustment_date && period_lock_is_locked($conn, $adjustment_date)) {
        header("Location: stock_adjustment_add.php?period_locked=1");
        exit();
    }
    $series = voucher_series_next($conn, 'adj', $adjustment_date);

    mysqli_query($conn,"
        INSERT INTO stock_adjustment_master
        (adjustment_no, adjustment_date, adjustment_type, master_remarks, created_by, approval_status, financial_year_id, series_no, display_voucher_no)
        VALUES
        ('$adjustment_no', '$adjustment_date', '$adjustment_type', '$master_remarks', '$created_by', 'Draft',
         " . (($series['financial_year_id'] !== null) ? (int) $series['financial_year_id'] : "NULL") . ",
         " . (($series['series_no'] !== null) ? (int) $series['series_no'] : "NULL") . ",
         " . (($series['display_no'] !== null) ? ("'" . mysqli_real_escape_string($conn, $series['display_no']) . "'") : "NULL") . ")
    ");

    $adjustment_id = mysqli_insert_id($conn);

    /* ================= DETAIL ================= */

    foreach($_POST['item_code'] as $i => $item_code){

        $item_code      = mysqli_real_escape_string($conn, $_POST['item_code'][$i]);
        $item_name      = mysqli_real_escape_string($conn, $_POST['item_name'][$i]);
        $adjust_qty     = floatval($_POST['adjust_qty'][$i]);
        $per_item_price = floatval($_POST['per_item_price'][$i]);
        $total_price    = floatval($_POST['total_price'][$i]);
        $remarks        = mysqli_real_escape_string($conn, $_POST['remarks'][$i]);

        mysqli_query($conn,"
            INSERT INTO stock_adjustment_detail
            (adjustment_id, item_code, item_name,
             adjust_qty, per_item_price, total_price, remarks)
            VALUES
            ('$adjustment_id', '$item_code', '$item_name',
             '$adjust_qty', '$per_item_price', '$total_price', '$remarks')
        ");
    }

    mysqli_commit($conn);

    header("Location: stock_adjustment_add.php?ano=".$adjustment_no."&saved=1");
    exit();

}catch(Exception $e){

    mysqli_rollback($conn);
    die("Error Saving Adjustment: ".$e->getMessage());
}

}
?>
