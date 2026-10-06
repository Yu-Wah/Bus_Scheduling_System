<?php
// 1. Resume the current session so the server knows which one to destroy
session_start();

// 2. Remove all session variables
session_unset();

// 3. Destroy the session completely
session_destroy();

// 4. Redirect the user back to the login gateway
header("Location: login.php");
exit;
?>