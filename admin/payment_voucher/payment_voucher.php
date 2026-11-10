<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('add_records')) { header("Location: payment_voucher_grid.php?denied=1"); exit; }
include "../../includes/financial_year_helper.php";
include "../../includes/header.php";
include "../../includes/account_lookup_helper.php";

financial_year_ensure_schema($conn);
voucher_series_ensure_transaction_columns($conn);
$r = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT IFNULL(MAX(voucher_no), 0) AS vno
    FROM payment_voucher
"));
$lastVoucher = $r['vno'];
$newVoucher = $_GET['vno'] ?? '';
$previewVoucher = voucher_series_preview($conn, 'pv', date('Y-m-d'));
$accountLookupRows = get_account_lookup_rows($conn);
?>

<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">

<style>
.pv-page .card {
    border: 0;
    border-radius: 14px;
}

.pv-page .card-header {
    border-radius: 14px 14px 0 0;
}

.pv-page .summary-card .card-body {
    border-radius: 14px;
}

.pv-page .table td,
.pv-page .table th {
    vertical-align: middle;
}

.pv-page .section-title {
    font-weight: 700;
    letter-spacing: 0.3px;
}
</style>

<div class="container-fluid pv-page mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1">Payment Voucher</h3>
            <div class="text-muted">Create and save a new payment voucher entry.</div>
        </div>
    </div>

    <div class="alert alert-info shadow-sm">
        <div><strong>Last Voucher Generated:</strong> <?php echo htmlspecialchars($lastVoucher); ?></div>
        <?php if ($previewVoucher): ?>
            <div><strong>Next Series No:</strong> <?php echo htmlspecialchars($previewVoucher); ?></div>
        <?php endif; ?>
        <?php if ($newVoucher): ?>
            <div><strong>Current Voucher Saved:</strong> <?php echo htmlspecialchars($newVoucher); ?></div>
        <?php endif; ?>
    </div>

    <form method="post" action="payment_voucher_save.php" id="pvForm">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0 section-title">Voucher Details</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label fw-bold">Payment Account</label>
                        <div class="input-group">
                            <input type="text" name="account_no" id="account_no" class="form-control fw-bold" readonly required>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#accountModal">
                                Search
                            </button>
                        </div>
                    </div>

                    <div class="col-lg-5 col-md-6">
                        <label class="form-label fw-bold">Account Description</label>
                        <input type="text" id="account_desc" class="form-control fw-bold text-danger" readonly>
                    </div>

                    <div class="col-lg-3 col-md-6">
                        <label class="form-label fw-bold">Voucher Date</label>
                        <input type="date" name="voucher_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="col-lg-6 col-md-6">
                        <label class="form-label fw-bold d-block">Voucher Type</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="voucher_type" value="BPV" checked>
                            <label class="form-check-label">BPV</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="voucher_type" value="CPV">
                            <label class="form-check-label">CPV</label>
                        </div>
                    </div>

                    <div class="col-lg-6 col-md-6">
                        <label class="form-label fw-bold">Voucher #</label>
                        <input type="text" class="form-control fw-bold" value="AUTO" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0 section-title">Debit Accounts</h6>
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#detailAccountModal">
                    + Add Account
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0" id="detailTable">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th style="width: 160px;">Account No</th>
                                <th>Account Description</th>
                                <th style="width: 220px;">Remarks</th>
                                <th class="text-end" style="width: 140px;">Debit</th>
                                <th style="width: 80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card summary-card shadow-sm mb-4">
            <div class="card-body bg-primary text-white">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-4 col-md-6">
                        <label class="form-label fw-bold">Total Debit</label>
                        <input type="text" id="total_debit" class="form-control fw-bold" readonly value="0.00">
                    </div>
                    <div class="col-lg-8 col-md-6 text-md-end fw-bold">
                        <div class="pt-md-0 pt-2">Total Accounts: <span id="rowCount">0</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-success btn-lg">Save</button>
        </div>
    </form>
</div>

