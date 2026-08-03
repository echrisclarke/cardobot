<?php
/**
 * Logout Handler for Card-o-Bot
 */

require_once __DIR__ . '/includes/auth.php';

// Log out the user
logout_user();

// Redirect to login page
$basePath = get_base_path();
header('Location: ' . $basePath . '/login.php');
exit;
