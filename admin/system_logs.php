<?php
session_start();
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../service/login.php");
    exit();
}

include '../service/connection.php';

// Get admin data
$email = $_SESSION['email'];
$query = mysqli_query($conn, "SELECT * FROM admin WHERE email = '$email'");
$admin = mysqli_fetch_assoc($query);

// Pagination setup
$per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page > 1) ? ($page * $per_page) - $per_page : 0;

// Search and filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_admin = isset($_GET['admin']) ? (int)$_GET['admin'] : 0;

// Build query
$where = [];
if (!empty($search)) {
    $where[] = "(activity LIKE '%$search%' OR ip_address LIKE '%$search%')";
}
if ($filter_admin > 0) {
    $where[] = "admin_id = $filter_admin";
}
$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total logs
$total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM activity_logs $where_clause");
$total = mysqli_fetch_assoc($total_query)['total'];
$pages = ceil($total / $per_page);

// Get logs data
$logs_query = mysqli_query($conn, "SELECT l.*, a.username as admin_name 
                                  FROM activity_logs l 
                                  LEFT JOIN admin a ON l.admin_id = a.id 
                                  $where_clause 
                                  ORDER BY timestamp DESC 
                                  LIMIT $start, $per_page");

// Get all admins for filter dropdown
$admins_query = mysqli_query($conn, "SELECT id, username FROM admin ORDER BY username");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log Sistem - MediPOS</title>
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
        .bg-super-admin {
            background-color: #6b46c1;
        }
        .text-super-admin {
            color: #6b46c1;
        }
        .nav-active {
            background-color: #805ad5;
        }
        .pagination .active {
            background-color: #6b46c1;
            color: white;
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
                    <p class="font-medium text-white"><?= htmlspecialchars($admin['username']) ?></p>
                    <p class="text-xs text-purple-200">Super Admin</p>
                </div>
            </div>

            <nav class="mt-8">
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
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
                <a href="system_logs.php" class="flex items-center px-4 py-3 rounded-lg nav-active">
                    <i class="fas fa-clipboard-list mr-3"></i>
                    Log Sistem
                </a>
                <a href="../service/logout.php" class="flex items-center px-4 py-0 rounded-lg hover:bg-purple-800 mt-5 text-red-200">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
        </div>

        <!-- Main Content -->
        <div class="ml-64 flex-1 overflow-y-auto">
            <header class="bg-white shadow-sm">
                <div class="flex justify-between items-center px-6 py-4">
                    <h2 class="text-xl font-semibold text-gray-800">Log Sistem</h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500" id="currentDateTime"></span>
                        <div class="relative">
                            <a href="profile.php">
                                <img src="../uploads/<?= htmlspecialchars($admin['image']) ?>" 
                                     alt="Profile" 
                                     class="w-8 h-8 rounded-full border-2 border-purple-500 cursor-pointer">
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <!-- Search and Filter Card -->
                <div class="bg-white rounded-xl shadow-md p-6 mb-6">
                    <form method="GET" action="system_logs.php">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Cari Aktivitas</label>
                                <div class="relative">
                                    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" 
                                           class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500" 
                                           placeholder="Cari log...">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-search text-gray-400"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <label for="admin" class="block text-sm font-medium text-gray-700 mb-1">Filter Admin</label>
                                <select id="admin" name="admin" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                    <option value="0">Semua Admin</option>
                                    <?php while ($admin_row = mysqli_fetch_assoc($admins_query)): ?>
                                        <option value="<?= $admin_row['id'] ?>" <?= $filter_admin == $admin_row['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($admin_row['username']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="flex items-end">
                                <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                                    <i class="fas fa-filter mr-2"></i> Filter
                                </button>
                                <?php if (!empty($search) || $filter_admin > 0): ?>
                                    <a href="system_logs.php" class="ml-2 px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                        <i class="fas fa-times mr-2"></i> Reset
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Logs Table -->
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-purple-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-purple-800 uppercase tracking-wider">Waktu</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-purple-800 uppercase tracking-wider">Admin</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-purple-800 uppercase tracking-wider">Aktivitas</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-purple-800 uppercase tracking-wider">IP Address</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (mysqli_num_rows($logs_query) > 0): ?>
                                    <?php while ($log = mysqli_fetch_assoc($logs_query)): ?>
                                        <tr class="hover:bg-purple-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('d M Y H:i:s', strtotime($log['timestamp'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10 bg-purple-100 rounded-full flex items-center justify-center">
                                                        <i class="fas fa-user text-purple-600"></i>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?= htmlspecialchars($log['admin_name'] ?: 'System') ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500">
                                                <?= htmlspecialchars($log['activity']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= isset($log['ip_address']) ? htmlspecialchars($log['ip_address']) : 'N/A' ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">
                                            Tidak ada log yang ditemukan
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($pages > 1): ?>
                        <div class="px-6 py-4 bg-purple-50 flex items-center justify-between border-t border-gray-200">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <a href="system_logs.php?page=<?= $page-1 ?>&search=<?= $search ?>&admin=<?= $filter_admin ?>" 
                                   class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                    Sebelumnya
                                </a>
                                <a href="system_logs.php?page=<?= $page+1 ?>&search=<?= $search ?>&admin=<?= $filter_admin ?>" 
                                   class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 <?= $page >= $pages ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                    Selanjutnya
                                </a>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Menampilkan <span class="font-medium"><?= $start+1 ?></span> sampai <span class="font-medium"><?= min($start + $per_page, $total) ?></span> dari <span class="font-medium"><?= $total ?></span> log
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <a href="system_logs.php?page=<?= $page-1 ?>&search=<?= $search ?>&admin=<?= $filter_admin ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                            <span class="sr-only">Sebelumnya</span>
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                        
                                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                                            <a href="system_logs.php?page=<?= $i ?>&search=<?= $search ?>&admin=<?= $filter_admin ?>" 
                                               class="<?= $i == $page ? 'bg-purple-600 text-white' : 'bg-white text-gray-500 hover:bg-gray-50' ?> relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium">
                                                <?= $i ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <a href="system_logs.php?page=<?= $page+1 ?>&search=<?= $search ?>&admin=<?= $filter_admin ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?= $page >= $pages ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                            <span class="sr-only">Selanjutnya</span>
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
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