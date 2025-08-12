<?php
require_once '../service/connection.php';
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: ../service/login.php");
    exit();
}

// Database connection
$email = $_SESSION['email'];

// Get cashier data
$query = mysqli_query($conn, "SELECT * FROM admin WHERE email = '$email'");
$cashier = mysqli_fetch_assoc($query);

// Set variables
$username = $cashier['username'];
$image = !empty($cashier['image']) ? '../uploads/' . $cashier['image'] : 'default.jpg';

// Get today's sales data
$today = date('Y-m-d');
$salesQuery = "SELECT COUNT(*) as total_transactions, SUM(total_price) as total_sales 
               FROM transactions 
               WHERE DATE(date) = '$today' AND fid_admin = ".$cashier['id'];
$salesResult = mysqli_query($conn, $salesQuery);
$salesData = mysqli_fetch_assoc($salesResult);
$totalSales = $salesData['total_sales'] ?? 0;
$totalTransactions = $salesData['total_transactions'] ?? 0;

// Get yesterday's sales for comparison
$yesterday = date('Y-m-d', strtotime('-1 day'));
$yesterdayQuery = "SELECT SUM(total_price) as total_sales 
                   FROM transactions 
                   WHERE DATE(date) = '$yesterday' AND fid_admin = ".$cashier['id'];
$yesterdayResult = mysqli_query($conn, $yesterdayQuery);
$yesterdayData = mysqli_fetch_assoc($yesterdayResult);
$yesterdaySales = $yesterdayData['total_sales'] ?? 0;

// Calculate sales change percentage
$salesChange = 0;
if ($yesterdaySales > 0) {
    $salesChange = (($totalSales - $yesterdaySales) / $yesterdaySales) * 100;
}

// Get low stock products (quantity < 5)
$lowStockQuery = "SELECT COUNT(*) as low_stock_count 
                  FROM products 
                  WHERE qty > 0 AND qty < 5";
$lowStockResult = mysqli_query($conn, $lowStockQuery);
$lowStockData = mysqli_fetch_assoc($lowStockResult);
$lowStockCount = $lowStockData['low_stock_count'] ?? 0;

// Handle search functionality
$searchKeyword = '';
$searchCondition = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchKeyword = mysqli_real_escape_string($conn, $_GET['search']);
    $searchCondition = " WHERE (t.id LIKE '%$searchKeyword%' OR 
                               a.username LIKE '%$searchKeyword%' OR
                               t.payment_method LIKE '%$searchKeyword%' OR
                               t.date LIKE '%$searchKeyword%') ";
}

// Get recent transactions with search condition
$recentTransactionsQuery = "SELECT t.id, t.date, t.total_price, t.payment_method, a.username 
                            FROM transactions t
                            JOIN admin a ON t.fid_admin = a.id
                            WHERE t.fid_admin = ".$cashier['id']."
                            " . ($searchCondition ? 'AND' . substr($searchCondition, 6) : '') . "
                            ORDER BY t.date DESC 
                            LIMIT 5";
