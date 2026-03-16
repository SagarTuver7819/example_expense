<?php
require_once __DIR__ . "/includes/bootstrap.php";
requireLogin();
requirePermission($conn, "view_reports");

$where = [];
$params = [];

if (!empty($_GET["employee_id"])) {
    $where[] = "e.user_id = :employee_id";
    $params["employee_id"] = (int)$_GET["employee_id"];
}
if (!empty($_GET["category"])) {
    $where[] = "e.category = :category";
    $params["category"] = clean_input($_GET["category"]);
}
if (!empty($_GET["status"])) {
    $where[] = "e.status = :status";
    $params["status"] = clean_input($_GET["status"]);
}
if (!empty($_GET["from_date"])) {
    $where[] = "e.expense_date >= :from_date";
    $params["from_date"] = clean_input($_GET["from_date"]);
}
if (!empty($_GET["to_date"])) {
    $where[] = "e.expense_date <= :to_date";
    $params["to_date"] = clean_input($_GET["to_date"]);
}

$sql = "SELECT e.id, e.expense_date, e.category, e.party_name, e.amount, e.payment_mode, e.status, e.approval_comment, u.name
        FROM expenses e
        INNER JOIN users u ON u.id = e.user_id";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY e.expense_date DESC, e.id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if (!empty($_GET["export"]) && hasPermission($conn, "export_reports")) {
    $export = clean_input($_GET["export"]);
    if ($export === "excel") {
        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename=expense_report_" . date("Ymd_His") . ".csv");
        $out = fopen("php://output", "w");
        fputcsv($out, ["ID", "Date", "Employee", "Category", "Party Name", "Amount", "Payment", "Status", "Comment"]);
        foreach ($rows as $r) {
            fputcsv($out, [$r["id"], $r["expense_date"], $r["name"], $r["category"], $r["party_name"], $r["amount"], $r["payment_mode"], $r["status"], $r["approval_comment"]]);
        }
        fclose($out);
        exit();
    }
    if ($export === "pdf") {
        header("Content-Type: text/html; charset=utf-8");
        echo "<h3>Ocean Expense Report</h3>";
        echo "<table border='1' cellpadding='6' cellspacing='0'><tr><th>ID</th><th>Date</th><th>Employee</th><th>Category</th><th>Party Name</th><th>Amount</th><th>Status</th></tr>";
        foreach ($rows as $r) {
            echo "<tr><td>" . (int)$r["id"] . "</td><td>" . clean_input($r["expense_date"]) . "</td><td>" . clean_input($r["name"]) . "</td><td>" . clean_input($r["category"]) . "</td><td>" . clean_input($r["party_name"]) . "</td><td>" . formatCurrency($r["amount"]) . "</td><td>" . clean_input($r["status"]) . "</td></tr>";
        }
        echo "</table><script>window.print();</script>";
        exit();
    }
}

$employees = $conn->query(
    "SELECT u.id, u.name
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id
     WHERE r.name = 'Employee'
     ORDER BY u.name"
)->fetchAll();

$totalAmount = array_sum(array_map(static fn($x) => (float)$x["amount"], $rows));

$pageTitle = "Reports";
require_once __DIR__ . "/includes/layout_top.php";
require_once __DIR__ . "/includes/sidebar.php";
?>
<div class="card p-3">
    <h6>Expense Reports</h6>
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-2">
            <select class="form-select" name="employee_id">
                <option value="">All Employees</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?php echo (int)$emp["id"]; ?>" <?php echo (($_GET["employee_id"] ?? "") == $emp["id"]) ? "selected" : ""; ?>>
                        <?php echo clean_input($emp["name"]); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" name="category">
                <option value="">All Categories</option>
                <?php foreach (["Travel","Food","Office","Other"] as $cat): ?>
                    <option value="<?php echo $cat; ?>" <?php echo (($_GET["category"] ?? "") === $cat) ? "selected" : ""; ?>><?php echo $cat; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select" name="status">
                <option value="">All Status</option>
                <?php foreach (["Pending","Approved","Rejected"] as $st): ?>
                    <option value="<?php echo $st; ?>" <?php echo (($_GET["status"] ?? "") === $st) ? "selected" : ""; ?>><?php echo $st; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><input class="form-control" type="date" name="from_date" value="<?php echo clean_input($_GET["from_date"] ?? ""); ?>"></div>
        <div class="col-md-2"><input class="form-control" type="date" name="to_date" value="<?php echo clean_input($_GET["to_date"] ?? ""); ?>"></div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </form>
    <div class="d-flex gap-2 mb-3">
        <a class="btn btn-sm btn-outline-success" href="?<?php echo http_build_query(array_merge($_GET, ["export" => "excel"])); ?>">Export Excel</a>
        <a class="btn btn-sm btn-outline-secondary" href="?<?php echo http_build_query(array_merge($_GET, ["export" => "pdf"])); ?>" target="_blank">Export PDF</a>
        <span class="ms-auto fw-bold">Total: Rs <?php echo formatCurrency($totalAmount); ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-custom">
            <thead><tr><th>ID</th><th>Date</th><th>Employee</th><th>Category</th><th>Party Name</th><th>Amount</th><th>Mode</th><th>Status</th><th>Comment</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?php echo (int)$r["id"]; ?></td>
                    <td><?php echo clean_input($r["expense_date"]); ?></td>
                    <td><?php echo clean_input($r["name"]); ?></td>
                    <td><?php echo clean_input($r["category"]); ?></td>
                    <td><?php echo clean_input((string)$r["party_name"]); ?></td>
                    <td>Rs <?php echo formatCurrency($r["amount"]); ?></td>
                    <td><?php echo clean_input($r["payment_mode"]); ?></td>
                    <td><span class="badge badge-<?php echo strtolower($r["status"]); ?>"><?php echo clean_input($r["status"]); ?></span></td>
                    <td><?php echo clean_input((string)$r["approval_comment"]); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr><td colspan="8" class="text-center text-muted">No report data found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . "/includes/layout_bottom.php"; ?>
