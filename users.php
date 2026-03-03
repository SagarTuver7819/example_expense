<?php
require_once __DIR__ . "/includes/bootstrap.php";
requireLogin();
requirePermission($conn, "manage_users");

$alert = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["create_user"])) {
        $name = clean_input($_POST["name"] ?? "");
        $email = clean_input($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";
        $roleId = (int)($_POST["role_id"] ?? 0);

        if (!$name || !$email || !$password || $roleId <= 0) {
            $error = "All fields are required.";
        } else {
            $check = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $check->execute(["email" => $email]);
            if ((int)$check->fetchColumn() > 0) {
                $error = "Email already exists.";
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO users (name, email, password, role_id, status)
                     VALUES (:name, :email, :password, :role_id, 'Active')"
                );
                $stmt->execute([
                    "name" => $name,
                    "email" => $email,
                    "password" => password_hash($password, PASSWORD_BCRYPT),
                    "role_id" => $roleId
                ]);
                setFlash("success", "User created.");
                redirect("users.php");
            }
        }
    }

    if (isset($_POST["toggle_status"])) {
        $id = (int)($_POST["id"] ?? 0);
        $status = clean_input($_POST["status"] ?? "Inactive");
        if ($id > 0 && in_array($status, ["Active", "Inactive"], true)) {
            $stmt = $conn->prepare("UPDATE users SET status = :status WHERE id = :id");
            $stmt->execute(["status" => $status, "id" => $id]);
            setFlash("success", "User status updated.");
            redirect("users.php");
        }
    }

    if (isset($_POST["update_user"])) {
        $id = (int)($_POST["id"] ?? 0);
        $name = clean_input($_POST["name"] ?? "");
        $email = clean_input($_POST["email"] ?? "");
        $roleId = (int)($_POST["role_id"] ?? 0);
        $status = clean_input($_POST["status"] ?? "Active");
        $newPassword = $_POST["password"] ?? "";

        if ($id <= 0 || !$name || !$email || $roleId <= 0 || !in_array($status, ["Active", "Inactive"], true)) {
            $error = "Please provide valid user details.";
        } else {
            $check = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id <> :id");
            $check->execute(["email" => $email, "id" => $id]);
            if ((int)$check->fetchColumn() > 0) {
                $error = "Email already exists for another user.";
            } else {
                $roleCheck = $conn->prepare("SELECT COUNT(*) FROM roles WHERE id = :id AND status = 'Active'");
                $roleCheck->execute(["id" => $roleId]);
                if ((int)$roleCheck->fetchColumn() === 0) {
                    $error = "Selected role is not active.";
                } else {
                    if (trim($newPassword) !== "") {
                        $stmt = $conn->prepare(
                            "UPDATE users
                             SET name = :name, email = :email, role_id = :role_id, status = :status, password = :password
                             WHERE id = :id"
                        );
                        $stmt->execute([
                            "name" => $name,
                            "email" => $email,
                            "role_id" => $roleId,
                            "status" => $status,
                            "password" => password_hash($newPassword, PASSWORD_BCRYPT),
                            "id" => $id
                        ]);
                    } else {
                        $stmt = $conn->prepare(
                            "UPDATE users
                             SET name = :name, email = :email, role_id = :role_id, status = :status
                             WHERE id = :id"
                        );
                        $stmt->execute([
                            "name" => $name,
                            "email" => $email,
                            "role_id" => $roleId,
                            "status" => $status,
                            "id" => $id
                        ]);
                    }
                    setFlash("success", "User updated.");
                    redirect("users.php");
                }
            }
        }
    }
}

$roles = $conn->query("SELECT id, name FROM roles WHERE status = 'Active' ORDER BY name")->fetchAll();
$users = $conn->query(
    "SELECT u.id, u.name, u.email, u.status, r.name AS role_name
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     ORDER BY u.id DESC"
)->fetchAll();

