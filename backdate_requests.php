<?php
require_once __DIR__ . "/includes/bootstrap.php";
requireLogin();
requirePermission($conn, "request_backdate");

$userId = (int)$_SESSION["user_id"];
$alert = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["cancel_request"])) {
    $id = (int)($_POST["id"] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM backdate_requests WHERE id = :id AND user_id = :user_id AND status = 'Pending'");
        $stmt->execute(["id" => $id, "user_id" => $userId]);
        setFlash("success", "Backdate request cancelled.");
    }
    redirect("backdate_requests.php");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $requestDate = clean_input($_POST["request_date"] ?? "");
    $reason = clean_input($_POST["reason"] ?? "");

    if (!$requestDate || !$reason) {
        $error = "Date and reason are required.";
    } elseif (!isPastDate($requestDate)) {
        $error = "Backdate request is only for past dates.";
    } else {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM backdate_requests
             WHERE user_id = :user_id AND request_date = :request_date AND status = 'Pending'"
        );
        $stmt->execute([
            "user_id" => $userId,
            "request_date" => $requestDate
        ]);
        if ((int)$stmt->fetchColumn() > 0) {
            $error = "A pending request already exists for this date.";
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO backdate_requests (user_id, request_date, reason)
                 VALUES (:user_id, :request_date, :reason)"
            );
            $stmt->execute([
                "user_id" => $userId,
                "request_date" => $requestDate,
                "reason" => $reason
            ]);

            notifyRole(
                $conn,
                "Admin",
                "Backdate Request Submitted",
                $_SESSION["name"] . " requested backdate permission for " . $requestDate . "."
            );
            setFlash("success", "Backdate request sent to admin.");
            redirect("backdate_requests.php");
        }
    }
}

$stmt = $conn->prepare(
    "SELECT * FROM backdate_requests
     WHERE user_id = :user_id
     ORDER BY id DESC"
);
$stmt->execute(["user_id" => $userId]);
$requests = $stmt->fetchAll();

$pageTitle = "Backdate Requests";
require_once __DIR__ . "/includes/layout_top.php";
require_once __DIR__ . "/includes/sidebar.php";
?>
<?php if ($alert): ?><div class="alert alert-success"><?php echo clean_input($alert); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo clean_input($error); ?></div><?php endif; ?>

<div class="card p-3">
    <h6>Request Backdated Expense Permission</h6>
    <p class="text-muted small mb-3">Past expense entries are blocked by default. Submit reason for admin approval.</p>
    <form method="post" class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Past Date</label>
            <input class="form-control" type="date" name="request_date" required>
        </div>
        <div class="col-md-8">
            <label class="form-label">Reason</label>
            <input class="form-control" type="text" name="reason" maxlength="255" required>
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit">Submit Request</button>
        </div>
    </form>
</div>

<div class="card p-3">
    <h6>My Backdate Request History</h6>
    <div class="table-responsive">
        <table class="table table-custom">
            <thead>
            <tr>
                <th>Date</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Admin Comment</th>
                <th>Used</th>
                <th>Requested At</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $req): ?>
                <tr>
                    <td><?php echo clean_input($req["request_date"]); ?></td>
                    <td><?php echo clean_input($req["reason"]); ?></td>
                    <td>
                        <?php $st = strtolower($req["status"]); ?>
                        <span class="badge badge-<?php echo $st; ?>"><?php echo clean_input($req["status"]); ?></span>
                    </td>
                    <td><?php echo clean_input((string)$req["admin_comment"]); ?></td>
                    <td><?php echo ((int)$req["is_consumed"] === 1) ? "Yes" : "No"; ?></td>
                    <td><?php echo clean_input($req["created_at"]); ?></td>
                    <td>
                        <?php if ($req["status"] === "Pending"): ?>
                            <form method="post" onsubmit="return confirm('Cancel this pending request?');">
                                <input type="hidden" name="cancel_request" value="1">
                                <input type="hidden" name="id" value="<?php echo (int)$req["id"]; ?>">
                                <button class="btn btn-sm btn-outline-danger">Cancel</button>
                            </form>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$requests): ?>
                <tr><td colspan="7" class="text-center text-muted">No requests found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
