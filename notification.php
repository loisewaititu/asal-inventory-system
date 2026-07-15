<?php
$message = $_GET['message'] ?? '';
$type = $_GET['type'] ?? 'success'; // 'success' or 'error'
?>

<!DOCTYPE html>
<html>
<head>
    <title>Notification</title>
    <style>
    body {
        /* Consistent gradient background from Akaroa to Tan */
        background: linear-gradient(to right, #D2BCA1, #D5B893); /* Akaroa, Tan */
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
        /* Default body text color to Coffee for readability */
        color: #6F4D38; /* Coffee */
    }

    .box {
        background: white; /* Clean white background for the box */
        padding: 35px 45px; /* More generous padding */
        border-radius: 15px;
        /* Richer shadow using Walnut */
        box-shadow: 0 10px 25px rgba(111, 72, 28, 0.3); /* Walnut shadow */
        width: 90%;
        max-width: 500px;
        text-align: center;
        box-sizing: border-box; /* Include padding in width */
    }

    h2 {
        /* Consistent heading color: Space Cadet */
        color: #25344F; /* Space Cadet */
        margin-bottom: 25px; /* More space below heading */
        font-size: 32px; /* Consistent heading size */
    }

    .success {
        /* Replaced green with Coffee for a success message that fits the palette */
        color: #6F4D38; /* Coffee */
        font-weight: bold;
        margin-bottom: 15px; /* Ensure spacing */
    }

    .error {
        /* Consistent error color: Caput Mortuum */
        color: #632024; /* Caput Mortuum */
        font-weight: bold;
        margin-bottom: 15px; /* Ensure spacing */
    }

    a {
        display: inline-block;
        margin-top: 25px; /* More space above the link/button */
        /* Consistent button background: Desert */
        background: #A76825; /* Desert */
        color: #fff;
        padding: 12px 25px; /* More padding for button */
        border-radius: 8px; /* More rounded button */
        text-decoration: none;
        font-weight: bold; /* Make link text bold */
        transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
    }

    a:hover {
        /* Consistent button hover: Walnut */
        background: #6F481C; /* Walnut */
        transform: translateY(-2px); /* Slight lift effect */
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); /* Deeper shadow on hover */
    }

    .toggle-container {
        margin-top: 25px; /* More space above the toggle */
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .toggle-label {
        font-size: 15px; /* Slightly larger font for label */
        /* Text color: Coffee */
        color: #6F4D38; /* Coffee */
        font-weight: 600;
    }

    .switch {
        position: relative;
        display: inline-block;
        width: 55px; /* Slightly wider switch */
        height: 28px; /* Slightly taller switch */
        margin-left: 12px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        /* Off state background: Slate Gray */
        background-color: #617891; /* Slate Gray */
        transition: 0.4s;
        border-radius: 34px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 22px; /* Slightly larger thumb */
        width: 22px; /* Slightly larger thumb */
        left: 3px;
        bottom: 3px;
        background-color: white; /* White thumb */
        transition: 0.4s;
        border-radius: 50%;
    }

    input:checked + .slider {
        /* On state background: Desert */
        background-color: #A76825; /* Desert */
    }

    input:checked + .slider:before {
        transform: translateX(27px); /* Adjusted transform for wider switch */
        /* Thumb color on checked: Space Cadet */
        background-color: #25344F; /* Space Cadet */
    }

    /* Responsive adjustments */
    @media screen and (max-width: 480px) {
        body {
            padding: 15px;
        }
        .box {
            padding: 25px 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(111, 72, 28, 0.2); /* Lighter shadow on small screens */
        }
        h2 {
            font-size: 28px;
            margin-bottom: 20px;
        }
        a {
            padding: 10px 18px;
            font-size: 15px;
            margin-top: 20px;
        }
        .toggle-container {
            margin-top: 20px;
        }
        .toggle-label {
            font-size: 14px;
        }
        .switch {
            width: 45px;
            height: 22px;
        }
        .slider:before {
            height: 16px;
            width: 16px;
            left: 3px;
            bottom: 3px;
        }
        input:checked + .slider:before {
            transform: translateX(23px);
        }
    }
</style>
</head>
<body>

<div class="box">
    <h2>Notification</h2>

    <?php if ($message): ?>
        <p class="<?= $type ?>"><?= htmlspecialchars($message) ?></p>
    <?php else: ?>
        <p>No message to show.</p>
    <?php endif; ?>

    <div class="toggle-container">
        <span class="toggle-label">Enable Notifications</span>
        <label class="switch">
            <input type="checkbox" id="toggleNotify">
            <span class="slider"></span>
        </label>
    </div>

    <a href="dashboard.php">← Back to Dashboard</a>
</div>

<!-- 🎵 Audio files -->
<audio id="successSound" src="success.mp3" preload="auto"></audio>
<audio id="errorSound" src="error.mp3" preload="auto"></audio>

<script>
    const message = <?= json_encode($message) ?>;
    const type = <?= json_encode($type) ?>;
    const toggle = document.getElementById('toggleNotify');
    const successSound = document.getElementById('successSound');
    const errorSound = document.getElementById('errorSound');

    // Load notification toggle from localStorage
    const notifyEnabled = localStorage.getItem('notify') === 'true';
    toggle.checked = notifyEnabled;

    // Update preference when user changes toggle
    toggle.addEventListener('change', () => {
        localStorage.setItem('notify', toggle.checked);
    });

    // Show alert and play sound if enabled
    if (message && notifyEnabled) {
        alert(message);
        if (type === 'success') {
            successSound.play();
        } else if (type === 'error') {
            errorSound.play();
        }
    }
</script>

</body>
</html>

