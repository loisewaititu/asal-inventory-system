<?php
require_once('tcpdf/tcpdf.php');
session_start();
require_once 'connect.php';
require_once 'includes/audit_logger.php';

// Access check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: loginpage.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "jl_tracking_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$filter = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : 'all';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

// Final shared columns across all equipment types for PDF report
// Final shared columns across all equipment types for PDF report
$columns = ['type', 'date', 'floor', 'room', 'department', 'brand', 'model', 'serial_no', 'state'];

$equipmentQueries = [
    'desktop' => "SELECT 'Desktop' AS type, date, floor, room, department, brand, model, serial_no, state FROM desktop",
    'laptop' => "SELECT 'Laptop' AS type, date, floor, room, department, brand, model, serial_no, state FROM laptop",
    'cctv' => "SELECT 'CCTV' AS type, date, floor, room, department, brand, model, serial_no, state FROM cctv",
    'scanner' => "SELECT 'Scanner' AS type, date, floor, room, department, brand, model, serial_no, state FROM scanner",
    'printer' => "SELECT 'Printer' AS type, date, floor, room, department, brand, model, serial_no, state FROM printer",
    'firewall' => "SELECT 'Firewall' AS type, date, floor, room, department, brand, model, serial_no, state FROM firewall",
    'ip_phone' => "SELECT 'IP Phone' AS type, date, floor, room, department, brand, model, serial_no, state FROM ip_phone",
    'network_switches' => "SELECT 'Network Switch' AS type, date, floor, room, department, brand, model, serial_no, state FROM network_switches",
    'projector' => "SELECT 'Projector' AS type, date, floor, room, department, brand, model, serial_no, state FROM projector",
    'router' => "SELECT 'Router' AS type, date, floor, room, department, brand, model, serial_no, state FROM router",
    'tablet' => "SELECT 'Tablet' AS type, date, floor, room, department, brand, model, serial_no, state FROM tablet",
    'ups' => "SELECT 'UPS' AS type, date, floor, room, department, brand, model, serial_no, state FROM ups",
    'wireless_access_point' => "SELECT 'Wireless AP' AS type, date, floor, room, department, brand, model, serial_no, state FROM wireless_access_point"
];


// Build SQL
if ($filter !== 'all' && isset($equipmentQueries[$filter])) {
    $sql = $equipmentQueries[$filter];
    if (!empty($search)) {
        $sql .= " WHERE serial_no LIKE '%$search%'";
    }
} else {
    $unionQueries = array_values($equipmentQueries);
    $sql = implode(" UNION ", $unionQueries);
    if (!empty($search)) {
        $sql = "SELECT * FROM ($sql) AS combined WHERE serial_no LIKE '%$search%'";
    }
}

// Execute
$result = $conn->query($sql);
if (!$result) {
    die("Query failed: " . $conn->error);
}

// Build HTML table
// Build HTML table
$html = '<table border="1" cellspacing="0" cellpadding="4">
<thead>
<tr style="background-color:#f2f2f2;">
    <th><b>No.</b></th>'; // Added Serial Number Column

foreach ($columns as $col) {
    $html .= '<th><b>' . htmlspecialchars(ucwords(str_replace("_", " ", $col))) . '</b></th>';
}
$html .= '</tr></thead><tbody>';

$counter = 1; // Counter for numbering

while ($row = $result->fetch_assoc()) {
    $html .= '<tr>';
    $html .= '<td>' . $counter++ . '</td>'; // Add row number

    foreach ($columns as $col) {
        $value = isset($row[$col]) && $row[$col] !== '' ? htmlspecialchars($row[$col]) : 'N/A'; // Avoid empty cells
        $html .= '<td>' . $value . '</td>';
    }

    $html .= '</tr>';
}
$html .= '</tbody></table>';

class MYPDF extends TCPDF {
    // Custom Header
    public function Header() {
        // Logo centered
       $logo = 'logo.jpeg';
       $logoWidth = 25; // reduced from 40 to 25
       $pageWidth = $this->getPageWidth();
       $x = ($pageWidth - $logoWidth) / 2;

// Logo
     $this->Image($logo, $x, 10, $logoWidth);
     $this->SetY(45);
 // Move below logo + some space

        // Title
       // Title Block
$this->SetFont('helvetica', 'B', 13); // slightly smaller font

$this->Cell(0, 7, 'REPUBLIC OF KENYA', 0, 1, 'C');

$this->Ln(1); // tighter spacing

$this->Cell(0, 7, 'MINISTRY OF EAST AFRICAN COMMUNITY, THE ASALS AND REGIONAL DEVELOPMENT', 0, 1, 'C');

$this->Ln(1); // tighter spacing

$this->Cell(0, 7, 'STATE DEPARTMENT OF ASALS AND REGIONAL DEVELOPMENT', 0, 1, 'C');

$this->Ln(3); // small gap before subheading

// Subheading with report date
$this->SetFont('helvetica', '', 10);
$this->Cell(0, 6, 'Equipment Report - ' . date('Y-m-d'), 0, 1, 'C');

// Add tighter spacing before the table starts
$this->Ln(10);
    }
    // Empty Footer (we'll manually add signature only once later)
    public function Footer() {}
}

// Create PDF
$pdf = new MYPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('JL Tracking System');
$pdf->SetAuthor('Admin');
$pdf->SetTitle('Equipment Report');

// Margins
$pdf->SetMargins(10, 70, 10); // Top margin large enough for header
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(0);
$pdf->SetAutoPageBreak(TRUE, 25); // Room at bottom for manual signature

$pdf->AddPage();
$pdf->SetFont('helvetica', '', 8);

// Add spacing between header and data rows
$html = str_replace('<thead>', '<thead><tr><td colspan="8" style="height:10px;"></td></tr>', $html);

// Output table
$pdf->writeHTML($html, true, false, true, false, '');

// Signature section only ONCE at the end, manually
// Space between table and signature section
// Check if enough space remains on the current page for signature section
if ($pdf->GetY() > 160) {
    $pdf->AddPage();
}

$pdf->Ln(15);
$pdf->SetFont('helvetica', '', 10);

// Name placeholders aligned like signatures
$pdf->Cell(95, 6, 'Name: __________________________', 0, 0, 'C');
$pdf->Cell(0, 6, 'Name: __________________________', 0, 1, 'C');

$pdf->Ln(5);
// Date placeholders aligned under each name
$pdf->Cell(95, 6, 'Date: __________________________', 0, 0, 'C');
$pdf->Cell(0, 6, 'Date: __________________________', 0, 1, 'C');

$pdf->Ln(8); // Space before signature lines

// Signature lines
$pdf->Cell(95, 10, 'Signature: __________________________', 0, 0, 'C');
$pdf->Cell(0, 10, 'Signature: __________________________', 0, 1, 'C');

$pdf->Cell(95, 5, 'Deputy Director, ICT', 0, 0, 'C');
$pdf->Cell(0, 5, 'Senior ICT Officer', 0, 1, 'C');


// Output the file
$pdf->Output('equipment_report.pdf', 'D');
exit();
