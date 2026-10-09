<?php

session_start();
$error_message = "";

// --- CATCH THE REDIRECT SIGNAL ---
// If a user is sent here from the Session Lock on another page, show this warning.
if (isset($_GET['error']) && $_GET['error'] == 'auth') {
    $error_message = "⚠️ Please log in first to access the dashboard.";
}
// ==========================================
// 1. HELPER FUNCTIONS
// ==========================================

function calculateHybridScore($time_min, $dist_km, $alpha, $beta)
{
    // Normalize values so they are judged on an equal scale of 0.0 to 1.0
    // (Assuming 45 mins is the max possible time, and 20 km is max distance)
    $norm_time = $time_min / 45.0;
    $norm_dist = $dist_km / 20.0;

    return ($alpha * $norm_time) + ($beta * $norm_dist);
}
function getDistance_km($lat1, $lon1, $lat2, $lon2)
{
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);
    return $R * (2 * asin(sqrt($a)));
}

function getTrafficTime_min($student_lat, $student_lng, $distance_km)
{
    // 1. Array of Chiang Mai Traffic Hotspots
    $traffic_hotspots = [
        // The Original Core
        ['name' => 'Old City (Tha Phae Gate)', 'lat' => 18.7883, 'lng' => 98.9853],
        ['name' => 'Maya / Nimman', 'lat' => 18.8024, 'lng' => 98.9673],
        ['name' => 'Central Festival', 'lat' => 18.8066, 'lng' => 99.0183],
        ['name' => 'Arcade Bus Terminal', 'lat' => 18.8005, 'lng' => 99.0172],

        // --- NEW ADDITIONS ---
        // South / Airport Zone
        ['name' => 'Chiang Mai Airport / Plaza', 'lat' => 18.7694, 'lng' => 98.9754],
        // Riverside / Local Market Bottlenecks
        ['name' => 'Warorot Market (Kad Luang)', 'lat' => 18.7900, 'lng' => 99.0006],
        // West / University Rush Hour
        ['name' => 'CMU Front Gate / Malin', 'lat' => 18.8080, 'lng' => 98.9554],
        ['name' => 'Suan Dok / Hospital Area', 'lat' => 18.7885, 'lng' => 98.9730],
        // North / Ring Road Bottlenecks
        ['name' => 'Ruamchok Intersection', 'lat' => 18.8239, 'lng' => 99.0116],
        // South-East Bottlenecks
        ['name' => 'Nong Hoi Intersection', 'lat' => 18.7565, 'lng' => 99.0063]
    ];

    // 2. Finding which hotspot the student is closest to
    $closest_hotspot_dist = 999999;

    foreach ($traffic_hotspots as $spot) {
        $dist = getDistance_km($student_lat, $student_lng, $spot['lat'], $spot['lng']);
        if ($dist < $closest_hotspot_dist) {
            $closest_hotspot_dist = $dist;
        }
    }

    // 3. Application of the time multiplier based on the NEAREST hotspot
    if ($closest_hotspot_dist < 1.0) {
        // 🔴 RED ZONE: 4 minutes to drive 1 km
        return $distance_km * 4.0;

    } elseif ($closest_hotspot_dist < 2.5) {
        // 🟡 YELLOW ZONE: 2 minutes to drive 1 km
        return $distance_km * 2.0;

    } else {
        // 🟢 GREEN ZONE: 1 minute to drive 1 km
        return $distance_km * 1.0;
    }
}
// ==========================================
// 2. SWEEP ALGORITHM (Clustering)
// ==========================================

function SweepClustering($students, $school, $bus_capacity)
{
    foreach ($students as $index => $student) {
        $y_distance = $student['lat'] - $school['lat'];
        $x_distance = $student['lng'] - $school['lng'];
        $students[$index]['angle'] = atan2($y_distance, $x_distance);
    }
    usort($students, function ($student_A, $student_B) {
        return $student_A['angle'] <=> $student_B['angle'];
    });
    return array_chunk($students, $bus_capacity);
}

// ==========================================
// 3. ROUTING ENGINE (Farthest-First + Hybrid)
// ==========================================

function buildBusRoute($bus_students, $school, $alpha, $beta)
{
    $route = [];
    $bus_students = array_values($bus_students);

    // --- STEP 1: Find the FARTHEST student from school ---
    $farthest_index = -1;
    $max_dist = -1;
    foreach ($bus_students as $index => $student) {
        $dist = getDistance_km($school['lat'], $school['lng'], $student['lat'], $student['lng']);
        if ($dist > $max_dist) {
            $max_dist = $dist;
            $farthest_index = $index;
        }
    }

    // Add farthest student as Stop #1
    $current = $bus_students[$farthest_index];
    $route[] = $current;
    unset($bus_students[$farthest_index]);

    // --- STEP 2: Hybrid Nearest Neighbor loop ---
    while (count($bus_students) > 0) {
        $best_idx = -1;
        $lowest_score = 999999;

        foreach ($bus_students as $index => $student) {
            $dist = getDistance_km($current['lat'], $current['lng'], $student['lat'], $student['lng']);
            $time = getTrafficTime_min($student['lat'], $student['lng'], $dist);
            // Using the dynamic admin weights here
            $score = calculateHybridScore($time, $dist, $alpha, $beta);

            if ($score < $lowest_score) {
                $lowest_score = $score;
                $best_idx = $index;
            }
        }

        $current = $bus_students[$best_idx];
        $route[] = $current;
        unset($bus_students[$best_idx]);
    }

    return $route;
}

