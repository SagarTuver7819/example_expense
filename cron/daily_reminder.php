<?php
// Run via Windows Task Scheduler / cron:
// php C:\xampp\htdocs\expense_management\cron\daily_reminder.php

require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/../includes/functions.php";

$force = in_array("--force", $argv ?? [], true);
$hour = (int)date("H");
if ($hour < 18 && !$force) {
    echo "Skip: before 18:00. Use --force to run anyway." . PHP_EOL;
    exit(0);
}

$today = todayDate();
$title = "Daily Expense Reminder";
$message = "You have not added today's expenses (" . $today . "). Please add before end of day.";

$usersStmt = $conn->query(
    "SELECT u.id
     FROM users u
     INNER JOIN roles r ON r.id = u.role_id AND r.name = 'Employee'
     WHERE u.status = 'Active'"
);
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

$created = 0;
$hasExpenseStmt = $conn->prepare("SELECT COUNT(*) FROM expenses WHERE user_id = :user_id AND expense_date = :d");
$existsStmt = $conn->prepare(
    "SELECT COUNT(*)
     FROM notifications
     WHERE user_id = :user_id AND title = :title AND DATE(created_at) = :d"
);

foreach ($users as $u) {
    $userId = (int)$u["id"];
    $hasExpenseStmt->execute(["user_id" => $userId, "d" => $today]);
    if ((int)$hasExpenseStmt->fetchColumn() > 0) {
        continue;
    }

    $existsStmt->execute(["user_id" => $userId, "title" => $title, "d" => $today]);
    if ((int)$existsStmt->fetchColumn() > 0) {
        continue;
    }

    notifyUser($conn, $userId, $title, $message);
    $created++;
}

echo "Reminder notifications created: " . $created . PHP_EOL;
