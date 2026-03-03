<?php
require_once __DIR__ . "/includes/bootstrap.php";
requireLogin();
requirePermission($conn, "view_notifications");

$userId = (int)$_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["mark_all_read"])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id");
    $stmt->execute(["user_id" => $userId]);
    setFlash("success", "All notifications marked as read.");
    redirect("notifications.php");
}

$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY id DESC LIMIT 200");
$stmt->execute(["user_id" => $userId]);
$notifications = $stmt->fetchAll();

$pageTitle = "Notifications";
require_once __DIR__ . "/includes/layout_top.php";
require_once __DIR__ . "/includes/sidebar.php";
?>
<div class="card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0">In-App Notifications</h6>
        <form method="post"><button name="mark_all_read" value="1" class="btn btn-sm btn-outline-primary">Mark all as read</button></form>
    </div>
    <div class="list-group">
        <?php foreach ($notifications as $n): ?>
            <div class="list-group-item <?php echo ((int)$n["is_read"] === 0) ? "list-group-item-light" : ""; ?>">
                <div class="d-flex justify-content-between">
                    <strong><?php echo clean_input($n["title"]); ?></strong>
                    <small class="text-muted"><?php echo clean_input($n["created_at"]); ?></small>
                </div>
                <div><?php echo clean_input($n["message"]); ?></div>
            </div>
        <?php endforeach; ?>
        <?php if (!$notifications): ?>
            <div class="list-group-item text-muted">No notifications.</div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