// ==========================================
// 4. ADMIN CONTROL PANEL & EXECUTION
// ==========================================

// Default Settings
$alpha = 0.7;
$beta = 0.3;

// Catch the form submission if the admin clicks "Run"
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_engine'])) {
    $alpha = (float) $_POST['alpha'];
    $beta = (float) $_POST['beta'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Routing Engine Setup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }

        .admin-box {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin-bottom: 30px;
            border-top: 4px solid #1a237e;
        }

        .admin-box h2 {
            margin-top: 0;
            color: #1a237e;
        }

        .input-group {
            margin-bottom: 15px;
        }

        .input-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }

        .input-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .btn-run {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }

        .btn-run:hover {
            background: #45a049;
        }

        .results-box {
            max-width: 600px;
        }


        .btn-dashboard {
            position: absolute;
            top: 20px;
            right: 20px;
            background: white;
            color: #1a237e;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
            padding: 10px 16px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            transition: background 0.2s ease;
        }

        .btn-dashboard:hover {
            background: #f4f4f4;
        }
    </style>
</head>

<body>

    <!-- THE ADMIN FORM -->
    <div class="admin-box">
        <h2> ChronoPath Configuration</h2>
        <form method="POST" action="">
            <div class="input-group">
                <label>Time Weight (&alpha;) - Prioritize Time</label>
                <input type="number" name="alpha" step="0.1" min="0" max="1"
                    value="<?php echo htmlspecialchars($alpha); ?>" required>
            </div>
            <div class="input-group">
                <label>Distance Weight (&beta;) - Prioritize Distance</label>
                <input type="number" name="beta" step="0.1" min="0" max="1"
                    value="<?php echo htmlspecialchars($beta); ?>" required>
            </div>
            <button type="submit" name="run_engine" class="btn-run">Calculate Routes</button>
        </form>
    </div>

    <div class="results-box">
        <?php
        // Only run the database connection and engine IF the form was submitted
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_engine'])) {

            $host = 'sql103.infinityfree.com';
            $dbname = 'if0_43124298_student';
            $user = 'if0_43124298';
            $pass = 'Uh84E2EjFR';
            $school = ['id' => 'School', 'lat' => 18.797071, 'lng' => 99.032938];
            $bus_capacity = 5;

            try {
                $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $stmt = $pdo->prepare("SELECT student_code AS id, lat, lng FROM students");
                $stmt->execute();
                $students_from_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "<h2>Routing Results (Using &alpha;={$alpha}, &beta;={$beta})</h2>";

                if (count($students_from_db) === 0) {
                    echo "<p style='color:red;'>No students found. Please add students first.</p>";
                } else {
                    // B. Cluster into bus groups
                    $bus_groups = SweepClustering($students_from_db, $school, $bus_capacity);

                    echo "<p>Total <strong>" . count($students_from_db) . " students</strong> divided into <strong>" . count($bus_groups) . " bus(es)</strong>.</p><hr>";

                    // C. Clear ALL old routes
                    $pdo->query("DELETE FROM bus_routes");

                    // D. Route EVERY bus and save to DB
                    $insert_stmt = $pdo->prepare("INSERT INTO bus_routes (bus_number, stop_sequence, student_id) VALUES (?, ?, ?)");

                    foreach ($bus_groups as $index => $group) {
                        $bus_num = $index + 1;

                        // PASSING THE ADMIN'S ALPHA AND BETA HERE
                        $final_route = buildBusRoute($group, $school, $alpha, $beta);

                        echo "<div style='background:white; padding:15px; margin-bottom:15px; border-radius:5px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);'>";
                        echo "<h3 style='margin-top:0; color:#2196F3;'>Bus {$bus_num} — " . count($final_route) . " students</h3>";
                        echo "<ol>";
                        foreach ($final_route as $stop) {
                            echo "<li>Pick up Student <strong>{$stop['id']}</strong></li>";
                        }
                        echo "<li><strong>Return to School</strong></li>";
                        echo "</ol>";
                        echo "</div>";

                        $stop_order = 1;
                        foreach ($final_route as $stop) {
                            $insert_stmt->execute([$bus_num, $stop_order, $stop['id']]);
                            $stop_order++;
                        }
                    }

                    echo "<h3 style='color:green;'>✅ All " . count($bus_groups) . " bus route(s) saved!</h3>";
                    echo "<p><a href='map.php' style='color:#2196F3; font-weight:bold; text-decoration:none;'>🗺️ View All Routes on Map ➔</a></p>";
                }

            } catch (PDOException $e) {
                die("Database error: " . $e->getMessage());
            }
        } else {
            // Message to show before they click run
            echo "<p style='color:#666;'><em>Please adjust the weights above and click 'Calculate Routes' to generate the schedules.</em></p>";
        }
        ?>
    </div>

    <body>

        <a href="index.php" class="btn-dashboard">⬅ Dashboard</a>


    </body>

</html>