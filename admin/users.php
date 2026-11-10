<?php
session_start();
include "../includes/auth_check.php";
include "../config/db.php";

// ADD USER LOGIC
if (isset($_POST['add_user'])) {

    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = mysqli_real_escape_string($conn, trim($_POST['password']));
    $role_id = isset($_POST['role_id']) && $_POST['role_id'] !== '' ? intval($_POST['role_id']) : "NULL";

    $check = mysqli_query($conn, "SELECT * FROM users WHERE username='$username'");

    if (mysqli_num_rows($check) > 0) {
        $message = "<div class='alert alert-danger'>Username already exists!</div>";
    } else {
        mysqli_query($conn, "INSERT INTO users (username, password, role_id) VALUES ('$username', '$password', " . ($role_id === "NULL" ? "NULL" : "'" . $role_id . "'") . ")");
        $message = "<div class='alert alert-success'>User Added Successfully!</div>";
    }
}

// DELETE USER LOGIC
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM users WHERE id='$id'");
    header("Location: users.php");
    exit();
}

include "../includes/header.php";
?>

<div class="container mt-5">
    <h3 class="mb-4">User Management</h3>

    <?php if (isset($message)) echo $message; ?>

    <!-- Add User Form -->
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Add New User</h5>
        </div>
        <div class="card-body">

            <form method="POST">
                <div class="row">
                    <div class="col-md-4">
                        <input type="text" name="username" class="form-control" placeholder="Username" required>
                    </div>

                    <div class="col-md-3">
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                    </div>

                    <div class="col-md-3">
                        <select name="role_id" class="form-select">
                            <option value="">Select Role</option>
                            <?php
                            $roleList = mysqli_query($conn, "SELECT id, role_name FROM roles ORDER BY role_name");
                            while ($role = mysqli_fetch_assoc($roleList)) {
                                echo "<option value='" . (int) $role['id'] . "'>" . htmlspecialchars($role['role_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <button type="submit" name="add_user" class="btn btn-primary w-100">Add User</button>
                    </div>
                </div>
            </form>

        </div>
    </div>

    <!-- User List -->
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Users List</h5>
        </div>
        <div class="card-body">

            <table class="table table-striped table-bordered">
                <thead class="table-primary text-white">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>

                    <?php
                    $result = mysqli_query($conn, "
                        SELECT u.*, r.role_name
                        FROM users u
                        LEFT JOIN roles r ON r.id = u.role_id
                    ");

                    if (mysqli_num_rows($result) > 0) {
                        while ($row = mysqli_fetch_assoc($result)) {
                            $id = intval($row['id']);
                            $uname = htmlspecialchars($row['username']);
                            $roleName = $row['role_name'] ? htmlspecialchars($row['role_name']) : '<span class="text-muted">No Role</span>';
                            echo "<tr>
                                    <td>{$id}</td>
                                    <td>{$uname}</td>
                                    <td>{$roleName}</td>
                                    <td>
                                        <a href='edit_user.php?id={$id}' class='btn btn-sm btn-primary'>Edit</a>
                                        <a href='change_password.php?id={$id}' class='btn btn-sm btn-warning'>Change Password</a>
                                        <a href='users.php?delete={$id}' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                                    </td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='4' class='text-center text-muted'>No users found</td></tr>";
                    }
                    ?>

                </tbody>
            </table>

        </div>
    </div>
</div>

<?php include "../includes/footer.php"; ?>
