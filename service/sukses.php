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
$subtotal_before_discount = 0;

while ($row = $detail_result->fetch_assoc()) {
    $details[] = $row;
    $subtotal_before_discount += $row['subtotal'];
}

// Hitung jumlah diskon
$discount_amount = $subtotal_before_discount * $transaction['discount'];
$total_after_discount = $subtotal_before_discount - $discount_amount;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Transaksi - MediPOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body * { visibility: hidden; }
            .receipt-container, .receipt-container * { visibility: visible; }
            .receipt-container { 
                position: absolute; 
                left: 0; 
                top: 0;
                width: 100%;
                padding: 0;
                margin: 0;
                background: none;
            }
            .no-print { display: none !important; }
            .receipt {
                box-shadow: none;
                border: none;
                width: 80mm; /* Lebar struk standar */
                margin: 0 auto;
            }
        }
        body {
            background-color: #1E1B2E;
            font-family: 'Inter', sans-serif;
        }
        .receipt-container {
            display: flex;
            justify-content: center;
            padding: 20px;
        }
        .receipt {
            width: 80mm; /* Lebar struk standar */
            background-color: white;
            padding: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-radius: 0; /* Struk biasanya tidak punya border radius */
            font-family: 'Courier New', monospace; /* Font struk klasik */
            color: black;
            border: 1px dashed #ccc; /* Garis putus-putus tipis */
        }
        .receipt-header {
            text-align: center;
            margin-bottom: 10px;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
        }
        .receipt-title {
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 5px;
        }
        .receipt-address {
            font-size: 12px;
            margin-bottom: 5px;
        }
        .receipt-divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        .receipt-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 13px;
        }
        .receipt-total {
            font-weight: bold;
            margin-top: 10px;
            border-top: 1px dashed #000;
            padding-top: 10px;
        }
        .receipt-footer {
            text-align: center;
            font-size: 11px;
            margin-top: 15px;
            border-top: 1px dashed #000;
            padding-top: 10px;
        }
        .btn-print {
            background-color: #6a0dad;
            color: white;
        }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
</head>
<body class="bg-[#1E1B2E]">
    <div class="container mx-auto p-4">
        <div class="flex justify-between mb-4 no-print">
            <a href="../cashier/transaksi.php" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 flex items-center space-x-2 transition">
                <i class="fas fa-arrow-left"></i>
                <span>Kembali</span>
            </a>
            <div class="flex space-x-2">
                <button onclick="window.print()" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 flex items-center space-x-2 transition">
                    <i class="fas fa-print"></i>
                    <span>Cetak</span>
                </button>
                <a href="download_receipt.php?id=<?= $transaction_id ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center space-x-2 transition">
                    <i class="fas fa-download"></i>
                    <span>PDF</span>
                </a>
                <?php if ($transaction['fid_member']): ?>
                <button onclick="sendToWhatsApp()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center space-x-2 transition">
                    <i class="fab fa-whatsapp"></i>
                    <span>WhatsApp</span>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="receipt-container">
            <div class="receipt">
                <!-- Header Struk -->
                <div class="receipt-header">
                    <div class="receipt-title">MEDIPOS</div>
                    <div class="receipt-address">Jl. Swadaya 4 Kp. Pulo Jahe No. 71</div>
                    <div class="receipt-address">Jakarta Timur</div>
                    <div class="receipt-address">Telp: (081) 2844-21151</div>
                    <div class="receipt-address"><?= date('d/m/Y') ?></div>
                </div>

                <!-- Info Transaksi -->
                <div class="receipt-item">
                    <span>No. TRX:</span>
                    <span>TRX-<?= str_pad($transaction['id'], 6, "0", STR_PAD_LEFT) ?></span>
                </div>
                <div class="receipt-item">
                    <span>Kasir:</span>
                    <span><?= htmlspecialchars($transaction['kasir']) ?></span>
                </div>
                
                <?php if ($transaction['fid_member']): ?>
                <div class="receipt-divider"></div>
                <div class="receipt-item">
                    <span>Member:</span>
                    <span>M-<?= str_pad($transaction['fid_member'], 6, "0", STR_PAD_LEFT) ?></span>
                </div>
                <div class="receipt-item">
                    <span>Poin:</span>
                    <span><?= $transaction['points'] ?> pts</span>
                </div>
                <?php endif; ?>

                <div class="receipt-divider"></div>

                <!-- Daftar Produk -->
                <?php foreach ($details as $item): ?>
                <div class="receipt-item">
                    <span><?= htmlspecialchars($item['product_name']) ?> x<?= $item['quantity'] ?></span>
                    <span>Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></span>
                </div>
                <?php endforeach; ?>

                <div class="receipt-divider"></div>

                <!-- Total Pembayaran -->
                <?php if ($transaction['discount'] > 0): ?>
                <div class="receipt-item">
                    <span>Subtotal:</span>
                    <span>Rp <?= number_format($subtotal_before_discount, 0, ',', '.') ?></span>
                </div>
                <div class="receipt-item">
                    <span>Diskon (<?= ($transaction['discount'] * 100) ?>%):</span>
                    <span>- Rp <?= number_format($discount_amount, 0, ',', '.') ?></span>
                </div>
                <?php endif; ?>

                <div class="receipt-item receipt-total">
                    <span>TOTAL:</span>
                    <span>Rp <?= number_format($transaction['total_price'], 0, ',', '.') ?></span>
                </div>

                <div class="receipt-item">
                    <span>Pembayaran:</span>
                    <span><?= ucfirst($transaction['payment_method']) ?></span>
                </div>
                <div class="receipt-item">
                    <span>Tunai:</span>
                    <span>Rp <?= number_format($transaction['paid_amount'], 0, ',', '.') ?></span>
                </div>
                <div class="receipt-item">
                    <span>Kembali:</span>
                    <span>Rp <?= number_format($transaction['kembalian'], 0, ',', '.') ?></span>
                </div>

                <!-- Footer Struk -->
                <div class="receipt-footer">
                    <div>Terima kasih telah berbelanja</div>
                    <div>Barang yang sudah dibeli</div>
                    <div>tidak dapat ditukar/dikembalikan</div>
                    <div class="mt-2">www.medipos.com</div>
                </div>

                
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
                        const transactionUrl = window.location.origin + window.location.pathname + '?id=<?= $transaction_id ?>';
                        
                        let message = `Halo Member MediPOS! Berikut detail transaksi Anda:\n\n` +
                                     `No. Transaksi: TRX-<?= str_pad($transaction['id'], 6, "0", STR_PAD_LEFT) ?>\n` +
                                     `Tanggal: <?= date('d/m/Y H:i:s') ?>\n` +
                                     `Kasir: <?= htmlspecialchars($transaction['kasir']) ?>\n\n` +
                                     `Detail Pembelian:\n`;
                        
                        <?php foreach ($details as $item): ?>
                        message += `- <?= htmlspecialchars($item['product_name']) ?> ` +
                                   `(<?= $item['quantity'] ?> x Rp <?= number_format($item['harga'], 0, ',', '.') ?>)\n`;
                        <?php endforeach; ?>
                        
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
                        
                        const encodedMessage = encodeURIComponent(message);
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