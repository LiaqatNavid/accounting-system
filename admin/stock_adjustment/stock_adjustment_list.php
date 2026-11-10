<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/approval_helper.php";
include "../../includes/soft_delete_helper.php";
include "../../includes/header.php";
approval_ensure_schema($conn);
soft_delete_ensure_schema($conn);
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">

<style>
.stock-adjust-list-page .card { border: 0; border-radius: 14px; }
.stock-adjust-list-page .card-header { border-radius: 14px 14px 0 0; }
.stock-adjust-list-page .action-group { display: flex; gap: 6px; flex-wrap: wrap; }
</style>

<div class="container-fluid stock-adjust-list-page mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1">Stock Adjustment List</h3>
            <div class="text-muted">View, edit, print, and manage stock adjustments.</div>
        </div>
        <a href="<?php echo $base_url; ?>admin/stock_adjustment/stock_adjustment_add.php" class="btn btn-primary">New Adjustment</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0">Stock Adjustment Grid</h6>
        </div>
        <div class="card-body p-3">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm w-100 mb-0" id="adjustmentTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Adjustment #</th>
                            <th>Series No</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Total Items</th>
                            <th>Status</th>
                            <th>Total Amount</th>
                            <th style="width:300px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $q = mysqli_query($conn, "
                        SELECT m.id, m.adjustment_no, m.display_voucher_no, m.adjustment_date, m.adjustment_type, COALESCE(m.approval_status, 'Draft') AS approval_status,
                               COUNT(d.id) AS total_items, IFNULL(SUM(d.total_price),0) AS total_amount
                        FROM stock_adjustment_master m
                        LEFT JOIN stock_adjustment_detail d ON m.id = d.adjustment_id
                        WHERE m.deleted_at IS NULL
                        GROUP BY m.id
                        ORDER BY m.adjustment_no DESC
                    ");
                    while ($row = mysqli_fetch_assoc($q)) {
                        $isApproved = approval_is_locked($row['approval_status'] ?? 'Draft');
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['adjustment_no']); ?></td>
                            <td><?php echo htmlspecialchars($row['display_voucher_no'] ?: ('ADJ-' . $row['adjustment_no'])); ?></td>
                            <td><?php echo date('d-m-Y', strtotime($row['adjustment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['adjustment_type']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($row['total_items']); ?></td>
                            <td>
                                <span class="badge <?php echo $isApproved ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                    <?php echo $isApproved ? 'Approved' : 'Draft'; ?>
                                </span>
                            </td>
                            <td class="text-end"><?php echo number_format((float) $row['total_amount'], 2); ?></td>
                            <td>
                                <div class="action-group">
                                    <?php if (rbac_can_do('edit_records')): ?>
                                    <a href="stock_adjustment_edit.php?id=<?php echo urlencode($row['id']); ?>" class="btn btn-sm btn-primary <?php echo $isApproved ? 'disabled' : ''; ?>">Edit</a>
                                    <?php endif; ?>
                                    <?php if (rbac_can_do('delete_records')): ?>
                                    <a href="stock_adjustment_delete.php?id=<?php echo urlencode($row['id']); ?>" class="btn btn-sm btn-danger delete-adjustment <?php echo $isApproved ? 'disabled' : ''; ?>">Delete</a>
                                    <?php endif; ?>
                                    <?php if (rbac_can_do('approve_records')): ?>
                                    <a href="stock_adjustment_approval.php?id=<?php echo urlencode($row['id']); ?>&action=<?php echo $isApproved ? 'reopen' : 'approve'; ?>" class="btn btn-sm <?php echo $isApproved ? 'btn-warning' : 'btn-dark'; ?>">
                                        <?php echo $isApproved ? 'Reopen' : 'Approve'; ?>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (rbac_can_do('print_records')): ?>
                                    <a href="stock_adjustment_print.php?id=<?php echo urlencode($row['id']); ?>" class="btn btn-sm btn-success" onclick="return openPrintPopup(this.href)">Print</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo $base_url; ?>assets/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function () {
    $('#adjustmentTable').DataTable({
        pageLength: 15,
        order: [[0, 'desc']],
        autoWidth: false,
        columnDefs: [{ orderable: false, targets: 7 }]
    });

    $(document).on('click', '.delete-adjustment', function (e) {
        if (!confirm('Delete this Adjustment?')) {
            e.preventDefault();
        }
    });
});
</script>

<?php include "../../includes/footer.php"; ?>
