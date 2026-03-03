<?php
// includes/functions.php

function clean_input($data) {
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, "UTF-8");
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role_name']) && $_SESSION['role_name'] === 'Admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect("login.php");
    }
}

function loadCurrentUser(PDO $conn, int $userId): ?array {
    $sql = "SELECT u.id, u.name, u.email, u.status, r.id AS role_id, r.name AS role_name
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE u.id = :id
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute(["id" => $userId]);
    $user = $stmt->fetch();
    if (!$user || $user["status"] !== "Active") {
        return null;
    }
    return $user;
}

function hydrateSessionFromUser(array $user): void {
    $_SESSION["user_id"] = (int)$user["id"];
    $_SESSION["name"] = $user["name"];
    $_SESSION["email"] = $user["email"];
    $_SESSION["role_id"] = (int)$user["role_id"];
    $_SESSION["role_name"] = $user["role_name"];
}

function hasPermission(PDO $conn, string $permissionCode): bool {
    if (!isLoggedIn()) {
        return false;
    }

    $sql = "SELECT 1
            FROM role_permissions rp
            INNER JOIN permissions p ON p.id = rp.permission_id
            WHERE rp.role_id = :role_id AND p.code = :code
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        "role_id" => (int)$_SESSION["role_id"],
        "code" => $permissionCode
    ]);
    return (bool)$stmt->fetchColumn();
}

function requirePermission(PDO $conn, string $permissionCode): void {
    if (!hasPermission($conn, $permissionCode)) {
        http_response_code(403);
        echo "Forbidden: Missing permission <code>" . clean_input($permissionCode) . "</code>.";
        exit();
    }
}

function todayDate(): string {
    return date("Y-m-d");
}

function isPastDate(string $date): bool {
    return strtotime($date) < strtotime(todayDate());
}

function userHasApprovedBackdate(PDO $conn, int $userId, string $expenseDate): ?array {
    $sql = "SELECT id, request_date
            FROM backdate_requests
            WHERE user_id = :user_id
              AND request_date = :request_date
              AND status = 'Approved'
              AND is_consumed = 0
            ORDER BY id DESC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        "user_id" => $userId,
        "request_date" => $expenseDate
    ]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function markBackdateConsumed(PDO $conn, int $requestId): void {
    $stmt = $conn->prepare("UPDATE backdate_requests SET is_consumed = 1 WHERE id = :id");
    $stmt->execute(["id" => $requestId]);
}

function notifyUser(PDO $conn, int $userId, string $title, string $message): void {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (:user_id, :title, :message)");
    $stmt->execute([
        "user_id" => $userId,
        "title" => $title,
        "message" => $message
    ]);
}

function notifyRole(PDO $conn, string $roleName, string $title, string $message): void {
    $sql = "SELECT u.id
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            WHERE r.name = :role_name AND u.status = 'Active'";
    $stmt = $conn->prepare($sql);
    $stmt->execute(["role_name" => $roleName]);
    $users = $stmt->fetchAll();

    if (!$users) {
        return;
    }

    $insert = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (:user_id, :title, :message)");
    foreach ($users as $u) {
        $insert->execute([
            "user_id" => (int)$u["id"],
            "title" => $title,
            "message" => $message
        ]);
    }
}

function setFlash(string $type, string $message): void {
    if (!isset($_SESSION["flash_toasts"]) || !is_array($_SESSION["flash_toasts"])) {
        $_SESSION["flash_toasts"] = [];
    }
    $_SESSION["flash_toasts"][] = [
        "type" => $type,
        "message" => $message
    ];
}

function consumeFlashToasts(): array {
    $items = $_SESSION["flash_toasts"] ?? [];
    unset($_SESSION["flash_toasts"]);
    if (!is_array($items)) {
        return [];
    }
    return $items;
}

function createBackdateRequestIfNotExists(PDO $conn, int $userId, string $requestDate, string $reason): array {
    if (!isPastDate($requestDate)) {
        return ["ok" => false, "message" => "Only past dates are allowed for backdate request."];
    }

    $stmt = $conn->prepare(
        "SELECT id, status
         FROM backdate_requests
         WHERE user_id = :user_id AND request_date = :request_date
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([
        "user_id" => $userId,
        "request_date" => $requestDate
    ]);
    $existing = $stmt->fetch();

    if ($existing && $existing["status"] === "Pending") {
        return ["ok" => false, "message" => "A pending request already exists for this date."];
    }

    $insert = $conn->prepare(
        "INSERT INTO backdate_requests (user_id, request_date, reason)
         VALUES (:user_id, :request_date, :reason)"
    );
    $insert->execute([
        "user_id" => $userId,
        "request_date" => $requestDate,
        "reason" => $reason
    ]);

    return ["ok" => true, "message" => "Backdate request sent to admin."];
}

function unreadNotificationCount(PDO $conn, int $userId): int {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
    $stmt->execute(["user_id" => $userId]);
    return (int)$stmt->fetchColumn();
}

function formatCurrency($amount): string {
    return number_format((float)$amount, 2);
}

function jsonResponse(array $payload, int $status = 200): void {
    http_response_code($status);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($payload);
    exit();
}

function maybeCreateDailyExpenseReminder(PDO $conn): void {
    if (!isLoggedIn()) {
        return;
    }
    if (($_SESSION["role_name"] ?? "") !== "Employee") {
        return;
    }

    $hour = (int)date("H");
    if ($hour < 18) {
        return;
    }

    $userId = (int)$_SESSION["user_id"];
    $today = todayDate();
    $title = "Daily Expense Reminder";

    $stmt = $conn->prepare("SELECT COUNT(*) FROM expenses WHERE user_id = :user_id AND expense_date = :d");
    $stmt->execute(["user_id" => $userId, "d" => $today]);
    if ((int)$stmt->fetchColumn() > 0) {
        return;
    }

    $exists = $conn->prepare(
        "SELECT COUNT(*)
         FROM notifications
         WHERE user_id = :user_id AND title = :title AND DATE(created_at) = :d"
    );
    $exists->execute(["user_id" => $userId, "title" => $title, "d" => $today]);
    if ((int)$exists->fetchColumn() > 0) {
        return;
    }

    notifyUser($conn, $userId, $title, "You have not added today's expenses (" . $today . "). Please add before end of day.");
}
?>
