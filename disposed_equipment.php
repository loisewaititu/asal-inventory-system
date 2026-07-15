<?php
require 'connect.php';
session_start();

if (!isset($_SESSION['admin_id'])) {
    // header("Location: login.php");
    // exit();
}

function safe($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

$stmt = $conn->prepare("SELECT * FROM disposed_equipment ORDER BY disposed_at DESC");
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disposed Equipment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <style>
    body {
        font-family: Arial, sans-serif;
        background: rgba(210, 188, 161, 0.3);
        margin: 0;
        padding: 20px;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        min-height: 100vh;
        box-sizing: border-box;
    }

    .container {
        width: 90%;
        max-width: 1200px;
        background: white;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 8px 30px rgba(111, 72, 28, 0.2);
    }

    h2 {
        color: #6F4D38;
        font-size: 36px;
        margin-top: 0;
        margin-bottom: 30px;
        text-align: left;
        letter-spacing: 1px;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        border-bottom: 3px solid #A76825;
        padding-bottom: 10px;
        display: inline-block;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        border-spacing: 0;
        background: #ffffff;
        border-radius: 10px;
        overflow: hidden;
    }

    th, td {
        padding: 15px;
        text-align: left;
        border-bottom: 1px solid #D2BCA1;
        font-size: 15px;
        color: #6F4D38;
    }

    th {
        background-color: #632024;
        color: #D5B893;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }

    th:first-child {
        border-top-left-radius: 10px;
    }
    th:last-child {
        border-top-right-radius: 10px;
    }

    tr:nth-child(even) {
        background-color: #D2BCA1;
    }

    tr:hover {
        background-color: #D5B893;
        color: #6F4D38;
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    .no-data-message {
        text-align: center;
        padding: 30px;
        font-size: 18px;
        color: #6F4D38;
        border: 2px dashed #D5B893;
        border-radius: 10px;
        margin-top: 20px;
        background-color: rgba(255, 255, 255, 0.6);
    }

    /* Styles for the new button */
    .btn {
        display: inline-block;
        padding: 10px 20px;
        font-size: 16px;
        font-weight: bold;
        text-align: center;
        text-decoration: none;
        border-radius: 8px;
        cursor: pointer;
        transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        margin-top: 20px; /* Add some space above the button */
        margin-bottom: 20px; /* Add some space below the button */
    }

    .btn-primary {
        background-color: #A76825; /* A primary color that fits your theme */
        color: white;
        border: 2px solid #A76825;
    }

    .btn-primary:hover {
        background-color: #8C5620; /* Darker shade on hover */
        border-color: #8C5620;
    }

    @media (max-width: 768px) {
        .container {
            padding: 15px;
            width: 95%;
        }
        h2 {
            font-size: 28px;
            text-align: center;
            display: block;
        }
        th, td {
            padding: 10px;
            font-size: 14px;
        }
        table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
        }
        table thead, table tbody tr {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        table tbody {
            display: block;
            max-height: 500px;
            overflow-y: auto;
        }
        table th, table td {
            min-width: 120px;
        }
    }

    @media (max-width: 480px) {
        h2 {
            font-size: 24px;
        }
        th, td {
            padding: 8px;
            font-size: 12px;
        }
        table th, table td {
            min-width: 100px;
        }
        .btn {
            padding: 8px 15px;
            font-size: 14px;
        }
    }
</style>
</head>
<body>

<div class="container">
    <h2>Disposed Equipment</h2>

    <?php if ($result && $result->num_rows > 0): ?>
        <a href="download_disposed_report.php" class="btn btn-primary" target="_blank">Download PDF Report</a>

        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Original ID</th>
                    <th>Serial Number</th>
                    <th>Reason</th>
                    <th>Disposed By</th>
                    <th>Disposed At</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= safe($row['equipment_type']) ?></td>
                    <td><?= safe($row['equipment_id']) ?></td>
                    <td><?= safe($row['serial_no']) ?></td>
                    <td><?= safe($row['reason']) ?></td>
                    <td><?= safe($row['disposed_by']) ?></td>
                    <td><?= safe($row['disposed_at']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="no-data-message">No disposed equipment found.</p>
    <?php endif; ?>
</div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
