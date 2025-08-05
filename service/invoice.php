<?php
session_start();
include 'connection.php';

if (!isset($_SESSION['last_transaction_id'])) {
    echo "Tidak ada transaksi untuk ditampilkan.";
    exit();
}

$id_transaksi = $_SESSION['last_transaction_id'];

// Ambil data transaksi utama
$stmt_transaksi = $conn->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt_transaksi->bind_param("i", $id_transaksi);
$stmt_transaksi->execute();
$transaksi_result = $stmt_transaksi->get_result();
$transaksi = $transaksi_result->fetch_assoc();

// Ambil data produk yang dibeli
$stmt_items = $conn->prepare("SELECT * FROM transaction_items WHERE fid_transaction = ?");
$stmt_items->bind_param("i", $id_transaksi);
$stmt_items->execute();
$items_result = $stmt_items->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - Transaksi #<?= $id_transaksi ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="font-sans bg-gray-100">
    <div class="max-w-4xl mx-auto p-6 bg-white shadow-lg">
        <h1 class="text-3xl font-semibold text-center mb-6">Invoice Transaksi #<?= $id_transaksi ?></h1>
        
        <div class="mb-4">
            <strong>Admin:</strong> <?= $transaksi['fid_admin'] ?><br>
            <strong>Metode Pembayaran:</strong> <?= $transaksi['payment_method'] ?><br>
            <strong>Tanggal:</strong> <?= $transaksi['date'] ?><br>
            <strong>Total Pembayaran:</strong> Rp. <?= number_format($transaksi['total_price'], 0, ',', '.') ?><br>
            <strong>Nominal Pembayaran:</strong> Rp. <?= number_format($transaksi['paid_amount'], 0, ',', '.') ?><br>
            <strong>Kembalian:</strong> Rp. <?= number_format($transaksi['kembalian'], 0, ',', '.') ?><br>
        </div>

        <table class="min-w-full table-auto border-collapse border border-gray-200">
            <thead>
                <tr class="bg-gray-200">
                    <th class="border border-gray-300 px-4 py-2">Produk</th>
                    <th class="border border-gray-300 px-4 py-2">Jumlah</th>
                    <th class="border border-gray-300 px-4 py-2">Harga Satuan</th>
                    <th class="border border-gray-300 px-4 py-2">Total Harga</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = $items_result->fetch_assoc()) { ?>
                <tr>
                    <td class="border border-gray-300 px-4 py-2"><?= $item['fid_product'] ?></td>
                    <td class="border border-gray-300 px-4 py-2"><?= $item['jumlah'] ?></td>
                    <td class="border border-gray-300 px-4 py-2">Rp. <?= number_format($item['harga_satuan'], 0, ',', '.') ?></td>
                    <td class="border border-gray-300 px-4 py-2">Rp. <?= number_format($item['total_harga'], 0, ',', '.') ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>

        <div class="mt-4 text-right">
            <a href="javascript:window.print()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Cetak Invoice</a>
        </div>
    </div>
</body>
</html>
