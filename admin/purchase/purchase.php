<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('add_records')) { header("Location: purchase_grid_view.php?denied=1"); exit; }
include "../../includes/approval_helper.php";
include "../../includes/financial_year_helper.php";
include "../../includes/header.php";
include "../../includes/account_lookup_helper.php";
approval_ensure_schema($conn);
financial_year_ensure_schema($conn);
voucher_series_ensure_transaction_columns($conn);

$last = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT IFNULL(MAX(voucher_no),0) AS last_vno
    FROM purchase_issue_return
    WHERE deleted_at IS NULL
"));
$lastVoucher = $last['last_vno'] ?? 0;
$previewVoucher = voucher_series_preview($conn, 'pur', date('Y-m-d'));
$accountLookupRows = get_account_lookup_rows($conn);
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">

<style>
.purchase-page .card { border: 0; border-radius: 14px; }
.purchase-page .card-header { border-radius: 14px 14px 0 0; }
.purchase-page .summary-card { border-radius: 14px; }
</style>

<div class="container-fluid purchase-page mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 id="voucherTitle" class="mb-1">Purchase Voucher</h3>
            <div class="text-muted">Create and save purchase vouchers or purchase returns.</div>
        </div>
        <div class="fw-bold">
            Last Voucher: <span class="text-primary"><?php echo htmlspecialchars($lastVoucher); ?></span>
            <?php if ($previewVoucher): ?><div class="small text-muted">Next Series: <?php echo htmlspecialchars($previewVoucher); ?></div><?php endif; ?>
        </div>
    </div>

    <form id="purchaseForm">
        <input type="hidden" id="voucher_no" name="voucher_no" value="">

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">Voucher Details</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Account</label>
                        <div class="input-group">
                            <input type="text" name="account_no" id="account_no" class="form-control" readonly required>
                            <button type="button" id="accountSearchBtn" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#accountModal">Search</button>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold">Description</label>
                        <input type="text" name="account_description" id="account_desc" class="form-control" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Date</label>
                        <input type="date" name="record_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Type</label>
                        <select name="issue_or_return" id="issue_or_return" class="form-control">
                            <option value="PURCHASE VOUCHER">PURCHASE VOUCHER</option>
                            <option value="PURCHASE RETURN">PURCHASE RETURN</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Remarks</label>
                        <input type="text" name="purchase_remarks" class="form-control">
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
                    <table class="table table-bordered mb-0" id="itemTable">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:60px;">#</th>
                                <th style="width:140px;">Code</th>
                                <th>Name</th>
                                <th style="width:120px;">Qty</th>
                                <th style="width:140px;">Rate</th>
                                <th style="width:140px;">Total</th>
                                <th style="width:80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card summary-card shadow-sm mb-4 bg-primary text-white">
            <div class="card-body">
                <div class="row align-items-end g-3">
                    <div class="col-md-4"><div class="fw-bold">Total: Rs <span id="billTotalText">0.00</span></div></div>
                    <div class="col-md-4 text-md-center fw-bold"><div>Total Items: <span id="itemCount">0</span></div></div>
                    <div class="col-md-4 text-md-end"><input type="hidden" name="net_amount" id="grandTotal" value="0"></div>
                </div>
            </div>
        </div>

        <div class="text-end">
            <button type="button" id="saveBtn" class="btn btn-success me-2" onclick="saveVoucher()">Save</button>
            <button type="button" id="printBtn" class="btn btn-secondary me-2" disabled onclick="openPrint()">Print</button>
            <button type="button" id="newBtn" class="btn btn-primary" disabled onclick="newVoucher()">New Voucher</button>
        </div>
    </form>
</div>

<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Account</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table id="accountTable" class="table table-bordered table-hover w-100">
                    <thead><tr><th>No</th><th>Description</th><th>Last Balance</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($accountLookupRows as $a) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['account_no']); ?></td>
                            <td><?php echo htmlspecialchars($a['account_description']); ?></td>
                            <td class="text-end"><?php echo number_format((float) $a['last_balance'], 2); ?></td>
                            <td class="<?php echo $a['balance_label'] === 'Payable' ? 'text-danger fw-bold' : ($a['balance_label'] === 'Receivable' ? 'text-success fw-bold' : 'text-muted fw-bold'); ?>"><?php echo htmlspecialchars($a['balance_label']); ?></td>
                            <td><button type="button" class="btn btn-sm btn-success select-account" data-no="<?php echo htmlspecialchars($a['account_no']); ?>" data-desc="<?php echo htmlspecialchars($a['account_description'], ENT_QUOTES); ?>">Select</button></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select Item</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table id="itemSearchTable" class="table table-bordered table-hover w-100">
                    <thead><tr><th>Code</th><th>Name</th><th>Rate</th></tr></thead>
                    <tbody>
                    <?php
                    $it = mysqli_query($conn, "SELECT item_code,item_name,retail_rate FROM all_inventory");
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
let savedVoucherNo = 0;

function saveVoucher() {
    const formData = new FormData(document.getElementById('purchaseForm'));
    if (savedVoucherNo > 0) { formData.append('voucher_no', savedVoucherNo); }

    fetch('purchase_voucher_save.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                savedVoucherNo = data.voucher_no;
                document.getElementById('voucher_no').value = savedVoucherNo;
                document.getElementById('printBtn').disabled = false;
                document.getElementById('newBtn').disabled = false;
                document.getElementById('accountSearchBtn').disabled = true;
                alert('Voucher Saved Successfully!');
            } else {
                alert('Save Failed');
            }
        })
        .catch(() => { alert('Server Error'); });
}

function openPrint() {
    if (savedVoucherNo > 0) {
        openPrintPopup('purchase_print.php?voucher_no=' + savedVoucherNo);
    }
}

function newVoucher() {
    window.location.href = 'purchase.php';
}

function selectAccount(no, desc) {
    $('#account_no').val(no);
    $('#account_desc').val(desc);
    const modal = bootstrap.Modal.getInstance(document.getElementById('accountModal'));
    if (modal) { modal.hide(); }
    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
    document.body.classList.remove('modal-open');
}

$(document).on('click', '.select-account', function () {
    selectAccount($(this).data('no'), $(this).data('desc'));
});

$(function () {
    $('#accountTable').DataTable({ pageLength: 5 });
    $('#itemSearchTable').DataTable({ pageLength: 5 });
});
</script>

<?php include "../../includes/footer.php"; ?>
