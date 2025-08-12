<?php
require_once '../service/connection.php';
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: ../service/login.php");
    exit();
}

// Get today's sales data
$today = date('Y-m-d');
$salesQuery = "SELECT COUNT(*) as total_transactions, SUM(total_price) as total_sales 
               FROM transactions 
               WHERE DATE(date) = '$today'";
$salesResult = mysqli_query($conn, $salesQuery);
$salesData = mysqli_fetch_assoc($salesResult);
$totalSales = $salesData['total_sales'] ?? 0;
$totalTransactions = $salesData['total_transactions'] ?? 0;

// Get yesterday's sales for comparison
$yesterday = date('Y-m-d', strtotime('-1 day'));
$yesterdayQuery = "SELECT SUM(total_price) as total_sales 
                   FROM transactions 
                   WHERE DATE(date) = '$yesterday'";
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
$userId = $_SESSION['id'];
$userQuery = "SELECT username, image FROM admin WHERE id = $userId";
$userResult = mysqli_query($conn, $userQuery);
$userData = mysqli_fetch_assoc($userResult);
$profilePicture = $userData['image'] ?? 'default.jpg';
// Get recent transactions with search condition
$recentTransactionsQuery = "SELECT t.id, t.date, t.total_price, t.payment_method, a.username 
                            FROM transactions t
                            JOIN admin a ON t.fid_admin = a.id
                            WHERE t.fid_admin = $userId 
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
    <title>MediPOS - Dashboard Kasir</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: #1E1B2E;
            font-family: 'Inter', sans-serif;
        }

        .sidebar {
            background: linear-gradient(180deg, #2A2540 0%, #1E1B2E 100%);
            border-right: 1px solid #3B3360;
        }

        .nav-item {
            transition: all 0.2s ease;
            border-radius: 0.5rem;
        }

        .nav-item:hover {
            background-color: rgba(155, 135, 245, 0.1);
        }

        .nav-item.active {
            background-color: #9B87F5;
            color: white;
        }

        .nav-item.active:hover {
            background-color: #8A75E5;
        }

        .stat-card {
            background: linear-gradient(135deg, #2A2540 0%, #3B3360 100%);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .table-row:hover {
            background-color: rgba(155, 135, 245, 0.05);
        }
    </style>
</head>

<body class="text-gray-200">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="sidebar w-64 flex flex-col p-5 space-y-8">
            <!-- Logo -->
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-lg bg-purple-500 flex items-center justify-center">
                    <span class="material-icons text-white">local_pharmacy</span>
                </div>
                <h1 class="text-xl font-bold text-purple-300">MediPOS</h1>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 flex flex-col space-y-2">
                <a href="dashboard.php" class="nav-item active flex items-center p-3 space-x-3">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="transaksi.php" class="nav-item flex items-center p-3 space-x-3">
                    <span class="material-icons">point_of_sale</span>
                    <span>Transaksi</span>
                </a>
                <a href="manage_member.php" class="nav-item flex items-center p-3 space-x-3">
                    <span class="material-icons">people</span>
                    <span>Member</span>
                </a>
                <a href="reports.php" class="nav-item flex items-center p-3 space-x-3">
                    <span class="material-icons">insert_chart</span>
                    <span>Laporan</span>
                </a>
            </nav>

            <!-- User & Logout -->
            <div class="mt-auto">
                <div class="flex items-center p-3 space-x-3 rounded-lg bg-[#3B3360]">
                    <?php if (!empty($profilePicture) && file_exists("../uploads/" . $profilePicture)): ?>
                        <img src="../uploads/<?php echo $profilePicture; ?>" class="w-10 h-10 rounded-full object-cover">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center">
                            <span class="material-icons">person</span>
                        </div>
                    <?php endif; ?>
                    <div class="flex-1">
                        <p class="font-medium"><?php echo $_SESSION['username']; ?></p>
                        <p class="text-xs text-purple-300">Kasir</p>
                    </div>
                    <a href="../service/logout.php" class="text-red-400 hover:text-red-300 transition">
                        <span class="material-icons">logout</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8 overflow-y-auto">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h2 class="text-2xl font-bold text-white">Dashboard Kasir</h2>
                        <p class="text-purple-300">Selamat datang kembali!</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <form method="GET" action="" class="relative">
                            <span class="material-icons absolute left-3 top-1/2 transform -translate-y-1/2 text-purple-300">search</span>
                            <input
                                type="text"
                                name="search"
                                placeholder="Cari transaksi..."
                                value="<?php echo htmlspecialchars($searchKeyword); ?>"
                                class="bg-[#2A2540] pl-10 pr-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 w-64">
                        </form>
                        <a href="transaksi.php" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition">
                            <span class="material-icons">add</span>
                            <span>Transaksi Baru</span>
                        </a>
                    </div>
                </div>

                <!-- Statistik -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="stat-card p-6 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-purple-300 mb-1">Total Penjualan</h3>
                                <p class="text-2xl font-bold text-white">Rp <?php echo number_format($totalSales, 0, ',', '.'); ?></p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-purple-500 bg-opacity-20 flex items-center justify-center">
                                <span class="material-icons text-purple-400">attach_money</span>
                            </div>
                        </div>
                        <p class="text-xs <?php echo $salesChange >= 0 ? 'text-green-400' : 'text-red-400'; ?> mt-2">
                            <?php echo $salesChange >= 0 ? '+' : ''; ?><?php echo number_format($salesChange, 2); ?>% dari kemarin
                        </p>
                    </div>

                    <div class="stat-card p-6 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-purple-300 mb-1">Jumlah Transaksi</h3>
                                <p class="text-2xl font-bold text-white"><?php echo $totalTransactions; ?></p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-blue-500 bg-opacity-20 flex items-center justify-center">
                                <span class="material-icons text-blue-400">receipt</span>
                            </div>
                        </div>
                        <p class="text-xs text-green-400 mt-2">Hari ini</p>
                    </div>

                    <div class="stat-card p-6 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-purple-300 mb-1">Stok Hampir Habis</h3>
                                <p class="text-2xl font-bold text-white"><?php echo $lowStockCount; ?> Produk</p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-red-500 bg-opacity-20 flex items-center justify-center">
                                <span class="material-icons text-red-400">warning</span>
                            </div>
                        </div>
                        <a href="#" class="text-xs text-purple-400 hover:text-purple-300 mt-2 inline-block transition">Lihat detail</a>
                    </div>
                </div>

                <!-- Riwayat Transaksi -->
                <div class="bg-[#2A2540] p-6 rounded-xl shadow-lg">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-white">Riwayat Transaksi Terbaru</h3>
                        <a href="reports.php" class="text-sm text-purple-400 hover:text-purple-300 transition">Lihat Semua</a>
                    </div>
                    <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                        <div class="mb-4 text-sm text-purple-300">
                            Menampilkan hasil pencarian untuk: "<?php echo htmlspecialchars($_GET['search']); ?>"
                            <a href="dashboard.php" class="ml-2 text-red-400 hover:text-red-300">Hapus pencarian</a>
                        </div>
                    <?php endif; ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="border-b border-gray-700 text-purple-300 text-sm">
                                    <th class="pb-3 px-4">ID Transaksi</th>
                                    <th class="pb-3 px-4">Tanggal</th>
                                    <th class="pb-3 px-4">Total</th>
                                    <th class="pb-3 px-4">Metode</th>
                                    <th class="pb-3 px-4">Kasir</th>
                                    <th class="pb-3 px-4"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php if (mysqli_num_rows($recentTransactionsResult) > 0): ?>
                                    <?php while ($transaction = mysqli_fetch_assoc($recentTransactionsResult)): ?>
                                        <tr class="table-row">
                                            <td class="py-4 px-4">#TRX-<?php echo $transaction['id']; ?></td>
                                            <td class="py-4 px-4"><?php echo date('d M Y, H:i', strtotime($transaction['date'])); ?></td>
                                            <td class="py-4 px-4 font-medium">Rp <?php echo number_format($transaction['total_price'], 0, ',', '.'); ?></td>
                                            <td class="py-4 px-4">
                                                <span class="px-2 py-1 <?php echo $transaction['payment_method'] == 'tunai' ? 'bg-green-900 bg-opacity-30 text-green-400' : 'bg-blue-900 bg-opacity-30 text-blue-400'; ?> rounded-full text-xs">
                                                    <?php echo ucfirst($transaction['payment_method']); ?>
                                                </span>
                                            </td>
                                            <td class="py-4 px-4"><?php echo $transaction['username']; ?></td>
                                            <td class="py-4 px-4 text-right">
                                                <button class="text-purple-400 hover:text-purple-300 transition">
                                                    <span class="material-icons">more_vert</span>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="py-4 px-4 text-center text-gray-400">Tidak ada transaksi ditemukan</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Google Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</body>

</html>