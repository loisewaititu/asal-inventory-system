<?php
// Start session
session_start();


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - State Department for ASALs & Regional Development</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="About_us.css">
</head>
<body>

    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="logo">ASALs & Regional Dev</div>
            <ul class="nav-links">
                <li><a href="Dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> Admin dashboard</a></li>
                <li><a href="About us.php" class="active"><i class="fas fa-info-circle"></i> About Us</a></li>
                <li><a href="contact us.php"><i class="fas fa-envelope"></i> Contact</a></li>
                <li><a href="loginpage.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <header>
        <h1>About Us</h1>
        <p>State Department for Arid and Semi-Arid Lands (ASALs) and Regional Development</p>
    </header>

    <div class="content">

        <div class="section">
            <h2>Who We Are</h2>
            <p>
                The State Department for the ASALs and Regional Development was established through Executive Order No. 1 of January 2023, merging the functions of the ASALs with those of Regional Development. This reorganization aimed to coordinate and accelerate integrated development in the ASALs and basin-based regions. The department's expanded mandate includes Special Programmes, Food Relief Management, Humanitarian Emergency Response, and Projects in Response to Displacement Impacts.
            </p>
        </div>

        <div class="section">
            <h2>Vision</h2>
            <p>Transformed and sustained lives and livelihoods.</p>
        </div>

        <div class="section">
            <h2>Mission</h2>
            <p>To coordinate planning and development in ASALs and basin-based regions for inclusive, resilient, and sustainable livelihoods.</p>
        </div>

        <div class="section">
            <h2>Core Values</h2>
            <ul class="values-list">
                <li>Innovativeness and Creativity</li>
                <li>Inclusivity</li>
                <li>Integrity</li>
                <li>Professionalism</li>
            </ul>
        </div>

        <div class="section">
            <h2>Strategic Goals</h2>
            <ul class="goals-list">
                <li>Reduce climate change effects</li>
                <li>Promote peaceful co-existence among communities</li>
                <li>Improve food and nutrition security</li>
                <li>Enhance regional and ASALs development</li>
                <li>Attain financial sustainability</li>
                <li>Accelerate adoption of knowledge management</li>
                <li>Improve service delivery</li>
            </ul>
        </div>

        <div class="section">
            <h2>Our Leadership</h2>
            <div class="leadership">
                <div class="leader">
                    <h3>Hon. Peninah Malonza, OGW</h3>
                    <p>Cabinet Secretary, Ministry of EAC, ASALs and Regional Development</p>
                </div>
                <div class="leader">
                    <h3>PS. Harsama Kello</h3>
                    <p>Principal Secretary, State Department for Arid & Semi-Arid Lands and Regional Development</p>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Contact Us</h2>
            <p>
                State Department for Arid and Semi-Arid Lands (ASALs), and Regional Development<br>
                Hazina Trade Centre, Moktar Daddah Street<br>
                P.O.Box 40213 – 00100, Nairobi, Kenya<br>
                Tel: +254-20-3317641/2/3<br>
                Email: <a href="mailto:ps@asals.go.ke">ps@asals.go.ke</a><br>
                Website: <a href="https://www.asalrd.go.ke" target="_blank">www.asalrd.go.ke</a>
            </p>
        </div>

    </div>
    <div style="text-align: center; margin: 40px 0;">
    <a href="Dashboard.php" style="
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
</div>

    <footer>
        <p>&copy; 2025 State Department for ASALs and Regional Development. All rights reserved.</p>
    </footer>

</body>
</html>
