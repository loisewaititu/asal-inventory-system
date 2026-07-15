<?php
session_start();

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    header("Location: loginpage.php?message=session_expired");
    exit();
}
$_SESSION['last_activity'] = time();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: loginpage.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "jl_tracking_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$room = isset($_GET['room']) ? $conn->real_escape_string($_GET['room']) : '';
$date = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : '';
$whereClause = '';

if (!empty($room) || !empty($date)) {
    $conditions = [];
    if (!empty($room)) $conditions[] = "room = '$room'";
    if (!empty($date)) $conditions[] = "date = '$date'";
    $whereClause = 'WHERE ' . implode(' AND ', $conditions);
}

// Auto-detect equipment tables
$equipmentTables = [];
$skipKeywords = ['user', 'staff', 'register', 'archive', 'contact', 'ownership'];
$tablesResult = $conn->query("SHOW TABLES");
while ($row = $tablesResult->fetch_array()) {
    $tableName = $row[0];
    $skip = false;
    foreach ($skipKeywords as $keyword) {
        if (stripos($tableName, $keyword) !== false) {
            $skip = true;
            break;
        }
    }
    if (!$skip) {
        $equipmentTables[] = $tableName;
    }
}

if (empty($equipmentTables)) {
    die("No equipment tables found in the database.");
}

$queries = [];
foreach ($equipmentTables as $table) {
    $queries[] = "SELECT '$table' AS equipment_type, COUNT(*) AS total FROM `$table` $whereClause";
}
$sql = implode(" UNION ALL ", $queries);

$result = $conn->query($sql);
$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

