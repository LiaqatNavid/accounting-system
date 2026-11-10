<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/audit_helper.php";
include "../../includes/history_retention_helper.php";

$account_no = $_GET['account_no'] ?? '';
ensure_audit_schema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_deleted_logs'])) {
    if (!rbac_is_admin()) {
        header("Location: account_ai_dashboard.php?denied=1");
        exit;
    }

    $result = history_retention_manual_clear_delete_log(
        $conn,
        'deleted_records_history',
        'Deleted records history manually cleared after archive'
    );

    $redirectFlag = ($result['deleted_rows'] ?? 0) > 0 ? 'cleared=1' : 'empty=1';
    header("Location: account_ai_dashboard.php?{$redirectFlag}");
    exit;
}

include "../../includes/header.php";

$today = date('Y-m-d');
$auditWindowStart = date('Y-m-d', strtotime('-21 days'));

$backdatedSummarySql = "
SELECT COUNT(*) AS total_backdated_today
FROM (
    SELECT voucher_no AS record_no
    FROM payment_voucher
    WHERE DATE(created_at) = CURDATE()
      AND voucher_date >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
      AND voucher_date < DATE(created_at)
    UNION ALL
    SELECT voucher_no AS record_no
    FROM receipt_voucher
    WHERE DATE(created_at) = CURDATE()
      AND voucher_date >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
      AND voucher_date < DATE(created_at)
    UNION ALL
    SELECT VOUCHER_NO AS record_no
    FROM online_general_voucher
    WHERE DATE(created_at) = CURDATE()
      AND VOUCHER_DATE >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
      AND VOUCHER_DATE < DATE(created_at)
    UNION ALL
    SELECT id AS record_no
    FROM stock_adjustment_master
    WHERE DATE(created_at) = CURDATE()
      AND adjustment_date >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
      AND adjustment_date < DATE(created_at)
    UNION ALL
    SELECT voucher_no AS record_no
    FROM purchase_issue_return
    WHERE DATE(created_at) = CURDATE()
      AND record_date >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
      AND record_date < DATE(created_at)
    UNION ALL
    SELECT voucher_no AS record_no
    FROM sale_issue_return
    WHERE DATE(created_at) = CURDATE()
      AND record_date >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
      AND record_date < DATE(created_at)
    UNION ALL
    SELECT RECORD_ID AS record_no
    FROM opening_balance
    WHERE DATE(RECORD_DATE) = CURDATE()
      AND BALANCE_DATE >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
      AND BALANCE_DATE < DATE(RECORD_DATE)
) audit_today
";
$backdatedSummary = mysqli_fetch_assoc(mysqli_query($conn, $backdatedSummarySql)) ?: [];
$totalBackdatedToday = (int) ($backdatedSummary['total_backdated_today'] ?? 0);

