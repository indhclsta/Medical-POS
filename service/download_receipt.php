<?php
require_once '../vendor/autoload.php';
include 'connection.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Ambil ID transaksi dari URL
$transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ambil data transaksi
$query = "SELECT t.*, a.username as kasir 
          FROM transactions t
          LEFT JOIN admin a ON t.fid_admin = a.id
          WHERE t.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Transaksi tidak ditemukan");
}

$transaction = $result->fetch_assoc();

// Ambil detail transaksi
$detail_query = "SELECT td.*, p.product_name 
                 FROM transaction_details td
                 JOIN products p ON td.product_id = p.id
                 WHERE td.transaction_id = ?";
$detail_stmt = $conn->prepare($detail_query);
$detail_stmt->bind_param("i", $transaction_id);
$detail_stmt->execute();
$detail_result = $detail_stmt->get_result();

$details = [];
$subtotal_before_discount = 0;

while ($row = $detail_result->fetch_assoc()) {
    $details[] = $row;
    $subtotal_before_discount += $row['subtotal'];
}

// Hitung jumlah diskon
$discount_amount = $subtotal_before_discount * $transaction['discount'];
$total_after_discount = $subtotal_before_discount - $discount_amount;

// Buat HTML untuk PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Nota Transaksi - MediPOS</title>
    <style>
        body { 
            font-family: Arial, sans-serif;
            background-color: white;
            padding: 20px;
        }
        .receipt {
            max-width: 500px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-green-600 { color: #16a34a; }
        .font-bold { font-weight: bold; }
        .text-sm { font-size: 0.875rem; }
        .text-xs { font-size: 0.75rem; }
        .text-gray-600 { color: #4b5563; }
        .border-b { border-bottom-width: 1px; }
        .border-t { border-top-width: 1px; }
        .border-gray-300 { border-color: #d1d5db; }
        .mb-4 { margin-bottom: 1rem; }
        .mt-2 { margin-top: 0.5rem; }
        .mt-4 { margin-top: 1rem; }
        .mt-6 { margin-top: 1.5rem; }
        .py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
        .pb-2 { padding-bottom: 0.5rem; }
        .pt-2 { padding-top: 0.5rem; }
        .flex { display: flex; }
        .justify-between { justify-content: space-between; }
        .w-full { width: 100%; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 0.25rem 0; }
        .text-[#779341] { color: #779341; }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="text-center mb-4">
            <h1 class="text-2xl font-bold">Medi<span class="text-[#779341]">POS</span></h1>
            <p class="text-sm text-gray-600">Jl. Swadaya 4 Kp. Pulo Jahe No. 71, Jakarta Timur</p>
            <p class="text-sm text-gray-600">Telp: (081) 2844-21151</p>
            <p class="text-sm text-gray-600">' . date('d/m/Y H:i:s') . '</p>
        </div>

        <div class="border-b border-gray-300 pb-2 mb-4">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">No. Transaksi:</span>
                <span class="font-medium">TRX-' . str_pad($transaction['id'], 6, "0", STR_PAD_LEFT) . '</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Kasir:</span>
                <span class="font-medium">' . htmlspecialchars($transaction['kasir']) . '</span>
            </div>';

if ($transaction['fid_member']) {
    $html .= '
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Member ID:</span>
                <span class="font-medium">M-' . str_pad($transaction['fid_member'], 6, "0", STR_PAD_LEFT) . '</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Poin Didapat:</span>
                <span class="font-medium">' . $transaction['points'] . ' poin</span>
            </div>';
    if ($transaction['discount'] > 0) {
        $html .= '
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Diskon Member:</span>
                <span class="font-medium text-green-600">' . ($transaction['discount'] * 100) . '%</span>
            </div>';
    }
}

$html .= '
        </div>

        <!-- Daftar Produk -->
        <div class="mb-4">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-300">
                        <th class="text-left pb-2 text-sm text-gray-600">Produk</th>
                        <th class="text-right pb-2 text-sm text-gray-600">Qty</th>
                        <th class="text-right pb-2 text-sm text-gray-600">Harga</th>
                        <th class="text-right pb-2 text-sm text-gray-600">Subtotal</th>
                    </tr>
                </thead>
                <tbody>';

foreach ($details as $item) {
    $html .= '
                    <tr class="border-b border-gray-200">
                        <td class="py-2 text-sm">' . htmlspecialchars($item['product_name']) . '</td>
                        <td class="py-2 text-right text-sm">' . $item['quantity'] . '</td>
                        <td class="py-2 text-right text-sm">Rp ' . number_format($item['harga'], 0, ',', '.') . '</td>
                        <td class="py-2 text-right text-sm">Rp ' . number_format($item['subtotal'], 0, ',', '.') . '</td>
                    </tr>';
}

$html .= '
                </tbody>
            </table>
        </div>

        <!-- Total Pembayaran -->
        <div class="border-t border-gray-300 pt-2 mb-4">';
if ($transaction['discount'] > 0) {
    $html .= '
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Subtotal:</span>
                <span>Rp ' . number_format($subtotal_before_discount, 0, ',', '.') . '</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Diskon (' . ($transaction['discount'] * 100) . '%):</span>
                <span class="text-green-600">- Rp ' . number_format($discount_amount, 0, ',', '.') . '</span>
            </div>';
}
$html .= '
            <div class="flex justify-between font-bold mt-2">
                <span>TOTAL:</span>
                <span>Rp ' . number_format($transaction['total_price'], 0, ',', '.') . '</span>
            </div>
            <div class="flex justify-between text-sm mt-2">
                <span class="text-gray-600">Pembayaran:</span>
                <span class="font-medium">' . ucfirst($transaction['payment_method']) . '</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Dibayar:</span>
                <span class="font-medium">Rp ' . number_format($transaction['paid_amount'], 0, ',', '.') . '</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600">Kembali:</span>
                <span class="font-medium">Rp ' . number_format($transaction['kembalian'], 0, ',', '.') . '</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center text-xs text-gray-500 mt-6">
            <p>Terima kasih telah berbelanja di Medical POS</p>
            <p>Barang yang sudah dibeli tidak dapat ditukar/dikembalikan</p>
            <p class="mt-2">www.medipos.com</p>
        </div>

    </div>
</body>
</html>';

// Konfigurasi DOMPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A5', 'portrait');
$dompdf->render();

// Output PDF
$dompdf->stream('nota-transaksi-' . $transaction_id . '.pdf', [
    'Attachment' => true
]);
?>