$recentTransactionsResult = mysqli_query($conn, $recentTransactionsQuery);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Dashboard - MediPOS</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
        }
        .sidebar {
            background-color: #6b46c1;
            color: white;
        }
        .sidebar a:hover {
            background-color: #805ad5;
        }
        .stat-card {
            border-left: 4px solid #6b46c1;
        }
        .bg-cashier {
            background-color: #6b46c1;
        }
        .text-cashier {
            color: #6b46c1;
        }
        .nav-active {
            background-color: #805ad5;
        }
        .payment-cash {
            background-color: #e6ffed;
            color: #38a169;
        }
        .payment-transfer {
            background-color: #ebf8ff;
            color: #3182ce;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="sidebar w-64 px-4 py-8 shadow-lg fixed h-full">
            <div class="flex items-center justify-center mb-8">
                <h1 class="text-2xl font-bold">
                    <span class="text-white">Medi</span><span class="text-purple-300">POS</span>
                </h1>
            </div>
            
            <div class="flex items-center px-4 py-3 mb-6 rounded-lg bg-purple-900">
                <div class="w-10 h-10 rounded-full bg-purple-700 flex items-center justify-center">
                    <i class="fas fa-user-tie text-white"></i>
                </div>
                <div class="ml-3">
                    <p class="font-medium text-white"><?= htmlspecialchars($username) ?></p>
                    <p class="text-xs text-purple-200">Kasir</p>
                </div>
            </div>

            <nav class="mt-8">
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg nav-active">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="transaksi.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-cash-register mr-3"></i>
                    Transaksi
                </a>
                <a href="manage_member.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-users mr-3"></i>
                    Kelola Member
                </a>
                <a href="reports.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Laporan
                </a>
                <a href="../service/logout.php" class="flex items-center px-4 py-0 rounded-lg hover:bg-purple-800 mt-5 text-red-200">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="ml-64 flex-1 overflow-y-auto">
            <header class="bg-white shadow-sm">
                <div class="flex justify-between items-center px-6 py-4">
                    <h2 class="text-xl font-semibold text-gray-800">Dashboard Kasir</h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500" id="currentDateTime"></span>
                        <div class="relative">
                            <a href="profile.php">
                                <img src="<?= $image ?>" 
                                     alt="Profile" 
                                     class="w-8 h-8 rounded-full border-2 border-purple-500 cursor-pointer">
                                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full"></span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <!-- Welcome Card -->
                <div class="bg-white rounded-xl shadow-md p-6 mb-6 border-l-4 border-purple-600">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-2xl font-bold mb-2 text-gray-800">Halo, Kasir <?= htmlspecialchars($username) ?>!</h2>
                            <p class="text-gray-600">Selamat bekerja hari ini! Berikut ringkasan aktivitas Anda.</p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <i class="fas fa-user-tie text-purple-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="stat-card bg-white rounded-lg p-6 shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm">Total Penjualan Hari Ini</h3>
                                <p class="text-2xl font-bold text-purple-600">Rp <?= number_format($totalSales, 0, ',', '.') ?></p>
                                <p class="text-xs mt-1 <?= $salesChange >= 0 ? 'text-green-500' : 'text-red-500' ?>">
                                    <?= $salesChange >= 0 ? '↑' : '↓' ?> <?= number_format(abs($salesChange), 2) ?>% dari kemarin
                                </p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-money-bill-wave text-purple-600"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="reports.php" class="text-purple-600 text-sm hover:underline">Lihat Laporan →</a>
                        </div>
                    </div>

                    <div class="stat-card bg-white rounded-lg p-6 shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm">Transaksi Hari Ini</h3>
                                <p class="text-2xl font-bold text-purple-600"><?= $totalTransactions ?></p>
                                <p class="text-xs mt-1 text-gray-500"><?= date('d M Y') ?></p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-receipt text-purple-600"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="transaksi.php" class="text-purple-600 text-sm hover:underline">Buat Transaksi →</a>
                        </div>
                    </div>

                    <div class="stat-card bg-white rounded-lg p-6 shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm">Stok Hampir Habis</h3>
                                <p class="text-2xl font-bold text-purple-600"><?= $lowStockCount ?></p>
                                <p class="text-xs mt-1 text-gray-500">Produk dengan stok < 5</p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-exclamation-triangle text-purple-600"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="#" class="text-purple-600 text-sm hover:underline">Lihat Detail →</a>
                        </div>
                    </div>
                </div>

                <!-- Two Column Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Transactions -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 bg-purple-50">
                            <div class="flex justify-between items-center">
                                <h3 class="font-semibold text-lg text-purple-800">Transaksi Terakhir</h3>
                                <form method="GET" action="" class="relative">
                                    <input
                                        type="text"
                                        name="search"
                                        placeholder="Cari transaksi..."
                                        value="<?= htmlspecialchars($searchKeyword) ?>"
                                        class="bg-white border border-gray-300 px-4 py-1 rounded-lg focus:outline-none focus:ring-1 focus:ring-purple-500 text-sm w-48">
                                </form>
                            </div>
                        </div>
                        <div class="divide-y divide-gray-200">
                            <?php if (mysqli_num_rows($recentTransactionsResult) > 0): ?>
                                <?php while ($transaction = mysqli_fetch_assoc($recentTransactionsResult)): ?>
                                    <div class="px-6 py-4 hover:bg-purple-50">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="font-medium">TRX-<?= $transaction['id'] ?></p>
                                                <p class="text-sm text-gray-500"><?= date('d M Y H:i', strtotime($transaction['date'])) ?></p>
                                            </div>
                                            <div class="text-right">
                                                <p class="font-bold text-purple-600">Rp <?= number_format($transaction['total_price'], 0, ',', '.') ?></p>
                                                <span class="text-xs px-2 py-1 rounded-full <?= $transaction['payment_method'] == 'tunai' ? 'payment-cash' : 'payment-transfer' ?>">
                                                    <?= ucfirst($transaction['payment_method']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="px-6 py-4 text-center text-gray-500">
                                    Tidak ada transaksi ditemukan
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="px-6 py-4 bg-purple-50 text-right">
                            <a href="reports.php" class="text-sm text-purple-600 hover:underline font-medium">Lihat semua →</a>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 bg-purple-50">
                            <h3 class="font-semibold text-lg text-purple-800">Aksi Cepat</h3>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <a href="transaksi.php" class="bg-purple-100 hover:bg-purple-200 p-4 rounded-lg text-center transition-colors">
                                <div class="text-purple-600 mb-2">
                                    <i class="fas fa-cash-register text-2xl"></i>
                                </div>
                                <p class="font-medium text-purple-800">Transaksi Baru</p>
                            </a>
                            <a href="manage_member.php" class="bg-purple-100 hover:bg-purple-200 p-4 rounded-lg text-center transition-colors">
                                <div class="text-purple-600 mb-2">
                                    <i class="fas fa-user-plus text-2xl"></i>
                                </div>
                                <p class="font-medium text-purple-800">Tambah Member</p>
                            </a>
                            <a href="reports.php" class="bg-purple-100 hover:bg-purple-200 p-4 rounded-lg text-center transition-colors">
                                <div class="text-purple-600 mb-2">
                                    <i class="fas fa-file-pdf text-2xl"></i>
                                </div>
                                <p class="font-medium text-purple-800">Cetak Laporan</p>
                            </a>
                            <a href="#" class="bg-purple-100 hover:bg-purple-200 p-4 rounded-lg text-center transition-colors">
                                <div class="text-purple-600 mb-2">
                                    <i class="fas fa-bell text-2xl"></i>
                                </div>
                                <p class="font-medium text-purple-800">Notifikasi</p>
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Update date and time
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('currentDateTime').textContent = now.toLocaleDateString('id-ID', options);
        }
        
        setInterval(updateDateTime, 1000);
        updateDateTime();

        // Logout confirmation
        document.querySelector('a[href="../service/logout.php"]').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Anda yakin ingin logout?')) {
                window.location.href = this.getAttribute('href');
            }
        });
    </script>
</body>
</html>