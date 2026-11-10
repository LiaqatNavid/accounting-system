<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
include "../../includes/header.php";

$id = intval($_GET['id']);

$master = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT * FROM stock_adjustment_master WHERE id='$id'
"));

$details = mysqli_query($conn,"
SELECT d.*, i.item_name,i.item_code
FROM stock_adjustment_detail d
JOIN inventory i ON d.item_id=i.id
WHERE d.adjustment_id='$id'
");
?>

<div class="container mt-4">
<h4>Adjustment Detail</h4>

<p><b>No:</b> <?= $master['adjustment_no'] ?></p>
<p><b>Date:</b> <?= $master['adjustment_date'] ?></p>
<p><b>Type:</b> <?= $master['adjustment_type'] ?></p>
<p><b>Remarks:</b> <?= $master['remarks'] ?></p>

<table class="table table-bordered">
<thead>
<tr>
<th>Item</th>
<th>Current</th>
<th>Adjusted</th>
<th>New</th>
</tr>
</thead>
<tbody>
<?php while($row=mysqli_fetch_assoc($details)){ ?>
<tr>
<td><?= $row['item_code']." - ".$row['item_name'] ?></td>
<td><?= $row['current_stock'] ?></td>
<td><?= $row['adjusted_qty'] ?></td>
<td><?= $row['new_stock'] ?></td>
</tr>
<?php } ?>
</tbody>
</table>

<a href="stock_adjustment_list.php" class="btn btn-dark">Back</a>
</div>

<?php include "../../includes/footer.php"; ?>
