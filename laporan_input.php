<?php
require_once './service/connection.php';
session_start();

$email = $_SESSION['email'];

$query = mysqli_query($conn, "SELECT * FROM admin WHERE email = '$email'");
$admin = mysqli_fetch_assoc($query);

$username = $admin['username'];
$image = !empty($admin['image']) ? 'uploads/' . $admin['image'] : 'default.jpg';

// Tangkap parameter filter
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'keseluruhan';

// Query untuk mendapatkan data transaksi
$query = "SELECT t.*, a.username as admin_name, m.name as member_name 
          FROM transactions t
          LEFT JOIN admin a ON t.fid_admin = a.id
          LEFT JOIN member m ON t.fid_member = m.id";

// Tambahkan filter tanggal jika dipilih
if ($report_type == 'periode' && !empty($start_date) && !empty($end_date)) {
    $query .= " WHERE DATE(t.date) BETWEEN '$start_date' AND '$end_date'";
}

$query .= " ORDER BY t.date DESC";
$transactions = mysqli_query($conn, $query);

// Hitung total pendapatan
$total_query = "SELECT SUM(total_price) as total_income FROM transactions";
if ($report_type == 'periode' && !empty($start_date)) {
    $total_query .= " WHERE DATE(date) BETWEEN '$start_date' AND '$end_date'";
}
$total_result = mysqli_query($conn, $total_query);
$total_income = mysqli_fetch_assoc($total_result)['total_income'];

// Hitung total margin
$margin_query = "SELECT SUM(margin_total) as total_margin FROM transactions";
if ($report_type == 'periode' && !empty($start_date)) {
    $margin_query .= " WHERE DATE(date) BETWEEN '$start_date' AND '$end_date'";
}
$margin_result = mysqli_query($conn, $margin_query);
$total_margin = mysqli_fetch_assoc($margin_result)['total_margin'];

// Hitung jumlah transaksi
$count_query = "SELECT COUNT(*) as total_transactions FROM transactions";
if ($report_type == 'periode' && !empty($start_date)) {
    $count_query .= " WHERE DATE(date) BETWEEN '$start_date' AND '$end_date'";
}
$count_result = mysqli_query($conn, $count_query);
$total_transactions = mysqli_fetch_assoc($count_result)['total_transactions'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - SmartCash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <style>
        .sidebar {
            transition: transform 0.3s ease, width 0.3s ease;
            border-radius: 0 20px 20px 0;
            width: 250px;
            background-color: #61892F;
            position: fixed;
            top: 50px;
            left: 0;
            height: calc(100% - 50px);
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }

        .sidebar-hidden {
            transform: translateX(-100%);
        }

        .sidebar a {
            display: block;
            padding: 10px;
            text-decoration: none;
            color: #ffffff;
            font-weight: 500;
            transition: all 0.3s;
        }

        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            transform: translateX(5px);
        }
        a, button {
            outline: none;
        }
        .content {
            transition: margin-left 0.3s ease;
            margin-left: 0;
            padding: 20px;
        }
        .sidebar-open .content {
            margin-left: 250px;
        }
        .hidden {
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: opacity 0.3s ease, transform 0.3s ease, visibility 0.3s ease;
        }
        .show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
    </style>
