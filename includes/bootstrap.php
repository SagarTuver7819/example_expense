<?php
require_once __DIR__ . "/../config/db.php";
require_once __DIR__ . "/functions.php";

if (isLoggedIn()) {
    $user = loadCurrentUser($conn, (int)$_SESSION["user_id"]);
    if (!$user) {
        session_destroy();
        redirect("login.php");
    }
    hydrateSessionFromUser($user);

    // Creates a reminder notification after 18:00 if today's expense is missing.
    maybeCreateDailyExpenseReminder($conn);
}
