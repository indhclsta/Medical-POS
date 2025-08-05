<?php
require_once 'connection.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'keseluruhan';

// Query transactions with the same filters
$query = "SELECT t.*, a.username as admin_name, m.name as member_name 
          FROM transactions t
          LEFT JOIN admin a ON t.fid_admin = a.id
          LEFT JOIN member m ON t.fid_member = m.id";

if ($report_type == 'periode' && !empty($start_date) && !empty($end_date)) {
    $query .= " WHERE DATE(t.date) BETWEEN '$start_date' AND '$end_date'";
}

$query .= " ORDER BY t.date DESC";
$transactions = mysqli_query($conn, $query);

// Calculate totals (same as in laporan_input.php)
$total_query = "SELECT SUM(total_price) as total_income, SUM(margin_total) as total_margin, COUNT(*) as total_transactions FROM transactions";
if ($report_type == 'periode' && !empty($start_date)) {
    $total_query .= " WHERE DATE(date) BETWEEN '$start_date' AND '$end_date'";
}
$total_result = mysqli_query($conn, $total_query);
$totals = mysqli_fetch_assoc($total_result);

// HTML content for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Transaksi SmartCash</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 20px; }
        .title { font-size: 20px; font-weight: bold; color: #4CAF50; }
        .subtitle { font-size: 16px; color: #666; margin-bottom: 10px; }
        .report-info { margin-bottom: 15px; font-size: 12px; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .table th { background-color: #8BC34A; color: white; text-align: left; padding: 8px; }
        .table td { padding: 8px; border-bottom: 1px solid #ddd; }
        .text-right { text-align: right; }
        .summary-card { background-color: #f9f9f9; border: 1px solid #ddd; padding: 10px; margin-bottom: 15px; border-radius: 5px; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
        .footer { text-align: center; font-size: 10px; margin-top: 20px; color: #777; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Laporan Transaksi SmartCash</div>
        <div class="subtitle">' . ($report_type == 'periode' ? 
            "Periode: " . date('d M Y', strtotime($start_date)) . " - " . date('d M Y', strtotime($end_date)) : 
            "Laporan Keseluruhan") . '</div>
        <div class="report-info">Dicetak pada: ' . date('d/m/Y H:i:s') . '</div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <h3>Total Transaksi</h3>
            <p class="text-2xl">' . $totals['total_transactions'] . '</p>
        </div>
        <div class="summary-card">
            <h3>Total Pendapatan</h3>
            <p class="text-2xl">Rp ' . number_format($totals['total_income'], 0, ',', '.') . '</p>
        </div>
        <div class="summary-card">
            <h3>Total Margin</h3>
            <p class="text-2xl">Rp ' . number_format($totals['total_margin'], 0, ',', '.') . '</p>
        </div>
    </div>

    <!-- Transaction Table -->
    <table class="table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tanggal</th>
                <th>Admin</th>
                <th>Member</th>
                <th class="text-right">Total</th>
                <th>Metode</th>
            </tr>
        </thead>
        <tbody>';

while($transaction = mysqli_fetch_assoc($transactions)) {
    $html .= '
            <tr>
                <td>' . $transaction['id'] . '</td>
                <td>' . date('d M Y H:i', strtotime($transaction['date'])) . '</td>
                <td>' . $transaction['admin_name'] . '</td>
                <td>' . ($transaction['member_name'] ?? '-') . '</td>
                <td class="text-right">Rp ' . number_format($transaction['total_price'], 0, ',', '.') . '</td>
                <td>' . ucfirst($transaction['payment_method']) . '</td>
            </tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="footer">
        <p>Laporan ini dibuat secara otomatis oleh sistem SmartCash</p>
        <p>www.smartcash.com</p>
    </div>
</body>
</html>';

// Configure Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Generate filename
$filename = 'Laporan_Transaksi_SmartCash_' . ($report_type == 'periode' ? 
    date('Ymd', strtotime($start_date)) . '_' . date('Ymd', strtotime($end_date)) : 
    'Keseluruhan') . '.pdf';

// Output the PDF
$dompdf->stream($filename, [
    'Attachment' => true
]);
?>