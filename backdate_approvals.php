<?php
require_once __DIR__ . "/includes/bootstrap.php";
requireLogin();
requirePermission($conn, "approve_backdate");

$alert = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = (int)($_POST["id"] ?? 0);
    $decision = clean_input($_POST["decision"] ?? "");
    $comment = clean_input($_POST["admin_comment"] ?? "");

    if ($id > 0 && in_array($decision, ["Approved", "Rejected"], true)) {
        $stmt = $conn->prepare(
            "UPDATE backdate_requests
             SET status = :status, admin_comment = :comment, approved_by = :approved_by
             WHERE id = :id AND status = 'Pending'"
        );
        $stmt->execute([
            "status" => $decision,
            "comment" => $comment ?: null,
            "approved_by" => (int)$_SESSION["user_id"],
            "id" => $id
        ]);

        $fetch = $conn->prepare(
            "SELECT br.request_date, u.id AS user_id, u.name
             FROM backdate_requests br
             INNER JOIN users u ON u.id = br.user_id
             WHERE br.id = :id"
        );
        $fetch->execute(["id" => $id]);
        $row = $fetch->fetch();

        if ($row) {
            $message = "Your backdate request for " . $row["request_date"] . " was " . strtolower($decision) . ".";
            if ($comment) {
                $message .= " Comment: " . $comment;
            }
            notifyUser($conn, (int)$row["user_id"], "Backdate Request " . $decision, $message);
        }

        setFlash("success", "Backdate request updated.");
        redirect("backdate_approvals.php");
    }
}

$rows = $conn->query(
    "SELECT br.*, u.name, u.email
     FROM backdate_requests br
     INNER JOIN users u ON u.id = br.user_id
     ORDER BY FIELD(br.status,'Pending','Approved','Rejected'), br.id DESC"
)->fetchAll();

$pageTitle = "Backdate Approvals";
require_once __DIR__ . "/includes/layout_top.php";
require_once __DIR__ . "/includes/sidebar.php";
?>
<?php if ($alert): ?><div class="alert alert-success"><?php echo clean_input($alert); ?></div><?php endif; ?>

<div class="card p-3">
    <h6>Backdate Requests</h6>
    <div class="table-responsive">
        <table class="table table-custom">
            <thead>
            <tr>
                <th>Employee</th>
                <th>Date</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Admin Comment</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo clean_input($row["name"]); ?><br><small class="text-muted"><?php echo clean_input($row["email"]); ?></small></td>
                    <td><?php echo clean_input($row["request_date"]); ?></td>
                    <td><?php echo clean_input($row["reason"]); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($row["status"]); ?>"><?php echo clean_input($row["status"]); ?></span></td>
                    <td><?php echo clean_input((string)$row["admin_comment"]); ?></td>
                    <td>
                        <?php if ($row["status"] === "Pending"): ?>
                            <form method="post" class="d-flex gap-2">
                                <input type="hidden" name="id" value="<?php echo (int)$row["id"]; ?>">
                                <input type="text" class="form-control form-control-sm" name="admin_comment" placeholder="Comment">
                                <button class="btn btn-sm btn-success" name="decision" value="Approved">Approve</button>
                                <button class="btn btn-sm btn-danger" name="decision" value="Rejected">Reject</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted">Closed</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="text-center text-muted">No requests found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