<div class="modal fade" id="accountModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Select Payment Account</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered table-hover table-sm w-100" id="accountTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Account No</th>
                            <th>Description</th>
                            <th>Last Balance</th>
                            <th>Status</th>
                            <th style="width: 110px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($accountLookupRows as $a) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['account_no']); ?></td>
                            <td><?php echo htmlspecialchars($a['account_description']); ?></td>
                            <td class="text-end"><?php echo number_format((float) $a['last_balance'], 2); ?></td>
                            <td class="<?php echo $a['balance_label'] === 'Payable' ? 'text-danger fw-bold' : ($a['balance_label'] === 'Receivable' ? 'text-success fw-bold' : 'text-muted fw-bold'); ?>"><?php echo htmlspecialchars($a['balance_label']); ?></td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-success select-main-account"
                                    data-no="<?php echo htmlspecialchars($a['account_no']); ?>"
                                    data-desc="<?php echo htmlspecialchars($a['account_description'], ENT_QUOTES); ?>">
                                    Select
                                </button>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detailAccountModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Select Debit Account</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered table-hover table-sm w-100" id="detailAccountTable">
                    <thead class="table-dark">
                        <tr>
                            <th>Account No</th>
                            <th>Description</th>
                            <th>Last Balance</th>
                            <th>Status</th>
                            <th style="width: 110px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($accountLookupRows as $a) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['account_no']); ?></td>
                            <td><?php echo htmlspecialchars($a['account_description']); ?></td>
                            <td class="text-end"><?php echo number_format((float) $a['last_balance'], 2); ?></td>
                            <td class="<?php echo $a['balance_label'] === 'Payable' ? 'text-danger fw-bold' : ($a['balance_label'] === 'Receivable' ? 'text-success fw-bold' : 'text-muted fw-bold'); ?>"><?php echo htmlspecialchars($a['balance_label']); ?></td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-success select-detail-account"
                                    data-no="<?php echo htmlspecialchars($a['account_no']); ?>"
                                    data-desc="<?php echo htmlspecialchars($a['account_description'], ENT_QUOTES); ?>">
                                    Add
                                </button>
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
    $('#accountTable, #detailAccountTable').DataTable({
        pageLength: 8
    });

    function closeModal(modalId) {
        var modalInstance = bootstrap.Modal.getInstance(document.getElementById(modalId));
        if (modalInstance) {
            modalInstance.hide();
        }
    }

    function calculate() {
        var total = 0;

        $('.debit').each(function () {
            var val = parseFloat($(this).val());
            if (!isNaN(val)) {
                total += val;
            }
        });

        $('#total_debit').val(total.toFixed(2));
        $('#rowCount').text($('#detailTable tbody tr').length);
    }

    function renumberRows() {
        $('#detailTable tbody tr').each(function (index) {
            $(this).find('td:first').text(index + 1);
        });
    }

    $(document).on('click', '.select-main-account', function () {
        $('#account_no').val($(this).data('no'));
        $('#account_desc').val($(this).data('desc'));
        closeModal('accountModal');
    });

    $(document).on('click', '.select-detail-account', function () {
        var no = $(this).data('no');
        var desc = $(this).data('desc');
        var row = $('#detailTable tbody tr').length + 1;

        $('#detailTable tbody').append(`
            <tr>
                <td>${row}</td>
                <td>
                    ${no}
                    <input type="hidden" name="d_account_no[]" value="${no}">
                </td>
                <td>${desc}</td>
                <td>
                    <input type="text" name="remarks[]" class="form-control form-control-sm">
                </td>
                <td>
                    <input type="number" step="0.01" name="debit[]" class="form-control form-control-sm debit" min="0">
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm remove">X</button>
                </td>
            </tr>
        `);

        closeModal('detailAccountModal');
        calculate();
    });

    $(document).on('click', '.remove', function () {
        $(this).closest('tr').remove();
        renumberRows();
        calculate();
    });

    $(document).on('input', '.debit', calculate);

    $('#pvForm').on('submit', function (e) {
        var mainAccount = $('#account_no').val();
        var rowCount = $('#detailTable tbody tr').length;
        var totalDebit = parseFloat($('#total_debit').val()) || 0;

        if (!mainAccount) {
            alert('Please select Payment Account.');
            e.preventDefault();
            return;
        }

        if (rowCount === 0) {
            alert('Please add at least one Debit Account.');
            e.preventDefault();
            return;
        }

        if (totalDebit <= 0) {
            alert('Total Debit must be greater than 0.');
            e.preventDefault();
        }
    });
});
</script>

<?php include "../../includes/footer.php"; ?>
