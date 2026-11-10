<?php
if (!function_exists('get_account_lookup_rows')) {
    function get_account_lookup_rows($conn) {
        $queries = [
            "
            SELECT
                a.ACCOUNT_NO AS account_no,
                a.ACCOUNT_DESCRIPTION AS account_description,
                COALESCE((
                    SELECT r.running_balance
                    FROM ind_ledgre_running_balance r
                    WHERE r.account_no = a.ACCOUNT_NO
                    ORDER BY DATE(r.record_date) DESC, r.voucher_print DESC
                    LIMIT 1
                ), 0) AS last_balance
            FROM all_chart_of_accounts a
            ORDER BY a.ACCOUNT_NO
            ",
            "
            SELECT
                c.account_no AS account_no,
                c.account_description AS account_description,
                0 AS last_balance
            FROM chart_of_account c
            ORDER BY c.account_no
            "
        ];

        foreach ($queries as $sql) {
            $result = mysqli_query($conn, $sql);
            if ($result) {
                $rows = [];
                while ($row = mysqli_fetch_assoc($result)) {
                    $balance = (float) ($row['last_balance'] ?? 0);
                    $row['last_balance'] = $balance;
                    $row['balance_label'] = $balance > 0 ? 'Payable' : ($balance < 0 ? 'Receivable' : 'Balanced');
                    $rows[] = $row;
                }
                return $rows;
            }
        }

        return [];
    }
}
