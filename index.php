<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect(BASE_URL . 'login.php');
}

// Redirect to appropriate dashboard based on role
if (isAdmin()) {
    redirect(BASE_URL . 'admin/dashboard.php');
} else {
    redirect(BASE_URL . 'user/dashboard.php');
}
?>
