<?php
require_once(__DIR__ . '/fpdf.php');

session_name('DRIVER_SESSION');
session_start();
include 'config.php';

if (!isset($_GET['id'])) {
    die('Delivery ID required');
}

$delivery_id = $_GET['id'];
$download = isset($_GET['download']) ? true : false;

$query = mysqli_query($conn, "SELECT d.*, c.name as customer_name, c.address, c.phone, u.username as driver_name, v.plate_number 
    FROM deliveries d 
    LEFT JOIN customers c ON d.customer_id = c.id 
    LEFT JOIN users u ON d.driver_id = u.id 
    LEFT JOIN vehicles v ON d.vehicle_id = v.id
    WHERE d.id = $delivery_id");

$delivery = mysqli_fetch_assoc($query);

if (!$delivery) {
    die('Delivery not found');
}

if ($delivery['status'] != 'delivered') {
    die('Receipt only available after delivery is completed.');
}

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
        $this->Cell(0, 4, 'Industrial Area, Nairobi, Kenya', 0, 1, 'C');
        
        $this->Ln(5);
        $this->SetDrawColor(212, 175, 55);
        $this->SetLineWidth(0.5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, 'DELIVERY RECEIPT', 0, 1, 'C');
        $this->Ln(3);
    }
    
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 5, 'This is a computer-generated receipt. Valid without stamp.', 0, 0, 'C');
    }
    
    function ReceiptRow($label, $value)
    {
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(45, 6, $label, 0, 0);
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 6, $value, 0, 1);
    }
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(95, 7, 'RECEIPT INFORMATION', 0, 0, 'L', true);
$pdf->Cell(95, 7, 'DELIVERY INFORMATION', 0, 1, 'L', true);

$pdf->ReceiptRow('Receipt No:', $delivery['delivery_code']);
$pdf->ReceiptRow('Date:', date('d/m/Y H:i', strtotime($delivery['delivered_at'])));
$pdf->ReceiptRow('Status:', 'DELIVERED');
$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(212, 175, 55);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 7, 'CUSTOMER DETAILS', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

$pdf->ReceiptRow('Customer Name:', $delivery['customer_name']);
$pdf->ReceiptRow('Delivery Address:', $delivery['address']);
if ($delivery['phone']) {
    $pdf->ReceiptRow('Phone:', $delivery['phone']);
}
$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(212, 175, 55);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 7, 'DELIVERY DETAILS', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

$pdf->ReceiptRow('Goods Description:', 'Maize Flour');
$pdf->ReceiptRow('Weight:', number_format($delivery['weight_kg']) . ' kg');
$pdf->ReceiptRow('Delivery Date:', date('d/m/Y', strtotime($delivery['delivery_date'])));
if ($delivery['time_window_end']) {
    $pdf->ReceiptRow('Deadline:', date('H:i', strtotime($delivery['time_window_end'])));
}
$pdf->Ln(3);

// PENALTY ON RECEIPT
if ($delivery['penalty_amount'] > 0) {
    $pdf->SetFillColor(229, 62, 62);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 7, 'LATE DELIVERY PENALTY: KES ' . number_format($delivery['penalty_amount']), 0, 1, 'C', true);
    $pdf->Ln(3);
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(212, 175, 55);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 7, 'DELIVERED BY', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

$pdf->ReceiptRow('Driver Name:', $delivery['driver_name']);
$pdf->ReceiptRow('Vehicle Registration:', $delivery['plate_number']);
$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(212, 175, 55);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 7, 'SIGNATURES', 0, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(90, 5, 'Driver Signature:', 0, 0);
$pdf->Cell(90, 5, 'Customer Signature:', 0, 1);
$pdf->Ln(2);

$y_before = $pdf->GetY();

if ($delivery['driver_signature_path'] && file_exists($delivery['driver_signature_path'])) {
    $pdf->Image($delivery['driver_signature_path'], 15, $y_before, 65, 20);
} else {
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Text(15, $y_before + 12, '_________________________');
}

if ($delivery['signature_path'] && file_exists($delivery['signature_path'])) {
    $pdf->Image($delivery['signature_path'], 110, $y_before, 65, 20);
} else {
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(150, 150, 150);
    $pdf->Text(110, $y_before + 12, '_________________________');
}

$pdf->Ln(28);

$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(120, 120, 120);
$pdf->MultiCell(0, 4, 'This document serves as proof of delivery. The goods have been received in good condition.', 0, 'C');

if ($download) {
    $pdf->Output('D', 'Receipt_' . $delivery['delivery_code'] . '.pdf');
} else {
    $pdf->Output('I', 'Receipt_' . $delivery['delivery_code'] . '.pdf');
}
?>