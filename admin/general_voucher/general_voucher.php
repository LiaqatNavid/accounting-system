<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('add_records')) { header("Location: general_voucher_list.php?denied=1"); exit; }
include "../../includes/financial_year_helper.php";
include "../../includes/header.php";
include "../../includes/account_lookup_helper.php";

financial_year_ensure_schema($conn);
voucher_series_ensure_transaction_columns($conn);
$lastVoucher = "None";
$qv = mysqli_query($conn, "
    SELECT MAX(VOUCHER_NO) AS last_voucher
    FROM ONLINE_GENERAL_VOUCHER
");
if ($row = mysqli_fetch_assoc($qv)) {
    if ($row['last_voucher']) {
        $lastVoucher = $row['last_voucher'];
    }
}
$previewVoucher = voucher_series_preview($conn, 'gv', date('Y-m-d'));
$accountLookupRows = get_account_lookup_rows($conn);
?>

<link href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css" rel="stylesheet">

<style>
.gv-page .card { border: 0; border-radius: 14px; }
.gv-page .card-header { border-radius: 14px 14px 0 0; }
.gv-page .summary-card .card-body { border-radius: 14px; }
.gv-page .table td, .gv-page .table th { vertical-align: middle; }
</style>

<div class="container-fluid gv-page mt-4">
    <div class="mb-3">
        <h3 class="mb-1">General Voucher</h3>
        <div class="text-muted">Create a balanced journal voucher entry.</div>
    </div>

    <div class="alert alert-info shadow-sm">
        <strong>Last Voucher Generated:</strong> <?php echo htmlspecialchars($lastVoucher); ?>
        <?php if ($previewVoucher): ?>
            <div class="mt-1"><strong>Next Series No:</strong> <?php echo htmlspecialchars($previewVoucher); ?></div>
        <?php endif; ?>
    </div>

    <form method="post" action="general_voucher_save.php" id="jvForm">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">Voucher Details</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Voucher Date</label>
                        <input type="date" name="voucher_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Voucher Type</label>
                        <select name="voucher_type" class="form-control fw-bold">
                            <option value="JV">JV</option>
                            <option value="OTHER">OTHER</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Voucher #</label>
                        <input type="text" class="form-control fw-bold" value="AUTO GENERATED" readonly>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0">Voucher Accounts</h6>
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#detailAccountModal">+ Add Account</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0" id="detailTable">
                        <thead class="table-dark">
                            <tr>
                                <th style="width:60px;">#</th>
                                <th style="width:160px;">Account No</th>
                                <th>Description</th>
                                <th style="width:220px;">Remarks</th>
                                <th class="text-end" style="width:140px;">Debit</th>
                                <th class="text-end" style="width:140px;">Credit</th>
                                <th style="width:80px;">Action</th>
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
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Total Debit</label>
                        <input type="text" id="total_debit" class="form-control fw-bold" readonly value="0.00">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Total Credit</label>
                        <input type="text" id="total_credit" class="form-control fw-bold" readonly value="0.00">
                    </div>
                    <div class="col-md-6 text-md-end fw-bold">
                        <div>Total Accounts: <span id="rowCount">0</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-success btn-lg">Save Voucher</button>
        </div>
    </form>
</div>

<div class="modal fade" id="detailAccountModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">Select Account</h5>
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
                            <th style="width:110px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($accountLookupRows as $a) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($a['account_no']); ?></td>
                            <td><?php echo htmlspecialchars($a['account_description']); ?></td>
                            <td class="text-end"><?php echo number_format((float) $a['last_balance'], 2); ?></td>
                            <td class="<?php echo $a['balance_label'] === 'Payable' ? 'text-danger fw-bold' : ($a['balance_label'] === 'Receivable' ? 'text-success fw-bold' : 'text-muted fw-bold'); ?>"><?php echo htmlspecialchars($a['balance_label']); ?></td>
                            <td><button type="button" class="btn btn-sm btn-success select-detail-account" data-no="<?php echo htmlspecialchars($a['account_no']); ?>" data-desc="<?php echo htmlspecialchars($a['account_description'], ENT_QUOTES); ?>">Add</button></td>
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
    $('#detailAccountTable').DataTable({ pageLength: 8 });

    function calculate() {
        var td = 0, tc = 0;
        $('.debit').each(function () { td += parseFloat($(this).val()) || 0; });
        $('.credit').each(function () { tc += parseFloat($(this).val()) || 0; });
        $('#total_debit').val(td.toFixed(2));
        $('#total_credit').val(tc.toFixed(2));
        $('#rowCount').text($('#detailTable tbody tr').length);
    }

    function reIndex() {
        $('#detailTable tbody tr').each(function (index) {
            $(this).find('.row-no').text(index + 1);
        });
    }

    $(document).on('click', '.select-detail-account', function () {
        var no = $(this).data('no');
        var desc = $(this).data('desc');
        var row = $('#detailTable tbody tr').length + 1;

        $('#detailTable tbody').append(`
            <tr>
                <td class="row-no">${row}</td>
                <td>${no}<input type="hidden" name="account_no[]" value="${no}"></td>
                <td>${desc}</td>
                <td><input type="text" name="remarks[]" class="form-control form-control-sm"></td>
                <td><input type="number" step="0.01" name="debit[]" class="form-control form-control-sm debit" min="0"></td>
                <td><input type="number" step="0.01" name="credit[]" class="form-control form-control-sm credit" min="0"></td>
                <td class="text-center"><button type="button" class="btn btn-danger btn-sm remove">X</button></td>
            </tr>
        `);

        var modalInstance = bootstrap.Modal.getInstance(document.getElementById('detailAccountModal'));
        if (modalInstance) { modalInstance.hide(); }
        calculate();
        reIndex();
    });

    $(document).on('input', '.debit', function () {
        if ($(this).val() !== '') { $(this).closest('tr').find('.credit').val(''); }
        calculate();
    });

    $(document).on('input', '.credit', function () {
        if ($(this).val() !== '') { $(this).closest('tr').find('.debit').val(''); }
        calculate();
    });

    $(document).on('click', '.remove', function () {
        $(this).closest('tr').remove();
        calculate();
        reIndex();
    });

    $('#jvForm').on('submit', function (e) {
        var d = parseFloat($('#total_debit').val()) || 0;
        var c = parseFloat($('#total_credit').val()) || 0;
        if (d !== c || d === 0) {
            alert('Debit and Credit must be equal and greater than 0');
            e.preventDefault();
        }
    });
});
</script>

<?php include "../../includes/footer.php"; ?>
