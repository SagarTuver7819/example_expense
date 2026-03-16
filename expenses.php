<?php
require_once __DIR__ . "/includes/bootstrap.php";
requireLogin();

$canAdd = hasPermission($conn, "add_expense");
$canViewOwn = hasPermission($conn, "view_own_expense");
$canViewAll = hasPermission($conn, "view_all_expense");

if (!$canAdd && !$canViewOwn && !$canViewAll) {
    http_response_code(403);
    exit("Forbidden");
}

$alert = "";
$error = "";
$userId = (int)$_SESSION["user_id"];
$isPrivileged = $canViewAll;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_expense"])) {
    $expenseId = (int)($_POST["expense_id"] ?? 0);
    if ($expenseId <= 0) {
        setFlash("error", "Invalid expense.");
        redirect("expenses.php");
    }

    $stmt = $conn->prepare("SELECT id, user_id, status, bill_file FROM expenses WHERE id = :id LIMIT 1");
    $stmt->execute(["id" => $expenseId]);
    $exp = $stmt->fetch();
    if (!$exp) {
        setFlash("error", "Expense not found.");
        redirect("expenses.php");
    }

    $canDelete = false;
    if ($canViewAll || isAdmin()) {
        $canDelete = true;
    } else {
        $canDelete = ((int)$exp["user_id"] === $userId) && ($exp["status"] === "Pending");
    }

    if (!$canDelete) {
        setFlash("error", "You can only delete your own pending expenses.");
        redirect("expenses.php");
    }

    $del = $conn->prepare("DELETE FROM expenses WHERE id = :id");
    $del->execute(["id" => $expenseId]);

    if (!empty($exp["bill_file"]) && substr((string)$exp["bill_file"], 0, 13) === "uploads/bills/") {
        $path = __DIR__ . "/" . $exp["bill_file"];
        if (is_file($path)) {
            @unlink($path);
        }
    }

    setFlash("success", "Expense deleted.");
    redirect("expenses.php");
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_expense"])) {
    requirePermission($conn, "add_expense");

    $expenseDate = clean_input($_POST["expense_date"] ?? "");
    $category = clean_input($_POST["category"] ?? "");
    $partyName = clean_input($_POST["party_name"] ?? "");
    $amount = (float)($_POST["amount"] ?? 0);
    $paymentMode = clean_input($_POST["payment_mode"] ?? "");
    $description = clean_input($_POST["description"] ?? "");

    $validCategories = ["Travel", "Food", "Office", "Other"];
    $validPaymentModes = ["Cash", "UPI", "Card"];

    if (!$expenseDate || !in_array($category, $validCategories, true) || !in_array($paymentMode, $validPaymentModes, true) || $amount <= 0) {
        $error = "Please provide valid expense details.";
    } else {
        $backdateRequest = null;
        if (isPastDate($expenseDate) && !isAdmin()) {
            $backdateRequest = userHasApprovedBackdate($conn, $userId, $expenseDate);
            if (!$backdateRequest) {
                $error = "Backdated entry is blocked. Submit a backdate request first.";
            }
        }
    }

    $billFilePath = null;
    if (!$error && !empty($_FILES["bill_file"]["name"])) {
        $allowed = ["image/jpeg", "image/png", "application/pdf"];
        $maxSize = 3 * 1024 * 1024;
        $mime = mime_content_type($_FILES["bill_file"]["tmp_name"]);
        if (!in_array($mime, $allowed, true) || $_FILES["bill_file"]["size"] > $maxSize) {
            $error = "Bill must be JPG, PNG or PDF and max 3MB.";
        } else {
            $ext = pathinfo($_FILES["bill_file"]["name"], PATHINFO_EXTENSION);
            $fileName = uniqid("bill_", true) . "." . strtolower($ext);
            $uploadDir = __DIR__ . "/uploads/bills";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $target = $uploadDir . "/" . $fileName;
            if (!move_uploaded_file($_FILES["bill_file"]["tmp_name"], $target)) {
                $error = "Unable to upload bill file.";
            } else {
                $billFilePath = "uploads/bills/" . $fileName;
            }
        }
    }

    if (!$error) {
        $stmt = $conn->prepare(
            "INSERT INTO expenses (user_id, expense_date, category, party_name, amount, payment_mode, description, bill_file)
             VALUES (:user_id, :expense_date, :category, :party_name, :amount, :payment_mode, :description, :bill_file)"
        );
        $stmt->execute([
            "user_id" => $userId,
            "expense_date" => $expenseDate,
            "category" => $category,
            "party_name" => $partyName ?: null,
            "amount" => $amount,
            "payment_mode" => $paymentMode,
            "description" => $description ?: null,
            "bill_file" => $billFilePath
        ]);

        if (!empty($backdateRequest["id"])) {
            markBackdateConsumed($conn, (int)$backdateRequest["id"]);
        }

        notifyRole($conn, "Admin", "New Expense Submitted", $_SESSION["name"] . " submitted an expense of Rs " . formatCurrency($amount) . " for " . $expenseDate . ".");
        setFlash("success", "Expense added and sent for approval.");
        redirect("expenses.php");
    }
}

