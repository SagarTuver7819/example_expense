<?php
require_once __DIR__ . "/includes/bootstrap.php";
requireLogin();
requirePermission($conn, "manage_roles");

$alert = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["create_role"])) {
        $name = clean_input($_POST["name"] ?? "");
        $description = clean_input($_POST["description"] ?? "");
        if (!$name) {
            $error = "Role name is required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO roles (name, description) VALUES (:name, :description)");
            try {
                $stmt->execute(["name" => $name, "description" => $description ?: null]);
                setFlash("success", "Role created.");
                redirect("roles.php");
            } catch (Throwable $e) {
                $error = "Role already exists or invalid.";
            }
        }
    }

    if (isset($_POST["save_permissions"])) {
        $roleId = (int)($_POST["role_id"] ?? 0);
        $permissionIds = $_POST["permission_ids"] ?? [];

        if ($roleId > 0) {
            $conn->beginTransaction();
            try {
                $del = $conn->prepare("DELETE FROM role_permissions WHERE role_id = :role_id");
                $del->execute(["role_id" => $roleId]);

                if (is_array($permissionIds)) {
                    $ins = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)");
                    foreach ($permissionIds as $pid) {
                        $ins->execute([
                            "role_id" => $roleId,
                            "permission_id" => (int)$pid
                        ]);
                    }
                }
                $conn->commit();
                setFlash("success", "Permissions updated.");
                redirect("roles.php");
            } catch (Throwable $e) {
                $conn->rollBack();
                $error = "Could not update permissions.";
            }
        }
    }
}

$roles = $conn->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$permissions = $conn->query("SELECT * FROM permissions ORDER BY id")->fetchAll();
$roleMapRows = $conn->query("SELECT role_id, permission_id FROM role_permissions")->fetchAll();
$rolePermissionMap = [];
foreach ($roleMapRows as $row) {
    $rolePermissionMap[(int)$row["role_id"]][] = (int)$row["permission_id"];
}

$pageTitle = "Roles & Permissions";
require_once __DIR__ . "/includes/layout_top.php";
require_once __DIR__ . "/includes/sidebar.php";
?>
<?php if ($alert): ?><div class="alert alert-success"><?php echo clean_input($alert); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo clean_input($error); ?></div><?php endif; ?>

<div class="card p-3">
    <h6>Create Role</h6>
    <form method="post" class="row g-3">
        <input type="hidden" name="create_role" value="1">
        <div class="col-md-4"><input class="form-control" name="name" placeholder="Role Name" required></div>
        <div class="col-md-6"><input class="form-control" name="description" placeholder="Description"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Create</button></div>
    </form>
</div>

<?php foreach ($roles as $role): ?>
    <div class="card p-3">
        <h6><?php echo clean_input($role["name"]); ?> Permissions</h6>
        <form method="post">
            <input type="hidden" name="save_permissions" value="1">
            <input type="hidden" name="role_id" value="<?php echo (int)$role["id"]; ?>">
            <div class="row">
                <?php foreach ($permissions as $perm): ?>
                    <div class="col-md-4 mb-2">
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                name="permission_ids[]"
                                value="<?php echo (int)$perm["id"]; ?>"
                                id="perm_<?php echo (int)$role["id"] . "_" . (int)$perm["id"]; ?>"
                                <?php echo in_array((int)$perm["id"], $rolePermissionMap[(int)$role["id"]] ?? [], true) ? "checked" : ""; ?>
                            >
                            <label class="form-check-label" for="perm_<?php echo (int)$role["id"] . "_" . (int)$perm["id"]; ?>">
                                <?php echo clean_input($perm["label"]); ?>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-sm btn-success">Save Permissions</button>
        </form>
    </div>
<?php endforeach; ?>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
