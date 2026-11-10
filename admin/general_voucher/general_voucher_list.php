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

<link href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css" rel="stylesheet">

<style>
.gv-list-page .card { border: 0; border-radius: 14px; }
.gv-list-page .card-header { border-radius: 14px 14px 0 0; }
.gv-list-page .action-group { display: flex; gap: 6px; flex-wrap: wrap; }
.gv-list-page .status-badge { min-width: 82px; }
</style>

<div class="container-fluid gv-list-page mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1">General Voucher List</h3>
            <div class="text-muted">View, edit, print, and manage saved general vouchers.</div>
        </div>
        <a href="<?php echo $base_url; ?>admin/general_voucher/general_voucher.php" class="btn btn-primary">New Voucher</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0">All General Vouchers</h6>
        </div>
        <div class="card-body p-3">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-sm w-100 mb-0" id="voucherTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Voucher No</th>
                            <th>Series No</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-end">Total Debit</th>
                            <th class="text-end">Total Credit</th>
                            <th class="text-center">Accounts</th>
                            <th style="width:290px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $query = mysqli_query($conn, "
                        SELECT g.*, h.display_voucher_no, COALESCE(h.approval_status, 'Draft') AS approval_status
                        FROM vw_general_voucher_grid g
                        LEFT JOIN ONLINE_GENERAL_VOUCHER h ON h.VOUCHER_NO = g.voucher_no
                        WHERE h.deleted_at IS NULL
                        ORDER BY voucher_no DESC
                    ");
                    while ($row = mysqli_fetch_assoc($query)) {
                        $isApproved = approval_is_locked($row['approval_status'] ?? 'Draft');
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['voucher_no']); ?></td>
                            <td><?php echo htmlspecialchars($row['display_voucher_no'] ?: ('GV-' . $row['voucher_no'])); ?></td>
                            <td><?php echo htmlspecialchars($row['voucher_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['voucher_date']); ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo $isApproved ? 'bg-success' : 'bg-warning text-dark'; ?> status-badge">
                                    <?php echo htmlspecialchars($isApproved ? 'Approved' : 'Draft'); ?>
                                </span>
                            </td>
                            <td class="text-end"><?php echo number_format((float) $row['total_debit'], 2); ?></td>
                            <td class="text-end"><?php echo number_format((float) $row['total_credit'], 2); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($row['total_accounts']); ?></td>
                            <td>
                                <div class="action-group">
                                    <?php if (rbac_can_do('edit_records')): ?>
                                    <a href="general_voucher_edit.php?vno=<?php echo urlencode($row['voucher_no']); ?>" class="btn btn-sm btn-warning <?php echo $isApproved ? 'disabled' : ''; ?>">Edit</a>
                                    <?php endif; ?>
                                    <?php if (rbac_can_do('delete_records')): ?>
                                    <a href="general_voucher_delete.php?vno=<?php echo urlencode($row['voucher_no']); ?>" class="btn btn-sm btn-danger delete-voucher <?php echo $isApproved ? 'disabled' : ''; ?>">Delete</a>
                                    <?php endif; ?>
                                    <?php if (rbac_can_do('approve_records')): ?>
                                    <a href="general_voucher_approval.php?vno=<?php echo urlencode($row['voucher_no']); ?>&action=<?php echo $isApproved ? 'reopen' : 'approve'; ?>" class="btn btn-sm <?php echo $isApproved ? 'btn-warning' : 'btn-dark'; ?>">
                                        <?php echo $isApproved ? 'Reopen' : 'Approve'; ?>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (rbac_can_do('print_records')): ?>
                                    <a href="general_voucher_print.php?vno=<?php echo urlencode($row['voucher_no']); ?>" class="btn btn-sm btn-primary" onclick="return openPrintPopup(this.href)">Print</a>
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
        pageLength: 10,
        columnDefs: [{ orderable: false, targets: 8 }]
    });

    $(document).on('click', '.delete-voucher', function (e) {
        if (!confirm('Are you sure you want to delete this voucher?')) {
            e.preventDefault();
        }
    });
});
</script>

<?php include "../../includes/footer.php"; ?>