$where = [];
$params = [];
if (!$isPrivileged) {
    $where[] = "e.user_id = :user_id";
    $params["user_id"] = $userId;
}

if (!empty($_GET["status"])) {
    $where[] = "e.status = :status";
    $params["status"] = clean_input($_GET["status"]);
}

$sql = "SELECT e.*, u.name
        FROM expenses e
        INNER JOIN users u ON u.id = e.user_id";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY e.expense_date DESC, e.id DESC LIMIT 200";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

$pageTitle = "Expenses";
require_once __DIR__ . "/includes/layout_top.php";
require_once __DIR__ . "/includes/sidebar.php";
?>
<?php if ($alert): ?><div class="alert alert-success"><?php echo clean_input($alert); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo clean_input($error); ?></div><?php endif; ?>

<?php if ($canAdd): ?>
<div class="card p-3 expense-entry-card">
    <h6 class="mb-1">Add Expense</h6>
    <p class="text-muted small mb-3">Today entries are direct. Backdated entries require admin approval for that exact date.</p>
    <form method="post" enctype="multipart/form-data" class="row g-3" id="expenseForm">
        <input type="hidden" name="add_expense" value="1">
        <div class="col-md-3">
            <label class="form-label">Expense Date</label>
            <input type="date" class="form-control" id="expenseDate" name="expense_date" value="<?php echo todayDate(); ?>" required>
            <small class="text-muted" id="backdateHint">Past date needs approved backdate request.</small>
        </div>
        <div class="col-md-2">
            <label class="form-label">Category</label>
            <select class="form-select" name="category" required>
                <option value="">Select</option>
                <option>Travel</option>
                <option>Food</option>
                <option>Office</option>
                <option>Other</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Party Name</label>
            <input type="text" class="form-control" name="party_name" placeholder="Vendor/Person name">
        </div>
        <div class="col-md-2">
            <label class="form-label">Amount</label>
            <input type="number" step="0.01" min="0.01" class="form-control" name="amount" required>
        </div>
        <div class="col-md-2">
            <label class="form-label">Payment Mode</label>
            <select class="form-select" name="payment_mode" required>
                <option value="">Select</option>
                <option>Cash</option>
                <option>UPI</option>
                <option>Card</option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Bill (JPG/PNG/PDF)</label>
            <input type="file" class="form-control" name="bill_file">
        </div>
        <div class="col-12">
            <label class="form-label">Description</label>
            <textarea class="form-control" name="description" rows="2" placeholder="Optional notes"></textarea>
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit">Submit Expense</button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="mb-0"><?php echo $isPrivileged ? "All Expenses" : "My Expenses"; ?></h6>
        <form method="get" class="d-flex gap-2">
            <select class="form-select form-select-sm" name="status">
                <option value="">All Status</option>
                <option value="Pending" <?php echo (($_GET["status"] ?? "") === "Pending") ? "selected" : ""; ?>>Pending</option>
                <option value="Approved" <?php echo (($_GET["status"] ?? "") === "Approved") ? "selected" : ""; ?>>Approved</option>
                <option value="Rejected" <?php echo (($_GET["status"] ?? "") === "Rejected") ? "selected" : ""; ?>>Rejected</option>
            </select>
            <button class="btn btn-sm btn-outline-secondary">Filter</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-custom">
            <thead>
            <tr>
                <?php if ($isPrivileged): ?><th>Employee</th><?php endif; ?>
                <th>Date</th>
                <th>Category</th>
                <th>Party Name</th>
                <th>Amount</th>
                <th>Mode</th>
                <th>Status</th>
                <th>Bill</th>
                <th>Comment</th>
                <?php if (!$isPrivileged): ?><th>Action</th><?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($expenses as $exp): ?>
                <tr>
                    <?php if ($isPrivileged): ?><td><?php echo clean_input($exp["name"]); ?></td><?php endif; ?>
                    <td><?php echo clean_input($exp["expense_date"]); ?></td>
                    <td><?php echo clean_input($exp["category"]); ?></td>
                    <td><?php echo clean_input((string)$exp["party_name"]); ?></td>
                    <td>Rs <?php echo formatCurrency($exp["amount"]); ?></td>
                    <td><?php echo clean_input($exp["payment_mode"]); ?></td>
                    <td>
                        <?php $st = strtolower($exp["status"]); ?>
                        <span class="badge badge-<?php echo $st; ?>"><?php echo clean_input($exp["status"]); ?></span>
                    </td>
                    <td>
                        <?php if (!empty($exp["bill_file"])): ?>
                            <a href="<?php echo clean_input($exp["bill_file"]); ?>" target="_blank">View</a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo clean_input((string)$exp["approval_comment"]); ?></td>
                    <?php if (!$isPrivileged): ?>
                        <td>
                            <?php if ($exp["status"] === "Pending"): ?>
                                <form method="post" onsubmit="return confirm('Delete this pending expense?');">
                                    <input type="hidden" name="delete_expense" value="1">
                                    <input type="hidden" name="expense_id" value="<?php echo (int)$exp["id"]; ?>">
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$expenses): ?>
                <tr><td colspan="<?php echo $isPrivileged ? 8 : 8; ?>" class="text-center text-muted">No expenses found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="backdateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title text-danger"><i class="fa-solid fa-triangle-exclamation"></i> Backdated Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">This is a backdated entry. Direct submission is blocked.</p>
                <p class="small text-muted mb-3">Do you want to send a request to admin for this date?</p>
                <div class="mb-2">
                    <label class="form-label">Reason for backdated entry</label>
                    <textarea id="backdateReason" class="form-control" rows="3" placeholder="Example: I was on site visit and forgot to submit"></textarea>
                </div>
                <div class="small text-muted" id="backdateModalInfo"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="sendBackdateRequestBtn" class="btn btn-primary">Request Admin Approval</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const dateInput = document.getElementById("expenseDate");
    const form = document.getElementById("expenseForm");
    const hint = document.getElementById("backdateHint");
    const reasonInput = document.getElementById("backdateReason");
    const modalInfo = document.getElementById("backdateModalInfo");
    const requestBtn = document.getElementById("sendBackdateRequestBtn");
    const modalEl = document.getElementById("backdateModal");
    if (!dateInput || !form || !modalEl) return;

    let pendingDate = "";
    let backdateBlocked = false;
    const modal = new bootstrap.Modal(modalEl);

    function todayYmd() {
        const d = new Date();
        const mm = String(d.getMonth() + 1).padStart(2, "0");
        const dd = String(d.getDate()).padStart(2, "0");
        return `${d.getFullYear()}-${mm}-${dd}`;
    }

    function isPastDate(ymd) {
        return ymd && ymd < todayYmd();
    }

    async function checkBackdatePermission(expenseDate) {
        const res = await fetch(`api/check_backdate.php?expense_date=${encodeURIComponent(expenseDate)}`, { credentials: "same-origin" });
        return await res.json();
    }

    async function onDateChanged() {
        const selectedDate = dateInput.value;
        if (!isPastDate(selectedDate)) {
            hint.textContent = "Past date needs approved backdate request.";
            backdateBlocked = false;
            return;
        }

        const data = await checkBackdatePermission(selectedDate);
        if (data.allowed) {
            hint.textContent = "Approved for this date. You can submit the expense now.";
            backdateBlocked = false;
            showAppToast("success", "Backdate approved for selected date. Submission allowed.");
            return;
        }

        pendingDate = selectedDate;
        backdateBlocked = true;
        modalInfo.textContent = data.message || "Backdated entry requires admin approval.";
        hint.textContent = "Backdate pending approval. Request admin first.";
        modal.show();
    }

    dateInput.addEventListener("change", function () {
        onDateChanged().catch(() => showAppToast("error", "Could not validate backdate permission."));
    });

    form.addEventListener("submit", async function (e) {
        const selectedDate = dateInput.value;
        if (!isPastDate(selectedDate)) return;

        if (backdateBlocked) {
            e.preventDefault();
            modal.show();
            return;
        }

        const data = await checkBackdatePermission(selectedDate);
        if (!data.allowed) {
            e.preventDefault();
            pendingDate = selectedDate;
            backdateBlocked = true;
            modalInfo.textContent = data.message || "Backdated entry requires admin approval.";
            modal.show();
        } else {
            backdateBlocked = false;
        }
    });

    requestBtn.addEventListener("click", async function () {
        const reason = reasonInput.value.trim();
        if (!pendingDate) {
            showAppToast("warning", "Please choose a date first.");
            return;
        }
        if (!reason) {
            showAppToast("warning", "Please enter reason for admin.");
            return;
        }

        requestBtn.disabled = true;
        try {
            const body = new URLSearchParams({ request_date: pendingDate, reason: reason });
            const res = await fetch("api/request_backdate.php", {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: body.toString()
            });
            const data = await res.json();
            if (!data.ok) {
                showAppToast("error", data.message || "Could not submit request.");
                return;
            }
            backdateBlocked = true;
            modal.hide();
            reasonInput.value = "";
            showAppToast("success", "Backdate request sent to admin.");
        } catch (err) {
            showAppToast("error", "Could not submit backdate request.");
        } finally {
            requestBtn.disabled = false;
        }
    });
})();
</script>

<?php if ($error): ?>
<script>showAppToast("error", <?php echo json_encode($error); ?>);</script>
<?php endif; ?>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