// Fetch daily changes for the pop-up
$dailyChanges = [];
if (!empty($date)) {
    foreach ($equipmentTables as $table) {
        // Assuming each table has a 'date_added' or similar column for changes
        // This is a placeholder query, you might need to adjust column names
        $changesQuery = "SELECT * FROM `$table` WHERE DATE(date_added) = '$date'";
        $changesResult = $conn->query($changesQuery);
        if ($changesResult && $changesResult->num_rows > 0) {
            while ($row = $changesResult->fetch_assoc()) {
                $dailyChanges[] = [
                    'type' => ucfirst($table),
                    'item' => $row['item_name'] ?? 'Unknown Item', // Adjust to your actual column name
                    'action' => 'Added', // Or fetch from a log if you have one
                    'time' => $row['date_added'] ?? 'Unknown Time'
                ];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>📊 Equipment Analysis</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon/fonts/remixicon.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg);
            color: var(--text);
            transition: all 0.5s ease;
        }

        :root {
            --bg: linear-gradient(to bottom right, #D2BCA1, #D5B893);
            --card-bg: #ffffff;
            --text: #25344F;
            --accent: #A76825;
            --hover: rgba(167, 104, 37, 0.15);
        }

        body.dark {
            --bg: linear-gradient(to bottom right, #25344F, #273F5B);
            --card-bg: #2F3D57;
            --text: #E0DCE0;
            --accent: #D5B893;
            --hover: rgba(213, 184, 147, 0.1);
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            font-size: 3rem;
            color: var(--accent);
            font-weight: 800;
            margin-bottom: 40px;
            letter-spacing: 1px;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
        }

        .filters input, .filters button {
            padding: 10px 15px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            background: var(--card-bg);
            color: var(--text);
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .filters button {
            background: var(--accent);
            color: #fff;
            cursor: pointer;
        }

        .filters button:hover {
            background: #6F4D38;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }

        .card {
            background: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            text-align: center;
            padding: 25px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.2);
        }

        .card i {
            font-size: 2.5rem;
            color: var(--accent);
            margin-bottom: 10px;
        }

        .card h3 {
            margin: 0;
            font-size: 1.4rem;
        }

        .card p {
            margin: 8px 0 0;
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--text);
        }

        .charts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            justify-content: center;
        }

        canvas {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 15px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        canvas:hover {
            transform: scale(1.03);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .mode-toggle {
            position: fixed;
            top: 20px;
            right: 25px;
            font-size: 1.8rem;
            cursor: pointer;
            color: var(--accent);
        }

        /* Pop-up styles */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .popup-content {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            color: var(--text);
            position: relative;
        }

        .popup-content h2 {
            margin-top: 0;
            color: var(--accent);
            font-size: 1.8rem;
            text-align: center;
            margin-bottom: 20px;
        }

        .popup-content ul {
            list-style: none;
            padding: 0;
        }

        .popup-content li {
            padding: 10px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-size: 1.1rem;
        }

        .popup-content li:last-child {
            border-bottom: none;
        }

        .popup-close {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 1.8rem;
            cursor: pointer;
            color: var(--text);
        }

        .popup-close:hover {
            color: var(--accent);
        }
    </style>
</head>
<body>
    <div class="mode-toggle" onclick="toggleMode()">
        <i class="ri-moon-line" id="modeIcon"></i>
    </div>
    <div class="container">
        <div class="header">📊 Equipment Overview</div>

        <div class="filters">
            <form method="GET">
                <input type="text" name="room" placeholder="Filter by room" value="<?= htmlspecialchars($room) ?>">
                <input type="date" name="date" value="<?= htmlspecialchars($date) ?>">
                <button type="submit">Apply Filters</button>
            </form>
            <?php if (!empty($date)): ?>
                <button onclick="showDailyChanges()">View Daily Changes for <?= htmlspecialchars($date) ?></button>
            <?php endif; ?>
        </div>

        <div class="summary">
            <?php foreach ($data as $row): ?>
                <div class="card">
                    <i class="ri-device-line"></i>
                    <h3><?= ucfirst($row['equipment_type']) ?></h3>
                    <p><?= $row['total'] ?> items</p>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="charts">
            <canvas id="barChart"></canvas> <canvas id="pieChart"></canvas>
        </div>
    </div>

    <div class="popup-overlay" id="dailyChangesPopup">
        <div class="popup-content">
            <span class="popup-close" onclick="hideDailyChanges()">&times;</span>
            <h2 id="popupTitle">Daily Changes</h2>
            <ul id="changesList">
                </ul>
        </div>
    </div>
 <a href="admin_dashboard.php" style="
        display: inline-block;
        background-color:rgb(53, 25, 25);
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
    ">
        ← Back to Dashboard
    </a>
    <script>
        const chartData = <?= json_encode($data) ?>;
        const dailyChangesData = <?= json_encode($dailyChanges) ?>;
        const selectedDate = "<?= htmlspecialchars($date) ?>";

        const ctxBar = document.getElementById('barChart').getContext('2d');
        const ctxPie = document.getElementById('pieChart').getContext('2d');

        const themedColors = [
            '#A76825', '#D5B893', '#6F4D38', '#D2BCA1', '#25344F', '#E0DCE0', '#617891'
        ];

        // Bar Chart (behind)
        const barChart = new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: chartData.map(item => item.equipment_type),
                datasets: [{
                    label: 'Equipment Count',
                    data: chartData.map(item => item.total),
                    backgroundColor: themedColors,
                    borderRadius: 8
                }]
            },
            options: {
                indexAxis: 'y', // Makes the bars go sideways (horizontal)
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        titleFont: {
                            size: 18 // Make hover title big
                        },
                        bodyFont: {
                            size: 16 // Make hover body big
                        },
                        padding: 12 // Increase padding for bigger hover box
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Items',
                            font: {
                                size: 14
                            }
                        },
                        ticks: {
                            font: {
                                size: 12
                            }
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: 'Equipment Type',
                            font: {
                                size: 14
                            }
                        },
                        ticks: {
                            font: {
                                size: 12
                            }
                        }
                    }
                }
            }
        });

        // Pie Chart (front)
        const pieChart = new Chart(ctxPie, {
            type: 'pie',
            data: {
                labels: chartData.map(item => item.equipment_type),
                datasets: [{
                    data: chartData.map(item => item.total),
                    backgroundColor: themedColors,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    tooltip: {
                        enabled: true,
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += context.parsed + ' items';
                                }
                                return label;
                            }
                        },
                        titleFont: {
                            size: 18 // Make hover title big
                        },
                        bodyFont: {
                            size: 16 // Make hover body big
                        },
                        padding: 12 // Increase padding for bigger hover box
                    },
                    legend: {
                        position: 'right',
                        labels: {
                            font: {
                                size: 14
                            }
                        }
                    }
                }
            }
        });

        function toggleMode() {
            document.body.classList.toggle('dark');
            const icon = document.getElementById('modeIcon');
            icon.className = document.body.classList.contains('dark') ? 'ri-sun-line' : 'ri-moon-line';
            pieChart.update();
            barChart.update();
        }

        // Pop-up functions
        function showDailyChanges() {
            const popup = document.getElementById('dailyChangesPopup');
            const changesList = document.getElementById('changesList');
            const popupTitle = document.getElementById('popupTitle');

            popupTitle.textContent = `Daily Changes for ${selectedDate}`;
            changesList.innerHTML = ''; // Clear previous entries

            if (dailyChangesData.length === 0) {
                changesList.innerHTML = '<li>No changes recorded for this date.</li>';
            } else {
                dailyChangesData.forEach(change => {
                    const li = document.createElement('li');
                    li.textContent = `${change.time}: ${change.action} - ${change.item} (${change.type})`;
                    changesList.appendChild(li);
                });
            }
            popup.style.display = 'flex'; // Show the pop-up
        }

        function hideDailyChanges() {
            document.getElementById('dailyChangesPopup').style.display = 'none'; // Hide the pop-up
        }
    </script>
</body>
</html>