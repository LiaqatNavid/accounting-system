<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('edit_records')) { header("Location: stock_adjustment_list.php?denied=1"); exit; }
include "../../includes/approval_helper.php";
include "../../includes/period_lock_helper.php";
include "../../includes/audit_helper.php";
approval_ensure_schema($conn);
period_lock_ensure_schema($conn);

if($_SERVER['REQUEST_METHOD'] == 'POST'){

mysqli_begin_transaction($conn);

try{

    $id = intval($_POST['id']);

    /* ================= UPDATE MASTER ================= */

    $adjustment_date = mysqli_real_escape_string($conn, $_POST['adjustment_date']);
    $adjustment_type = mysqli_real_escape_string($conn, $_POST['adjustment_type']);
    $master_remarks  = mysqli_real_escape_string($conn, $_POST['master_remarks']);

    $current = mysqli_fetch_assoc(mysqli_query($conn, "SELECT approval_status, deleted_at FROM stock_adjustment_master WHERE id = '$id'"));
    if (!$current || !empty($current['deleted_at']) || approval_is_locked($current['approval_status'] ?? 'Draft')) {
        header("Location: stock_adjustment_list.php?locked=1");
        exit();
    }

    if ($adjustment_date && period_lock_is_locked($conn, $adjustment_date)) {
        header("Location: stock_adjustment_edit.php?id=".$id."&period_locked=1");
        exit();
    }

    $before = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT id, adjustment_no, adjustment_date, adjustment_type, master_remarks
        FROM stock_adjustment_master
        WHERE id = '$id'
    "));

    mysqli_query($conn,"
        UPDATE stock_adjustment_master
        SET
            adjustment_date = '$adjustment_date',
            adjustment_type = '$adjustment_type',
            master_remarks  = '$master_remarks'
        WHERE id = '$id'
    ");

    /* ================= DELETE OLD DETAILS ================= */

    mysqli_query($conn,"
        DELETE FROM stock_adjustment_detail
        WHERE adjustment_id = '$id'
    ");

    /* ================= INSERT NEW DETAILS ================= */

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
            ('$id', '$item_code', '$item_name',
             '$adjust_qty', '$per_item_price', '$total_price', '$remarks')
        ");
    }

    mysqli_commit($conn);
    $after = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT id, adjustment_no, adjustment_date, adjustment_type, master_remarks
        FROM stock_adjustment_master
        WHERE id = '$id'
    "));
    audit_log_event($conn, 'update', 'stock_adjustment', (string) $id, 'Stock adjustment updated', $before ?: [], $after ?: [], ['detail_rows' => count($_POST['item_code'] ?? [])]);

    header("Location: stock_adjustment_list.php?updated=1");
    exit();

}catch(Exception $e){

    mysqli_rollback($conn);
    die("Error Updating Record: ".$e->getMessage());
}

}else{

    header("Location: stock_adjustment_list.php");
    exit();
}
?>
