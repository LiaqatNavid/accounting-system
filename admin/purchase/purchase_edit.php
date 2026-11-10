<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('edit_records')) { header("Location: purchase_grid_view.php?denied=1"); exit; }
include "../../includes/approval_helper.php";
approval_ensure_schema($conn);

if (!isset($_GET['voucher_no'])) {
    die("Voucher not found.");
}

$voucher_no = (int) $_GET['voucher_no'];
$master = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT p.*, c.ACCOUNT_DESCRIPTION AS account_description
    FROM purchase_issue_return p
    LEFT JOIN all_chart_of_accounts c ON p.account_no = c.account_no
    WHERE p.voucher_no = $voucher_no
      AND p.deleted_at IS NULL
"));

if (!$master) {
    die("Voucher not found.");
}

if (approval_is_locked($master['approval_status'] ?? 'Draft')) {
    header("Location: purchase_grid_view.php?locked=1");
    exit;
}

$detail = mysqli_query($conn, "SELECT * FROM purchase_issue_bill WHERE voucher_no = $voucher_no");
include "../../includes/header.php";
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">

<style>
.purchase-edit-page .card { border: 0; border-radius: 14px; }
.purchase-edit-page .card-header { border-radius: 14px 14px 0 0; }
.purchase-edit-page .summary-card .card-body { border-radius: 14px; }
</style>

<div class="container-fluid purchase-edit-page mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1">Edit Purchase Voucher #<?php echo htmlspecialchars($voucher_no); ?></h3>
            <div class="text-muted">Update purchase voucher lines and voucher details.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo $base_url; ?>admin/purchase/purchase_grid_view.php" class="btn btn-outline-primary">Back To List</a>
            <button type="button" class="btn btn-secondary" onclick="return openPrintPopup('purchase_print.php?voucher_no=<?php echo $voucher_no; ?>')">Print</button>
        </div>
    </div>

    <form method="post" action="purchase_update.php">
        <input type="hidden" name="voucher_no" value="<?php echo htmlspecialchars($voucher_no); ?>">

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white"><h6 class="mb-0">Voucher Details</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Account</label>
                        <input type="text" name="account_no" class="form-control fw-bold" value="<?php echo htmlspecialchars($master['account_no']); ?>" readonly>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold">Account Description</label>
                        <input type="text" name="account_description" class="form-control fw-bold text-danger" value="<?php echo htmlspecialchars($master['account_description'] ?? ''); ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Date</label>
                        <input type="date" name="record_date" class="form-control" value="<?php echo htmlspecialchars($master['record_date']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Type</label>
                        <select name="issue_or_return" class="form-control">
                            <option value="PURCHASE VOUCHER" <?php echo $master['issue_or_return'] == 'PURCHASE VOUCHER' ? 'selected' : ''; ?>>PURCHASE VOUCHER</option>
                            <option value="PURCHASE RETURN" <?php echo $master['issue_or_return'] == 'PURCHASE RETURN' ? 'selected' : ''; ?>>PURCHASE RETURN</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Remarks</label>
                        <input type="text" name="purchase_remarks" class="form-control" value="<?php echo htmlspecialchars($master['purchase_remarks']); ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0">Purchase Items</h6>
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#itemModal">+ Add Item</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0" id="itemTable">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:60px;">#</th>
                                <th style="width:140px;">Item Code</th>
                                <th>Item Name</th>
                                <th class="text-end" style="width:120px;">Qty</th>
                                <th class="text-end" style="width:140px;">Rate</th>
                                <th class="text-end" style="width:140px;">Total</th>
                                <th style="width:80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $sr = 1; while ($d = mysqli_fetch_assoc($detail)) { ?>
                            <tr>
                                <td class="fw-bold"><?php echo $sr++; ?></td>
                                <td><input name="item_code[]" class="form-control" value="<?php echo htmlspecialchars($d['item_code']); ?>" readonly></td>
                                <td><input name="item_name[]" class="form-control" value="<?php echo htmlspecialchars($d['item_name']); ?>" readonly></td>
                                <td><input name="qty[]" class="form-control text-end" value="<?php echo htmlspecialchars($d['qty']); ?>" oninput="calcRow(this)" required></td>
                                <td><input name="rate[]" class="form-control text-end" value="<?php echo htmlspecialchars($d['net_amount']); ?>" oninput="calcRow(this)" required></td>
                                <td><input name="item_total[]" class="form-control text-end" value="<?php echo htmlspecialchars($d['item_total']); ?>" readonly></td>
                                <td class="text-center"><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">X</button></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card summary-card shadow-sm mb-4">
            <div class="card-body bg-primary text-white">
                <div class="fw-bold">Bill Total: Rs. <span id="billTotalText"><?php echo number_format((float) $master['net_amount'], 2); ?></span></div>
                <input type="hidden" name="net_amount" id="grandTotal" value="<?php echo htmlspecialchars($master['net_amount']); ?>">
            </div>
        </div>

        <div class="text-end">
            <button type="submit" id="updateBtn" class="btn btn-lg btn-success me-2" onclick="disableUpdateBtn()">Update Voucher</button>
            <button type="button" class="btn btn-secondary" onclick="return openPrintPopup('purchase_print.php?voucher_no=<?php echo $voucher_no; ?>')">Re-Print</button>
        </div>
    </form>
</div>

<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Select Item</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table id="itemSearchTable" class="table table-bordered table-hover table-sm w-100">
                    <thead class="table-dark"><tr><th>Code</th><th>Name</th><th>Rate</th></tr></thead>
                    <tbody>
                    <?php
                    $it = mysqli_query($conn, "SELECT item_code,item_name,retail_rate FROM all_inventory ORDER BY item_name");
                    while ($i = mysqli_fetch_assoc($it)) {
                    ?>
                        <tr onclick="addItemFromModal('<?php echo htmlspecialchars($i['item_code'], ENT_QUOTES); ?>','<?php echo htmlspecialchars(addslashes($i['item_name']), ENT_QUOTES); ?>','<?php echo htmlspecialchars($i['retail_rate'], ENT_QUOTES); ?>')">
                            <td><?php echo htmlspecialchars($i['item_code']); ?></td>
                            <td><?php echo htmlspecialchars($i['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($i['retail_rate']); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo $base_url; ?>assets/js/jquery.dataTables.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/purchase.js"></script>
<script>
$(document).ready(function () {
    $('#itemSearchTable').DataTable({ pageLength: 5 });
});

function disableUpdateBtn() {
    const btn = document.getElementById('updateBtn');
    btn.disabled = true;
    btn.innerText = 'Updating...';
}
</script>

<?php include "../../includes/footer.php"; ?>
