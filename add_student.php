<?php

// Log errors instead of showing sensitive details to visitors.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

// Load .env
$envFile = __DIR__ . '/.env';

if (is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);

        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // Remove optional surrounding quotes.
            $value = trim($value, "\"'");

            $_ENV[$key] = $value;
        }
    }
}

session_start();

$message = '';
$error_message = '';

if (isset($_GET['error']) && $_GET['error'] === 'auth') {
    $error_message = 'Please log in first to access the dashboard.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Validate submitted values.
        $student_code = trim($_POST['student_code'] ?? '');
        $student_name = trim($_POST['student_name'] ?? '');
        $raw_address = trim($_POST['address'] ?? '');

        if ($student_code === '' || $student_name === '' || $raw_address === '') {
            throw new RuntimeException(
                'Please fill in all required fields.'
            );
        }

        // 2. Get Google API key.
        $apiKey = $_ENV['Std_add_API'] ?? '';

        if ($apiKey === '') {
            throw new RuntimeException(
                'Google API key is missing from the environment configuration.'
            );
        }

        // 3. Geocode the address.
        $searchAddress = $raw_address . ', Chiang Mai, Thailand';

        $googleUrl =
            'https://maps.googleapis.com/maps/api/geocode/json?' .
            http_build_query([
                'address' => $searchAddress,
                'key' => $apiKey
            ]);

        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents(
            $googleUrl,
            false,
            $context
        );

        if ($response === false) {
            throw new RuntimeException(
                'Could not connect to the Google Geocoding API.'
            );
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            throw new RuntimeException(
                'Google returned an invalid response.'
            );
        }

        if (($data['status'] ?? '') !== 'OK') {
            // Log the API status, but don't expose the API key.
            error_log(
                'Geocoding API status: ' .
                ($data['status'] ?? 'UNKNOWN') .
                '; details: ' .
                ($data['error_message'] ?? 'No details')
            );

            throw new RuntimeException(
                'Address lookup failed. Check the address or API configuration.'
            );
        }

        $location = $data['results'][0]['geometry']['location'] ?? null;

        if (
            !is_array($location) ||
            !isset($location['lat'], $location['lng'])
        ) {
            throw new RuntimeException(
                'Google did not return valid coordinates.'
            );
        }

        $lat = $location['lat'];
        $lng = $location['lng'];

        // 4. Connect to the database using .env credentials.
        $host = $_ENV['DB_HOST'] ?? '';
        $dbname = $_ENV['DB_NAME'] ?? '';
        $dbuser = $_ENV['DB_USER'] ?? '';
        $dbpass = $_ENV['DB_PASS'] ?? '';

        if ($host === '' || $dbname === '' || $dbuser === '' || $dbpass === '') {
            throw new RuntimeException(
                'Database configuration is incomplete.'
            );
        }

        $pdo = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $dbuser,
            $dbpass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );

        // IMPORTANT:
        // Replace students with your REAL table name.
        // The table must contain student_code, student_name,
        // home_address, lat and lng columns.
        $sql = "INSERT INTO students
                (student_code, student_name, home_address, lat, lng)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $student_code,
            $student_name,
            $raw_address,
            $lat,
            $lng
        ]);

        $message = 'Student registered successfully.';

    } catch (Throwable $e) {
        // Full technical details go to the server error log.
        error_log(
            'Student registration error: ' .
            get_class($e) . ': ' . $e->getMessage()
        );

        // Visitors see only a safe message.
        $message = 'Registration failed. Check the server error log.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Add Student Address</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        .form-box {
            background: #f4f4f4;
            padding: 20px;
            max-width: 300px;
            border-radius: 8px;
            margin: 0 auto;
        }

        input {
            width: 100%;
            padding: 8px;
            margin-top: 5px;
            margin-bottom: 15px;
            box-sizing: border-box;
        }

        button {
            background: #197cce;
            color: white;
            padding: 10px;
            width: 100%;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        h2 {
            text-align: center;
        }

        .message {
            text-align: center;
            margin: 15px 0;
        }
    </style>
</head>

<body>

    <h2>Register New Student</h2>

    <?php if ($error_message !== ''): ?>
        <p class="message" style="color: red;">
            <?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <?php if ($message !== ''): ?>
        <p class="message" style="color: <?= $message === 'Student registered successfully.' ? 'green' : 'red' ?>;">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <div class="form-box">
        <form method="POST" action="">
            <label for="student_code">Student ID</label>
            <input id="student_code" type="text" name="student_code" required placeholder="e.g., 6605040056">

            <label for="student_name">Name</label>
            <input id="student_name" type="text" name="student_name" required>

            <label for="address">Home Address</label>
            <input id="address" type="text" name="address" required placeholder="e.g., 123 Nimman Road">

            <button type="submit">Submit</button>
        </form>
    </div>

</body>

</html>