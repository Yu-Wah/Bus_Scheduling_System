<?php



require_once 'config.php';

// Load .env file into $_ENV
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#')
            continue;
        list($key, $value) = array_map('trim', explode('=', $line, 2));
        $_ENV[$key] = $value;
    }
}

$Bus_Routing_API = $_ENV['Bus_Routing_API'];
session_start();


// --- CATCH THE REDIRECT SIGNAL ---
// If a user is sent here from the Session Lock on another page, show this warning.
if (isset($_GET['error']) && $_GET['error'] == 'auth') {
    $error_message = "⚠️ Please log in first to access the dashboard.";
}

// ==========================================
// PULL ALL BUS ROUTES FROM DATABASE
// ==========================================
$host = 'sql103.infinityfree.com';
$dbname = 'if0_43124298_student';
$user = 'if0_43124298';
$pass = 'Uh84E2EjFR';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch ALL buses and their stops (with coordinates) in one query
    $sql = "SELECT r.bus_number, r.stop_sequence, r.student_id, s.lat, s.lng
            FROM bus_routes r
            JOIN students s ON r.student_id = s.student_code
            ORDER BY r.bus_number ASC, r.stop_sequence ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $all_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group rows by bus_number into a clean nested array
    $buses_data = [];
    foreach ($all_rows as $row) {
        $buses_data[(int) $row['bus_number']][] = $row;
    }

    $json_buses = json_encode($buses_data);
    $total_buses = count($buses_data);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$school = ['lat' => 18.797071, 'lng' => 99.032938];
$json_school = json_encode($school);
?>
<!DOCTYPE html>
<html>

