<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
if (!rbac_can_do('edit_records')) { header("Location: general_voucher_list.php?denied=1"); exit; }
include "../../includes/approval_helper.php";
include "../../includes/soft_delete_helper.php";
include "../../includes/account_lookup_helper.php";
approval_ensure_schema($conn);
soft_delete_ensure_schema($conn);

if (!isset($_GET['vno'])) {
    die("Voucher Number Missing");
}

$voucher_no = (int) $_GET['vno'];
$header = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT *
    FROM ONLINE_GENERAL_VOUCHER
    WHERE VOUCHER_NO = $voucher_no
      AND deleted_at IS NULL
"));

if (!$header) {
    die("Voucher Not Found");
}

if (approval_is_locked($header['approval_status'] ?? 'Draft')) {
    header("Location: general_voucher_list.php?locked=1");
    exit;
}

$detailQuery = mysqli_query($conn, "
    SELECT d.*, coa.account_description
    FROM ONLINE_GENERAL_VOUCHER_DETAIL d
    LEFT JOIN all_chart_of_accounts coa ON d.ACCOUNT_NO = coa.account_no
    WHERE d.VOUCHER_NO = $voucher_no
    ORDER BY d.ID ASC
");
$accountLookupRows = get_account_lookup_rows($conn);
include "../../includes/header.php";
?>

<link href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css" rel="stylesheet">

<style>
.gv-edit-page .card { border: 0; border-radius: 14px; }
.gv-edit-page .card-header { border-radius: 14px 14px 0 0; }
.gv-edit-page .summary-card .card-body { border-radius: 14px; }
.gv-edit-page .table td, .gv-edit-page .table th { vertical-align: middle; }
</style>

<div class="container-fluid gv-edit-page mt-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h3 class="mb-1">Edit General Voucher #<?php echo htmlspecialchars($voucher_no); ?></h3>
            <div class="text-muted">Update the journal voucher header and account lines.</div>
        </div>
        <a href="<?php echo $base_url; ?>admin/general_voucher/general_voucher_list.php" class="btn btn-outline-primary">Back To List</a>
    </div>

    <form method="post" action="general_voucher_update.php" id="jvForm">
        <input type="hidden" name="voucher_no" value="<?php echo htmlspecialchars($voucher_no); ?>">

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">Voucher Details</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Voucher Date</label>
                        <input type="date" name="voucher_date" class="form-control" value="<?php echo htmlspecialchars($header['VOUCHER_DATE']); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Voucher Type</label>
                        <select name="voucher_type" class="form-control">
                            <option value="JV" <?php echo $header['VOUCHER_TYPE'] === 'JV' ? 'selected' : ''; ?>>JV</option>
                            <option value="OTHER" <?php echo $header['VOUCHER_TYPE'] === 'OTHER' ? 'selected' : ''; ?>>OTHER</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Voucher No</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($voucher_no); ?>" readonly>
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
                        <tbody>
                        <?php
                        $i = 1;
                        while ($row = mysqli_fetch_assoc($detailQuery)) {
                        ?>
                            <tr>
                                <td class="row-no"><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($row['ACCOUNT_NO']); ?><input type="hidden" name="account_no[]" value="<?php echo htmlspecialchars($row['ACCOUNT_NO']); ?>"></td>
                                <td><?php echo htmlspecialchars($row['ACCOUNT_DESCRIPTION']); ?></td>
                                <td><input type="text" name="remarks[]" class="form-control form-control-sm" value="<?php echo htmlspecialchars($row['REMARKS']); ?>"></td>
                                <td><input type="number" step="0.01" name="debit[]" class="form-control form-control-sm debit" value="<?php echo htmlspecialchars($row['DEBIT']); ?>"></td>
                                <td><input type="number" step="0.01" name="credit[]" class="form-control form-control-sm credit" value="<?php echo htmlspecialchars($row['CREDIT']); ?>"></td>
                                <td class="text-center"><button type="button" class="btn btn-danger btn-sm remove">X</button></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card summary-card shadow-sm mb-4">
            <div class="card-body bg-primary text-white">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Total Debit</label>
                        <input type="text" id="total_debit" class="form-control fw-bold" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Total Credit</label>
                        <input type="text" id="total_credit" class="form-control fw-bold" readonly>
                    </div>
                    <div class="col-md-6 text-md-end fw-bold">
                        <div>Total Accounts: <span id="rowCount">0</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-success btn-lg">Update Voucher</button>
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
                        <tr><th>Account No</th><th>Description</th><th>Last Balance</th><th>Status</th><th style="width:110px;">Action</th></tr>
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
        let td = 0, tc = 0;
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

    calculate();
    reIndex();

    $(document).on('click', '.select-detail-account', function () {
        let no = $(this).data('no');
        let desc = $(this).data('desc');
        let row = $('#detailTable tbody tr').length + 1;
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
        const modal = bootstrap.Modal.getInstance(document.getElementById('detailAccountModal'));
        if (modal) { modal.hide(); }
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
        let d = parseFloat($('#total_debit').val()) || 0;
        let c = parseFloat($('#total_credit').val()) || 0;
        if (d !== c || d === 0) {
            alert('Debit and Credit must be equal and greater than 0');
            e.preventDefault();
        }
    });
});
</script>

<?php include "../../includes/footer.php"; ?>