</head>
<body class="bg-[#F1F9E4] font-sans">

    <!-- Header -->
    <div class="container mx-auto p-4">
        <div class="flex items-center mb-8 relative">
            <i id="menuToggle" class="fas fa-bars text-2xl mr-4 cursor-pointer text-[#61892F] hover:text-[#4a6e24]"></i>
            <h1 class="text-3xl font-bold text-black">Smart <span class="text-[#779341]">Cash</span></h1>
            <div class="ml-auto flex items-center space-x-6">
            <a href="profil.php" class="flex items-center hover:opacity-80 transition">
                    <img src="<?= htmlspecialchars($image) ?>" alt="Foto Profil" class="w-10 h-10 rounded-full object-cover inline-block border-2 border-[#61892F]">
                    <span class="text-[#1F2937] font-medium ml-2"><?= htmlspecialchars($username) ?></span>
                </a>
            </div>
        </div>
    </div>

    <div class="flex justify-center items-center pt-10">
        <div class="bg-white p-8 rounded-lg shadow-md border border-[#8BC34A] w-full max-w-6xl">
            <h2 class="text-2xl font-bold text-[#4CAF50] mb-6">Laporan Transaksi</h2>
            
            <!-- Filter Form -->
            <form method="GET" class="mb-8">
                <div class="flex flex-wrap gap-4 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="report-type">
                            Jenis Laporan
                        </label>
                        <select name="report_type" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" id="report-type">
                            <option value="keseluruhan" <?= $report_type == 'keseluruhan' ? 'selected' : '' ?>>Laporan Keseluruhan</option>
                            <option value="periode" <?= $report_type == 'periode' ? 'selected' : '' ?>>Laporan Berdasarkan Periode</option>
                        </select>
                    </div>

                    <!-- Date Range Fields -->
                    <div id="dateRangeFields" class="flex-1 min-w-[200px] <?= $report_type != 'periode' ? 'hidden' : '' ?>">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal Mulai</label>
                                <input type="date" name="start_date" value="<?= $start_date ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Tanggal Akhir</label>
                                <input type="date" name="end_date" value="<?= $end_date ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            </div>
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="bg-[#4CAF50] hover:bg-[#388E3C] text-white font-bold py-2 px-6 rounded focus:outline-none focus:shadow-outline">
                            Filter
                        </button>
                        <?php if ($report_type == 'periode' && !empty($start_date)): ?>
                        <a href="laporan_input.php" class="ml-2 bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Reset
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow-md border border-[#8BC34A]">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Transaksi</h3>
                    <p class="text-3xl font-bold text-[#4CAF50]"><?= $total_transactions ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md border border-[#8BC34A]">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Pendapatan</h3>
                    <p class="text-3xl font-bold text-[#4CAF50]">Rp <?= number_format($total_income, 0, ',', '.') ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md border border-[#8BC34A]">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Margin</h3>
                    <p class="text-3xl font-bold text-[#4CAF50]">Rp <?= number_format($total_margin, 0, ',', '.') ?></p>
                </div>
            </div>

            <!-- Transaction Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white rounded-lg overflow-hidden">
                    <thead class="bg-[#8BC34A] text-white">
                        <tr>
                            <th class="py-3 px-4 text-left">ID</th>
                            <th class="py-3 px-4 text-left">Tanggal</th>
                            <th class="py-3 px-4 text-left">Admin</th>
                            <th class="py-3 px-4 text-left">Member</th>
                            <th class="py-3 px-4 text-left">Total</th>
                            <th class="py-3 px-4 text-left">Metode</th>
                            <th class="py-3 px-4 text-left">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php while($transaction = mysqli_fetch_assoc($transactions)): ?>
                        <tr>
                            <td class="py-3 px-4"><?= $transaction['id'] ?></td>
                            <td class="py-3 px-4"><?= date('d M Y H:i', strtotime($transaction['date'])) ?></td>
                            <td class="py-3 px-4"><?= $transaction['admin_name'] ?></td>
                            <td class="py-3 px-4"><?= $transaction['member_name'] ?? '-' ?></td>
                            <td class="py-3 px-4">Rp <?= number_format($transaction['total_price'], 0, ',', '.') ?></td>
                            <td class="py-3 px-4"><?= ucfirst($transaction['payment_method']) ?></td>
                            <td class="py-3 px-4">
                                <button onclick="showDetail(<?= $transaction['id'] ?>)" class="text-[#4CAF50] hover:text-[#388E3C]">
                                    <i class="fas fa-eye"></i> Detail
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Print Button -->
            <div class="mt-6 flex justify-end">
                <button onclick="window.print()" class="bg-[#4CAF50] hover:bg-[#388E3C] text-white font-bold py-2 px-6 rounded focus:outline-none focus:shadow-outline flex items-center">
                    <i class="fas fa-print mr-2"></i> Cetak Laporan
                </button>
                                            <!-- In the print button section -->

<a href="./service/download_report.php?<?= http_build_query($_GET) ?>" 
   class="ml-4 bg-[#4CAF50] hover:bg-[#388E3C] text-white font-bold py-2 px-6 rounded focus:outline-none focus:shadow-outline flex items-center">
    <i class="fas fa-file-pdf mr-2"></i> Download PDF
</a>
            </div>
        </div>
    </div>

    <!-- Modal for Transaction Details -->
    <div id="detailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Detail Transaksi #<span id="modalTransactionId"></span></h3>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="mb-4">
                <p><strong>Tanggal:</strong> <span id="modalDate"></span></p>
                <p><strong>Admin:</strong> <span id="modalAdmin"></span></p>
                <p><strong>Member:</strong> <span id="modalMember"></span></p>
                <p><strong>Total Harga:</strong> Rp <span id="modalTotal"></span></p>
                <p><strong>Total Margin:</strong> Rp <span id="modalMargin"></span></p>
                <p><strong>Metode Pembayaran:</strong> <span id="modalMethod"></span></p>
                <p><strong>Jumlah Dibayar:</strong> Rp <span id="modalPaid"></span></p>
                <p><strong>Kembalian:</strong> Rp <span id="modalChange"></span></p>
            </div>
            <h4 class="font-bold mb-2">Produk:</h4>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr class="bg-gray-100">
                        <th class="py-2 px-4 text-left">Nama Produk</th>
                        <th class="py-2 px-4 text-left">Harga</th>
                        <th class="py-2 px-4 text-left">Jumlah</th>
                        <th class="py-2 px-4 text-left">Margin</th>
                        <th class="py-2 px-4 text-left">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="modalProducts">
                        <!-- Products will be inserted here -->
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex justify-end">
                <button onclick="closeModal()" class="bg-[#4CAF50] hover:bg-[#388E3C] text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    <div id="sidebar" class="sidebar sidebar-hidden">
        <div class="mb-6 px-2">
            <h3 class="text-white font-bold text-lg mb-2">Menu Navigasi</h3>
            <div class="h-px bg-white bg-opacity-20 mb-3"></div>
        </div>
        <ul class="space-y-2">
            <li><a href="home.php" class="flex items-center"><i class="fas fa-home mr-3 w-5 text-center"></i> Beranda</a></li>
            <li><a href="kategori.php" class="flex items-center"><i class="fas fa-tags mr-3 w-5 text-center"></i> Kategori</a></li>
            <li><a href="produk.php" class="flex items-center"><i class="fas fa-box-open mr-3 w-5 text-center"></i> Produk</a></li>
            <li><a href="member.php" class="flex items-center"><i class="fas fa-users mr-3 w-5 text-center"></i> Member</a></li>
            <li><a href="admin.php" class="flex items-center"><i class="fas fa-user-shield mr-3 w-5 text-center"></i> Admin</a></li>
            <li><a href="transaksi.php" class="flex items-center"><i class="fas fa-shopping-cart mr-3 w-5 text-center"></i> Transaksi</a></li>
            <li><a href="laporan_input.php" class="flex items-center"><i class="fas fa-file-alt mr-3 w-5 text-center"></i> Laporan</a></li>
            <li class="mt-8 pt-4 border-t border-white border-opacity-20">
                <a href="#" id="logoutBtn" class="flex items-center text-red-200 font-semibold"><i class="fas fa-sign-out-alt mr-3 w-5 text-center"></i> Logout</a>
            </li>
        </ul>
    </div>

    <script>
        // Show date range inputs when "Laporan Berdasarkan Periode Waktu" is selected
        document.getElementById('report-type').addEventListener('change', function() {
            const dateRangeFields = document.getElementById('dateRangeFields');
            if (this.value === 'periode') {
                dateRangeFields.classList.remove('hidden');
            } else {
                dateRangeFields.classList.add('hidden');
            }
        });

        // Sidebar toggle functionality
        document.getElementById('menuToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('sidebar-hidden');
        });

        // Function to show transaction details modal
        function showDetail(transactionId) {
            fetch(`./service/get_transaction_detail.php?id=${transactionId}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('modalTransactionId').textContent = data.id;
                    document.getElementById('modalDate').textContent = new Date(data.date).toLocaleString();
                    document.getElementById('modalAdmin').textContent = data.admin_name;
                    document.getElementById('modalMember').textContent = data.member_name || '-';
                    document.getElementById('modalTotal').textContent = new Intl.NumberFormat('id-ID').format(data.total_price);
                    document.getElementById('modalMargin').textContent = new Intl.NumberFormat('id-ID').format(data.margin_total || 0);
                    document.getElementById('modalMethod').textContent = data.payment_method.charAt(0).toUpperCase() + data.payment_method.slice(1);
                    document.getElementById('modalPaid').textContent = new Intl.NumberFormat('id-ID').format(data.paid_amount);
                    document.getElementById('modalChange').textContent = new Intl.NumberFormat('id-ID').format(data.kembalian);

                    // Populate products
                    const productsTable = document.getElementById('modalProducts');
                    productsTable.innerHTML = '';
                    if (data.details && data.details.length > 0) {
                        data.details.forEach(item => {
                            const row = document.createElement('tr');
                            row.className = 'border-b';
                            row.innerHTML = `
                            <td class="py-2 px-4">${item.product_name}</td>
                            <td class="py-2 px-4">Rp ${new Intl.NumberFormat('id-ID').format(item.harga)}</td>
                            <td class="py-2 px-4">${item.quantity}</td>
                            <td class="py-2 px-4">Rp ${new Intl.NumberFormat('id-ID').format(item.margin || 0)}</td>
                            <td class="py-2 px-4">Rp ${new Intl.NumberFormat('id-ID').format(item.subtotal)}</td>
                        `;
                            productsTable.appendChild(row);
                        });
                    } else {
                        // Handle case where details are not available (using transaction.detail field)
                        const detailText = data.detail || 'Tidak ada detail produk';
                        const row = document.createElement('tr');
                        row.className = 'border-b';
                        row.innerHTML = `
                            <td colspan="4" class="py-2 px-4">${detailText}</td>
                        `;
                        productsTable.appendChild(row);
                    }

                    // Show modal
                    document.getElementById('detailModal').classList.remove('hidden');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Gagal memuat detail transaksi');
                });
        }

        function closeModal() {
            document.getElementById('detailModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>