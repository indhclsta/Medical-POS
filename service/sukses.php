<?php
session_start();
include 'connection.php';

if (!isset($_GET['id'])) {
    die("ID transaksi tidak valid");
}

$transaction_id = intval($_GET['id']);

// Ambil data transaksi utama
$query = "SELECT t.*, a.username as kasir, m.phone as phone 
FROM transactions t
LEFT JOIN admin a ON t.fid_admin = a.id
LEFT JOIN member m ON t.fid_member = m.id
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
$detail_query = "SELECT td.*, p.product_name, p.image 
                 FROM transaction_details td
                 JOIN products p ON td.product_id = p.id
                 WHERE td.transaction_id = ?";
$detail_stmt = $conn->prepare($detail_query);
$detail_stmt->bind_param("i", $transaction_id);
$detail_stmt->execute();
$detail_result = $detail_stmt->get_result();

$details = [];
$subtotal_before_discount = 0; // Tambahkan ini

while ($row = $detail_result->fetch_assoc()) {
    $details[] = $row;
    $subtotal_before_discount += $row['subtotal']; // Hitung subtotal sebelum diskon
}

// Hitung jumlah diskon
$discount_amount = $subtotal_before_discount * $transaction['discount'];
$total_after_discount = $subtotal_before_discount - $discount_amount;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota Transaksi - MediPOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <style>
        @media print {
            body * { visibility: hidden; }
            .receipt, .receipt * { visibility: visible; }
            .receipt { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
            body { background-color: white; }
        }
        .receipt {
            max-width: 500px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <div class="flex justify-between mb-4 no-print">
            <a href="../cashier/transaksi.php" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                <i class="fas fa-arrow-left mr-2"></i>Kembali ke Transaksi
            </a>
            <div class="space-x-2">
                <button onclick="window.print()" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                    <i class="fas fa-print mr-2"></i>Cetak Nota
                </button>
                <a href="download_receipt.php?id=<?= $transaction_id ?>" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600">
                    <i class="fas fa-download mr-2"></i>Download PDF
                </a>
            </div>
             <div class="space-x-2">
                <?php if ($transaction['fid_member']): ?>
    <button onclick="sendToWhatsApp()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 no-print">
        <i class="fab fa-whatsapp mr-2"></i>Kirim ke WhatsApp
    </button>
<?php endif; ?>
             </div>
        </div>

        <div class="receipt">
            <div class="text-center mb-4">
                <h1 class="text-2xl font-bold">Medi <span class="text-[#779341]">POS</span></h1>
                <p class="text-sm text-gray-600">Jl. Swadaya 4 Kp. Pulo Jahe No. 71, Jakarta Timur</p>
                <p class="text-sm text-gray-600">Telp: (081) 2844-21151</p>
                <p class="text-sm text-gray-600"><?= date('d/m/Y H:i:s') ?></p>
            </div>

            <div class="border-b border-gray-300 pb-2 mb-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">No. Transaksi:</span>
                    <span class="font-medium">TRX-<?= str_pad($transaction['id'], 6, "0", STR_PAD_LEFT) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Kasir:</span>
                    <span class="font-medium"><?= htmlspecialchars($transaction['kasir']) ?></span>
                </div>
                <?php if ($transaction['fid_member']): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Member ID:</span>
                    <span class="font-medium">M-<?= str_pad($transaction['fid_member'], 6, "0", STR_PAD_LEFT) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">No. Tlp Member:</span>
                    <span class="font-medium"><?= htmlspecialchars($transaction['phone']) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Poin Didapat:</span>
                    <span class="font-medium"><?= $transaction['points'] ?> poin</span>
                </div>
                <?php if ($transaction['discount'] > 0): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Diskon Member:</span>
                    <span class="font-medium text-green-600"><?= ($transaction['discount'] * 100) ?>%</span>
                </div>
                <?php endif; ?>
                <?php endif; ?>
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
                    <tbody>
                        <?php foreach ($details as $item): ?>
                        <tr class="border-b border-gray-200">
                            <td class="py-2 text-sm"><?= htmlspecialchars($item['product_name']) ?></td>
                            <td class="py-2 text-right text-sm"><?= $item['quantity'] ?></td>
                            <td class="py-2 text-right text-sm">Rp <?= number_format($item['harga'], 0, ',', '.') ?></td>
                            <td class="py-2 text-right text-sm">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Total Pembayaran -->
<div class="border-t border-gray-300 pt-2 mb-4">
    <?php if ($transaction['discount'] > 0): ?>
    <div class="flex justify-between text-sm">
        <span class="text-gray-600">Subtotal:</span>
        <span>Rp <?= number_format($subtotal_before_discount, 0, ',', '.') ?></span>
    </div>
    <div class="flex justify-between text-sm">
        <span class="text-gray-600">Diskon (<?= ($transaction['discount'] * 100) ?>%):</span>
        <span class="text-green-600">- Rp <?= number_format($discount_amount, 0, ',', '.') ?></span>
    </div>
    <?php endif; ?>
    <div class="flex justify-between font-bold mt-2">
    <span>TOTAL:</span>
    <span>Rp <?= number_format($transaction['total_price'], 0, ',', '.') ?></span>
</div>
    <div class="flex justify-between text-sm mt-2">
        <span class="text-gray-600">Pembayaran:</span>
        <span class="font-medium"><?= ucfirst($transaction['payment_method']) ?></span>
    </div>
    <div class="flex justify-between text-sm">
        <span class="text-gray-600">Dibayar:</span>
        <span class="font-medium">Rp <?= number_format($transaction['paid_amount'], 0, ',', '.') ?></span>
    </div>
    <div class="flex justify-between text-sm">
        <span class="text-gray-600">Kembali:</span>
        <span class="font-medium">Rp <?= number_format($transaction['kembalian'], 0, ',', '.') ?></span>
    </div>
</div>

            <!-- Footer -->
            <div class="text-center text-xs text-gray-500 mt-6">
                <p>Terima kasih telah berbelanja di Smart Cash</p>
                <p>Barang yang sudah dibeli tidak dapat ditukar/dikembalikan</p>
                <p class="mt-2">www.medipos.com</p>
            </div>

            <!-- QR Code -->
            <div class="flex justify-center mt-4">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=TRX-<?= $transaction_id ?>" 
                     alt="QR Code Transaksi" 
                     class="h-20 w-20">
            </div>
        </div>
    </div>

    <script>
        // Auto print jika parameter print ada di URL
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if(urlParams.has('print')) {
                window.print();
            }
        };
        
        function sendToWhatsApp() {
    fetch('get_member_phone.php?id=<?= $transaction_id ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.phone) {
                // Membuat URL lengkap untuk transaksi ini
                const transactionUrl = window.location.origin + window.location.pathname + '?id=<?= $transaction_id ?>';
                
                // Format header pesan
                let message = `Halo Member SmartCash! Berikut detail transaksi Anda:\n\n` +
                             `No. Transaksi: TRX-<?= str_pad($transaction['id'], 6, "0", STR_PAD_LEFT) ?>\n` +
                             `Tanggal: <?= date('d/m/Y H:i:s') ?>\n` +
                             `Kasir: <?= htmlspecialchars($transaction['kasir']) ?>\n\n` +
                             `Detail Pembelian:\n`;
                
                // Tambahkan detail produk
                <?php foreach ($details as $item): ?>
                message += `- <?= htmlspecialchars($item['product_name']) ?> ` +
                           `(<?= $item['quantity'] ?> x Rp <?= number_format($item['harga'], 0, ',', '.') ?>)\n`;
                <?php endforeach; ?>
                
                // Tambahkan total dan footer
                message += `\nSubtotal: Rp <?= number_format($subtotal_before_discount, 0, ',', '.') ?>\n`;
                
                <?php if ($transaction['discount'] > 0): ?>
                message += `Diskon: <?= ($transaction['discount'] * 100) ?>% (-Rp <?= number_format($discount_amount, 0, ',', '.') ?>)\n`;
                <?php endif; ?>
                
                message += `TOTAL: Rp <?= number_format($transaction['total_price'], 0, ',', '.') ?>\n\n` +
                            `Pembayaran: <?= ucfirst($transaction['payment_method']) ?>\n` +
                            `Dibayar: Rp <?= number_format($transaction['paid_amount'], 0, ',', '.') ?>\n` +
                            `Kembali: Rp <?= number_format($transaction['kembalian'], 0, ',', '.') ?>\n\n` +
                            `Detail lengkap: ${transactionUrl}\n\n` +
                            `Terima kasih telah berbelanja di MediPOS!`;
                
                // Encode message untuk URL
                const encodedMessage = encodeURIComponent(message);
                
                // Buka WhatsApp
                window.open(`https://wa.me/${data.phone}?text=${encodedMessage}`, '_blank');
            } else {
                alert('Gagal mendapatkan nomor WhatsApp member');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat mengirim ke WhatsApp');
        });
}
    </script>
</body>
</html>