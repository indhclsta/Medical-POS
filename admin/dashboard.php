<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("location:../service/login.php");
    exit();
}

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../service/login.php");
    exit();
}

// Database connection
include '../service/connection.php';
$email = $_SESSION['email'];

// Get admin data
$query = mysqli_query($conn, "SELECT * FROM admin WHERE email = '$email'");
$admin = mysqli_fetch_assoc($query);

// Set variables
$username = $admin['username'];
$image = !empty($admin['../image']) ? '../uploads/' . $admin['../image'] : 'default.jpg';

// Queries for statistics
$total_admin_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM admin");
$total_admin = mysqli_fetch_assoc($total_admin_query);

$admin_activity_query = mysqli_query($conn, "SELECT a.username, COUNT(t.id) as transactions 
                                           FROM admin a 
                                           LEFT JOIN transactions t ON a.id = t.fid_admin 
                                           GROUP BY a.username 
                                           ORDER BY transactions DESC 
                                           LIMIT 5");
$admin_activities = [];
while ($row = mysqli_fetch_assoc($admin_activity_query)) {
    $admin_activities[] = $row;
}

// Using transactions as temporary activity logs
$recent_logs_query = mysqli_query($conn, "SELECT 
    t.id,
    a.username,
    CONCAT('Transaksi #', t.id) as activity,
    t.date as timestamp
FROM transactions t
JOIN admin a ON t.fid_admin = a.id
ORDER BY t.date DESC 
LIMIT 5");
$recent_logs = [];
while ($row = mysqli_fetch_assoc($recent_logs_query)) {
    $recent_logs[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - MediPOS</title>
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
        .bg-super-admin {
            background-color: #6b46c1;
        }
        .text-super-admin {
            color: #6b46c1;
        }
        .nav-active {
            background-color: #805ad5;
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
                    <i class="fas fa-user-shield text-white"></i>
                </div>
                <div class="ml-3">
                    <p class="font-medium text-white"><?= htmlspecialchars($username) ?></p>
                    <p class="text-xs text-purple-200">Super Admin</p>
                </div>
            </div>

            <nav class="mt-8">
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg nav-active">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="manage_admin.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-user-cog mr-3"></i>
                    Kelola Kasir
                </a>
                <a href="manage_member.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-users mr-3"></i>
                    Kelola Member
                </a>
                <a href="manage_category.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-tags mr-3"></i>
                    Kategori Produk
                </a>
                <a href="manage_product.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-boxes mr-3"></i>
                    Kelola Produk
                </a>
                <a href="reports.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Laporan & Grafik
                </a>
                <a href="system_logs.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-clipboard-list mr-3"></i>
                    Log Sistem
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
        <h2 class="text-xl font-semibold text-gray-800">Kelola Kasir</h2>
        <div class="flex items-center space-x-4">
            <span class="text-sm text-gray-500" id="currentDateTime"></span>
            <div class="relative">
                <a href="profile.php">
                    <img src="../uploads/<?= htmlspecialchars($admin['image']) ?>" 
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
                            <h2 class="text-2xl font-bold mb-2 text-gray-800">Halo, Super Admin <?= htmlspecialchars($username) ?>!</h2>
                            <p class="text-gray-600">Anda memiliki akses penuh untuk mengelola sistem Medical POS.</p>
                        </div>
                        <div class="bg-purple-100 p-3 rounded-full">
                            <i class="fas fa-user-shield text-purple-600 text-2xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="stat-card bg-white rounded-lg p-6 shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm">Total Admin</h3>
                                <p class="text-2xl font-bold text-purple-600"><?= $total_admin['total'] ?></p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-users text-purple-600"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="manage_admin.php" class="text-purple-600 text-sm hover:underline">Kelola Kasir →</a>
                        </div>
                    </div>

                    <div class="stat-card bg-white rounded-lg p-6 shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm">Total Produk</h3>
                                <?php 
                                $product_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM products"));
                                ?>
                                <p class="text-2xl font-bold text-purple-600"><?= $product_count['total'] ?></p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-boxes text-purple-600"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="manage_product.php" class="text-purple-600 text-sm hover:underline">Kelola Produk →</a>
                        </div>
                    </div>

                    <div class="stat-card bg-white rounded-lg p-6 shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm">Total Member</h3>
                                <?php 
                                $member_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM member"));
                                ?>
                                <p class="text-2xl font-bold text-purple-600"><?= $member_count['total'] ?></p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-users text-purple-600"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="manage_member.php" class="text-purple-600 text-sm hover:underline">Kelola Member →</a>
                        </div>
                    </div>

                    <div class="stat-card bg-white rounded-lg p-6 shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm">Aktivitas Terakhir</h3>
                                <p class="text-2xl font-bold text-purple-600"><?= count($recent_logs) ?></p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-clipboard-list text-purple-600"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <a href="system_logs.php" class="text-purple-600 text-sm hover:underline">Lihat Log →</a>
                        </div>
                    </div>
                </div>

                <!-- Two Column Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Admin Activities -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 bg-purple-50">
                            <h3 class="font-semibold text-lg text-purple-800">Aktivitas Kasir</h3>
                        </div>
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($admin_activities as $admin): ?>
                            <div class="px-6 py-4 flex items-center justify-between hover:bg-purple-50">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center mr-3">
                                        <i class="fas fa-user text-purple-600"></i>
                                    </div>
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($admin['username']) ?></p>
                                        <p class="text-sm text-gray-500"><?= $admin['transactions'] ?> transaksi</p>
                                    </div>
                                </div>
                                <span class="text-xs px-2 py-1 rounded-full bg-purple-100 text-purple-800">
                                    <?= round($admin['transactions'] / array_sum(array_column($admin_activities, 'transactions')) * 100) ?>%
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="px-6 py-4 bg-purple-50 text-right">
                            <a href="admin_activities.php" class="text-sm text-purple-600 hover:underline font-medium">Lihat semua →</a>
                        </div>
                    </div>

                    <!-- Recent System Logs -->
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 bg-purple-50">
                            <h3 class="font-semibold text-lg text-purple-800">Aktivitas Terkini</h3>
                        </div>
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($recent_logs as $log): ?>
                            <div class="px-6 py-4 hover:bg-purple-50">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($log['activity']) ?></p>
                                        <p class="text-sm text-gray-500">Oleh: <?= htmlspecialchars($log['username']) ?></p>
                                    </div>
                                    <span class="text-xs text-purple-600 bg-purple-50 px-2 py-1 rounded">
                                        <?= date('H:i', strtotime($log['timestamp'])) ?>
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">
                                    <?= date('d M Y', strtotime($log['timestamp'])) ?>
                                </p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="px-6 py-4 bg-purple-50 text-right">
                            <a href="system_logs.php" class="text-sm text-purple-600 hover:underline font-medium">Lihat semua →</a>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="mt-8 bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-purple-50">
                        <h3 class="font-semibold text-lg text-purple-800">Aksi Cepat</h3>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                        <a href="manage_product.php?action=add" class="bg-purple-100 hover:bg-purple-200 p-4 rounded-lg text-center transition-colors">
                            <div class="text-purple-600 mb-2">
                                <i class="fas fa-plus-circle text-2xl"></i>
                            </div>
                            <p class="font-medium text-purple-800">Tambah Produk</p>
                        </a>
                        <a href="manage_category.php" class="bg-purple-100 hover:bg-purple-200 p-4 rounded-lg text-center transition-colors">
                            <div class="text-purple-600 mb-2">
                                <i class="fas fa-tags text-2xl"></i>
                            </div>
                            <p class="font-medium text-purple-800">Kelola Kategori</p>
                        </a>
                        <a href="reports.php" class="bg-purple-100 hover:bg-purple-200 p-4 rounded-lg text-center transition-colors">
                            <div class="text-purple-600 mb-2">
                                <i class="fas fa-file-pdf text-2xl"></i>
                            </div>
                            <p class="font-medium text-purple-800">Cetak Laporan</p>
                        </a>
                        <a href="manage_admin.php?action=add" class="bg-purple-100 hover:bg-purple-200 p-4 rounded-lg text-center transition-colors">
                            <div class="text-purple-600 mb-2">
                                <i class="fas fa-user-plus text-2xl"></i>
                            </div>
                            <p class="font-medium text-purple-800">Tambah Kasir</p>
                        </a>
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