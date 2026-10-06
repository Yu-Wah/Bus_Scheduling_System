<?php
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$Std_Add_API = $_ENV['Std_add_API'];

require_once 'config.php';


session_start();
$error_message = "";

// --- CATCH THE REDIRECT SIGNAL ---
// If a user is sent here from the Session Lock on another page, show this warning.
if (isset($_GET['error']) && $_GET['error'] == 'auth') {
    $error_message = "⚠️ Please log in first to access the dashboard.";
}

$message = "";

// checking the submit-button click
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $student_code = $_POST['student_code'];
    $raw_address = $_POST['address'];

    // to protect miss-match address with another country. Here our school asumed located in ChiangMai/Thailand
    $search_address = $raw_address . ", Chiang Mai, Thailand";

    //  Google API Call
 

    // URL encode the address (changes spaces to %20 so it works in a web link)
    $encoded_address = urlencode($search_address);
    $google_url = "https://maps.googleapis.com/maps/api/geocode/json?address={$encoded_address}&key={$Std_Add_API}";

    // getting lat,lng coordinates response back from Google
    $response = file_get_contents($google_url);
    $data = json_decode($response, true);

    // checking the system
    if ($data['status'] == 'OK') {
        // Extract the exact coordinates from Google's JSON reply
        $lat = $data['results'][0]['geometry']['location']['lat'];
        $lng = $data['results'][0]['geometry']['location']['lng'];

        // adding to our Database
        $host = 'localhost';
        $dbname = 'student';
        $user = 'root';
        $pass = '';
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);

        $sql = "INSERT INTO students (student_code, home_address, lat, lng) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$student_code, $raw_address, $lat, $lng]);

        $message = "<div style='color: green;'>✅ Success! Student {$student_code} saved at Coordinates: {$lat}, {$lng}</div>";
    } else {
        $message = "<div style='color: red;'>❌ Error: Google could not find that address. Please be more specific.</div>";
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Add Student Address</title>
    <style>
        body {
            font-family: Arial;
            padding: 20px;
        }

        .form-box {
            background: #f4f4f4;
            padding: 20px;
            width: 300px;
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
            background: #197cceff;
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
    </style>
</head>

<body>

    <h2>Register New Student </h2>

    <?php echo $message; ?>

    <div class="form-box">
        <form method="POST" action="">
            <label>Student ID </label>
            <input type="text" name="student_code" required placeholder="e.g., 6605040056">

            <label>Name:</label>
            <input type="text" name="student_name" required>

            <label>Home Address :</label>
            <input type="text" name="address" placeholder="e.g., 123 Nimman Road" required>

            <button type="submit">Submit</button>

        </form>
    </div>

</body>

</html>