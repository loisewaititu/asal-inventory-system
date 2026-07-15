<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Prevent cached pages from being accessed after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ✅ Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: loginpage.php");
    exit();
}

// ✅ Auto logout after 1 hour of inactivity
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    header("Location: loginpage.php?message=session_expired");
    exit();
}
$_SESSION['last_activity'] = time(); // update last activity
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Dashboard - J.L Tracking System</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link rel="stylesheet" href="Dashboard.css" />
</head>
<body>
  <div class="sidebar">
    <h2>Menu</h2>
    <a href="#"><i class="fas fa-home"></i> Dashboard</a>
    <a href="About us.php"><i class="fas fa-info-circle"></i> About Us</a>
    <a href="search.php"><i class="fas fa-search"></i> Search</a>
    <a href="contact us.php"><i class="fas fa-envelope"></i> Contact Us</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>

  <div class="main">
    <h1>ICT Equipment Inventory</h1>

    <p class="intro">Click on a tech equipment below to fill in its details:</p>

    <!-- Equipment Grid -->
    <div class="equipment-list">
     <div class="equipment-item" data-name="desktop"><a href="device-details.php?type=desktop">Desktop</a></div>
<div class="equipment-item" data-name="laptop"><a href="device-details.php?type=laptop">Laptop</a></div>
<div class="equipment-item" data-name="ups"><a href="device-details.php?type=ups">UPS</a></div>
<div class="equipment-item" data-name="printer"><a href="device-details.php?type=printer">Printer</a></div>
<div class="equipment-item" data-name="scanner"><a href="device-details.php?type=scanner">Scanner</a></div>
<div class="equipment-item" data-name="tablet"><a href="device-details.php?type=tablet">Tablet</a></div>
<div class="equipment-item" data-name="wireless access point"><a href="device-details.php?type=wireless_access_point">Wireless Access Point</a></div>
<div class="equipment-item" data-name="projector"><a href="device-details.php?type=projector">Projector</a></div>
<div class="equipment-item" data-name="network switches"><a href="device-details.php?type=network_switches">Network Switches</a></div>
<div class="equipment-item" data-name="cctv"><a href="device-details.php?type=cctv">CCTV</a></div>
<div class="equipment-item" data-name="ip phone"><a href="device-details.php?type=ip_phone">IP Phone</a></div>
<div class="equipment-item" data-name="router"><a href="device-details.php?type=router">Router</a></div>
<div class="equipment-item" data-name="firewall"><a href="device-details.php?type=firewall">Firewall</a></div>
  </div>
  </div>
</body>
</html>
