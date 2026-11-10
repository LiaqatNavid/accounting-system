<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('add_records')) { header("Location: sale_view.php?denied=1"); exit; }
include "../../includes/approval_helper.php";
include "../../includes/financial_year_helper.php";
include "../../includes/header.php";
include "../../includes/account_lookup_helper.php";
approval_ensure_schema($conn);
financial_year_ensure_schema($conn);
voucher_series_ensure_transaction_columns($conn);

$last = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT IFNULL(MAX(voucher_no),0) AS last_vno
    FROM sale_issue_return
    WHERE deleted_at IS NULL
"));
$lastVoucher = $last['last_vno'] ?? 0;
$previewVoucher = voucher_series_preview($conn, 'sale', date('Y-m-d'));
$accountLookupRows = get_account_lookup_rows($conn);
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">

<style>
.sale-page .card { border: 0; border-radius: 14px; }
.sale-page .card-header { border-radius: 14px 14px 0 0; }
.sale-page .summary-card { border-radius: 14px; }
</style>

<div class="container-fluid sale-page mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1">Sale Voucher</h3>
            <div class="text-muted">Create and save sale vouchers or sale returns.</div>
        </div>
        <div class="fw-bold">
            Last Voucher: <span class="text-primary"><?php echo htmlspecialchars($lastVoucher); ?></span>
            <?php if ($previewVoucher): ?><div class="small text-muted">Next Series: <?php echo htmlspecialchars($previewVoucher); ?></div><?php endif; ?>
        </div>
    </div>

    <form id="saleForm">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white"><h6 class="mb-0">Voucher Details</h6></div>
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
                        <input type="text" id="account_desc" class="form-control" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Date</label>
                        <input type="date" name="record_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Sale Type</label>
                        <select name="sale_or_return" id="sale_or_return" class="form-control">
                            <option value="SALE VOUCHER">SALE VOUCHER</option>
                            <option value="SALE RETURN">SALE RETURN</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Sale Voucher No</label>
                        <input type="number" name="sale_voucher_no" id="sale_voucher_no" class="form-control" disabled>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Bill No</label>
                        <input type="number" name="bill_no" class="form-control" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Remarks</label>
                        <input type="text" name="remarks" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0">Sale Items</h6>
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

        <div class="card summary-card mt-3 p-4 shadow-lg border-0 text-white" style="background: linear-gradient(135deg,#1e3c72,#2a5298);">
            <div class="row justify-content-end">
                <div class="col-md-5">
                    <table class="table text-white mb-0">
                        <tr><th>Total</th><td class="text-end fw-bold">Rs <span id="billTotalText">0.00</span></td></tr>
                        <tr><th>Discount</th><td><input type="number" step="0.01" name="discount" id="discount" class="form-control form-control-sm" value="0"></td></tr>
                        <tr><th>Cash Received</th><td><input type="number" step="0.01" name="cash_received" id="cash_received" class="form-control form-control-sm" value="0"></td></tr>
                        <tr><th>Remaining</th><td class="text-end fw-bold">Rs <span id="remainingText">0.00</span></td></tr>
                    </table>
                    <input type="hidden" name="net_amount" id="grandTotal" value="0">
                </div>
            </div>
        </div>

        <div class="text-end mt-3">
            <button type="button" id="newBtn" class="btn btn-dark me-2">New Voucher</button>
            <button type="button" id="saveBtn" class="btn btn-success me-2">Save</button>
            <button type="button" id="printBtn" class="btn btn-primary">Print</button>
        </div>
    </form>
</div>

<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Select Account</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
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
            <div class="modal-header"><h5 class="modal-title">Select Item</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <table id="itemSearchTable" class="table table-bordered table-hover w-100">
                    <thead><tr><th>Code</th><th>Name</th><th>Retail</th></tr></thead>
                    <tbody>
                    <?php
                    $it = mysqli_query($conn, "SELECT item_code,item_name,retail_rate FROM all_inventory");
                    while ($i = mysqli_fetch_assoc($it)) {
                    ?>
                        <tr data-code="<?php echo htmlspecialchars($i['item_code']); ?>" data-name="<?php echo htmlspecialchars($i['item_name'], ENT_QUOTES); ?>" data-rate="<?php echo htmlspecialchars($i['retail_rate']); ?>">
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
<script>
let savedVoucherNo = 0;

$(document).ready(function () {
    $('#accountTable').DataTable({ pageLength: 5 });
    $('#itemSearchTable').DataTable({ pageLength: 5 });
});

$(document).on('change', '#sale_or_return', function () {
    if ($(this).val() === 'SALE RETURN') {
        $('#sale_voucher_no').prop('disabled', false);
    } else {
        $('#sale_voucher_no').prop('disabled', true).val('');
    }
});

$(document).on('click', '.select-account', function () {
    $('#account_no').val($(this).data('no'));
    $('#account_desc').val($(this).data('desc'));
    bootstrap.Modal.getInstance(document.getElementById('accountModal')).hide();
});

$(document).on('click', '#itemSearchTable tbody tr', function () {
    addItem($(this).data('code'), $(this).data('name'), $(this).data('rate'));
    bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide();
});

function addItem(code, name, rate) {
    let rowCount = $('#itemTable tbody tr').length + 1;
    let row = `<tr><td>${rowCount}</td><td>${code}<input type="hidden" name="item_code[]" value="${code}"></td><td>${name}<input type="hidden" name="item_name[]" value="${name}"></td><td><input type="number" name="qty[]" class="form-control qty" value="1"></td><td><input type="number" name="rate[]" class="form-control rate" value="${rate}"></td><td class="itemTotal">0.00</td><td><button type="button" class="btn btn-danger btn-sm remove">X</button></td></tr>`;
    $('#itemTable tbody').append(row);
    updateTotals();
}

$(document).on('click', '.remove', function () {
    $(this).closest('tr').remove();
    updateTotals();
});

$(document).on('input', '.qty,.rate,#discount,#cash_received', function () {
    updateTotals();
});

function updateTotals() {
    let total = 0;
    $('#itemTable tbody tr').each(function (index) {
        $(this).find('td:first').text(index + 1);
        let qty = parseFloat($(this).find('.qty').val()) || 0;
        let rate = parseFloat($(this).find('.rate').val()) || 0;
        let rowTotal = qty * rate;
        $(this).find('.itemTotal').text(rowTotal.toFixed(2));
        total += rowTotal;
    });

    let discount = parseFloat($('#discount').val()) || 0;
    let cash = parseFloat($('#cash_received').val()) || 0;
    let remaining = (total - discount) - cash;
    $('#grandTotal').val(total.toFixed(2));
    $('#billTotalText').text(total.toFixed(2));
    $('#remainingText').text(remaining.toFixed(2));
}

$('#saveBtn').click(function () {
    if ($('#itemTable tbody tr').length === 0) {
        alert('Add at least one item.');
        return;
    }

    let formData = new FormData(document.getElementById('saleForm'));
    if (savedVoucherNo > 0) { formData.append('voucher_no', savedVoucherNo); }

    fetch('sale_voucher_save.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                savedVoucherNo = data.voucher_no;
                $('#accountSearchBtn').prop('disabled', true);
                alert('Voucher Saved Successfully!');
            } else {
                alert(data.message);
            }
        });
});

$('#printBtn').click(function () {
    if (savedVoucherNo > 0) {
        openPrintPopup('sale_print.php?voucher_no=' + savedVoucherNo);
    } else {
        alert('Please save voucher first.');
    }
});

$('#newBtn').click(function () {
    location.reload();
});
</script>

<?php include "../../includes/footer.php"; ?>
