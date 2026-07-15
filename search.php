<?php
session_start(); 
include 'connect.php';


$results = null; // Add this at the top of your logic

$dashboard_link = "login.php"; // Default if not logged in

if (isset($_SESSION['role']) && $_SESSION['role'] === 'user') {
    $dashboard_link = "Dashboard.php"; // Set correct user dashboard
}
elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $dashboard_link = "admin_dashboard.php";
}
elseif (isset($_SESSION['admin_id']) && $_SESSION['is_super_admin'] == 1) {
    $dashboard_link = "admin_dashboard.php"; // fallback
}



if (isset($_GET['autocomplete'], $_GET['type'], $_GET['q'])) {
    $term = "%" . $conn->real_escape_string($_GET['q']) . "%";
    $field = ($_GET['type'] === 'brand') ? 'brand' : 'model';

    // Using UNION ALL to search distinct brands or models across all equipment tables
    $stmt = $conn->prepare("
        SELECT DISTINCT $field FROM (
            SELECT brand, model, serial_no FROM desktop
            UNION ALL SELECT brand, model, serial_no FROM laptop
            UNION ALL SELECT brand, model, serial_no FROM tablet
            UNION ALL SELECT brand, model, serial_no FROM ups
            UNION ALL SELECT brand, model, serial_no FROM wireless_access_point
            UNION ALL SELECT brand, model, serial_no FROM projector
            UNION ALL SELECT brand, model, serial_no FROM printer
            UNION ALL SELECT brand, model, serial_no FROM scanner
            UNION ALL SELECT brand, model, serial_no FROM network_switches
            UNION ALL SELECT brand, model, serial_no FROM cctv
            UNION ALL SELECT brand, model, serial_no FROM ip_phone
            UNION ALL SELECT brand, model, serial_no FROM router
            UNION ALL SELECT brand, model, serial_no FROM firewall
        ) AS eq
        WHERE $field LIKE ?
        LIMIT 10
    ");
    $stmt->bind_param("s", $term);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($row = $res->fetch_assoc()) {
        $list[] = $row[$field];
    }
    echo json_encode($list);
    $stmt->close();
    // It's good practice to close the database connection when you're done,
    // especially for AJAX calls where you exit immediately.
    $conn->close();
    exit; // Exit after sending JSON response for AJAX
}

// === Main Search Logic ===
// This section handles the form submission when the user clicks "Search"
if (isset($_GET['search'])) {
    // Sanitize and retrieve input values from the form
    $brand = $conn->real_escape_string($_GET['brand'] ?? '');
    $model = $conn->real_escape_string($_GET['model'] ?? '');
    $serial_no = $conn->real_escape_string($_GET['serial_no'] ?? '');

    $clauses = []; // Array to hold the WHERE conditions

    // Add conditions if the respective input fields are not empty
    if ($brand) {
        $clauses[] = "brand LIKE '%$brand%'";
    }
    if ($model) {
        $clauses[] = "model LIKE '%$model%'";
    }
    if ($serial_no) {
        $clauses[] = "serial_no LIKE '%$serial_no%'";
    }

    // --- Start of New Logic for Empty Search ---
    // If no search criteria are provided (all fields are empty),
    // we don't execute the full UNION ALL query.
    if (empty($clauses)) {
        $results = null; // Set results to null to trigger "No results found." message in HTML
    } else {
        // Construct the WHERE clause if there are any search criteria
        $where = "WHERE " . implode(' AND ', $clauses);

        // List of all equipment tables to search through
        $tables = [
            'desktop', 'laptop', 'tablet', 'ups', 'wireless_access_point',
            'projector', 'printer', 'scanner', 'network_switches',
            'cctv', 'ip_phone', 'router', 'firewall'
        ];

        $unionQueries = []; // Array to hold individual SELECT queries for UNION ALL
        foreach ($tables as $table_name) {
            // Adjust assigned_to column for CCTV, as it doesn't have it.
            // Other tables should have 'assigned_to'.
            $assigned_to_column = ($table_name === 'cctv') ? "NULL AS assigned_to" : "assigned_to";

            $unionQueries[] = "
                SELECT '$table_name' AS equipment_type,
                       date, floor, room, department, brand, model, serial_no,
                       state, $assigned_to_column
                FROM $table_name $where
            ";
        }

        // Combine all SELECT queries using UNION ALL
        $sql = implode(" UNION ALL ", $unionQueries);

        // Execute the combined SQL query
        $results = $conn->query($sql);

        // Basic error handling for the search query
        if (!$results) {
            die("Search Database Error: " . $conn->error);
        }
    }
    // --- End of New Logic for Empty Search ---
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Search</title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="search1.css">
</head>
<body>

<div class="search-container">
    <h2>Search ICT Equipment</h2>

    <form method="get" action="search.php" autocomplete="off">
        <div class="form-group">
            <label for="brand">Brand:</label>
            <input type="text" id="brand" name="brand" value="<?= htmlspecialchars($_GET['brand'] ?? '') ?>">
            <div id="brand-suggestions" class="autocomplete-suggestions"></div>
        </div>

        <div class="form-group">
            <label for="model">Model:</label>
            <input type="text" id="model" name="model" value="<?= htmlspecialchars($_GET['model'] ?? '') ?>">
            <div id="model-suggestions" class="autocomplete-suggestions"></div>
        </div>

        <div class="form-group">
            <label for="serial_no">Serial Number:</label>
            <input type="text" id="serial_no" name="serial_no" value="<?= htmlspecialchars($_GET['serial_no'] ?? '') ?>">
        </div>

        <input type="submit" name="search" value="Search">
    </form>

    <a href="<?= htmlspecialchars($dashboard_link) ?>" style="
        display: inline-block;
        background-color:rgb(53, 25, 25);
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
        margin-top: 20px;
    ">
        ← Back to Dashboard
    </a>

    <?php
    // Display results if search was performed and results exist
    if ($results !== null && $results->num_rows > 0):
    ?>
        <h3>Search Results:</h3>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Equipment Type</th>
                        <th>Date</th>
                        <th>Floor</th>
                        <th>Room</th>
                        <th>Department</th>
                        <th>Brand</th>
                        <th>Model</th>
                        <th>Serial No</th>
                        <th>State</th>
                        <th>Assigned To</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $results->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['equipment_type']) ?></td>
                            <td><?= htmlspecialchars($row['date']) ?></td>
                            <td><?= htmlspecialchars($row['floor']) ?></td>
                            <td><?= htmlspecialchars($row['room']) ?></td>
                            <td><?= htmlspecialchars($row['department']) ?></td>
                            <td><?= htmlspecialchars($row['brand']) ?></td>
                            <td><?= htmlspecialchars($row['model']) ?></td>
                            <td><?= htmlspecialchars($row['serial_no']) ?></td>
                            <td><?= htmlspecialchars($row['state']) ?></td>
                            <td><?= htmlspecialchars($row['assigned_to'] ?? 'N/A') ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php
    // Display "No results found." if search was performed but no rows returned
    elseif (isset($_GET['search'])):
    ?>
        <p>No results found.</p>
    <?php endif; ?>
</div>

<script>
    // Function to set up autocomplete for an input field
    function setupAutocomplete(inputId, type) {
        const input = $("#" + inputId);
        const suggestionBox = $("#" + inputId + "-suggestions");

        input.on("input", function () {
            const query = $(this).val();
            // Only fetch suggestions if query is at least 2 characters long
            if (query.length >= 2) {
                // AJAX GET request to search.php for autocomplete data
                $.get("search.php", { autocomplete: 1, type: type, q: query }, function (data) {
                    const suggestions = JSON.parse(data);
                    suggestionBox.empty().show();
                    suggestions.forEach(s => {
                        suggestionBox.append(`<div>${s}</div>`);
                    });
                });
            } else {
                suggestionBox.hide();
            }
        });

        // Handle click on a suggestion item
        suggestionBox.on("click", "div", function () {
            input.val($(this).text());
            suggestionBox.hide();
        });

        // Hide suggestions when clicking outside the input or suggestion box
        $(document).on("click", function (e) {
            if (!$(e.target).closest(".form-group").length) {
                suggestionBox.hide();
            }
        });
    }

    // Initialize autocomplete for Brand and Model fields when the document is ready
    $(document).ready(function () {
        setupAutocomplete("brand", "brand");
        setupAutocomplete("model", "model");
    });
</script>

</body>
</html>