$backdatedRows = [];
$backdatedResult = mysqli_query($conn, "
    SELECT *
    FROM (
        SELECT
            'Payment Voucher' AS source_name,
            CONCAT(voucher_type, '-', voucher_no) AS document_no,
            account_no,
            voucher_date AS document_date,
            DATE(created_at) AS created_date,
            DATEDIFF(DATE(created_at), voucher_date) AS back_days
        FROM payment_voucher
        WHERE DATE(created_at) = CURDATE()
          AND voucher_date >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
          AND voucher_date < DATE(created_at)

        UNION ALL

        SELECT
            'Receipt Voucher' AS source_name,
            CONCAT(voucher_type, '-', voucher_no) AS document_no,
            account_no,
            voucher_date AS document_date,
            DATE(created_at) AS created_date,
            DATEDIFF(DATE(created_at), voucher_date) AS back_days
        FROM receipt_voucher
        WHERE DATE(created_at) = CURDATE()
          AND voucher_date >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
          AND voucher_date < DATE(created_at)

        UNION ALL

        SELECT
            'General Voucher' AS source_name,
            CONCAT(VOUCHER_TYPE, '-', VOUCHER_NO) AS document_no,
            NULL AS account_no,
            VOUCHER_DATE AS document_date,
            DATE(created_at) AS created_date,
            DATEDIFF(DATE(created_at), VOUCHER_DATE) AS back_days
        FROM online_general_voucher
        WHERE DATE(created_at) = CURDATE()
          AND VOUCHER_DATE >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
          AND VOUCHER_DATE < DATE(created_at)

        UNION ALL

        SELECT
            'Stock Adjustment' AS source_name,
            CONCAT('SA-', adjustment_no) AS document_no,
            NULL AS account_no,
            adjustment_date AS document_date,
            DATE(created_at) AS created_date,
            DATEDIFF(DATE(created_at), adjustment_date) AS back_days
        FROM stock_adjustment_master
        WHERE DATE(created_at) = CURDATE()
          AND adjustment_date >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
          AND adjustment_date < DATE(created_at)

        UNION ALL

        SELECT
            'Purchase Voucher' AS source_name,
            CONCAT('P-', voucher_no) AS document_no,
            account_no,
            record_date AS document_date,
            DATE(created_at) AS created_date,
            DATEDIFF(DATE(created_at), record_date) AS back_days
        FROM purchase_issue_return
        WHERE DATE(created_at) = CURDATE()
          AND record_date >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
          AND record_date < DATE(created_at)

        UNION ALL

        SELECT
            'Sale Voucher' AS source_name,
            CONCAT('S-', voucher_no) AS document_no,
            account_no,
            record_date AS document_date,
            DATE(created_at) AS created_date,
            DATEDIFF(DATE(created_at), record_date) AS back_days
        FROM sale_issue_return
        WHERE DATE(created_at) = CURDATE()
          AND record_date >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
          AND record_date < DATE(created_at)

        UNION ALL

        SELECT
            'Opening Balance' AS source_name,
            CONCAT('OB-', RECORD_ID) AS document_no,
            ACCOUNT_NO AS account_no,
            BALANCE_DATE AS document_date,
            DATE(RECORD_DATE) AS created_date,
            DATEDIFF(DATE(RECORD_DATE), BALANCE_DATE) AS back_days
        FROM opening_balance
        WHERE DATE(RECORD_DATE) = CURDATE()
          AND BALANCE_DATE >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
          AND BALANCE_DATE < DATE(RECORD_DATE)
    ) recent_backdated
    ORDER BY back_days DESC, document_date ASC
    LIMIT 12
");
if ($backdatedResult) {
    while ($row = mysqli_fetch_assoc($backdatedResult)) {
        $backdatedRows[] = $row;
    }
}

$passiveSummary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*) AS entry_count,
        COUNT(DISTINCT l.account_no) AS account_count,
        IFNULL(SUM(l.debit), 0) AS total_debit,
        IFNULL(SUM(l.credit), 0) AS total_credit
    FROM ind_ledgre l
    INNER JOIN chart_of_account c
        ON c.ACCOUNT_NO = l.account_no
    WHERE c.ACC_STATUS = 'PASSIVE'
      AND l.record_date >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
")) ?: [];
$passiveEntryCount = (int) ($passiveSummary['entry_count'] ?? 0);
$passiveAccountCount = (int) ($passiveSummary['account_count'] ?? 0);
$passiveNetMovement = (float) ($passiveSummary['total_debit'] ?? 0) - (float) ($passiveSummary['total_credit'] ?? 0);

