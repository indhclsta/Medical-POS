<?php
// admin/dashboard.php
require_once '../includes/auth_check.php';

// Pastikan hanya Super Admin yang bisa akses
if ($_SESSION['role'] !== 'super_admin') {
    header('Location: /unauthorized.php');
    exit();
}

// Ambil data statistik
$conn = require_once '../includes/db_connect.php';

// Hitung jumlah admin
$adminCount = $conn->query("SELECT COUNT(*) FROM admin WHERE status='active'")->fetch_row()[0];

// Hitung jumlah transaksi hari ini
$today = date('Y-m-d');
$transactionCount = $conn->query("SELECT COUNT(*) FROM transactions WHERE DATE(date) = '$today'")->fetch_row()[0];

// Hitung total pendapatan bulan ini
$currentMonth = date('Y-m');
$revenue = $conn->query("SELECT SUM(total_price) FROM transactions WHERE DATE_FORMAT(date, '%Y-%m') = '$currentMonth'")->fetch_row()[0];
$revenue = $revenue ? number_format($revenue) : 0;

// Ambil admin pending verifikasi
$pendingAdmins = $conn->query("SELECT id, username, email FROM admin WHERE status='pending' LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            transition: all 0.3s;
        }
        .active-menu {
            border-left: 4px solid #779341;
            background-color: #f1f9e4;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="sidebar bg-white w-64 shadow-lg">
            <div class="p-4 border-b">
                <h1 class="text-xl font-bold text-primary flex items-center">
                    <img src="../image/icon.png" alt="Logo" class="w-8 h-8 mr-2">
                    SmartCash
                </h1>
                <p class="text-sm text-gray-500">Super Admin Panel</p>
            </div>
            <nav class="mt-4">
                <a href="dashboard.php" class="block py-2 px-4 active-menu">
                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                </a>
                <a href="manage_admins.php" class="block py-2 px-4 hover:bg-gray-100">
                    <i class="fas fa-users-cog mr-2"></i> Kelola Admin
                </a>
                <a href="../shared/products/list_products.php" class="block py-2 px-4 hover:bg-gray-100">
                    <i class="fas fa-boxes mr-2"></i> Produk
                </a>
                <a href="../shared/members/list_members.php" class="block py-2 px-4 hover:bg-gray-100">
                    <i class="fas fa-id-card mr-2"></i> Member
                </a>
                <a href="system_report.php" class="block py-2 px-4 hover:bg-gray-100">
                    <i class="fas fa-chart-bar mr-2"></i> Laporan
                </a>
                <a href="../shared/auth/logout.php" class="block py-2 px-4 hover:bg-gray-100 text-red-500">
                    <i class="fas fa-sign-out-alt mr-2"></i> Logout
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Header -->
            <header class="bg-white shadow-sm p-4 flex justify-between items-center">
                <h2 class="text-xl font-semibold">Dashboard</h2>
                <div class="flex items-center space-x-4">
                    <span class="text-sm"><?= date('d F Y') ?></span>
                    <div class="relative">
                        <img src="../uploads/<?= $_SESSION['image'] ?? 'default.jpg' ?>" 
                             class="w-10 h-10 rounded-full cursor-pointer" 
                             id="profileDropdown">
                        <div class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg hidden" 
                             id="dropdownContent">
                            <a href="profile.php" class="block px-4 py-2 hover:bg-gray-100">
                                <i class="fas fa-user mr-2"></i> Profil
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Dashboard Content -->
            <main class="p-6">
                <!-- Statistik -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-lg shadow">
                        <div class="flex justify-between">
                            <div>
                                <p class="text-gray-500">Admin Aktif</p>
                                <h3 class="text-2xl font-bold"><?= $adminCount ?></h3>
                            </div>
                            <div class="text-primary text-3xl">
                                <i class="fas fa-users-cog"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow">
                        <div class="flex justify-between">
                            <div>
                                <p class="text-gray-500">Transaksi Hari Ini</p>
                                <h3 class="text-2xl font-bold"><?= $transactionCount ?></h3>
                            </div>
                            <div class="text-blue-500 text-3xl">
                                <i class="fas fa-receipt"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-lg shadow">
                        <div class="flex justify-between">
                            <div>
                                <p class="text-gray-500">Pendapatan Bulan Ini</p>
                                <h3 class="text-2xl font-bold">Rp <?= $revenue ?></h3>
                            </div>
                            <div class="text-green-500 text-3xl">
                                <i class="fas fa-wallet"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Verifikasi Admin -->
                <div class="bg-white p-6 rounded-lg shadow mb-8">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Verifikasi Admin Baru</h3>
                        <a href="manage_admins.php" class="text-sm text-primary hover:underline">Lihat Semua</a>
                    </div>
                    
                    <?php if ($pendingAdmins->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-6 py-3 text-left">Nama</th>
                                        <th class="px-6 py-3 text-left">Email</th>
                                        <th class="px-6 py-3 text-left">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($admin = $pendingAdmins->fetch_assoc()): ?>
                                        <tr class="border-b">
                                            <td class="px-6 py-4"><?= htmlspecialchars($admin['username']) ?></td>
                                            <td class="px-6 py-4"><?= htmlspecialchars($admin['email']) ?></td>
                                            <td class="px-6 py-4">
                                                <a href="verify_admin.php?id=<?= $admin['id'] ?>" 
                                                   class="text-primary hover:underline mr-3">
                                                    <i class="fas fa-check-circle"></i> Verifikasi
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-500">Tidak ada admin yang menunggu verifikasi</p>
                    <?php endif; ?>
                </div>

                <!-- Grafik Aktivitas -->
                <div class="bg-white p-6 rounded-lg shadow">
                    <h3 class="text-lg font-semibold mb-4">Aktivitas Transaksi 7 Hari Terakhir</h3>
                    <div class="h-64" id="transactionChart">
                        <!-- Placeholder untuk grafik (bisa diisi dengan Chart.js) -->
                        <div class="flex items-center justify-center h-full bg-gray-50 rounded">
                            <p class="text-gray-400">Grafik akan ditampilkan di sini</p>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Dropdown profile
        document.getElementById('profileDropdown').addEventListener('click', function() {
            document.getElementById('dropdownContent').classList.toggle('hidden');
        });

        // Tutup dropdown saat klik di luar
        window.addEventListener('click', function(e) {
            if (!e.target.matches('#profileDropdown')) {
                const dropdown = document.getElementById('dropdownContent');
                if (!dropdown.classList.contains('hidden')) {
                    dropdown.classList.add('hidden');
                }
            }
        });

        // Contoh inisialisasi grafik dengan Chart.js
        // (Pastikan sudah include library Chart.js di head)
        /*
        const ctx = document.getElementById('transactionChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
                datasets: [{
                    label: 'Transaksi',
                    data: [12, 19, 3, 5, 2, 3, 15],
                    backgroundColor: 'rgba(119, 147, 65, 0.2)',
                    borderColor: 'rgba(119, 147, 65, 1)',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
        */
    </script>
</body>
</html>