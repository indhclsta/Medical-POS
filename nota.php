<?php
include './service/connection.php';

$query = "
    SELECT 
        t.id AS transaksi_id,
        t.date,
        t.total_price,
        t.paid_amount,
        t.kembalian,
        t.payment_method,
        product_name AS product_name,
        ti.jumlah AS quantity,
        ti.total_harga AS subtotal
    FROM transactions t
    JOIN transaction_items ti ON t.id = ti.fid_transaction
    JOIN products p ON ti.fid_product = p.id
    ORDER BY t.tanggal DESC, t.id ASC
";

$result = mysqli_query($conn, $query);

$laporan = [];
while ($row = mysqli_fetch_assoc($result)) {
    $id = $row['transaksi_id'];
    if (!isset($laporan[$id])) {
        $laporan[$id] = [
            'date' => $row['transaction_date'],
            'total' => $row['total_price'],
            'bayar' => $row['paid_amount'],
            'kembalian' => $row['kembalian'],
            'metode' => $row['payment_method'],
            'items' => []
        ];
    }
    $laporan[$id]['items'][] = [
        'produk' => $row['product_name'],
        'qty' => $row['quantity'],
        'subtotal' => $row['subtotal']
    ];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Laporan Transaksi</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="p-6 bg-gray-50 text-gray-800">
    <h1 class="text-2xl font-bold mb-6">Laporan Transaksi</h1>

    <?php foreach ($laporan as $id => $data): ?>
    <div class="bg-white p-4 mb-4 rounded shadow">
        <h2 class="text-lg font-semibold text-green-700">Transaksi #<?= $id ?> - <?= $data['tanggal'] ?></h2>
        <p class="text-sm text-gray-600">Metode: <?= $data['metode'] ?> | Bayar: Rp<?= number_format($data['bayar']) ?> | Kembali: Rp<?= number_format($data['kembalian']) ?></p>

        <table class="mt-2 w-full text-sm">
            <thead>
                <tr class="text-left bg-green-100">
                    <th class="py-1 px-2">Produk</th>
                    <th class="py-1 px-2">Qty</th>
                    <th class="py-1 px-2">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['items'] as $item): ?>
                <tr>
                    <td class="py-1 px-2"><?= $item['produk'] ?></td>
                    <td class="py-1 px-2"><?= $item['qty'] ?></td>
                    <td class="py-1 px-2">Rp<?= number_format($item['subtotal']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="text-right mt-2 font-semibold">
            Total: Rp<?= number_format($data['total']) ?>
        </div>
    </div>
    <?php endforeach; ?>
</body>
</html>
