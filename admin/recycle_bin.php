<?php
session_start();
include "../includes/auth_check.php";
include "../config/db.php";
include "../includes/soft_delete_helper.php";
include "../includes/header.php";

soft_delete_ensure_schema($conn);

$queries = [
    "SELECT 'payment_voucher' AS module_key, 'Payment Voucher' AS module_name, voucher_no AS record_id, CONCAT('Voucher #', voucher_no) AS title, voucher_date AS record_date, deleted_at FROM payment_voucher WHERE deleted_at IS NOT NULL",
    "SELECT 'receipt_voucher' AS module_key, 'Receipt Voucher' AS module_name, voucher_no AS record_id, CONCAT('Voucher #', voucher_no) AS title, voucher_date AS record_date, deleted_at FROM receipt_voucher WHERE deleted_at IS NOT NULL",
    "SELECT 'general_voucher' AS module_key, 'General Voucher' AS module_name, VOUCHER_NO AS record_id, CONCAT('Voucher #', VOUCHER_NO) AS title, VOUCHER_DATE AS record_date, deleted_at FROM ONLINE_GENERAL_VOUCHER WHERE deleted_at IS NOT NULL",
    "SELECT 'purchase_voucher' AS module_key, 'Purchase Voucher' AS module_name, voucher_no AS record_id, CONCAT('Voucher #', voucher_no) AS title, record_date AS record_date, deleted_at FROM purchase_issue_return WHERE deleted_at IS NOT NULL",
    "SELECT 'sale_voucher' AS module_key, 'Sale Voucher' AS module_name, voucher_no AS record_id, CONCAT('Voucher #', voucher_no) AS title, record_date AS record_date, deleted_at FROM sale_issue_return WHERE deleted_at IS NOT NULL",
    "SELECT 'stock_adjustment' AS module_key, 'Stock Adjustment' AS module_name, id AS record_id, CONCAT('Adjustment #', adjustment_no) AS title, adjustment_date AS record_date, deleted_at FROM stock_adjustment_master WHERE deleted_at IS NOT NULL"
];

$trashRows = mysqli_query($conn, implode(" UNION ALL ", $queries) . " ORDER BY deleted_at DESC");
?>

<div class="container-fluid mt-4">
    <div class="mb-3">
        <h3 class="mb-1">Recycle Bin</h3>
        <div class="text-muted">Restore soft-deleted records from the main transaction modules.</div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white"><h6 class="mb-0">Deleted Records</h6></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Module</th>
                            <th>Record</th>
                            <th>Transaction Date</th>
                            <th>Deleted At</th>
                            <th style="width:120px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = mysqli_fetch_assoc($trashRows)) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['module_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['record_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['deleted_at']); ?></td>
                            <td>
                                <a href="restore_record.php?module=<?php echo urlencode($row['module_key']); ?>&id=<?php echo urlencode($row['record_id']); ?>" class="btn btn-sm btn-success">
                                    Restore
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
