<?php
$host = 'localhost';
$dbname = 'student';

$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Count total registered students
    $stmt1 = $pdo->query("SELECT COUNT(*) as total_students FROM students");
    $total_students = $stmt1->fetch()['total_students'];

    // Get each bus number AND how many students are on it
    $stmt2 = $pdo->query("SELECT bus_number, COUNT(*) as total_stops FROM bus_routes GROUP BY bus_number ORDER BY bus_number ASC");
    $buses = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    $total_buses = count($buses);

}
catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ChronoPath Dashboard</title>
    <style>
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f2f5; color: #333; }
        
        .navbar { background-color: #1a237e; color: white; padding: 15px 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .navbar h2 { margin: 0; font-size: 24px; letter-spacing: 1px; text-align: center; }
        
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        
        /* Top Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 40px; }
        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; border-bottom: 4px solid #1a237e; }
        .card h3 { margin: 0; font-size: 40px; color: #1a237e; }
        .card p { margin: 10px 0 0 0; color: #666; font-size: 16px; font-weight: bold; }

        /* Bus Cards Section */
        .section-title { font-size: 18px; font-weight: bold; color: #333; margin: 30px 0 15px 0; border-left: 4px solid #1a237e; padding-left: 12px; }
        .bus-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; margin-bottom: 40px; }
        .bus-card { background: white; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); padding: 20px; text-align: center; border-top: 5px solid; transition: transform 0.2s; }
        .bus-card:hover { transform: translateY(-3px); }
        .bus-card .bus-num { font-size: 32px; font-weight: bold; margin-bottom: 6px; }
        .bus-card .bus-label { font-size: 13px; color: #888; }
        .bus-card .bus-stops { font-size: 13px; font-weight: bold; margin-top: 8px; color: #555; }
        .no-buses { color: #999; font-style: italic; padding: 15px 0; }

        /* Bus colors cycling */
        .bus-color-0 { border-color: #F44336; color: #F44336; }
        .bus-color-1 { border-color: #FF9800; color: #FF9800; }
        .bus-color-2 { border-color: #4CAF50; color: #4CAF50; }
        .bus-color-3 { border-color: #9C27B0; color: #9C27B0; }
        .bus-color-4 { border-color: #00BCD4; color: #00BCD4; }
        .bus-color-5 { border-color: #FF5722; color: #FF5722; }
        .bus-color-6 { border-color: #3F51B5; color: #3F51B5; }
        .bus-color-7 { border-color: #009688; color: #009688; }

        /* Actions Panel */
        .actions-section { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .actions-section h3 { margin-top: 0; color: #333; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .btn-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
        .btn { display: inline-block; padding: 15px 20px; text-decoration: none; color: white; text-align: center; border-radius: 6px; font-weight: bold; transition: 0.3s; }
        .btn-green  { background-color: #4CAF50; } .btn-green:hover  { background-color: #45a049; }
        .btn-blue   { background-color: #2196F3; } .btn-blue:hover   { background-color: #1e88e5; }
        .btn-purple { background-color: #9c27b0; } .btn-purple:hover { background-color: #8e24aa; }
        .btn-red    { background-color: #f44336; } .btn-red:hover    { background-color: #d32f2f; }

        .logout-wrap { text-align: right; margin-top: 30px; }
        .logout-wrap a { color: #888; text-decoration: none; font-size: 14px; }
        .logout-wrap a:hover { color: #f44336; }
    </style>
</head>
<body>

<div class="navbar">
    <h2>ChronoPath Engine</h2>
</div>

<div class="container">

    <!-- Top Stats -->
    <div class="stats-grid">
        <div class="card">
            <h3><?php echo $total_students; ?></h3>
            <p>Registered Students</p>
        </div>
        <div class="card">
            <h3><?php echo $total_buses; ?></h3>
            <p>Active Buses</p>
        </div>
        <div class="card" style="border-bottom-color: #4CAF50;">
            <h3 style="color: #4CAF50;">Online</h3>
            <p>System Status</p>
        </div>
    </div>

    <!-- Individual Bus Cards -->
    <div class="section-title">Active Bus Routes</div>

    <div class="bus-grid">
        <?php if (count($buses) > 0): ?>
            <?php foreach ($buses as $i => $bus): ?>
                <?php $colorClass = 'bus-color-' . ($i % 8); ?>
                <div class="bus-card <?php echo $colorClass; ?>">
                    <div class="bus-num">Bus <?php echo $bus['bus_number']; ?></div>
                    <div class="bus-label">Route Active</div>
                    <div class="bus-stops"><?php echo $bus['total_stops']; ?> students</div>
                </div>
            <?php
    endforeach; ?>
        <?php
else: ?>
            <p class="no-buses">No buses routed yet. Run the Routing Engine first.</p>
        <?php
endif; ?>
    </div>

    <!-- Action Buttons -->
    <div class="actions-section">
        <h3>System Controls</h3>
        <p style="color: #666;">Manage your database, generate bus-stop sequences, or view the map.</p>
        <div class="btn-grid">
            <a href="add_student.php"   class="btn btn-green">➕ Add New Student</a>
            <a href="engine.php"        class="btn btn-purple">⚙️ Run Routing Engine</a>
            <a href="map.php"           class="btn btn-blue" target="_blank">🗺️ View Map</a>
            <a href="delete_student.php" class="btn btn-red">🗑️ Delete Students</a>
        </div>
    </div>

    <div class="logout-wrap">
        <a href="log_out.php">🚪 Log Out</a>
    </div>

</div>

</body>
</html>


