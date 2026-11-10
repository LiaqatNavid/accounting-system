<?php
session_start();
include "../includes/auth_check.php";
include "../config/db.php";

// ADD ROLE LOGIC
if (isset($_POST['add_role'])) {
    $role_name = mysqli_real_escape_string($conn, trim($_POST['role_name']));
    $description = mysqli_real_escape_string($conn, trim($_POST['description']));

    $check = mysqli_query($conn, "SELECT * FROM roles WHERE role_name='$role_name'");

    if (mysqli_num_rows($check) > 0) {
        $message = "<div class='alert alert-danger'>Role already exists!</div>";
    } else {
        mysqli_query($conn, "INSERT INTO roles (role_name, description) VALUES ('$role_name', '$description')");
        $message = "<div class='alert alert-success'>Role Added Successfully!</div>";
    }
}

// DELETE ROLE LOGIC
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM role_permissions WHERE role_id='$id'");
    mysqli_query($conn, "DELETE FROM roles WHERE id='$id'");
    header("Location: roles.php");
    exit();
}

include "../includes/header.php";
?>

<div class="container mt-5">
    <h3 class="mb-4">Role Management</h3>

    <?php if (isset($message)) echo $message; ?>

    <!-- Add Role Form -->
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Add New Role</h5>
        </div>
        <div class="card-body">

            <form method="POST">
                <div class="row">
                    <div class="col-md-4">
                        <input type="text" name="role_name" class="form-control" placeholder="Role Name" required>
                    </div>

                    <div class="col-md-6">
                        <input type="text" name="description" class="form-control" placeholder="Description" required>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" name="add_role" class="btn btn-primary w-100">Add Role</button>
                    </div>
                </div>
            </form>

        </div>
    </div>

    <!-- Roles List -->
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Roles List</h5>
        </div>
        <div class="card-body">

            <table class="table table-striped table-bordered">
                <thead class="table-primary text-white">
                    <tr>
                        <th>ID</th>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>

                    <?php
                    $result = mysqli_query($conn, "SELECT * FROM roles");

                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $id = intval($row['id']);
                            $role_name = htmlspecialchars($row['role_name']);
                            $description = htmlspecialchars($row['description']);
                            echo "<tr>
                                    <td>{$id}</td>
                                    <td>{$role_name}</td>
                                    <td>{$description}</td>
                                    <td>
                                        <a href='edit_role.php?id={$id}' class='btn btn-sm btn-primary'>Edit</a>
                                        <a href='roles.php?delete={$id}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' class='text-center text-muted'>No roles found</td></tr>";
                    }
                    ?>

                </tbody>
            </table>

        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
