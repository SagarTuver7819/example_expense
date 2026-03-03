<?php
require_once __DIR__ . "/includes/bootstrap.php";
requireLogin();
requirePermission($conn, "approve_expense");

$alert = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = (int)($_POST["id"] ?? 0);
    $decision = clean_input($_POST["decision"] ?? "");
    $comment = clean_input($_POST["approval_comment"] ?? "");

    if ($id > 0 && in_array($decision, ["Approved", "Rejected"], true)) {
        $stmt = $conn->prepare(
            "UPDATE expenses
             SET status = :status, approval_comment = :comment, approved_by = :approved_by
             WHERE id = :id AND status = 'Pending'"
        );
        $stmt->execute([
            "status" => $decision,
            "comment" => $comment ?: null,
            "approved_by" => (int)$_SESSION["user_id"],
            "id" => $id
        ]);

        $fetch = $conn->prepare("SELECT user_id, amount, expense_date FROM expenses WHERE id = :id");
        $fetch->execute(["id" => $id]);
        $row = $fetch->fetch();
        if ($row) {
            $title = "Expense " . $decision;
            $message = "Your expense of Rs " . formatCurrency($row["amount"]) . " for " . $row["expense_date"] . " was " . strtolower($decision) . ".";
            if ($comment) {
                $message .= " Comment: " . $comment;
            }
            notifyUser($conn, (int)$row["user_id"], $title, $message);
        }

        setFlash("success", "Expense updated.");
        redirect("approvals.php");
    }
}

$rows = $conn->query(
    "SELECT e.*, u.name, u.email
     FROM expenses e
     INNER JOIN users u ON u.id = e.user_id
     ORDER BY FIELD(e.status,'Pending','Approved','Rejected'), e.id DESC"
)->fetchAll();

$pageTitle = "Expense Approvals";
require_once __DIR__ . "/includes/layout_top.php";
require_once __DIR__ . "/includes/sidebar.php";
?>
<?php if ($alert): ?><div class="alert alert-success"><?php echo clean_input($alert); ?></div><?php endif; ?>

<div class="card p-3">
    <h6>Expense Approval Queue</h6>
    <div class="table-responsive">
        <table class="table table-custom">
            <thead>
            <tr>
                <th>Employee</th>
                <th>Date</th>
                <th>Category</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Bill</th>
                <th>Comment</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo clean_input($row["name"]); ?><br><small class="text-muted"><?php echo clean_input($row["email"]); ?></small></td>
                    <td><?php echo clean_input($row["expense_date"]); ?></td>
                    <td><?php echo clean_input($row["category"]); ?></td>
                    <td>Rs <?php echo formatCurrency($row["amount"]); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($row["status"]); ?>"><?php echo clean_input($row["status"]); ?></span></td>
                    <td>
                        <?php if (!empty($row["bill_file"])): ?>
                            <a href="<?php echo clean_input($row["bill_file"]); ?>" target="_blank">View</a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo clean_input((string)$row["approval_comment"]); ?></td>
                    <td>
                        <?php if ($row["status"] === "Pending"): ?>
                            <form method="post" class="d-flex gap-2">
                                <input type="hidden" name="id" value="<?php echo (int)$row["id"]; ?>">
                                <input class="form-control form-control-sm" type="text" name="approval_comment" placeholder="Comment">
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
                <tr><td colspan="8" class="text-center text-muted">No expenses found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
