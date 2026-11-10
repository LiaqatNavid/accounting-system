<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/audit_helper.php";
include "../../includes/approval_helper.php";
include "../../includes/soft_delete_helper.php";
include "../../includes/period_lock_helper.php";
approval_ensure_schema($conn);
soft_delete_ensure_schema($conn);
period_lock_ensure_schema($conn);

if (!rbac_can_do('delete_records')) {
    header("Location: stock_adjustment_list.php?denied=1");
    exit();
}

if(isset($_GET['id'])){

    $id = intval($_GET['id']);
    $auditRow = mysqli_fetch_assoc(mysqli_query($conn, "
        SELECT id, adjustment_no, adjustment_date, adjustment_type, approval_status, deleted_at
        FROM stock_adjustment_master
        WHERE id = '$id'
    "));

    if(!$auditRow || !empty($auditRow['deleted_at'])){
        die("Record not found.");
    }

    if (approval_is_locked($auditRow['approval_status'] ?? 'Draft')) {
        header("Location: stock_adjustment_list.php?locked=1");
        exit();
    }

    if (period_lock_is_locked($conn, $auditRow['adjustment_date'] ?? '')) {
        header("Location: stock_adjustment_list.php?period_locked=1");
        exit();
    }

    mysqli_begin_transaction($conn);

    try{

        soft_delete_mark($conn, 'stock_adjustment_master', 'id', $id);

        audit_log_delete(
            $conn,
            'stock_adjustment',
            (string) $id,
            'Stock Adjustment #' . ($auditRow['adjustment_no'] ?? $id),
            [
                'adjustment_no' => $auditRow['adjustment_no'] ?? '',
                'adjustment_date' => $auditRow['adjustment_date'] ?? '',
                'adjustment_type' => $auditRow['adjustment_type'] ?? ''
            ]
        );

        mysqli_commit($conn);

        header("Location: stock_adjustment_list.php?deleted=1");
        exit();

    }catch(Exception $e){

        mysqli_rollback($conn);
        die("Error deleting record.");
    }

}else{
    header("Location: stock_adjustment_list.php");
    exit();
}
?>