$passiveRows = [];
$passiveResult = mysqli_query($conn, "
    SELECT
        l.account_no,
        c.ACCOUNT_DESCRIPTION,
        COUNT(*) AS txn_count,
        MAX(l.record_date) AS last_entry_date,
        IFNULL(SUM(l.debit), 0) AS total_debit,
        IFNULL(SUM(l.credit), 0) AS total_credit
    FROM ind_ledgre l
    INNER JOIN chart_of_account c
        ON c.ACCOUNT_NO = l.account_no
    WHERE c.ACC_STATUS = 'PASSIVE'
      AND l.record_date >= DATE_SUB(CURDATE(), INTERVAL 21 DAY)
    GROUP BY l.account_no, c.ACCOUNT_DESCRIPTION
    ORDER BY last_entry_date DESC, txn_count DESC
    LIMIT 10
");
if ($passiveResult) {
    while ($row = mysqli_fetch_assoc($passiveResult)) {
        $passiveRows[] = $row;
    }
}

$deletedSummary = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_deleted,
        SUM(CASE WHEN DATE(deleted_at) = CURDATE() THEN 1 ELSE 0 END) AS deleted_today
    FROM audit_delete_log
")) ?: [];
$totalDeleted = (int) ($deletedSummary['total_deleted'] ?? 0);
$deletedToday = (int) ($deletedSummary['deleted_today'] ?? 0);

$deletedBreakdownRows = [];
$deletedBreakdownResult = mysqli_query($conn, "
    SELECT
        entity_type,
        COUNT(*) AS total_count,
        SUM(CASE WHEN DATE(deleted_at) = CURDATE() THEN 1 ELSE 0 END) AS today_count,
        MAX(deleted_at) AS last_deleted_at
    FROM audit_delete_log
    GROUP BY entity_type
    ORDER BY total_count DESC, entity_type ASC
");
if ($deletedBreakdownResult) {
    while ($row = mysqli_fetch_assoc($deletedBreakdownResult)) {
        $deletedBreakdownRows[] = $row;
    }
}
?>

<link href="<?php echo $base_url; ?>assets/css/select2.min.css" rel="stylesheet">
<script src="<?php echo $base_url; ?>assets/js/select2.min.js"></script>

<style>
.ai-dashboard-page {
    position: relative;
}

.ai-dashboard-page::before {
    content: "";
    position: absolute;
    inset: 0 0 auto 0;
    height: 300px;
    background:
        radial-gradient(circle at top left, rgba(13,110,253,0.16), transparent 38%),
        radial-gradient(circle at top right, rgba(25,135,84,0.12), transparent 34%),
        linear-gradient(180deg, rgba(255,255,255,0.92), rgba(244,246,249,0));
    pointer-events: none;
}

.ai-hero {
    position: relative;
    z-index: 1;
    background: linear-gradient(135deg, #081f5c 0%, #0d6efd 54%, #20c997 100%);
    color: #fff;
    border-radius: 26px;
    padding: 28px;
    margin-bottom: 24px;
    overflow: hidden;
    box-shadow: 0 24px 58px rgba(8,31,92,0.18);
}

.ai-hero::after {
    content: "";
    position: absolute;
    right: -70px;
    top: -80px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: rgba(255,255,255,0.08);
}

.ai-hero .eyebrow {
    display: inline-block;
    text-transform: uppercase;
    letter-spacing: 1.4px;
    font-size: 12px;
    font-weight: 700;
    color: rgba(255,255,255,0.78);
    margin-bottom: 10px;
}

.ai-hero h3 {
    margin: 0 0 8px;
    font-size: 2rem;
    font-weight: 800;
}

.ai-hero p {
    margin: 0 0 20px;
    max-width: 720px;
    color: rgba(255,255,255,0.88);
}

.ai-search-card {
    position: relative;
    z-index: 1;
    background: rgba(255,255,255,0.14);
    border: 1px solid rgba(255,255,255,0.14);
    border-radius: 18px;
    padding: 16px;
    backdrop-filter: blur(8px);
}

.ai-search-card .form-label {
    color: rgba(255,255,255,0.86);
    font-weight: 700;
}

.ai-search-card .select2-container--default .select2-selection--single {
    height: 44px;
    border: 0;
    border-radius: 12px;
    padding: 8px 12px;
}

.ai-search-card .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 28px;
}

.ai-search-card .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 44px;
}

.ai-stat-card,
.ai-panel,
.ai-chart-card {
    position: relative;
    z-index: 1;
    border: 0;
    border-radius: 20px;
    box-shadow: 0 16px 38px rgba(15,23,42,0.08);
    overflow: hidden;
}

.ai-stat-card .card-body {
    padding: 22px;
}

.ai-stat-label {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
    color: rgba(255,255,255,0.76);
    margin-bottom: 10px;
}

.ai-stat-card h4 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 800;
    color: #fff;
}

.ai-panel .card-body,
.ai-chart-card .card-body {
    padding: 22px;
}

.ai-panel h5,
.ai-chart-card h5 {
    margin-bottom: 10px;
    font-weight: 800;
}

.ai-audit-card {
    background: linear-gradient(160deg, #ffffff, #f8fbff);
}

.ai-table-wrap {
    max-height: 360px;
    overflow: auto;
}

.ai-table-wrap table {
    margin-bottom: 0;
}

.audit-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 999px;
    background: rgba(255,255,255,0.16);
    font-size: 0.9rem;
    font-weight: 600;
    color: rgba(255,255,255,0.92);
}

