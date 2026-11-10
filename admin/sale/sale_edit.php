<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('edit_records')) { header("Location: sale_view.php?denied=1"); exit; }
include "../../includes/approval_helper.php";
include "../../includes/financial_year_helper.php";
approval_ensure_schema($conn);
financial_year_ensure_schema($conn);

if (!isset($_GET['voucher_no'])) {
    header("Location: sale_view.php");
    exit;
}

$voucher_no = intval($_GET['voucher_no']);
$master = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT s.*, c.ACCOUNT_DESCRIPTION AS account_description
    FROM sale_issue_return s
    LEFT JOIN all_chart_of_accounts c ON s.account_no = c.ACCOUNT_NO
    WHERE s.voucher_no = $voucher_no
      AND s.deleted_at IS NULL
"));

if (!$master) {
    header("Location: sale_view.php");
    exit;
}

if (approval_is_locked($master['approval_status'] ?? 'Draft')) {
    header("Location: sale_view.php?locked=1");
    exit;
}

$detail = mysqli_query($conn, "SELECT * FROM sale_issue_bill WHERE voucher_no = $voucher_no ORDER BY id ASC");
$displayVoucher = !empty($master['display_voucher_no']) ? $master['display_voucher_no'] : $master['voucher_no'];

include "../../includes/header.php";
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">

<style>
.sale-edit-page .card { border: 0; border-radius: 14px; }
.sale-edit-page .card-header { border-radius: 14px 14px 0 0; }
.sale-edit-page .summary-card { border-radius: 14px; }
</style>