<head>
    <title>ChronoPath: All Bus Routes</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body,
        html {
            height: 100%;
            font-family: 'Segoe UI', Tahoma, sans-serif;
        }

        #map {
            height: 100%;
            width: 100%;
        }

        /* ---- Side Panel ---- */
        #panel {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 280px;
            background: #1a237e;
            color: white;
            display: flex;
            flex-direction: column;
            z-index: 10;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease;
        }

        #panel.collapsed {
            transform: translateX(-280px);
        }

        #panel-header {
            padding: 18px 16px 12px;
            background: #0d1257;
            border-bottom: 1px solid rgba(215, 73, 73, 0.1);
        }

        #panel-header h2 {
            font-size: 17px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        #panel-header p {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 3px;
        }

        #bus-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px 0;
        }

        #bus-list::-webkit-scrollbar {
            width: 4px;
        }

        #bus-list::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
        }

        .bus-item {
            margin: 6px 10px;
            border-radius: 8px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: background 0.2s;
        }

        .bus-item.active {
            background: rgba(255, 255, 255, 0.14);
        }

        .bus-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            cursor: pointer;
        }

        .bus-dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            flex-shrink: 0;
            border: 2px solid rgba(255, 255, 255, 0.4);
        }

        /* NEW: Adjusted layout to stack title and stats */
        .bus-title-container {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .bus-label {
            font-weight: 600;
            font-size: 14px;
        }

        .bus-stats {
            font-size: 11px;
            color: #4CAF50;
            font-weight: bold;
            margin-top: 3px;
        }

        .bus-count {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.5);
        }

        .toggle-icon {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.4);
            transition: transform 0.2s;
        }

        .bus-item.active .toggle-icon {
            transform: rotate(90deg);
        }

        .bus-stops {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .bus-item.active .bus-stops {
            max-height: 600px;
        }

        .stop-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px 6px 36px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.75);
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .stop-num {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }

        /* ---- Toggle Button ---- */
        #toggle-btn {
            position: absolute;
            top: 50%;
            left: 280px;
            transform: translateY(-50%);
            z-index: 11;
            background: #1a237e;
            color: white;
            border: none;
            border-radius: 0 6px 6px 0;
            padding: 12px 6px;
            cursor: pointer;
            font-size: 14px;
            transition: left 0.3s ease;
            box-shadow: 3px 0 8px rgba(0, 0, 0, 0.2);
        }

        #panel.collapsed~#toggle-btn {
            left: 0;
        }

        /* ---- Legend ---- */
        #legend {
            position: absolute;
            bottom: 30px;
            right: 10px;
            background: white;
            border-radius: 8px;
            padding: 12px 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 5;
            min-width: 140px;
        }

        #legend h4 {
            font-size: 12px;
            color: #333;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            font-size: 12px;
            color: #444;
        }

        .legend-line {
            width: 24px;
            height: 4px;
            border-radius: 2px;
        }

        .legend-dot-school {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #0057ff;
            border: 2px solid white;
            box-shadow: 0 0 0 2px #0057ff;
        }

        #back-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 10;
            background: white;
            color: #1a237e;
            text-decoration: none;
            font-weight: bold;
            font-size: 13px;
            padding: 8px 14px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body>

    <div id="panel">
        <div id="panel-header">
            <h2>ChronoPath Live Routes</h2>
            <p><?php echo $total_buses; ?> bus<?php echo $total_buses !== 1 ? 'es' : ''; ?> active</p>
        </div>
        <div id="bus-list">
            <!-- Populated by JS -->
        </div>
    </div>

    <button id="toggle-btn" onclick="togglePanel()">◀</button>

    <div id="map"></div>

    <div id="legend">
        <h4>Bus route </h4>
        <div class="legend-item">
            <div class="legend-dot-school"></div>
            <span>School</span>
        </div>
        <!-- Bus color entries added by JS -->
    </div>

    <a href="index.php" id="back-btn">⬅ Dashboard</a>

    <script>
        // ── Data from PHP ──────────────────────────────────────────
        const busesData = <?php echo $json_buses; ?>;
        const schoolData = <?php echo $json_school; ?>;

        const BUS_COLORS = [
            '#F44336', '#FF9800', '#4CAF50', '#9C27B0',
            '#00BCD4', '#FF5722', '#3F51B5', '#009688',
            '#E91E63', '#8BC34A', '#FFC107', '#795548'
        ];

        let panelOpen = true;
        let busRenderers = {};
        let busMarkers = {};
        let busVisible = {};

        function togglePanel() {
            panelOpen = !panelOpen;
            document.getElementById('panel').classList.toggle('collapsed', !panelOpen);
            document.getElementById('toggle-btn').textContent = panelOpen ? '◀' : '▶';
            document.getElementById('toggle-btn').style.left = panelOpen ? '280px' : '0';
        }

        function toggleBusVisibility(busNum) {
            busVisible[busNum] = !busVisible[busNum];
            const visible = busVisible[busNum];

            if (busRenderers[busNum]) {
                busRenderers[busNum].setMap(visible ? map : null);
            }
            if (busMarkers[busNum]) {
                busMarkers[busNum].forEach(m => m.setMap(visible ? map : null));
            }

            const item = document.getElementById('bus-item-' + busNum);
            if (item) item.style.opacity = visible ? '1' : '0.4';
        }

        function expandBus(busNum) {
            const item = document.getElementById('bus-item-' + busNum);
            if (item) item.classList.toggle('active');
        }

        // ── Map Initialization ─────────────────────────────────────
        let map;

        function initMap() {
            const schoolPos = { lat: schoolData.lat, lng: schoolData.lng };

            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 13,
                center: schoolPos,
                mapTypeControl: false,
                streetViewControl: false,
                styles: [
                    { featureType: "poi", elementType: "labels", stylers: [{ visibility: "off" }] }
                ]
            });

            const directionsService = new google.maps.DirectionsService();
            const busNums = Object.keys(busesData);

            if (busNums.length === 0) {
                document.getElementById('bus-list').innerHTML =
                    "<p style='color:rgba(255,255,255,0.5); padding:20px; font-size:13px;'>No routes found.<br>Run the engine first.</p>";
                return;
            }

            // ── School Marker ──────────────────────────────────────
            new google.maps.Marker({
                position: schoolPos,
                map: map,
                title: 'School',
                zIndex: 9999,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 12,
                    fillColor: '#0057ff',
                    fillOpacity: 1,
                    strokeColor: 'white',
                    strokeWeight: 3
                }
            });

            const legend = document.getElementById('legend');
            const busList = document.getElementById('bus-list');

            // ── Process Each Bus ───────────────────────────────────
            busNums.forEach((busNum, idx) => {
                const color = BUS_COLORS[idx % BUS_COLORS.length];
                const stops = busesData[busNum];
                busVisible[busNum] = true;
                busMarkers[busNum] = [];

                // 1. Build sidebar entry with a placeholder for the stats
                const item = document.createElement('div');
                item.className = 'bus-item active';
                item.id = 'bus-item-' + busNum;

                let stopsHTML = '';
                stops.forEach((stop, si) => {
                    stopsHTML += `
                    <div class="stop-row">
                        <div class="stop-num" style="background:${color}">${si + 1}</div>
                        Student ${stop.student_id}
                    </div>`;
                });

                // Added the <div id="stats-..."> to hold the time and distance
                item.innerHTML = `
                <div class="bus-header" onclick="expandBus(${busNum})">
                    <div class="bus-dot" style="background:${color}"></div>
                    <div class="bus-title-container">
                        <span class="bus-label">Bus ${busNum}</span>
                        <span class="bus-stats" id="stats-${busNum}" style="color: rgba(255,255,255,0.6);">Calculating...</span>
                    </div>
                    <span class="bus-count">${stops.length} stops</span>
                    <span class="toggle-icon">▶</span>
                </div>
                <div class="bus-stops">
                    <div class="stop-row" style="font-style:italic; color:rgba(255,255,255,0.4);">
                        <div class="stop-num" style="background:#0057ff">🏫</div> Depart School
                    </div>
                    ${stopsHTML}
                    <div class="stop-row" style="font-style:italic; color:rgba(255,255,255,0.4);">
                        <div class="stop-num" style="background:#0057ff">🏫</div> Return to School
                    </div>
                </div>`;
                busList.appendChild(item);

                // 2. Add legend entry
                const li = document.createElement('div');
                li.className = 'legend-item';
                li.style.cursor = 'pointer';
                li.title = 'Click to toggle';
                li.innerHTML = `<div class="legend-line" style="background:${color}"></div> Bus ${busNum}`;
                li.onclick = () => toggleBusVisibility(busNum);
                legend.appendChild(li);

                // 3. Place numbered markers for each student stop
                stops.forEach((stop, si) => {
                    const pos = { lat: parseFloat(stop.lat), lng: parseFloat(stop.lng) };
                    const marker = new google.maps.Marker({
                        position: pos,
                        map: map,
                        zIndex: 100,
                        title: `Bus ${busNum} — Stop ${si + 1}: Student ${stop.student_id}`,
                        icon: {
                            path: google.maps.SymbolPath.CIRCLE,
                            scale: 14,
                            fillColor: color,
                            fillOpacity: 1,
                            strokeColor: 'white',
                            strokeWeight: 2
                        },
                        label: {
                            text: String(si + 1),
                            color: 'white',
                            fontSize: '11px',
                            fontWeight: 'bold'
                        }
                    });
                    busMarkers[busNum].push(marker);
                });

                // 4. Draw real driving route via Directions API
                const renderer = new google.maps.DirectionsRenderer({
                    map: map,
                    suppressMarkers: true,
                    polylineOptions: {
                        strokeColor: color,
                        strokeWeight: 5,
                        strokeOpacity: 0.85
                    }
                });
                busRenderers[busNum] = renderer;

                const waypoints = stops.map(stop => ({
                    location: { lat: parseFloat(stop.lat), lng: parseFloat(stop.lng) },
                    stopover: true
                }));

                if (waypoints.length <= 23) {
                    const request = {
                        origin: schoolPos,
                        destination: schoolPos,
                        waypoints: waypoints,
                        optimizeWaypoints: false,
                        travelMode: 'DRIVING'
                    };

                    directionsService.route(request, (result, status) => {
                        if (status === 'OK') {
                            renderer.setDirections(result);

                            // --- NEW FEATURE: Calculate and Display Totals ---
                            let totalDistanceMeters = 0;
                            let totalDurationSeconds = 0;
                            const legs = result.routes[0].legs; // The path is broken into "legs" between stops

                            // Add up the distance and duration for every leg of the journey
                            for (let i = 0; i < legs.length; i++) {
                                totalDistanceMeters += legs[i].distance.value;
                                totalDurationSeconds += legs[i].duration.value;
                            }

                            // Convert to readable formats (km and mins)
                            const finalDistance = (totalDistanceMeters / 1000).toFixed(1) + " km";
                            const finalDuration = Math.round(totalDurationSeconds / 60) + " min";

                            // Update the text and make it bright green to stand out
                            const statsElement = document.getElementById('stats-' + busNum);
                            statsElement.innerText = `⏱ ${finalDuration} • 🚗 ${finalDistance}`;
                            statsElement.style.color = '#4CAF50';
                            // --------------------------------------------------

                        } else {
                            console.warn(`Bus ${busNum} directions failed: ${status}`);
                            document.getElementById('stats-' + busNum).innerText = 'Metrics unavailable';
                            drawStraightPolyline(busNum, schoolPos, stops, color);
                        }
                    });
                } else {
                    document.getElementById('stats-' + busNum).innerText = 'Route too large';
                    drawStraightPolyline(busNum, schoolPos, stops, color);
                }
            });
        }

        function drawStraightPolyline(busNum, schoolPos, stops, color) {
            const path = [schoolPos];
            stops.forEach(s => path.push({ lat: parseFloat(s.lat), lng: parseFloat(s.lng) }));
            path.push(schoolPos);

            const poly = new google.maps.Polyline({
                path: path,
                geodesic: true,
                strokeColor: color,
                strokeOpacity: 0.7,
                strokeWeight: 4,
                map: map
            });

            busRenderers[busNum] = {
                setMap: (m) => poly.setMap(m)
            };
        }
    </script>

    <script async defer
        src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($Bus_Routing_API); ?>&callback=initMap">
        </script>
</body>

</html>