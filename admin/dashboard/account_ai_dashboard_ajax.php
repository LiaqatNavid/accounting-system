<?php
include "../../config/db.php";

$account_no = $_POST['account_no'] ?? '';

if ($account_no == '') {
    echo "<div class='alert alert-danger'>Invalid account.</div>";
    exit;
}

$account_no = mysqli_real_escape_string($conn, $account_no);

// ================= SUMMARY =================
$summary_sql = "
SELECT 
    a.ACCOUNT_DESCRIPTION,
    COUNT(l.account_no) as total_transactions,
    SUM(l.debit) as total_debit,
    SUM(l.credit) as total_credit,
    MIN(l.record_date) as first_date,
    MAX(l.record_date) as last_date
FROM ind_ledgre l
LEFT JOIN all_chart_of_accounts a 
    ON a.ACCOUNT_NO = l.account_no
WHERE l.account_no = '$account_no'
GROUP BY l.account_no
";

$result = mysqli_query($conn, $summary_sql);
$data = mysqli_fetch_assoc($result);

if (!$data) {
    echo "<div class='alert alert-danger'>Account not found.</div>";
    exit;
}

$total_debit  = $data['total_debit'] ?? 0;
$total_credit = $data['total_credit'] ?? 0;
$closing_balance = $total_debit - $total_credit;
$balance_type = $closing_balance >= 0 ? "Debit" : "Credit";

// Activity
if ($data['total_transactions'] > 100) {
    $activity = "High";
} elseif ($data['total_transactions'] > 30) {
    $activity = "Medium";
} else {
    $activity = "Low";
}

// Risk Logic
$risk_message = "";
$risk_type = "success";

if ($closing_balance < 0) {
    $risk_message = "Account is in negative position.";
    $risk_type = "danger";
}
elseif ($data['total_transactions'] > 200) {
    $risk_message = "Extremely high transaction activity.";
    $risk_type = "warning";
}
else {
    $risk_message = "Account appears financially stable.";
}
?>

<!-- SUMMARY CARDS -->
<div class="row text-center mb-4">
    <div class="col-md-3">
        <div class="card shadow p-3">
            <h6>Total Debit</h6>
            <h4><?php echo number_format($total_debit,2); ?></h4>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow p-3">
            <h6>Total Credit</h6>
            <h4><?php echo number_format($total_credit,2); ?></h4>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow p-3">
            <h6>Closing Balance</h6>
            <h4><?php echo number_format(abs($closing_balance),2); ?> (<?php echo $balance_type; ?>)</h4>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow p-3">
            <h6>Activity</h6>
            <h4><?php echo $activity; ?></h4>
        </div>
    </div>
</div>

<!-- RISK ALERT -->
<div class="alert alert-<?php echo $risk_type; ?>">
    <strong>Risk Analysis:</strong> <?php echo $risk_message; ?>
</div>

<!-- LEDGER WITH LIVE RUNNING BALANCE -->
<div class="card shadow p-4 mt-4">
    <h5>Ledger Detail (Live Running Balance)</h5>

    <div class="table-responsive">
        <table class="table table-bordered table-sm">
            <thead class="table-dark">
                <tr>
                    <th>Date</th>
                    <th>Voucher</th>
                    <th>Debit</th>
                    <th>Credit</th>
                    <th>Running Balance</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>

<?php
$ledger_sql = "
SELECT record_date, voucher_print, debit, credit, remarks
FROM ind_ledgre
WHERE account_no = '$account_no'
ORDER BY record_date
";

$ledger_result = mysqli_query($conn, $ledger_sql);

$running_balance = 0;

while ($row = mysqli_fetch_assoc($ledger_result)) {

    $running_balance += ($row['debit'] - $row['credit']);
?>
<tr>
    <td><?php echo $row['record_date']; ?></td>
    <td><?php echo $row['voucher_print']; ?></td>
    <td><?php echo number_format($row['debit'],2); ?></td>
    <td><?php echo number_format($row['credit'],2); ?></td>
    <td><strong><?php echo number_format($running_balance,2); ?></strong></td>
    <td><?php echo $row['remarks']; ?></td>
</tr>
<?php } ?>

            </tbody>
        </table>
    </div>
</div>