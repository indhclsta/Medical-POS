<?php
require_once 'connection.php';
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Get filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'keseluruhan';
$chart_type = isset($_GET['chart_type']) ? $_GET['chart_type'] : 'daily';

// Query transactions
$query = "SELECT t.*, a.username as admin_name, m.name as member_name 
          FROM transactions t
          LEFT JOIN admin a ON t.fid_admin = a.id
          LEFT JOIN member m ON t.fid_member = m.id";

if ($report_type == 'periode' && !empty($start_date) && !empty($end_date)) {
    $query .= " WHERE DATE(t.date) BETWEEN '$start_date' AND '$end_date'";
}

$query .= " ORDER BY t.date DESC";
$transactions = mysqli_query($conn, $query);

// Calculate totals
$total_query = "SELECT SUM(total_price) as total_income, SUM(margin_total) as total_margin, COUNT(*) as total_transactions FROM transactions";
if ($report_type == 'periode' && !empty($start_date)) {
    $total_query .= " WHERE DATE(date) BETWEEN '$start_date' AND '$end_date'";
}
$total_result = mysqli_query($conn, $total_query);
$totals = mysqli_fetch_assoc($total_result);

// Get chart data

$chart_labels = [];
$chart_data = [];
$chart_margin_data = [];

if ($chart_type == 'daily') {
    $chart_query = "SELECT DATE(date) as day, SUM(total_price) as total, SUM(margin_total) as margin 
                   FROM transactions";
    if ($report_type == 'periode' && !empty($start_date)) {
        $chart_query .= " WHERE DATE(date) BETWEEN '$start_date' AND '$end_date'";
    }
    $chart_query .= " GROUP BY DATE(date) ORDER BY DATE(date)";
} elseif ($chart_type == 'monthly') {
    $chart_query = "SELECT DATE_FORMAT(date, '%Y-%m') as month, SUM(total_price) as total, SUM(margin_total) as margin 
                   FROM transactions";
    if ($report_type == 'periode' && !empty($start_date)) {
        $chart_query .= " WHERE DATE(date) BETWEEN '$start_date' AND '$end_date'";
    }
    $chart_query .= " GROUP BY DATE_FORMAT(date, '%Y-%m') ORDER BY DATE_FORMAT(date, '%Y-%m')";
} else {
    $chart_query = "SELECT YEARWEEK(date) as week, SUM(total_price) as total, SUM(margin_total) as margin 
                   FROM transactions";
    if ($report_type == 'periode' && !empty($start_date)) {
        $chart_query .= " WHERE DATE(date) BETWEEN '$start_date' AND '$end_date'";
    }
    $chart_query .= " GROUP BY YEARWEEK(date) ORDER BY YEARWEEK(date)";
}

$chart_result = mysqli_query($conn, $chart_query);
while ($row = mysqli_fetch_assoc($chart_result)) {
    if ($chart_type == 'daily') {
        $chart_labels[] = date('d M', strtotime($row['day']));
    } elseif ($chart_type == 'monthly') {
        $chart_labels[] = date('M Y', strtotime($row['month'] . '-01'));
    } else {
        $chart_labels[] = 'Week ' . substr($row['week'], 4) . ' ' . substr($row['week'], 0, 4);
    }
    $chart_data[] = $row['total'];
    $chart_margin_data[] = $row['margin'];
}

// Generate chart images using QuickChart.io
$sales_chart_url = 'https://quickchart.io/chart?c=' . urlencode(json_encode([
    'type' => 'bar',
    'data' => [
        'labels' => $chart_labels,
        'datasets' => [[
            'label' => 'Total Penjualan',
            'data' => $chart_data,
            'backgroundColor' => 'rgba(76, 175, 80, 0.7)',
            'borderColor' => 'rgba(76, 175, 80, 1)',
            'borderWidth' => 1
        ]]
    ],
    'options' => [
        'scales' => [
            'yAxes' => [[
                'ticks' => [
                    'beginAtZero' => true
                ]
            ]]
        ]
    ]
]));

$margin_chart_url = 'https://quickchart.io/chart?c=' . urlencode(json_encode([
    'type' => 'line',
    'data' => [
        'labels' => $chart_labels,
        'datasets' => [[
            'label' => 'Total Margin',
            'data' => $chart_margin_data,
            'backgroundColor' => 'rgba(139, 195, 74, 0.2)',
            'borderColor' => 'rgba(139, 195, 74, 1)',
            'borderWidth' => 2,
            'fill' => true
        ]]
    ],
    'options' => [
        'scales' => [
            'yAxes' => [[
                'ticks' => [
                    'beginAtZero' => true
                ]
            ]]
        ]
    ]
]));

$chart_html = '<div style="margin-bottom: 30px;">
    <h3 style="text-align: center; font-weight: bold; margin-bottom: 10px;">Grafik Penjualan</h3>
    <div style="width: 100%; height: 300px; text-align: center; margin-bottom: 20px;">
        <img src="' . $sales_chart_url . '" style="max-width:100%;height:300px;" alt="Grafik Penjualan" />
    </div>
    <h3 style="text-align: center; font-weight: bold; margin-bottom: 10px;">Grafik Margin</h3>
    <div style="width: 100%; height: 300px; text-align: center;">
        <img src="' . $margin_chart_url . '" style="max-width:100%;height:300px;" alt="Grafik Margin" />
    </div>
</div>';

// HTML content for PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Transaksi SmartCash</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .title { font-size: 20px; font-weight: bold; color: #4CAF50; }
        .subtitle { font-size: 16px; color: #666; margin-bottom: 10px; }
        .report-info { margin-bottom: 15px; font-size: 12px; }
        .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
        .summary-item { border-left: 4px solid #4CAF50; padding-left: 10px; }
        .table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .table th { background-color: #8BC34A; color: white; text-align: left; padding: 8px; }
        .table td { padding: 8px; border-bottom: 1px solid #ddd; }
        .text-right { text-align: right; }
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

    <div class="summary-grid">
        <div class="summary-item">
            <h3>Total Transaksi</h3>
            <p>' . $totals['total_transactions'] . '</p>
        </div>
        <div class="summary-item">
            <h3>Total Pendapatan</h3>
            <p>Rp ' . number_format($totals['total_income'], 0, ',', '.') . '</p>
        </div>
        <div class="summary-item">
            <h3>Total Margin</h3>
            <p>Rp ' . number_format($totals['total_margin'], 0, ',', '.') . '</p>
        </div>
    </div>

    ' . $chart_html . '

    <h3 style="font-weight: bold; margin-bottom: 10px;">Daftar Transaksi</h3>
    <table class="table">
        <thead>
            <tr>
                <th>No</th>
                <th>ID Transaksi</th>
                <th>Tanggal</th>
                <th>Admin</th>
                <th>Member</th>
                <th class="text-right">Total</th>
                <th>Metode</th>
            </tr>
        </thead>
        <tbody>';

$no = 1;
mysqli_data_seek($transactions, 0);
while ($transaction = mysqli_fetch_assoc($transactions)) {
    $html .= '
            <tr>
                <td>' . $no++ . '</td>
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
        <p>Laporan ini dibuat secara otomatis oleh sistem MediPOS</p>
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
$filename = 'Laporan_Transaksi_MediPOS_' . ($report_type == 'periode' ?
    date('Ymd', strtotime($start_date)) . '_' . date('Ymd', strtotime($end_date)) :
    'Keseluruhan') . '.pdf';

// Output the PDF
$dompdf->stream($filename, [
    'Attachment' => true
]);
