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
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#faf5ff',
                            100: '#f3e8ff',
                            200: '#e9d5ff',
                            300: '#d8b4fe',
                            400: '#c084fc',
                            500: '#a855f7',
                            600: '#9333ea',
                            700: '#7e22ce',
                            800: '#6b21a8',
                            900: '#581c87',
                        }
                    }
                }
            }
        }
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            transition: all 0.3s;
            background: linear-gradient(180deg, #6b21a8 0%, #7e22ce 100%);
        }
        .active-menu {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        .active-menu:hover {
            background-color: rgba(255, 255, 255, 0.15);
        }
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .quick-action-card {
            transition: all 0.2s;
            border: 1px solid #e9d5ff;
        }
        .quick-action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="sidebar w-64 shadow-lg text-white">
            <div class="p-4 border-b border-purple-800">
                <h1 class="text-xl font-bold text-white">Medi<span class="text-purple-300">POS</span></h1>
                <p class="text-sm text-purple-200">Kasir Dashboard</p>
            </div>
            <div class="p-4">
                <div class="flex items-center space-x-3 mb-6 p-3 rounded-lg bg-purple-700/30">
                    <div class="w-10 h-10 rounded-full bg-purple-600 flex items-center justify-center">
                        <i class="fas fa-user text-purple-100"></i>
                    </div>
                    <div>
                        <p class="font-medium"><?= htmlspecialchars($_SESSION['username']) ?></p>
                        <p class="text-xs text-purple-200">Kasir</p>
                    </div>
                </div>
                
                <nav class="space-y-1">
                    <a href="dashboard.php" class="block py-2 px-3 mb-1 rounded-lg active-menu">
                        <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                    </a>
                    <a href="transaksi.php" class="block py-2 px-3 mb-1 rounded-lg hover:bg-white/10 text-purple-100">
                        <i class="fas fa-cash-register mr-2"></i> Transaksi Baru
                    </a>
                    <a href="daftar_transaksi.php" class="block py-2 px-3 mb-1 rounded-lg hover:bg-white/10 text-purple-100">
                        <i class="fas fa-list mr-2"></i> Daftar Transaksi
                    </a>
                    <a href="produk.php" class="block py-2 px-3 mb-1 rounded-lg hover:bg-white/10 text-purple-100">
                        <i class="fas fa-boxes mr-2"></i> Kelola Produk
                    </a>
                    <hr class="my-3 border-purple-700">
                    <a href="../service/logout.php" class="block py-2 px-3 mb-1 rounded-lg hover:bg-red-500/10 text-purple-100">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto bg-gray-50">
            <!-- Header -->
            <header class="bg-white shadow-sm p-4 flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-tachometer-alt mr-2 text-primary-600"></i> Dashboard
                </h2>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500">
                        <i class="far fa-calendar-alt mr-1"></i> <?= date('d F Y') ?>
                    </span>
                    <div class="relative">
                        <button class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-primary-600 hover:bg-primary-200">
                            <i class="fas fa-bell"></i>
                        </button>
                        <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="p-6">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="stat-card bg-white rounded-lg shadow p-6 border-l-primary-500">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-500">Penjualan Hari Ini</p>
                                <h3 class="text-2xl font-bold mt-1 text-primary-600"><?= format_currency($today_sales) ?></h3>
                            </div>
                            <div class="p-3 rounded-full bg-primary-50 text-primary-600">
                                <i class="fas fa-wallet text-lg"></i>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-primary-500 flex items-center">
                            <i class="fas fa-arrow-up mr-1"></i>
                            <span>12% dari kemarin</span>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow p-6 border-l-blue-500">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-500">Transaksi Hari Ini</p>
                                <h3 class="text-2xl font-bold mt-1 text-blue-600"><?= $today_transactions ?></h3>
                            </div>
                            <div class="p-3 rounded-full bg-blue-50 text-blue-600">
                                <i class="fas fa-receipt text-lg"></i>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-blue-500 flex items-center">
                            <i class="fas fa-arrow-up mr-1"></i>
                            <span>5 transaksi baru</span>
                        </div>
                    </div>
                    
                    <div class="stat-card bg-white rounded-lg shadow p-6 border-l-purple-500">
                        <div class="flex justify-between items-start">
                            <div>
                                <p class="text-gray-500">Produk Tersedia</p>
                                <h3 class="text-2xl font-bold mt-1 text-purple-600">142</h3>
                            </div>
                            <div class="p-3 rounded-full bg-purple-50 text-purple-600">
                                <i class="fas fa-box-open text-lg"></i>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-purple-500">
                            <span>8 produk hampir habis</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700">Aksi Cepat</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <a href="transaksi_baru.php" class="quick-action-card bg-white rounded-lg shadow p-4 text-center hover:border-primary-300">
                            <div class="text-primary-600 mb-2">
                                <i class="fas fa-cash-register text-3xl"></i>
                            </div>
                            <p class="font-medium text-gray-700">Transaksi Baru</p>
                            <p class="text-xs text-gray-500 mt-1">Buat transaksi baru</p>
                        </a>
                        <a href="produk.php" class="quick-action-card bg-white rounded-lg shadow p-4 text-center hover:border-blue-300">
                            <div class="text-blue-600 mb-2">
                                <i class="fas fa-search text-3xl"></i>
                            </div>
                            <p class="font-medium text-gray-700">Cari Produk</p>
                            <p class="text-xs text-gray-500 mt-1">Temukan produk cepat</p>
                        </a>
                        <a href="daftar_transaksi.php" class="quick-action-card bg-white rounded-lg shadow p-4 text-center hover:border-green-300">
                            <div class="text-green-600 mb-2">
                                <i class="fas fa-history text-3xl"></i>
                            </div>
                            <p class="font-medium text-gray-700">Riwayat Transaksi</p>
                            <p class="text-xs text-gray-500 mt-1">Lihat transaksi lalu</p>
                        </a>
                        <a href="laporan_harian.php" class="quick-action-card bg-white rounded-lg shadow p-4 text-center hover:border-orange-300">
                            <div class="text-orange-600 mb-2">
                                <i class="fas fa-chart-bar text-3xl"></i>
                            </div>
                            <p class="font-medium text-gray-700">Laporan Harian</p>
                            <p class="text-xs text-gray-500 mt-1">Ringkasan penjualan</p>
                        </a>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="p-4 border-b flex justify-between items-center bg-gradient-to-r from-primary-600 to-primary-700">
                        <h3 class="font-semibold text-white">Transaksi Terakhir</h3>
                        <a href="daftar_transaksi.php" class="text-sm text-purple-200 hover:text-white hover:underline">Lihat Semua</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Waktu</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <!-- Sample Data - Replace with actual PHP loop -->
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-primary-600">#TRX-20230801-001</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">10:25 AM</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">3 Items</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp 125.000</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Selesai</span>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-primary-600">#TRX-20230801-002</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">11:42 AM</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">5 Items</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp 87.500</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Selesai</span>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-primary-600">#TRX-20230801-003</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">01:15 PM</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2 Items</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Rp 42.000</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Pending</span>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Add any interactive functionality here
            const currentPage = window.location.pathname.split('/').pop();
            const menuItems = document.querySelectorAll('nav a');
            
            menuItems.forEach(item => {
                if (item.getAttribute('href') === currentPage) {
                    item.classList.add('active-menu');
                } else {
                    item.classList.remove('active-menu');
                }
            });
        });
    </script>
</body>
</html>