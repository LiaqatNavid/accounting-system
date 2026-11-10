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
.payment-grid-page .card {
    border: 0;
    border-radius: 14px;
}

.payment-grid-page .card-header {
    border-radius: 14px 14px 0 0;
}

.payment-grid-page .table td,
.payment-grid-page .table th {
    vertical-align: middle;
}

.payment-grid-page .action-group {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.payment-grid-page .status-badge {
    min-width: 82px;
}
</style>

<div class="container-fluid payment-grid-page mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1">Payment Voucher Grid</h3>
            <div class="text-muted">View, edit, print, and manage saved payment vouchers.</div>
        </div>
        <a href="<?php echo $base_url; ?>admin/payment_voucher/payment_voucher.php" class="btn btn-primary">
            New Voucher
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0">Payment Voucher Grid Debit From Accounts</h6>
        </div>
        <div class="card-body p-3">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm w-100 mb-0" id="voucherTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Voucher #</th>
                            <th>Series No</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Main Account</th>
                            <th>Account Name</th>
                            <th>Status</th>
                            <th>Total Debit Accounts</th>
                            <th>Total Debit</th>
                            <th style="width: 290px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $q = mysqli_query($conn, "
                        SELECT g.*, pv.display_voucher_no, COALESCE(pv.approval_status, 'Draft') AS approval_status
                        FROM vw_payment_voucher_grid g
                        LEFT JOIN payment_voucher pv ON pv.voucher_no = g.voucher_no
                        WHERE pv.deleted_at IS NULL
                        ORDER BY voucher_no DESC
                    ");

                    while ($row = mysqli_fetch_assoc($q)) {
                    ?>
                        <?php $isApproved = approval_is_locked($row['approval_status'] ?? 'Draft'); ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['voucher_no']); ?></td>
                            <td><?php echo htmlspecialchars($row['display_voucher_no'] ?: ('PV-' . $row['voucher_no'])); ?></td>
                            <td><?php echo htmlspecialchars($row['voucher_type']); ?></td>
                            <td><?php echo date('d-m-Y', strtotime($row['voucher_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['main_account']); ?></td>
                            <td><?php echo htmlspecialchars($row['main_account_name']); ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo $isApproved ? 'bg-success' : 'bg-warning text-dark'; ?> status-badge">
                                    <?php echo htmlspecialchars($isApproved ? 'Approved' : 'Draft'); ?>
                                </span>
                            </td>
                            <td class="text-center"><?php echo htmlspecialchars($row['total_debit_account']); ?></td>
                            <td class="text-end"><?php echo number_format((float) $row['total_debit'], 2); ?></td>
                            <td>
                                <div class="action-group">
                                    <?php if (rbac_can_do('edit_records')): ?>
                                    <a href="payment_voucher_edit.php?vno=<?php echo urlencode($row['voucher_no']); ?>" class="btn btn-sm btn-primary <?php echo $isApproved ? 'disabled' : ''; ?>">
                                        Edit
                                    </a>
                                    <?php endif; ?>
                                    <?php if (rbac_can_do('delete_records')): ?>
                                    <a href="payment_voucher_delete.php?vno=<?php echo urlencode($row['voucher_no']); ?>" class="btn btn-sm btn-danger delete-voucher <?php echo $isApproved ? 'disabled' : ''; ?>">
                                        Delete
                                    </a>
                                    <?php endif; ?>
                                    <?php if (rbac_can_do('approve_records')): ?>
                                    <a href="payment_voucher_approval.php?vno=<?php echo urlencode($row['voucher_no']); ?>&action=<?php echo $isApproved ? 'reopen' : 'approve'; ?>" class="btn btn-sm <?php echo $isApproved ? 'btn-warning' : 'btn-dark'; ?>">
                                        <?php echo $isApproved ? 'Reopen' : 'Approve'; ?>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (rbac_can_do('print_records')): ?>
                                    <a href="payment_voucher_print.php?vno=<?php echo urlencode($row['voucher_no']); ?>" class="btn btn-sm btn-success" onclick="return openPrintPopup(this.href)">
                                        Print
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
    $('#voucherTable').DataTable({
        pageLength: 15,
        order: [[0, 'desc']],
        autoWidth: false,
        columnDefs: [
            { orderable: false, targets: 9 }
        ]
    });

    $(document).on('click', '.delete-voucher', function (e) {
        if (!confirm('Delete this voucher?')) {
            e.preventDefault();
        }
    });
});
</script>

<?php include "../../includes/footer.php"; ?>
