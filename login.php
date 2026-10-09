<?php
require_once __DIR__ . '/env_loader.php';

session_start();
$error_message = "";

// --- CATCH THE REDIRECT SIGNAL ---
// If a user is sent here from the Session Lock on another page, show this warning.
if (isset($_GET['error']) && $_GET['error'] == 'auth') {
    $error_message = "⚠️ Please log in first to access the dashboard.";
}
// ---------------------------------

// 1. Process the login attempt
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Connect to database using environment variables
    $host = $_ENV['DB_HOST'] ?? '';
    $dbname = $_ENV['DB_NAME'] ?? '';
    $user = $_ENV['DB_USER'] ?? '';
    $pass = $_ENV['DB_PASS'] ?? '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Fetch the admin user from the database
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify the secure hashed password
        if ($admin && password_verify($password, $admin['password'])) {
            // 2. Initiate Secure PHP Session with a unique token
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['session_token'] = bin2hex(random_bytes(32)); // Security token

            // Redirect to the main dashboard
            header("Location: index.php");
            exit;
        } else {
            // Trigger the UI error message for wrong passwords
            $error_message = "❌ Invalid username or password!";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>ChronoPath Login</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #1a237e;
            /* Matches your dashboard blue */
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .login-box {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            width: 320px;
            text-align: center;
        }

        .login-box h2 {
            color: #1a237e;
            margin-bottom: 5px;
            font-size: 28px;
        }

        .login-box p {
            color: #666;
            margin-bottom: 25px;
            font-size: 14px;
        }

        .error-msg {
            color: #d32f2f;
            background-color: #fde0dc;
            border: 1px solid #f9bdbb;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: bold;
            font-size: 14px;
        }

        .input-group {
            text-align: left;
            margin-bottom: 15px;
        }

        .input-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 13px;
            color: #333;
        }

        .input-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .btn-login {
            background-color: #2196F3;
            color: white;
            border: none;
            padding: 12px;
            width: 100%;
            border-radius: 4px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            margin-top: 10px;
            transition: 0.3s;
        }

        .btn-login:hover {
            background-color: #1e88e5;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <h2>ChronoPath</h2>
        <p>Administrator Login</p>

        <?php if (!empty($error_message)): ?>
            <div class="error-msg">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" required
                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">Log In</button>
        </form>
    </div>
</body>

</html>