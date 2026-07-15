<?php
session_start();
ob_start();

require_once('tcpdf/tcpdf.php');
require_once 'connect.php';
require_once 'includes/audit_logger.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: loginpage.php");
    exit();
}

class DisposedReportPDF extends TCPDF {
    public function Header() {
        $logo = 'logo.jpeg';
        $logoWidth = 40;
        $pageWidth = $this->getPageWidth();
        $x = ($pageWidth - $logoWidth) / 2;
        $this->Image($logo, $x, 10, $logoWidth);

        $this->SetY(55); // Start of "REPUBLIC OF KENYA" after logo
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 8, 'REPUBLIC OF KENYA', 0, 1, 'C');
        $this->Ln(2);

        $this->SetFont('helvetica', 'B', 11);
        $this->Cell(0, 8, 'MINISTRY OF EAST AFRICAN COMMUNITY, THE ASALS AND REGIONAL DEVELOPMENT', 0, 1, 'C');
        $this->Ln(2);

        $this->SetFont('helvetica', 'B', 11);
        $this->Cell(0, 8, 'STATE DEPARTMENT OF ASALS AND REGIONAL DEVELOPMENT', 0, 1, 'C');
        $this->Ln(5); // Increased space before report title

        $this->SetFont('helvetica', 'B', 13);
        $this->Cell(0, 8, 'DISPOSED EQUIPMENT REPORT - ' . date('d F Y'), 0, 1, 'C');
        // No Ln() here. The main document's SetMargins will handle the space after the header.
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$sql = "SELECT * FROM disposed_equipment ORDER BY disposed_at DESC";
$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error);
}

$html = '<table border="1" cellspacing="0" cellpadding="5">
<thead>
<tr style="background-color:#f2f2f2;">
    <th><b>No.</b></th>
    <th><b>Type</b></th>
    <th><b>Original ID</b></th>
    <th><b>Serial Number</b></th>
    <th><b>Reason</b></th>
    <th><b>Disposed By</b></th>
    <th><b>Disposed At</b></th>
</tr>
</thead>
<tbody>';

$no = 1;
while ($row = $result->fetch_assoc()) {
    $html .= '<tr>';
    $html .= '<td>' . $no++ . '</td>';
    $html .= '<td>' . htmlspecialchars($row['equipment_type'] ?? 'N/A') . '</td>';
    $html .= '<td>' . htmlspecialchars($row['equipment_id'] ?? 'N/A') . '</td>';
    $html .= '<td>' . htmlspecialchars($row['serial_no'] ?? 'N/A') . '</td>';
    $html .= '<td>' . htmlspecialchars($row['reason'] ?? 'N/A') . '</td>';
    $html .= '<td>' . htmlspecialchars($row['disposed_by'] ?? 'N/A') . '</td>';
    $html .= '<td>' . htmlspecialchars(date('d M Y', strtotime($row['disposed_at'] ?? ''))) . '</td>';
    $html .= '</tr>';
}

$html .= '</tbody></table>';

$pdf = new DisposedReportPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('JL Tracking System');
$pdf->SetAuthor('Admin');
$pdf->SetTitle('Disposed Equipment Report');

// Crucial change: Calculate top margin based on header content height
// Logo (10mm top + 40mm height) = 50mm
// SetY(55) for "REPUBLIC OF KENYA"
// 4 lines of text (approx 8mm height each + 2-5mm Ln) = 4 * (8+2) = 40mm
// 55 (SetY) + 8 (Republic) + 2 (Ln) + 8 (Ministry) + 2 (Ln) + 8 (State Dept) + 5 (Ln) + 8 (Report Title) = 96mm
// Add some buffer, e.g., 10-15mm, to ensure table starts cleanly below.
$pdf->SetMargins(10, 110, 10); // Left, Top (adjusted), Right
$pdf->SetHeaderMargin(0); // Set to 0, as Header() manages its own Y position
$pdf->SetAutoPageBreak(TRUE, 25);

$pdf->AddPage();
$pdf->SetFont('helvetica', '', 9);

$pdf->writeHTML($html, true, false, true, false, '');

$pdf->Ln(35);
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

if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    log_audit(
        $_SESSION['user_id'],
        $_SESSION['username'],
        'REPORT_DOWNLOADED',
        'disposed_equipment',
        null,
        null,
        null,
        'Disposed equipment report downloaded.'
    );
}

ob_end_clean();
$pdf->Output('disposed_equipment_report.pdf', 'D');
exit();