<div class="container-fluid sale-edit-page mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1"><i class="bi bi-pencil-square text-primary"></i> Edit Sale Voucher #<?php echo htmlspecialchars($displayVoucher); ?></h3>
            <div class="text-muted">Update sale voucher items, discount, and payment details.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo $base_url; ?>admin/sale/sale_view.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back To List
            </a>
            <button type="button" class="btn btn-secondary" onclick="return openPrintPopup('sale_print.php?voucher_no=<?php echo $voucher_no; ?>')">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>
    </div>

    <form id="editSaleForm">
        <input type="hidden" name="voucher_no" value="<?php echo htmlspecialchars($voucher_no); ?>">

        <!-- Voucher Details Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white"><h6 class="mb-0"><i class="bi bi-receipt"></i> Voucher Details</h6></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Account No</label>
                        <input type="text" name="account_no" id="account_no" class="form-control bg-light" value="<?php echo htmlspecialchars($master['account_no']); ?>" readonly required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold">Account Description</label>
                        <input type="text" id="account_desc" class="form-control bg-light" value="<?php echo htmlspecialchars($master['account_description'] ?? ''); ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Date</label>
                        <input type="date" name="record_date" class="form-control" value="<?php echo htmlspecialchars($master['record_date']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Sale Type</label>
                        <select name="sale_or_return" id="sale_or_return" class="form-select">
                            <option value="SALE VOUCHER" <?php echo ($master['sale_or_return'] === 'SALE VOUCHER') ? 'selected' : ''; ?>>SALE VOUCHER</option>
                            <option value="SALE RETURN" <?php echo ($master['sale_or_return'] === 'SALE RETURN') ? 'selected' : ''; ?>>SALE RETURN</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Sale Voucher No</label>
                        <input type="number" name="sale_voucher_no" id="sale_voucher_no" class="form-control" value="<?php echo htmlspecialchars($master['sale_voucher_no'] ?? ''); ?>" <?php echo ($master['sale_or_return'] === 'SALE RETURN') ? '' : 'disabled'; ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Bill No</label>
                        <input type="number" name="bill_no" class="form-control" value="<?php echo htmlspecialchars($master['bill_no'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold">Remarks</label>
                        <input type="text" name="remarks" class="form-control" value="<?php echo htmlspecialchars($master['remarks'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Sale Items Card -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0"><i class="bi bi-cart"></i> Sale Items</h6>
                <button type="button" class="btn btn-light btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#itemModal">
                    <i class="bi bi-plus-lg"></i> Add Item
                </button>
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
                        <tbody>
                        <?php
                        $sr = 1;
                        if ($detail && mysqli_num_rows($detail) > 0) {
                            while ($d = mysqli_fetch_assoc($detail)) {
                                $dTotal = floatval($d['qty']) * floatval($d['net_amount']);
                        ?>
                            <tr>
                                <td><?php echo $sr++; ?></td>
                                <td><?php echo htmlspecialchars($d['item_code']); ?><input type="hidden" name="item_code[]" value="<?php echo htmlspecialchars($d['item_code']); ?>"></td>
                                <td><?php echo htmlspecialchars($d['item_name']); ?><input type="hidden" name="item_name[]" value="<?php echo htmlspecialchars($d['item_name']); ?>"></td>
                                <td><input type="number" step="any" name="qty[]" class="form-control form-control-sm qty" value="<?php echo htmlspecialchars($d['qty']); ?>" required></td>
                                <td><input type="number" step="0.01" name="rate[]" class="form-control form-control-sm rate" value="<?php echo htmlspecialchars($d['net_amount']); ?>" required></td>
                                <td class="itemTotal text-end fw-semibold"><?php echo number_format($dTotal, 2); ?></td>
                                <td class="text-center"><button type="button" class="btn btn-danger btn-sm remove"><i class="bi bi-x-lg"></i></button></td>
                            </tr>
                        <?php
                            }
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Summary Card -->
        <div class="card summary-card mt-3 p-4 shadow-lg border-0 text-white" style="background: linear-gradient(135deg,#1e3c72,#2a5298);">
            <div class="row justify-content-end">
                <div class="col-md-5">
                    <table class="table text-white mb-0">
                        <tr><th>Total Bill</th><td class="text-end fw-bold">Rs <span id="billTotalText"><?php echo number_format((float) $master['net_amount'], 2); ?></span></td></tr>
                        <tr><th>Discount</th><td><input type="number" step="0.01" name="discount" id="discount" class="form-control form-control-sm" value="<?php echo htmlspecialchars($master['discount'] ?? 0); ?>"></td></tr>
                        <tr><th>Cash Received</th><td><input type="number" step="0.01" name="cash_received" id="cash_received" class="form-control form-control-sm" value="<?php echo htmlspecialchars($master['cash_received'] ?? 0); ?>"></td></tr>
                        <tr><th>Remaining Balance</th><td class="text-end fw-bold">Rs <span id="remainingText"><?php echo number_format((float) $master['balance'], 2); ?></span></td></tr>
                    </table>
                    <input type="hidden" name="net_amount" id="grandTotal" value="<?php echo htmlspecialchars($master['net_amount']); ?>">
                </div>
            </div>
        </div>

        <div class="text-end mt-4 mb-5">
            <a href="sale_view.php" class="btn btn-secondary btn-lg me-2">Cancel</a>
            <button type="button" id="updateBtn" class="btn btn-success btn-lg me-2">
                <i class="bi bi-save"></i> Update Voucher
            </button>
            <button type="button" id="printBtn" class="btn btn-primary btn-lg">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>
    </form>
</div>

<!-- Modal Select Item -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-box-seam"></i> Select Item</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table id="itemSearchTable" class="table table-bordered table-hover w-100">
                    <thead class="table-dark"><tr><th>Code</th><th>Name</th><th>Retail Rate</th></tr></thead>
                    <tbody>
                    <?php
                    $it = mysqli_query($conn, "SELECT item_code, item_name, retail_rate FROM all_inventory ORDER BY item_name");
                    while ($i = mysqli_fetch_assoc($it)) {
                    ?>
                        <tr style="cursor:pointer;" data-code="<?php echo htmlspecialchars($i['item_code']); ?>" data-name="<?php echo htmlspecialchars($i['item_name'], ENT_QUOTES); ?>" data-rate="<?php echo htmlspecialchars($i['retail_rate']); ?>">
                            <td><?php echo htmlspecialchars($i['item_code']); ?></td>
                            <td><?php echo htmlspecialchars($i['item_name']); ?></td>
                            <td><?php echo number_format((float) $i['retail_rate'], 2); ?></td>
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
let voucherNo = <?php echo (int) $voucher_no; ?>;

$(document).ready(function () {
    $('#itemSearchTable').DataTable({ pageLength: 7 });
    updateTotals();
});

$(document).on('change', '#sale_or_return', function () {
    if ($(this).val() === 'SALE RETURN') {
        $('#sale_voucher_no').prop('disabled', false);
    } else {
        $('#sale_voucher_no').prop('disabled', true).val('');
    }
});

$(document).on('click', '#itemSearchTable tbody tr', function () {
    var code = $(this).data('code');
    var name = $(this).data('name');
    var rate = $(this).data('rate');
    if (code) {
        addItem(code, name, rate);
        bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide();
    }
});

function addItem(code, name, rate) {
    let rowCount = $('#itemTable tbody tr').length + 1;
    let row = `<tr>
        <td>${rowCount}</td>
        <td>${code}<input type="hidden" name="item_code[]" value="${code}"></td>
        <td>${name}<input type="hidden" name="item_name[]" value="${name}"></td>
        <td><input type="number" step="any" name="qty[]" class="form-control form-control-sm qty" value="1" required></td>
        <td><input type="number" step="0.01" name="rate[]" class="form-control form-control-sm rate" value="${rate}" required></td>
        <td class="itemTotal text-end fw-semibold">0.00</td>
        <td class="text-center"><button type="button" class="btn btn-danger btn-sm remove"><i class="bi bi-x-lg"></i></button></td>
    </tr>`;
    $('#itemTable tbody').append(row);
    updateTotals();
}

$(document).on('click', '.remove', function () {
    $(this).closest('tr').remove();
    updateTotals();
});

$(document).on('input', '.qty, .rate, #discount, #cash_received', function () {
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

$('#updateBtn').click(function () {
    if ($('#itemTable tbody tr').length === 0) {
        alert('Please add at least one item.');
        return;
    }

    let formData = new FormData(document.getElementById('editSaleForm'));
    const btn = $(this);
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Updating...');

    fetch('sale_voucher_update.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            btn.prop('disabled', false).html('<i class="bi bi-save"></i> Update Voucher');
            if (data.status === 'success') {
                alert('Sale Voucher Updated Successfully!');
                window.location.href = 'sale_view.php?updated=1';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(err => {
            btn.prop('disabled', false).html('<i class="bi bi-save"></i> Update Voucher');
            alert('An unexpected error occurred while updating the voucher.');
        });
});

$('#printBtn').click(function () {
    openPrintPopup('sale_print.php?voucher_no=' + voucherNo);
});
</script>

<?php include "../../includes/footer.php"; ?>