$pageTitle = "User Management";
require_once __DIR__ . "/includes/layout_top.php";
require_once __DIR__ . "/includes/sidebar.php";
?>
<?php if ($alert): ?><div class="alert alert-success"><?php echo clean_input($alert); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo clean_input($error); ?></div><?php endif; ?>

<div class="card p-3">
    <h6>Add Employee / User</h6>
    <form method="post" class="row g-3">
        <input type="hidden" name="create_user" value="1">
        <div class="col-md-3"><input class="form-control" name="name" placeholder="Full Name" required></div>
        <div class="col-md-3"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
        <div class="col-md-2"><input class="form-control" type="password" name="password" placeholder="Password" required></div>
        <div class="col-md-2">
            <select class="form-select" name="role_id" required>
                <option value="">Role</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?php echo (int)$role["id"]; ?>"><?php echo clean_input($role["name"]); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Create User</button></div>
    </form>
</div>

<div class="card p-3">
    <h6>All Users</h6>
    <div class="table-responsive">
        <table class="table table-custom">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?php echo clean_input($u["name"]); ?></td>
                    <td><?php echo clean_input($u["email"]); ?></td>
                    <td><?php echo clean_input($u["role_name"]); ?></td>
                    <td><?php echo clean_input($u["status"]); ?></td>
                    <td>
                        <div class="d-flex gap-2 flex-wrap">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#editUserModal"
                                data-id="<?php echo (int)$u["id"]; ?>"
                                data-name="<?php echo clean_input($u["name"]); ?>"
                                data-email="<?php echo clean_input($u["email"]); ?>"
                                data-role="<?php echo clean_input($u["role_name"]); ?>"
                                data-status="<?php echo clean_input($u["status"]); ?>"
                            >
                                Edit
                            </button>
                            <form method="post">
                                <input type="hidden" name="toggle_status" value="1">
                                <input type="hidden" name="id" value="<?php echo (int)$u["id"]; ?>">
                                <input type="hidden" name="status" value="<?php echo ($u["status"] === "Active") ? "Inactive" : "Active"; ?>">
                                <button class="btn btn-sm <?php echo ($u["status"] === "Active") ? "btn-outline-danger" : "btn-outline-success"; ?>">
                                    <?php echo ($u["status"] === "Active") ? "Disable" : "Enable"; ?>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="editUserForm">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="update_user" value="1">
                    <input type="hidden" name="id" id="editUserId">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input class="form-control" name="name" id="editUserName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input class="form-control" type="email" name="email" id="editUserEmail" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role_id" id="editUserRole" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo (int)$role["id"]; ?>"><?php echo clean_input($role["name"]); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Changing role updates permissions.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="editUserStatus" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">New Password (optional)</label>
                        <input class="form-control" type="password" name="password" id="editUserPassword" placeholder="Leave blank to keep current password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const modalEl = document.getElementById("editUserModal");
    if (!modalEl) return;

    const idEl = document.getElementById("editUserId");
    const nameEl = document.getElementById("editUserName");
    const emailEl = document.getElementById("editUserEmail");
    const roleEl = document.getElementById("editUserRole");
    const statusEl = document.getElementById("editUserStatus");
    const passEl = document.getElementById("editUserPassword");

    function roleNameToId(roleName) {
        for (const opt of roleEl.options) {
            if ((opt.textContent || "").trim() === (roleName || "").trim()) {
                return opt.value;
            }
        }
        return "";
    }

    modalEl.addEventListener("show.bs.modal", function (event) {
        const btn = event.relatedTarget;
        if (!btn) return;
        idEl.value = btn.getAttribute("data-id") || "";
        nameEl.value = btn.getAttribute("data-name") || "";
        emailEl.value = btn.getAttribute("data-email") || "";
        statusEl.value = btn.getAttribute("data-status") || "Active";
        passEl.value = "";
        const roleName = btn.getAttribute("data-role") || "";
        const roleId = roleNameToId(roleName);
        if (roleId) roleEl.value = roleId;
    });
});
</script>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
