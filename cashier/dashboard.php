<?php
require_once '../service/connection.php';
session_start();
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../service/login.php");
    exit();
}
// Secure session and role check
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Validate session and role
if (!isset($_SESSION['logged_in'])) {
    header("Location: ../service/login.php");
    exit;
}

if ($_SESSION['role'] !== 'cashier') {
    header("Location: ../unauthorized.php");
    exit;
}

// Verify session security
if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] || 
    $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    session_destroy();
    header("Location: ../service/login.php");
    exit;
}

// Get today's transactions
$today_sales = 0;
$today_transactions = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(total) as total 
                           FROM transactions 
                           WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $today_transactions = $row['count'];
        $today_sales = $row['total'] ?? 0;
    }
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Format currency
function format_currency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Kasir - MediPOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            transition: all 0.3s;
        }
        .active-menu {
            background-color: #7C3AED;
            color: white;
        }
        .active-menu:hover {
            background-color: #6B21A8;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <!-- [Rest of your HTML content remains exactly the same] -->
</body>
</html>
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="sidebar w-64 bg-white shadow-lg">
            <div class="p-4 border-b">
                <h1 class="text-xl font-bold text-purple-800">Medi<span class="text-purple-600">POS</span></h1>
                <p class="text-sm text-gray-500">Kasir Dashboard</p>
            </div>
            <div class="p-4">
                <div class="flex items-center space-x-3 mb-6">
                    <div class="w-10 h-10 rounded-full bg-purple-200 flex items-center justify-center">
                        <i class="fas fa-user text-purple-800"></i>
                    </div>
                    <div>
                        <p class="font-medium"><?= htmlspecialchars($_SESSION['username']) ?></p>
                        <p class="text-xs text-gray-500">Kasir</p>
                    </div>
                </div>
                
                <nav>
                    <a href="dashboard.php" class="block py-2 px-3 mb-1 rounded active-menu">
                        <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                    </a>
                    <a href="transaksi.php" class="block py-2 px-3 mb-1 rounded hover:bg-purple-100">
                        <i class="fas fa-cash-register mr-2"></i> Transaksi Baru
                    </a>
                    <a href="daftar_transaksi.php" class="block py-2 px-3 mb-1 rounded hover:bg-purple-100">
                        <i class="fas fa-list mr-2"></i> Daftar Transaksi
                    </a>
                    <a href="produk.php" class="block py-2 px-3 mb-1 rounded hover:bg-purple-100">
                        <i class="fas fa-boxes mr-2"></i> Kelola Produk
                    </a>
                    <hr class="my-3">
                    <a href="../service/logout.php" class="block py-2 px-3 mb-1 rounded hover:bg-red-100 text-red-600">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Header -->
            <header class="bg-white shadow-sm p-4 flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-tachometer-alt mr-2 text-purple-600"></i> Dashboard
                </h2>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500">
                        <i class="far fa-calendar-alt mr-1"></i> <?= date('d F Y') ?>
                    </span>
                    <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center">
                        <i class="fas fa-bell text-purple-600"></i>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="p-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-500">Penjualan Hari Ini</p>
                                <h3 class="text-2xl font-bold mt-1"><?= format_currency($today_sales) ?></h3>
                            </div>
                            <div class="p-3 rounded-full bg-green-100 text-green-600">
                                <i class="fas fa-wallet"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-500">Transaksi Hari Ini</p>
                                <h3 class="text-2xl font-bold mt-1"><?= $today_transactions ?></h3>
                            </div>
                            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                <i class="fas fa-receipt"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-500">Produk Tersedia</p>
                                <h3 class="text-2xl font-bold mt-1">142</h3>
                            </div>
                            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                <i class="fas fa-box-open"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <a href="transaksi_baru.php" class="bg-white rounded-lg shadow p-4 text-center hover:shadow-md transition-all">
                            <div class="text-purple-600 mb-2">
                                <i class="fas fa-cash-register text-2xl"></i>
                            </div>
                            <p>Transaksi Baru</p>
                        </a>
                        <a href="produk.php" class="bg-white rounded-lg shadow p-4 text-center hover:shadow-md transition-all">
                            <div class="text-blue-600 mb-2">
                                <i class="fas fa-search text-2xl"></i>
                            </div>
                            <p>Cari Produk</p>
                        </a>
                        <a href="daftar_transaksi.php" class="bg-white rounded-lg shadow p-4 text-center hover:shadow-md transition-all">
                            <div class="text-green-600 mb-2">
                                <i class="fas fa-history text-2xl"></i>
                            </div>
                            <p>Riwayat Transaksi</p>
                        </a>
                        <a href="laporan_harian.php" class="bg-white rounded-lg shadow p-4 text-center hover:shadow-md transition-all">
                            <div class="text-orange-600 mb-2">
                                <i class="fas fa-chart-bar text-2xl"></i>
                            </div>
                            <p>Laporan Harian</p>
                        </a>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-4 border-b flex justify-between items-center">
                        <h3 class="font-semibold">Transaksi Terakhir</h3>
                        <a href="daftar_transaksi.php" class="text-sm text-purple-600 hover:underline">Lihat Semua</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Waktu</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <!-- Sample Data - Replace with actual PHP loop -->
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">#TRX-20230801-001</td>
                                    <td class="px-6 py-4">10:25 AM</td>
                                    <td class="px-6 py-4">3 Items</td>
                                    <td class="px-6 py-4">Rp 125.000</td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Selesai</span>
                                    </td>
                                </tr>
                                <!-- Add more rows with PHP foreach -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle would go here
        document.addEventListener('DOMContentLoaded', function() {
            // Any JavaScript functionality needed
        });
    </script>
</body>
</html>