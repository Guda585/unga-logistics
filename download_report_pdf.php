<?php
session_name('ADMIN_SESSION');
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: admin_login.php');
    exit();
}

require_once(__DIR__ . '/fpdf.php');

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Get data
$stats_query = "SELECT 
    COUNT(*) as total_deliveries,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed,
    COALESCE(SUM(penalty_amount), 0) as total_penalties,
    SUM(CASE WHEN penalty_amount > 0 THEN 1 ELSE 0 END) as late_deliveries
    FROM deliveries 
    WHERE delivered_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

$on_time = ($stats['total_deliveries'] ?? 0) - ($stats['late_deliveries'] ?? 0);
$percentage = ($stats['total_deliveries'] ?? 0) > 0 ? round(($on_time / $stats['total_deliveries']) * 100, 1) : 0;

// Daily summary
$summary_query = mysqli_query($conn, "SELECT 
    DATE(delivered_at) as period,
    COUNT(*) as total,
    COALESCE(SUM(penalty_amount), 0) as penalties,
    COUNT(CASE WHEN penalty_amount > 0 THEN 1 END) as late_count
    FROM deliveries 
    WHERE delivered_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
    GROUP BY DATE(delivered_at)
    ORDER BY period DESC");

// Penalty details
$penalty_query = mysqli_query($conn, "SELECT d.delivery_code, c.name as customer_name, u.username as driver_name, d.delivered_at, d.time_window_end, d.penalty_amount 
    FROM deliveries d 
    LEFT JOIN customers c ON d.customer_id = c.id 
    LEFT JOIN users u ON d.driver_id = u.id 
    WHERE d.penalty_amount > 0 
    AND d.delivered_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
    ORDER BY d.penalty_amount DESC");

class PDF extends FPDF
{
    function Header()
    {
        $this->SetDrawColor(212, 175, 55);
        $this->SetLineWidth(2);
        $this->Line(10, 5, 200, 5);
        
        $this->SetY(12);
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(212, 175, 55);
        $this->Cell(0, 8, 'UNGA HOLDINGS LIMITED', 0, 1, 'C');
        
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 4, 'Logistics Management System', 0, 1, 'C');
        
        $this->Ln(5);
        $this->SetDrawColor(212, 175, 55);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(8);
        
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, 'DELIVERY PERFORMANCE REPORT', 0, 1, 'C');
        $this->Ln(3);
    }
    
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 5, 'Generated on: ' . date('d/m/Y H:i:s'), 0, 0, 'C');
    }
    
    function ReportRow($label, $value, $isPenalty = false)
    {
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(60, 8, $label, 0, 0);
        $this->SetFont('Arial', '', 10);
        if ($isPenalty) {
            $this->SetTextColor(229, 62, 62);
        } else {
            $this->SetTextColor(80, 80, 80);
        }
        $this->Cell(0, 8, $value, 0, 1);
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Report Period
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, 'Report Period: ' . date('d/m/Y', strtotime($start_date)) . ' to ' . date('d/m/Y', strtotime($end_date)), 0, 1, 'C');
$pdf->Ln(5);

// Statistics Section
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(0, 8, 'STATISTICS SUMMARY', 0, 1, 'L', true);
$pdf->Ln(3);

$pdf->ReportRow('Total Deliveries:', $stats['total_deliveries'] ?? 0);
$pdf->ReportRow('Completed Deliveries:', $stats['completed'] ?? 0);
$pdf->ReportRow('Late Deliveries:', $stats['late_deliveries'] ?? 0);
$pdf->ReportRow('Total Penalties:', 'KES ' . number_format($stats['total_penalties'] ?? 0), true);
$pdf->ReportRow('On-Time Performance:', $percentage . '% (' . $on_time . ' out of ' . ($stats['total_deliveries'] ?? 0) . ')');

$pdf->Ln(5);

// Daily Summary Table
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(0, 8, 'DAILY SUMMARY', 0, 1, 'L', true);
$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(45, 55, 72);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(50, 8, 'Period', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Deliveries', 1, 0, 'C', true);
$pdf->Cell(40, 8, 'Late', 1, 0, 'C', true);
$pdf->Cell(60, 8, 'Penalties (KES)', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);

if (mysqli_num_rows($summary_query) > 0) {
    while ($row = mysqli_fetch_assoc($summary_query)) {
        $pdf->Cell(50, 7, $row['period'], 1, 0, 'L');
        $pdf->Cell(40, 7, $row['total'], 1, 0, 'C');
        $pdf->Cell(40, 7, $row['late_count'], 1, 0, 'C');
        $pdf->Cell(60, 7, 'KES ' . number_format($row['penalties']), 1, 1, 'R');
    }
} else {
    $pdf->Cell(190, 7, 'No data for selected period', 1, 1, 'C');
}

$pdf->Ln(5);

// Penalties Table
if (mysqli_num_rows($penalty_query) > 0) {
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(245, 245, 245);
    $pdf->Cell(0, 8, 'LATE DELIVERY PENALTIES', 0, 1, 'L', true);
    $pdf->Ln(3);
    
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(45, 55, 72);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(35, 7, 'Delivery Code', 1, 0, 'C', true);
    $pdf->Cell(50, 7, 'Customer', 1, 0, 'C', true);
    $pdf->Cell(35, 7, 'Driver', 1, 0, 'C', true);
    $pdf->Cell(35, 7, 'Delivered At', 1, 0, 'C', true);
    $pdf->Cell(20, 7, 'Deadline', 1, 0, 'C', true);
    $pdf->Cell(20, 7, 'Penalty', 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(0, 0, 0);
    
    while ($row = mysqli_fetch_assoc($penalty_query)) {
        $pdf->Cell(35, 6, $row['delivery_code'], 1, 0, 'L');
        $customer_name = strlen($row['customer_name']) > 20 ? substr($row['customer_name'], 0, 17) . '...' : $row['customer_name'];
        $pdf->Cell(50, 6, $customer_name, 1, 0, 'L');
        $pdf->Cell(35, 6, $row['driver_name'], 1, 0, 'L');
        $pdf->Cell(35, 6, date('d/m/Y H:i', strtotime($row['delivered_at'])), 1, 0, 'L');
        $pdf->Cell(20, 6, date('H:i', strtotime($row['time_window_end'])), 1, 0, 'C');
        $pdf->SetTextColor(229, 62, 62);
        $pdf->Cell(20, 6, 'KES ' . number_format($row['penalty_amount']), 1, 1, 'R');
        $pdf->SetTextColor(0, 0, 0);
    }
}

$pdf->Output('D', 'Unga_Report_' . $start_date . '_to_' . $end_date . '.pdf');
?>