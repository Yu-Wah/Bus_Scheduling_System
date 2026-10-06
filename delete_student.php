<?php

session_start();
$error_message = "";

// --- CATCH THE REDIRECT SIGNAL ---
// If a user is sent here from the Session Lock on another page, show this warning.
if (isset($_GET['error']) && $_GET['error'] == 'auth') {
    $error_message = "⚠️ Please log in first to access the dashboard.";
}

$host = 'localhost';
$dbname = 'student';
$user = 'root';
$pass = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}


$message = "";


// ==========================================
// 1. MATH ENGINE FUNCTIONS
// ==========================================
function getDistance_km($lat1, $lon1, $lat2, $lon2)
{
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    return $R * (2 * asin(sqrt($a)));
}
function calculateHybridScore($time, $dist, $alpha = 0.7, $beta = 0.3)
{
    return ($alpha * ($time / 60)) + ($beta * ($dist / 100));
}
function SweepClustering($students, $school, $capacity)
{
    foreach ($students as $i => $s) {
        $students[$i]['angle'] = atan2($s['lat'] - $school['lat'], $s['lng'] - $school['lng']);
    }
    usort($students, function ($a, $b) {
        return $a['angle'] <=> $b['angle'];
    });
    return array_chunk($students, $capacity);
}
function buildBusRoute($bus_students, $school)
{
    $route = [];
    $farthest_idx = -1;
    $max_dist = -1;
    foreach ($bus_students as $i => $s) {
        $dist = getDistance_km($school['lat'], $school['lng'], $s['lat'], $s['lng']);
        if ($dist > $max_dist) {
            $max_dist = $dist;
            $farthest_idx = $i;
        }
    }
    $curr = $bus_students[$farthest_idx];
    $route[] = $curr;
    unset($bus_students[$farthest_idx]);
    while (count($bus_students) > 0) {
        $best_idx = -1;
        $lowest = 999999;
        foreach ($bus_students as $i => $s) {
            $dist = getDistance_km($curr['lat'], $curr['lng'], $s['lat'], $s['lng']);
            $score = calculateHybridScore($dist * 1.5, $dist);
            if ($score < $lowest) {
                $lowest = $score;
                $best_idx = $i;
            }
        }
        $curr = $bus_students[$best_idx];
        $route[] = $curr;
        unset($bus_students[$best_idx]);
    }
    return $route;
}


// ==========================================
// 2. PROCESS DELETION & AUTO-RECALCULATE ALL BUSES
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_selected'])) {
    if (!empty($_POST['student_ids'])) {
        $ids_to_delete = $_POST['student_ids'];

        // A. Delete selected students
        $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
        $pdo->prepare("DELETE FROM students WHERE student_code IN ($placeholders)")->execute($ids_to_delete);

        // B. Clear ALL old bus routes
        $pdo->query("TRUNCATE TABLE bus_routes");

        // C. Fetch remaining students
        $remaining = $pdo->query("SELECT student_code AS id, lat, lng FROM students")->fetchAll(PDO::FETCH_ASSOC);

        // D. Re-run engine for ALL buses if students remain
        if (count($remaining) > 0) {
            $school = ['lat' => 18.797071, 'lng' => 99.032938];
            $bus_capacity = 5;
            $bus_groups = SweepClustering($remaining, $school, $bus_capacity);

            $insert_stmt = $pdo->prepare("INSERT INTO bus_routes (bus_number, stop_sequence, student_id) VALUES (?, ?, ?)");

            foreach ($bus_groups as $index => $group) {
                $bus_num = $index + 1;
                $final_route = buildBusRoute($group, $school);
                $stop_order = 1;
                foreach ($final_route as $stop) {
                    $insert_stmt->execute([$bus_num, $stop_order, $stop['id']]);
                    $stop_order++;
                }
            }

            $num_buses = count($bus_groups);
            $message = "<div class='success'>✅ Removed " . count($ids_to_delete) . " student(s). Routes recalculated across {$num_buses} bus(es)!</div>";
        } else {
            $message = "<div class='success'>✅ Removed " . count($ids_to_delete) . " student(s). No students remaining.</div>";
        }
    } else {
        $message = "<div class='error'>⚠️ Please select at least one student to delete.</div>";
    }
}


// ==========================================
// 3. FETCH CURRENT STUDENTS FOR DISPLAY
// ==========================================
$students = $pdo->query("SELECT * FROM students ORDER BY student_code ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Manage Students</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #1a237e;
            margin-top: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #1a237e;
            color: white;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .btn-danger {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 4px;
            font-weight: bold;
            margin-top: 20px;
        }

        .btn-danger:hover {
            background-color: #d32f2f;
        }

        .btn-back {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #2196F3;
            font-weight: bold;
        }

        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .select-all-row {
            padding: 8px 12px;
            background: #f5f5f5;
        }
    </style>
</head>

<body>
    <div class="container">
        <a href="index.php" class="btn-back">⬅ Return to Dashboard</a>
        <h2>👥 Manage Registered Students</h2>
        <p>Select students to remove. All bus routes will be automatically regenerated after deletion.</p>

        <?php echo $message; ?>

        <form method="POST" action="">
            <table>
                <tr>
                    <th><input type="checkbox" id="select-all" title="Select all"></th>
                    <th>Student ID</th>
                    <th>Home Address</th>
                </tr>
                <?php if (count($students) > 0): ?>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><input type="checkbox" name="student_ids[]"
                                    value="<?php echo htmlspecialchars($student['student_code']); ?>" class="row-check"></td>
                            <td><strong><?php echo htmlspecialchars($student['student_code']); ?></strong></td>
                            <td><?php echo htmlspecialchars($student['home_address']); ?></td>
                        </tr>
                        <?php
                    endforeach; ?>
                    <?php
                else: ?>
                    <tr>
                        <td colspan="3" style="text-align:center; color:#888;">No students in the database.</td>
                    </tr>
                    <?php
                endif; ?>
            </table>

            <?php if (count($students) > 0): ?>
                <button type="submit" name="delete_selected" class="btn-danger"
                    onclick="return confirm('Are you sure? This will delete the selected students and recalculate all bus routes.');">
                    🗑️ Delete Selected & Recalculate All Routes
                </button>
                <?php
            endif; ?>
        </form>
    </div>

    <script>
        // Select-all checkbox logic
        document.getElementById('select-all').addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
        });
    </script>
</body>

</html>