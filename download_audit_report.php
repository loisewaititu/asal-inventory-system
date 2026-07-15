<?php
require('tcpdf/tcpdf.php');
require('connect.php');

class AuditPDF extends TCPDF {
    public function Header() {
        $logo = 'logo.jpeg';
        $logoWidth = 40;
        $pageWidth = $this->getPageWidth();
        $x = ($pageWidth - $logoWidth) / 2;
        $this->Image($logo, $x, 10, $logoWidth);

        $this->SetY(55);
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 8, 'REPUBLIC OF KENYA', 0, 1, 'C');
        $this->Ln(2);

        $this->SetFont('helvetica', 'B', 11);
        $this->Cell(0, 8, 'MINISTRY OF EAST AFRICAN COMMUNITY, THE ASALS AND REGIONAL DEVELOPMENT', 0, 1, 'C');
        $this->Ln(2);

        $this->SetFont('helvetica', 'B', 11);
        $this->Cell(0, 8, 'STATE DEPARTMENT OF ASALS AND REGIONAL DEVELOPMENT', 0, 1, 'C');
        $this->Ln(3);

        $this->SetFont('helvetica', 'B', 14);
        $this->Cell(0, 8, 'Audit Trail', 0, 1, 'C');
        $this->Ln(6);
    }

    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new AuditPDF('P', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator('JL Tracking System');
$pdf->SetAuthor('Admin');
$pdf->SetTitle('Audit Trail Report');
$pdf->SetMargins(10, 105, 10);
$pdf->SetAutoPageBreak(TRUE, 25);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

$query = "SELECT * FROM audit_log ORDER BY timestamp DESC";
$result = $conn->query($query);

$currentDate = '';
$row_number = 0;

// Define column widths in millimeters (must sum up to page width minus margins, e.g., 210 - 20 = 190 for A4 Portrait)
// A4 Portrait width is 210mm. Left/Right margins are 10mm each. Total usable width = 190mm.
$col_widths = [
    'ID' => 10,  // 5% of 190 = 9.5, let's make it 10
    'Time' => 28.5, // 15% of 190 = 28.5
    'User' => 28.5, // 15% of 190 = 28.5
    'Action' => 28.5, // 15% of 190 = 28.5
    'Description' => 66.5, // 35% of 190 = 66.5
    'EntityType' => 19, // 10% of 190 = 19
    'EntityID' => 9.5  // 5% of 190 = 9.5
];
// Check sum: 10 + 28.5 + 28.5 + 28.5 + 66.5 + 19 + 9.5 = 190 (Perfect!)

// Headers for the table
$headers = ['ID', 'Time', 'User', 'Action', 'Description', 'Entity Type', 'Entity ID'];

while ($row = $result->fetch_assoc()) {
    $timestamp = strtotime($row['timestamp']);
    $date = date('j F Y', $timestamp);
    $time = date('H:i:s', $timestamp);

    if ($currentDate !== $date) {
        if ($currentDate !== '') {
            $pdf->Ln(10); // Add space between date groups
        }
        // Print the date heading (can still use writeHTML for this simple text)
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, $date, 0, 1, 'L', 0, '', 0, false, 'T', 'M');
        $pdf->Ln(2); // Small space after date heading

        // Draw Table Header
        $pdf->SetFillColor(240, 240, 240); // #f0f0f0 background
        $pdf->SetTextColor(0, 0, 0); // Black text for headers
        $pdf->SetFont('helvetica', 'B', 9); // Header font

        $x = $pdf->GetX(); // Get current X position (left margin)
        $y = $pdf->GetY(); // Get current Y position

        // Loop through headers and draw cells
        foreach ($headers as $header_text) {
            $width = $col_widths[str_replace(' ', '', $header_text)]; // Get width by key
            $pdf->MultiCell($width, 7, $header_text, 1, 'C', 1, 0, '', '', true, 0, true, true, 0, 'T', true);
        }
        $pdf->Ln(); // Move to next line after drawing all headers

        $currentDate = $date;
        $row_number = 0; // Reset row number for each new date group
    }

    // Prepare row data
    $row_number++;
    $user = $row['user_name'] ?: 'Unknown';
    $action = $row['action_type'];
    $description = $row['description'];
    $entityType = $row['entity_type'];
    $entityId = $row['entity_id'];

    // Data for the current row
    $data_row = [
        'ID' => $row_number,
        'Time' => $time,
        'User' => $user,
        'Action' => $action,
        'Description' => $description,
        'Entity Type' => $entityType,
        'Entity ID' => $entityId
    ];

    $pdf->SetFont('helvetica', '', 9); // Font for data rows
    $pdf->SetFillColor(255, 255, 255); // White background for rows
    $pdf->SetTextColor(0, 0, 0); // Black text

    $x = $pdf->GetX(); // Store current X position
    $y = $pdf->GetY(); // Store current Y position

    // Calculate maximum height for the current row's cells
    $max_height = 0;
    foreach ($headers as $header_text) {
        $col_key = str_replace(' ', '', $header_text);
        $text = $data_row[$header_text]; // Use actual header text as key for data_row
        $current_cell_height = $pdf->getStringHeight($col_widths[$col_key], $text, true, true, '', 0, false);
        if ($current_cell_height > $max_height) {
            $max_height = $current_cell_height;
        }
    }
    // Add some padding to the max height
    $max_height += 2; // For padding top/bottom inside cell

    // Check for page break BEFORE drawing the row
    if ($pdf->GetY() + $max_height > ($pdf->getPageHeight() - $pdf->getBreakMargin())) {
        $pdf->AddPage(); // Add a new page
        // Redraw header on the new page
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', 'B', 9);
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        foreach ($headers as $header_text) {
            $width = $col_widths[str_replace(' ', '', $header_text)];
            $pdf->MultiCell($width, 7, $header_text, 1, 'C', 1, 0, '', '', true, 0, true, true, 0, 'T', true);
        }
        $pdf->Ln(); // Move to next line after drawing headers on new page
        $pdf->SetFont('helvetica', '', 9); // Reset font for data rows
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetTextColor(0, 0, 0);
    }

    // Draw cells for the current row
    $start_x = $pdf->GetX();
    $start_y = $pdf->GetY();

    foreach ($headers as $header_text) {
        $col_key = str_replace(' ', '', $header_text);
        $width = $col_widths[$col_key];
        $text = $data_row[$header_text];
        $align = ($header_text === 'ID' || $header_text === 'Entity ID') ? 'C' : 'L'; // Center ID columns

        // Use MultiCell for proper wrapping, with height fixed by $max_height
        $pdf->MultiCell($width, $max_height, $text, 1, $align, 0, 0, '', '', true, 0, true, true, $max_height, 'T', true);
    }
    $pdf->Ln(); // Move to next line for the next row
}

// Signature block (remains the same)
$pdf->Ln(15);
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

$pdf->Output('audit_trail_report.pdf', 'I');
exit();