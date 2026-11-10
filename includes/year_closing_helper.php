<?php

require_once __DIR__ . "/financial_year_helper.php";
require_once __DIR__ . "/period_lock_helper.php";
require_once __DIR__ . "/audit_helper.php";

function carry_forward_opening_balances(mysqli $conn, string $targetDate): array
{
    $safeTargetDate = mysqli_real_escape_string($conn, $targetDate);

    mysqli_query($conn, "DELETE FROM OPENING_BALANCE WHERE BALANCE_DATE = '$safeTargetDate'");

    $balances = mysqli_query($conn, "
        SELECT account_no,
               SUM(debit) AS total_debit,
               SUM(credit) AS total_credit
        FROM ind_ledgre_running_balance
        WHERE DATE(record_date) < '$safeTargetDate'
        GROUP BY account_no
        HAVING ABS(SUM(debit) - SUM(credit)) > 0.0001
    ");

    $rows = 0;
    $totalDebit = 0.0;
    $totalCredit = 0.0;

    while ($row = mysqli_fetch_assoc($balances)) {
        $accountNo = mysqli_real_escape_string($conn, $row['account_no']);
        $debit = (float) $row['total_debit'];
        $credit = (float) $row['total_credit'];

        mysqli_query($conn, "
            INSERT INTO OPENING_BALANCE (ACCOUNT_NO, DEBIT, CREDIT, BALANCE_DATE, RECORD_DATE)
            VALUES ('$accountNo', '$debit', '$credit', '$safeTargetDate', NOW())
        ");

        $rows++;
        $totalDebit += $debit;
        $totalCredit += $credit;
    }

    return [
        'accounts' => $rows,
        'total_debit' => $totalDebit,
        'total_credit' => $totalCredit,
        'difference' => $totalDebit - $totalCredit,
        'target_date' => $targetDate,
    ];
}
