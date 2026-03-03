<?php
require_once __DIR__ . "/../includes/bootstrap.php";

if (!isLoggedIn()) {
    jsonResponse(["ok" => false, "message" => "Unauthorized"], 401);
}

if (!hasPermission($conn, "request_backdate")) {
    jsonResponse(["ok" => false, "message" => "Forbidden"], 403);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    jsonResponse(["ok" => false, "message" => "Invalid method"], 405);
}

$requestDate = clean_input($_POST["request_date"] ?? "");
$reason = clean_input($_POST["reason"] ?? "");

if (!$requestDate || !$reason) {
    jsonResponse(["ok" => false, "message" => "Date and reason are required."], 422);
}

$result = createBackdateRequestIfNotExists($conn, (int)$_SESSION["user_id"], $requestDate, $reason);
if (!$result["ok"]) {
    jsonResponse(["ok" => false, "message" => $result["message"]], 422);
}

notifyRole(
    $conn,
    "Admin",
    "Backdate Request Submitted",
    $_SESSION["name"] . " requested backdate permission for " . $requestDate . "."
);

jsonResponse([
    "ok" => true,
    "message" => "Backdate request sent to admin.",
    "request_date" => $requestDate
]);
