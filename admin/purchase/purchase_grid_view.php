<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/approval_helper.php";
include "../../includes/soft_delete_helper.php";
include "../../includes/header.php";
approval_ensure_schema($conn);
soft_delete_ensure_schema($conn);

$result = mysqli_query($conn, "
    SELECT g.*, p.display_voucher_no, COALESCE(p.approval_status, 'Draft') AS approval_status
    FROM purchase_grid_view g
    INNER JOIN purchase_issue_return p ON p.voucher_no = g.voucher_no
    WHERE p.deleted_at IS NULL
    ORDER BY g.voucher_no DESC
");
$widgets = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total_vouchers,
           SUM(CASE WHEN issue_or_return = 'PURCHASE RETURN' THEN 1 ELSE 0 END) AS total_returns,
           SUM(net_amount) AS total_amount,
           SUM(CASE WHEN DATE(record_date) = CURDATE() THEN 1 ELSE 0 END) AS today_count
    FROM purchase_issue_return
    WHERE deleted_at IS NULL
"));
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">

<style>
.purchase-list-page .stat-card, .purchase-list-page .table-card { border: 0; border-radius: 14px; }
.purchase-list-page .action-group { display: flex; gap: 6px; flex-wrap: wrap; }
</style>

<div class="container-fluid purchase-list-page mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1">Purchase Voucher List</h3>
            <div class="text-muted">View, edit, print, and manage purchase vouchers.</div>
        </div>
        <a href="<?php echo $base_url; ?>admin/purchase/purchase.php" class="btn btn-primary">New Voucher</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card stat-card text-white bg-primary shadow-sm"><div class="card-body text-center"><h6>Total Vouchers</h6><h4><?php echo htmlspecialchars($widgets['total_vouchers']); ?></h4></div></div></div>
        <div class="col-md-3"><div class="card stat-card text-white bg-danger shadow-sm"><div class="card-body text-center"><h6>Purchase Returns</h6><h4><?php echo htmlspecialchars($widgets['total_returns']); ?></h4></div></div></div>
        <div class="col-md-3"><div class="card stat-card text-white bg-success shadow-sm"><div class="card-body text-center"><h6>Total Amount</h6><h4><?php echo number_format((float) $widgets['total_amount'], 2); ?></h4></div></div></div>
        <div class="col-md-3"><div class="card stat-card text-white bg-dark shadow-sm"><div class="card-body text-center"><h6>Today's Vouchers</h6><h4><?php echo htmlspecialchars($widgets['today_count']); ?></h4></div></div></div>
    </div>

    <div class="card table-card shadow-sm">
        <div class="card-header bg-primary text-white"><h6 class="mb-0">All Purchase Vouchers</h6></div>
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="purchaseTable" class="table table-bordered table-striped table-sm w-100 mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Voucher #</th>
                            <th>Series No</th>
                            <th>Date</th>
                            <th>Account</th>
                            <th>Description</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="text-end">Amount</th>
                            <th style="width:320px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                        <?php $isApproved = approval_is_locked($row['approval_status'] ?? 'Draft'); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['voucher_no']); ?></td>
                            <td><?php echo htmlspecialchars($row['display_voucher_no'] ?: $row['print_voucher']); ?></td>
                            <td><?php echo date('d-M-Y', strtotime($row['record_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['account_no']); ?></td>
                            <td><?php echo htmlspecialchars($row['account_description']); ?></td>
                            <td><span class="<?php echo $row['issue_or_return'] == 'PURCHASE RETURN' ? 'text-danger fw-bold' : 'fw-bold'; ?>"><?php echo htmlspecialchars($row['issue_or_return']); ?></span></td>
                            <td>
                                <span class="badge <?php echo $isApproved ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                    <?php echo $isApproved ? 'Approved' : 'Draft'; ?>
                                </span>
                            </td>
                            <td class="text-end"><?php echo number_format((float) $row['net_amount'], 2); ?></td>
                            <td>
                                <div class="action-group">
                                    <?php if (rbac_can_do('edit_records')): ?>
                                    <a href="purchase_edit.php?voucher_no=<?php echo urlencode($row['voucher_no']); ?>" class="btn btn-sm btn-primary <?php echo $isApproved ? 'disabled' : ''; ?>">Edit</a>
                                    <?php endif; ?>
                                    <?php if (rbac_can_do('delete_records')): ?>
                                    <a href="purchase_delete.php?voucher_no=<?php echo urlencode($row['voucher_no']); ?>" class="btn btn-sm btn-danger delete-purchase <?php echo $isApproved ? 'disabled' : ''; ?>">Delete</a>
                                    <?php endif; ?>
                                    <?php if (rbac_can_do('approve_records')): ?>
                                    <a href="purchase_approval.php?voucher_no=<?php echo urlencode($row['voucher_no']); ?>&action=<?php echo $isApproved ? 'reopen' : 'approve'; ?>" class="btn btn-sm <?php echo $isApproved ? 'btn-warning' : 'btn-dark'; ?>">
                                        <?php echo $isApproved ? 'Reopen' : 'Approve'; ?>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (rbac_can_do('print_records')): ?>
                                    <a href="purchase_print.php?voucher_no=<?php echo urlencode($row['voucher_no']); ?>" class="btn btn-sm btn-secondary" onclick="return openPrintPopup(this.href)">Print</a>
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
    $('#purchaseTable').DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        columnDefs: [{ orderable: false, targets: 8 }]
    });

    $(document).on('click', '.delete-purchase', function (e) {
        if (!confirm('Delete this voucher?')) {
            e.preventDefault();
        }
    });
});
</script>

<?php include "../../includes/footer.php"; ?>
