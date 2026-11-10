<?php
include "../../config/db.php";

// Drop old table
mysqli_query($conn, "DROP TABLE IF EXISTS ind_ledgre_running_balance_tbl");

// Create new table from view
mysqli_query($conn, "
    CREATE TABLE ind_ledgre_running_balance_tbl AS
    SELECT * FROM ind_ledgre_running_balance
");

header("Location: account_ledger.php");
exit;
?>