<?php
require_once __DIR__ . "/includes/bootstrap.php";

if (isLoggedIn()) {
    redirect("dashboard.php");
}
redirect("login.php");
