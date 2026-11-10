<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";
$base_url = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/accounting-system/') !== false) ? '/accounting-system/' : '/';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$widget = null;
$result = false;
$error = null;

if ($id > 0) {
    $widget = mysqli_fetch_assoc(
        mysqli_query($conn, "SELECT * FROM dashboard_widgets WHERE id = $id")
    );

    if ($widget && !empty($widget['final_query'])) {
        $result = mysqli_query($conn, $widget['final_query']);
        if (!$result) {
            $error = mysqli_error($conn);
        }
    } else {
        $error = "Widget not found.";
    }
} else {
    $error = "Invalid widget request.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Widget Detail</title>
<link href="<?php echo $base_url; ?>assets/css/bootstrap.min.css" rel="stylesheet">
<link href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css" rel="stylesheet">
<script src="<?php echo $base_url; ?>assets/js/jquery-3.7.1.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/jquery.dataTables.min.js"></script>
</head>
<body style="background:#fff;">

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><?php echo htmlspecialchars($widget['widget_name'] ?? 'Widget Detail'); ?></h5>
        <button type="button" class="btn btn-secondary btn-sm" onclick="window.close()">Close</button>
    </div>

    <?php if ($error) { ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php } elseif ($result) { ?>
        <div class="table-responsive">
            <table id="dynamicTable" class="table table-bordered table-striped w-100">
                <thead>
                    <tr>
                    <?php foreach (mysqli_fetch_fields($result) as $field) { ?>
                        <th><?php echo htmlspecialchars($field->name); ?></th>
                    <?php } ?>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)) { ?>
                    <tr>
                    <?php foreach ($row as $value) { ?>
                        <td><?php echo htmlspecialchars((string) $value); ?></td>
                    <?php } ?>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</div>

<script>
$(document).ready(function () {
    if ($('#dynamicTable').length) {
        $('#dynamicTable').DataTable({
            pageLength: 25,
            pagingType: 'simple_numbers',
            order: []
        });
    }
});
</script>

</body>
</html>