.ai-insight-copy {
    color: #475569;
    margin: 0;
    line-height: 1.6;
}

.ai-risk-banner {
    border-radius: 16px;
    border: 0;
    box-shadow: 0 10px 28px rgba(15,23,42,0.08);
}

.ai-risk-banner strong {
    letter-spacing: 0.2px;
}

.chart-canvas-wrap {
    position: relative;
    min-height: 320px;
}

.chart-canvas-wrap.chart-tall {
    min-height: 380px;
}

.chart-canvas-wrap canvas {
    position: absolute;
    inset: 0;
    width: 100% !important;
    height: 100% !important;
}

@media (max-width: 767.98px) {
    .ai-hero {
        border-radius: 20px;
        padding: 22px 18px;
    }

    .ai-hero h3 {
        font-size: 1.5rem;
    }

    .chart-canvas-wrap,
    .chart-canvas-wrap.chart-tall {
        min-height: 280px;
    }
}
</style>

<div class="container-fluid ai-dashboard-page mt-4">
    <div class="ai-hero">
        <div class="eyebrow">AI Analysis</div>
        <h3>AI Account Intelligence Dashboard</h3>
        <p>Analyze transaction density, debit and credit movement, risk signals, and running balance behavior for any account in the system.</p>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <div class="audit-chip">Audit Date: <?php echo htmlspecialchars($today); ?></div>
            <div class="audit-chip">Back-Date Window: <?php echo htmlspecialchars($auditWindowStart); ?> to <?php echo htmlspecialchars($today); ?></div>
        </div>

        <?php if (isset($_GET['cleared']) && $_GET['cleared'] == '1'): ?>
        <div class="alert alert-warning ai-risk-banner">Deleted log history was archived to a text file and then cleared.</div>
        <?php elseif (isset($_GET['empty']) && $_GET['empty'] == '1'): ?>
        <div class="alert alert-info ai-risk-banner">No deleted log records were available to clear.</div>
        <?php elseif (isset($_GET['denied']) && $_GET['denied'] == '1'): ?>
        <div class="alert alert-danger ai-risk-banner">Only admin users can clear deleted log history.</div>
        <?php endif; ?>

        <div class="ai-search-card">
            <form method="GET">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-8 col-md-7">
                        <label class="form-label">Account</label>
                        <select name="account_no" class="form-control select2" required>
                            <option value="">Search & Select Account</option>
                            <?php
                            $acc_query = mysqli_query($conn, "SELECT ACCOUNT_NO, ACCOUNT_DESCRIPTION FROM all_chart_of_accounts ORDER BY ACCOUNT_NO");
                            while ($acc = mysqli_fetch_assoc($acc_query)) {
                                $selected = ($account_no == $acc['ACCOUNT_NO']) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($acc['ACCOUNT_NO'], ENT_QUOTES) . "' {$selected}>"
                                    . htmlspecialchars($acc['ACCOUNT_NO']) . " - " . htmlspecialchars($acc['ACCOUNT_DESCRIPTION']) .
                                    "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-lg-2 col-md-3">
                        <button class="btn btn-light w-100 fw-bold">Analyze</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card ai-stat-card bg-warning text-dark">
                <div class="card-body">
                    <div class="ai-stat-label" style="color: rgba(15,23,42,0.72);">Today Back-Dated Entries</div>
                    <h4 class="text-dark"><?php echo number_format($totalBackdatedToday); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card ai-stat-card bg-info text-dark">
                <div class="card-body">
                    <div class="ai-stat-label" style="color: rgba(15,23,42,0.72);">Passive Account Entries</div>
                    <h4 class="text-dark"><?php echo number_format($passiveEntryCount); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card ai-stat-card bg-secondary text-white">
                <div class="card-body">
                    <div class="ai-stat-label">Total Deleted Records</div>
                    <h4><?php echo number_format($totalDeleted); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card ai-stat-card bg-dark text-white">
                <div class="card-body">
                    <div class="ai-stat-label">Deleted Today</div>
                    <h4><?php echo number_format($deletedToday); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card ai-panel ai-audit-card">
                <div class="card-body">
                    <h5>Audit Watch: Today Back-Dated Entries</h5>
                    <p class="ai-insight-copy mb-3">
                        This section shows records entered on <strong><?php echo htmlspecialchars($today); ?></strong> where the document date was back-dated within the previous three weeks.
                    </p>
                    <div class="ai-table-wrap">
                        <table class="table table-sm table-striped align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Source</th>
                                    <th>Document</th>
                                    <th>Account</th>
                                    <th>Doc Date</th>
                                    <th>Entry Date</th>
                                    <th>Back Days</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($backdatedRows)) { ?>
                                <?php foreach ($backdatedRows as $row) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['source_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['document_no']); ?></td>
                                        <td><?php echo htmlspecialchars($row['account_no'] ?: '-'); ?></td>
                                        <td><?php echo htmlspecialchars($row['document_date']); ?></td>
                                        <td><?php echo htmlspecialchars($row['created_date']); ?></td>
                                        <td class="fw-bold text-warning"><?php echo (int) $row['back_days']; ?></td>
                                    </tr>
                                <?php } ?>
                            <?php } else { ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No back-dated entries were created today inside the last three-week window.</td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card ai-panel ai-audit-card h-100">
                <div class="card-body">
                    <h5>Passive Account Activity</h5>
                    <div class="text-muted small mb-2">Window</div>
                    <div class="fw-bold mb-3"><?php echo htmlspecialchars($auditWindowStart); ?> to <?php echo htmlspecialchars($today); ?></div>
                    <div class="text-muted small mb-2">Passive Accounts Hit</div>
                    <div class="fw-bold mb-3"><?php echo number_format($passiveAccountCount); ?></div>
                    <div class="text-muted small mb-2">Entry Count</div>
                    <div class="fw-bold mb-3"><?php echo number_format($passiveEntryCount); ?></div>
                    <div class="text-muted small mb-2">Net Movement</div>
                    <div class="fw-bold"><?php echo number_format(abs($passiveNetMovement), 2); ?> <?php echo $passiveNetMovement >= 0 ? 'Debit' : 'Credit'; ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card ai-panel ai-audit-card">
                <div class="card-body">
                    <h5>Recent Passive Account Entries</h5>
                    <div class="ai-table-wrap">
                        <table class="table table-sm table-striped align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Account</th>
                                    <th>Description</th>
                                    <th>Entries</th>
                                    <th>Last Date</th>
                                    <th>Net</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($passiveRows)) { ?>
                                <?php foreach ($passiveRows as $row) { ?>
                                    <?php $net = (float) $row['total_debit'] - (float) $row['total_credit']; ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['account_no']); ?></td>
                                        <td><?php echo htmlspecialchars($row['ACCOUNT_DESCRIPTION']); ?></td>
                                        <td><?php echo number_format((int) $row['txn_count']); ?></td>
                                        <td><?php echo htmlspecialchars($row['last_entry_date']); ?></td>
                                        <td class="fw-bold"><?php echo number_format(abs($net), 2); ?> <?php echo $net >= 0 ? 'D' : 'C'; ?></td>
                                    </tr>
                                <?php } ?>
                            <?php } else { ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No passive account movement found in the current audit window.</td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card ai-panel ai-audit-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
                        <h5 class="mb-0">Deleted Records Breakdown</h5>
                        <?php if (rbac_is_admin()): ?>
                        <form method="post" onsubmit="return confirm('Archive and clear all deleted log records now?');">
                            <button type="submit" name="clear_deleted_logs" class="btn btn-danger btn-sm">Clear Deleted Logs</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <p class="ai-insight-copy mb-3">Delete history starts from this audit update. Older hard-deleted records cannot be counted from past data.</p>
                    <div class="ai-table-wrap">
                        <table class="table table-sm table-striped align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Total</th>
                                    <th>Today</th>
                                    <th>Last Delete</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!empty($deletedBreakdownRows)) { ?>
                                <?php foreach ($deletedBreakdownRows as $row) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(str_replace('_', ' ', ucwords($row['entity_type'], '_'))); ?></td>
                                        <td><?php echo number_format((int) $row['total_count']); ?></td>
                                        <td><?php echo number_format((int) $row['today_count']); ?></td>
                                        <td><?php echo htmlspecialchars($row['last_deleted_at']); ?></td>
                                    </tr>
                                <?php } ?>
                            <?php } else { ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">No delete history has been tracked yet.</td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php
if ($account_no != '') {
    $account_no = mysqli_real_escape_string($conn, $account_no);

    $summary_sql = "
    SELECT 
        a.ACCOUNT_DESCRIPTION,
        COUNT(l.account_no) as total_transactions,
        SUM(l.debit) as total_debit,
        SUM(l.credit) as total_credit,
        MAX(l.debit) as highest_debit,
        MAX(l.credit) as highest_credit,
        MIN(l.record_date) as first_date,
        MAX(l.record_date) as last_date
    FROM ind_ledgre l
    LEFT JOIN all_chart_of_accounts a 
        ON a.ACCOUNT_NO = l.account_no
    WHERE l.account_no = '$account_no'
    GROUP BY l.account_no
    ";

    $summary_result = mysqli_query($conn, $summary_sql);
    $data = mysqli_fetch_assoc($summary_result);

    if ($data) {
        $total_debit  = $data['total_debit'] ?? 0;
        $total_credit = $data['total_credit'] ?? 0;
        $closing_balance = $total_debit - $total_credit;
        $balance_type = $closing_balance >= 0 ? "Debit" : "Credit";

        if ($data['total_transactions'] > 100) {
            $activity = "High";
        } elseif ($data['total_transactions'] > 30) {
            $activity = "Medium";
        } else {
            $activity = "Low";
        }

        $risk_message = "";
        $risk_type = "success";

        if ($closing_balance < 0) {
            $risk_message = "Account is in negative position.";
            $risk_type = "danger";
        } elseif ($data['highest_debit'] > 1000000 || $data['highest_credit'] > 1000000) {
            $risk_message = "Large transaction spike detected.";
            $risk_type = "warning";
        } elseif ($data['total_transactions'] > 200) {
            $risk_message = "Extremely high transaction activity.";
            $risk_type = "warning";
        } else {
            $risk_message = "Account appears financially stable.";
        }

        $monthly_sql = "
        SELECT DATE_FORMAT(record_date,'%Y-%m') as month,
               SUM(debit) as debit,
               SUM(credit) as credit
        FROM ind_ledgre
        WHERE account_no = '$account_no'
        GROUP BY month
        ORDER BY month
        ";
        $monthly_result = mysqli_query($conn, $monthly_sql);

        $months = [];
        $debits = [];
        $credits = [];

        while ($row = mysqli_fetch_assoc($monthly_result)) {
            $months[] = $row['month'];
            $debits[] = $row['debit'] ?? 0;
            $credits[] = $row['credit'] ?? 0;
        }

        $balance_sql = "
        SELECT record_date, balance
        FROM ind_ledgre
        WHERE account_no = '$account_no'
        ORDER BY record_date
        ";

        $balance_result = mysqli_query($conn, $balance_sql);

        $balance_dates = [];
        $balances = [];

        while ($row = mysqli_fetch_assoc($balance_result)) {
            $balance_dates[] = $row['record_date'];
            $balances[] = $row['balance'];
        }
?>

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card ai-stat-card bg-primary text-white">
                <div class="card-body">
                    <div class="ai-stat-label">Total Transactions</div>
                    <h4><?php echo number_format((float) $data['total_transactions']); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card ai-stat-card bg-success text-white">
                <div class="card-body">
                    <div class="ai-stat-label">Total Debit</div>
                    <h4><?php echo number_format((float) $total_debit, 2); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card ai-stat-card bg-danger text-white">
                <div class="card-body">
                    <div class="ai-stat-label">Total Credit</div>
                    <h4><?php echo number_format((float) $total_credit, 2); ?></h4>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card ai-stat-card bg-dark text-white">
                <div class="card-body">
                    <div class="ai-stat-label">Closing Balance</div>
                    <h4><?php echo number_format(abs((float) $closing_balance), 2); ?> <small>(<?php echo htmlspecialchars($balance_type); ?>)</small></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card ai-panel">
                <div class="card-body">
                    <h5>AI Insight</h5>
                    <p class="ai-insight-copy">
                        Account <strong><?php echo htmlspecialchars($account_no); ?></strong>
                        (<?php echo htmlspecialchars($data['ACCOUNT_DESCRIPTION']); ?>)
                        shows <strong><?php echo htmlspecialchars($activity); ?></strong> activity between
                        <strong><?php echo htmlspecialchars($data['first_date']); ?></strong> and
                        <strong><?php echo htmlspecialchars($data['last_date']); ?></strong>.
                        The account closed at
                        <strong><?php echo number_format(abs((float) $closing_balance), 2); ?> <?php echo htmlspecialchars($balance_type); ?></strong>,
                        with peak single-side movement of
                        <strong><?php echo number_format(max((float) $data['highest_debit'], (float) $data['highest_credit']), 2); ?></strong>.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card ai-panel">
                <div class="card-body">
                    <h5>Account Snapshot</h5>
                    <div class="text-muted small mb-2">Account No</div>
                    <div class="fw-bold mb-3"><?php echo htmlspecialchars($account_no); ?></div>
                    <div class="text-muted small mb-2">Description</div>
                    <div class="fw-bold mb-3"><?php echo htmlspecialchars($data['ACCOUNT_DESCRIPTION']); ?></div>
                    <div class="text-muted small mb-2">Activity Level</div>
                    <div class="fw-bold"><?php echo htmlspecialchars($activity); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-<?php echo htmlspecialchars($risk_type); ?> ai-risk-banner mb-4">
        <strong>Risk Analysis:</strong> <?php echo htmlspecialchars($risk_message); ?>
    </div>

    <script src="<?php echo $base_url; ?>assets/js/chart.js"></script>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card ai-chart-card">
                <div class="card-body">
                    <h5>Debit Vs Credit</h5>
                    <div class="chart-canvas-wrap">
                        <canvas id="debitCreditChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card ai-chart-card">
                <div class="card-body">
                    <h5>Monthly Trend</h5>
                    <div class="chart-canvas-wrap">
                        <canvas id="monthlyTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card ai-chart-card mb-4">
        <div class="card-body">
            <h5>Running Balance</h5>
            <div class="chart-canvas-wrap chart-tall">
                <canvas id="runningBalanceChart"></canvas>
            </div>
        </div>
    </div>

<script>
$(document).ready(function() {
    $('.select2').select2({
        placeholder: 'Search account...',
        allowClear: true,
        width: '100%'
    });
});

new Chart(document.getElementById('debitCreditChart'), {
    type: 'bar',
    data: {
        labels: ['Debit','Credit'],
        datasets: [{
            label: 'Amount',
            data: [<?php echo (float) $total_debit; ?>, <?php echo (float) $total_credit; ?>],
            backgroundColor: ['#198754','#dc3545'],
            borderRadius: 12
        }]
    },
    options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
});

new Chart(document.getElementById('monthlyTrendChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [{
            label: 'Debit',
            data: <?php echo json_encode($debits); ?>,
            borderColor: '#198754',
            backgroundColor: 'rgba(25,135,84,0.08)',
            fill: true,
            tension: 0.35
        },
        {
            label: 'Credit',
            data: <?php echo json_encode($credits); ?>,
            borderColor: '#dc3545',
            backgroundColor: 'rgba(220,53,69,0.06)',
            fill: true,
            tension: 0.35
        }]
    },
    options: {
        maintainAspectRatio: false
    }
});

new Chart(document.getElementById('runningBalanceChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($balance_dates); ?>,
        datasets: [{
            label: 'Running Balance',
            data: <?php echo json_encode($balances); ?>,
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13,110,253,0.10)',
            fill: true,
            tension: 0.22,
            pointRadius: 0,
            pointHoverRadius: 4
        }]
    },
    options: {
        maintainAspectRatio: false
    }
});
</script>

<?php
    } else {
        echo "<div class='alert alert-danger ai-risk-banner'>Account not found.</div>";
    }
} else {
?>
    <script>
    $(document).ready(function() {
        $('.select2').select2({
            placeholder: 'Search account...',
            allowClear: true,
            width: '100%'
        });
    });
    </script>
<?php } ?>
</div>

<?php include "../../includes/footer.php"; ?>
