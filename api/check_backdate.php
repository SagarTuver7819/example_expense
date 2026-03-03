<?php
require_once __DIR__ . "/../includes/bootstrap.php";

if (!isLoggedIn()) {
    jsonResponse(["ok" => false, "message" => "Unauthorized"], 401);
}

if (!hasPermission($conn, "add_expense")) {
    jsonResponse(["ok" => false, "message" => "Forbidden"], 403);
}

$expenseDate = clean_input($_GET["expense_date"] ?? "");
if (!$expenseDate) {
    jsonResponse(["ok" => false, "message" => "Expense date is required."], 422);
}

if (!isPastDate($expenseDate) || isAdmin()) {
    jsonResponse(["ok" => true, "allowed" => true, "needs_request" => false]);
}

$allowed = userHasApprovedBackdate($conn, (int)$_SESSION["user_id"], $expenseDate);
if ($allowed) {
    jsonResponse(["ok" => true, "allowed" => true, "needs_request" => false]);
}

$stmt = $conn->prepare(
    "SELECT status
     FROM backdate_requests
     WHERE user_id = :user_id AND request_date = :request_date
     ORDER BY id DESC
     LIMIT 1"
);
$stmt->execute([
    "user_id" => (int)$_SESSION["user_id"],
    "request_date" => $expenseDate
]);
$last = $stmt->fetch();

$status = $last["status"] ?? null;
$message = "This is a backdated entry. Request admin approval to add expense.";
if ($status === "Pending") {
    $message = "Backdate request already pending for this date.";
}
if ($status === "Rejected") {
    $message = "Backdate request was rejected. You can submit a new request with reason.";
}

jsonResponse([
    "ok" => true,
    "allowed" => false,
    "needs_request" => true,
    "request_status" => $status,
    "message" => $message
]);
