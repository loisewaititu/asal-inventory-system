<?php
require_once('tcpdf/tcpdf.php');
require_once 'connect.php';
require_once 'includes/audit_logger.php';
session_start();

$current_admin_id = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';

$department = $_GET['department'] ?? '';
$show_all = empty($department);

// Query
if ($show_all) {
    $stmt = $conn->prepare("SELECT id, First_Names, Last_Name, designation, Personal_number, email, department FROM staff ORDER BY department, First_Names");
} else {
    $stmt = $conn->prepare("SELECT id, First_Names, Last_Name, designation, Personal_number, email, department FROM staff WHERE department = ? ORDER BY First_Names");
    $stmt->bind_param("s", $department);
}
$stmt->execute();
$result = $stmt->get_result();

// Custom TCPDF class
class StaffPDF extends TCPDF {
    public function Header() {}
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().' of '.$this->getAliasNbPages(), 0, 0, 'C');
    }
}

// PDF setup
$pdf = new StaffPDF();
$pdf->SetCreator('ASALs ICT System');
$pdf->SetAuthor('Admin');
$pdf->SetTitle($show_all ? "Staff Report - All Departments" : "Staff Report - $department");
$pdf->SetMargins(10, 10, 10);
$pdf->SetHeaderMargin(0);
$pdf->SetFooterMargin(15);
$pdf->SetAutoPageBreak(true, 25);
$pdf->AddPage();

// Add logo and header
$logo = 'logo.jpeg';
$logoWidth = 40;
$x = ($pdf->getPageWidth() - $logoWidth) / 2;
$pdf->Image($logo, $x, 10, $logoWidth);

$pdf->Ln(45);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'REPUBLIC OF KENYA', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 8, 'MINISTRY OF EAST AFRICAN COMMUNITY, THE ASALS AND REGIONAL DEVELOPMENT', 0, 1, 'C');
$pdf->Cell(0, 10, 'STATE DEPARTMENT FOR THE ASALS AND REGIONAL DEVELOPMENT', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 8, 'Staff Report - ' . date('Y-m-d'), 0, 1, 'C');
$pdf->Ln(5);

// Report title
$report_title = $show_all ? "All Departments" : htmlspecialchars($department);

// HTML table with numbering
$html = "
<style>
    table {
        border-collapse: collapse;
        width: 100%;
    }
    th, td {
        border: 1px solid #000;
        padding: 5px;
        font-size: 10pt;
    }
    th {
        background-color: #f2e6ff;
        font-weight: bold;
        text-align: center;
    }
    h2 {
        text-align: center;
        color: darkmagenta;
    }
</style>
<h2>Staff Report - $report_title</h2>
<table>
<tr>
    <th>No.</th>
    <th>First Name</th>
    <th>Last Name</th>
    <th>Designation</th>
    <th>Personal No</th>
    <th>Email</th>
    <th>Department</th>
</tr>";

if ($result->num_rows > 0) {
    $count = 1;
    while ($row = $result->fetch_assoc()) {
        $html .= "<tr>
            <td style='text-align: center;'>" . $count++ . "</td>
            <td>" . htmlspecialchars($row['First_Names']) . "</td>
            <td>" . htmlspecialchars($row['Last_Name']) . "</td>
            <td>" . htmlspecialchars($row['designation']) . "</td>
            <td>" . htmlspecialchars($row['Personal_number']) . "</td>
            <td>" . htmlspecialchars($row['email']) . "</td>
            <td>" . htmlspecialchars($row['department']) . "</td>
        </tr>";
    }
} else {
    $html .= "<tr><td colspan='7'>No staff records found.</td></tr>";
}

$html .= "</table>";

// Output table
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Ln(20);

// Signatures
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(95, 6, 'Name: __________________________', 0, 0, 'C');
$pdf->Cell(0, 6, 'Name: __________________________', 0, 1, 'C');

$pdf->Ln(5);
$pdf->Cell(95, 6, 'Date: __________________________', 0, 0, 'C');
$pdf->Cell(0, 6, 'Date: __________________________', 0, 1, 'C');

$pdf->Ln(10);
$pdf->Cell(95, 10, 'Signature: __________________________', 0, 0, 'C');
$pdf->Cell(0, 10, 'Signature: __________________________', 0, 1, 'C');

$pdf->Cell(95, 5, 'Deputy Director, ICT', 0, 0, 'C');
$pdf->Cell(0, 5, 'Senior ICT Officer', 0, 1, 'C');

// Output PDF
$filename = $show_all ? "Staff_Report_All_Departments.pdf" : "Staff_Report_{$department}.pdf";
$pdf->Output($filename, 'D');
exit;
?>
