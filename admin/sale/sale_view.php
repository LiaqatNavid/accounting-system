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
.sale-list-page .card { border: 0; border-radius: 14px; }
.sale-list-page .card-header { border-radius: 14px 14px 0 0; }
.sale-list-page .action-group { display: flex; gap: 6px; flex-wrap: wrap; }
</style>

<div class="container-fluid sale-list-page mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1"><i class="bi bi-cart-check text-primary"></i> Sale Voucher List</h3>
            <div class="text-muted">View, edit, print, and manage saved sale vouchers.</div>
        </div>
        <a href="<?php echo $base_url; ?>admin/sale/sale.php" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> New Voucher
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white"><h6 class="mb-0"><i class="bi bi-list-ul"></i> All Sale Vouchers</h6></div>
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="saleTable" class="table table-bordered table-hover w-100 mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Voucher</th>
                            <th>Account</th>
                            <th>Total Qty</th>
                            <th>Discount</th>
                            <th>Net Amount</th>
                            <th>Date</th>
                            <th>Cash/Credit</th>
                            <th>Status</th>
                            <th>Balance</th>
                            <th style="min-width:300px; width:300px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $q = mysqli_query($conn, "
                        SELECT g.*, s.display_voucher_no, COALESCE(s.approval_status, 'Draft') AS approval_status
                        FROM sale_grid_view g
                        INNER JOIN sale_issue_return s ON s.voucher_no = g.voucher_no
                        WHERE s.deleted_at IS NULL
                        ORDER BY g.voucher_no DESC
                    ");
                    while ($r = mysqli_fetch_assoc($q)) {
                        $isApproved = approval_is_locked($r['approval_status'] ?? 'Draft');
                    ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($r['display_voucher_no'] ?: $r['print_voucher']); ?></td>
                            <td><?php echo htmlspecialchars($r['account_no']); ?> - <?php echo htmlspecialchars($r['account_description']); ?></td>
                            <td><?php echo htmlspecialchars($r['total_qty']); ?></td>
                            <td><?php echo number_format((float) $r['discount'], 2); ?></td>
                            <td><?php echo number_format((float) $r['net_amount'], 2); ?></td>
                            <td><?php echo date('d-M-Y', strtotime($r['record_date'])); ?></td>
                            <td><?php echo htmlspecialchars($r['cash_credit']); ?></td>
                            <td>
                                <span class="badge <?php echo $isApproved ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                    <?php echo $isApproved ? 'Approved' : 'Draft'; ?>
                                </span>
                            </td>
                            <td class="<?php echo $r['balance'] > 0 ? 'text-danger' : 'text-success'; ?>"><?php echo number_format((float) $r['balance'], 2); ?></td>
                            <td>
                                <div class="action-group">
                                    <?php if (rbac_can_do('edit_records')): ?>
                                    <a href="sale_edit.php?voucher_no=<?php echo urlencode($r['voucher_no']); ?>" 
                                       class="btn btn-primary btn-sm <?php echo $isApproved ? 'disabled' : ''; ?>" 
                                       title="<?php echo $isApproved ? 'Cannot edit approved voucher' : 'Edit Voucher'; ?>">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </a>
                                    <?php endif; ?>

                                    <?php if (rbac_can_do('approve_records')): ?>
                                    <a href="sale_approval.php?voucher_no=<?php echo urlencode($r['voucher_no']); ?>&action=<?php echo $isApproved ? 'reopen' : 'approve'; ?>" 
                                       class="btn btn-dark btn-sm <?php echo $isApproved ? 'btn-warning' : ''; ?>"
                                       title="<?php echo $isApproved ? 'Reopen Voucher' : 'Approve Voucher'; ?>">
                                        <?php echo $isApproved ? '<i class="bi bi-unlock"></i> Reopen' : '<i class="bi bi-check2-circle"></i> Approve'; ?>
                                    </a>
                                    <?php endif; ?>

                                    <?php if (rbac_can_do('print_records')): ?>
                                    <a href="sale_print.php?voucher_no=<?php echo urlencode($r['voucher_no']); ?>" 
                                       class="btn btn-secondary btn-sm" 
                                       onclick="return openPrintPopup(this.href)" 
                                       title="Print Voucher">
                                        <i class="bi bi-printer"></i> Print
                                    </a>
                                    <?php endif; ?>

                                    <?php if (rbac_can_do('delete_records')): ?>
                                    <a href="sale_delete.php?voucher_no=<?php echo urlencode($r['voucher_no']); ?>" 
                                       class="btn btn-danger btn-sm delete-sale <?php echo $isApproved ? 'disabled' : ''; ?>" 
                                       title="Delete Voucher">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
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
    $('#saleTable').DataTable({
        pageLength: 10,
        order: [[0, 'desc']],
        columnDefs: [{ orderable: false, targets: 9 }]
    });

    $(document).on('click', '.delete-sale', function (e) {
        if (!confirm('Are you sure to delete this voucher?')) {
            e.preventDefault();
        }
    });
});
</script>

<?php include "../../includes/footer.php"; ?>
