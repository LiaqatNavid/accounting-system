<?php
session_start();
include "../../includes/auth_check.php";
include "../../config/db.php";

// Add Category
if (isset($_POST['add_category'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['category_name']));
    if ($name !== '') {
        $insert = "INSERT INTO category (category_name) VALUES ('{$name}')";
        if (mysqli_query($conn, $insert)) {
            $message = "<div class='alert alert-success'>Category added.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . mysqli_error($conn) . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Please enter a category name.</div>";
    }
}

// Update Category
if (isset($_POST['update_category'])) {
    $old = mysqli_real_escape_string($conn, trim($_POST['old_name']));
    $new = mysqli_real_escape_string($conn, trim($_POST['category_name']));
    if ($new !== '') {
        $update = "UPDATE category SET category_name='{$new}' WHERE category_name='{$old}'";
        if (mysqli_query($conn, $update)) {
            $message = "<div class='alert alert-success'>Category updated.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . mysqli_error($conn) . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Please enter a category name.</div>";
    }
}

// Delete
if (isset($_GET['delete'])) {
    $del = mysqli_real_escape_string($conn, $_GET['delete']);
    if (mysqli_query($conn, "DELETE FROM category WHERE category_name='{$del}'")) {
        header('Location: category.php');
        exit();
    } else {
        $message = "<div class='alert alert-danger'>Error deleting category.</div>";
    }
}

// Load edit if requested
$edit_name = '';
if (isset($_GET['edit'])) {
    $edit_name = mysqli_real_escape_string($conn, $_GET['edit']);
    $rs = mysqli_query($conn, "SELECT * FROM category WHERE category_name='{$edit_name}' LIMIT 1");
    if ($rs && mysqli_num_rows($rs) > 0) {
        $r = mysqli_fetch_assoc($rs);
        $edit_name = $r['category_name'];
    } else {
        $edit_name = '';
    }
}

// load list
$rows = mysqli_query($conn, "SELECT * FROM category ORDER BY category_name ASC");

include "../../includes/header.php";
?>

<!-- DataTables CSS -->
<link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/jquery.dataTables.min.css">

<style>
.category-page .card {
    border: 0;
    border-radius: 14px;
}

.category-page .card-header {
    border-radius: 14px 14px 0 0;
}
</style>

<div class="container-fluid category-page mt-4">
    <div class="mb-3">
        <h3 class="mb-1">Categories</h3>
        <div class="text-muted">Create, edit, and manage item categories.</div>
    </div>

    <?php if (isset($message)) echo $message; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><?php echo $edit_name ? 'Edit Category' : 'Add Category'; ?></h6>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row g-2">
                    <div class="col-md-8 col-12">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="category_name" class="form-control" required value="<?php echo htmlspecialchars($edit_name); ?>">
                        <?php if ($edit_name): ?>
                            <input type="hidden" name="old_name" value="<?php echo htmlspecialchars($edit_name); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 col-12 align-self-end">
                        <?php if ($edit_name): ?>
                            <button type="submit" name="update_category" class="btn btn-primary w-100">Update</button>
                            <a href="category.php" class="btn btn-secondary w-100 mt-2">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="add_category" class="btn btn-primary w-100">Add</button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0">All Categories</h6>
        </div>
        <div class="card-body p-2">
            <div>
                <table id="categoryTable" class="table table-sm table-striped table-bordered w-100" style="width:100%">
                    <thead class="table-dark">
                        <tr>
                            <th>Category Name</th>
                            <th style="min-width:120px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if ($rows && mysqli_num_rows($rows) > 0) {
                        while ($c = mysqli_fetch_assoc($rows)) {
                            $name = htmlspecialchars($c['category_name']);
                            $param = rawurlencode($c['category_name']);
                            echo '<tr>' .
                                 "<td>{$name}</td>" .
                                 '<td style="white-space:nowrap;">' .
                                     '<a href="?edit=' . $param . '" class="btn btn-sm btn-primary me-1">Edit</a>' .
                                     '&nbsp;' .
                                     '<a href="?delete=' . $param . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Delete this category?\')">Delete</a>' .
                                 '</td>' .
                                 '</tr>';
                        }
                    } else {
                        echo "<tr><td colspan='2' class='text-center text-muted'>No categories found</td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- DataTables JS -->
<script src="<?php echo $base_url; ?>assets/js/jquery-3.7.0.min.js"></script>
<script src="<?php echo $base_url; ?>assets/js/jquery.dataTables.min.js"></script>
<script>
$(document).ready(function(){
    $('#categoryTable').DataTable({
        "pageLength": 15,
        "ordering": true,
        "searching": true,
        "columnDefs": [{"orderable": false, "targets": 1}]
    });
});
</script>

<?php include "../../includes/footer.php"; ?>
