<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("location:../service/login.php");
    exit();
}

// Check for cashier role
if ($_SESSION['role'] !== 'cashier') {
    header("location:../unauthorized.php");
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

// Capture filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'keseluruhan';
$chart_type = isset($_GET['chart_type']) ? $_GET['chart_type'] : 'daily';

// Query for transaction data
// Filter hanya transaksi kasir yang sedang login
$query = "SELECT t.*, a.username as admin_name, m.name as member_name 
          FROM transactions t
          LEFT JOIN admin a ON t.fid_admin = a.id
          LEFT JOIN member m ON t.fid_member = m.id
          WHERE t.fid_admin = '" . $admin['id'] . "'";

// Add date filter if selected
if ($report_type == 'periode' && !empty($start_date) && !empty($end_date)) {
    $query .= " AND DATE(t.date) BETWEEN '$start_date' AND '$end_date'";
}

$query .= " ORDER BY t.date DESC";
$transactions = mysqli_query($conn, $query);

// Calculate total income
// Rekap hanya transaksi kasir yang sedang login
$total_query = "SELECT SUM(total_price) as total_income FROM transactions WHERE fid_admin = '" . $admin['id'] . "'";
if ($report_type == 'periode' && !empty($start_date)) {
    $total_query .= " AND DATE(date) BETWEEN '$start_date' AND '$end_date'";
}
$total_result = mysqli_query($conn, $total_query);
$total_income = mysqli_fetch_assoc($total_result)['total_income'];

// Calculate total margin
$margin_query = "SELECT SUM(margin_total) as total_margin FROM transactions WHERE fid_admin = '" . $admin['id'] . "'";
if ($report_type == 'periode' && !empty($start_date)) {
    $margin_query .= " AND DATE(date) BETWEEN '$start_date' AND '$end_date'";
}
$margin_result = mysqli_query($conn, $margin_query);
$total_margin = mysqli_fetch_assoc($margin_result)['total_margin'];

// Calculate transaction count
$count_query = "SELECT COUNT(*) as total_transactions FROM transactions WHERE fid_admin = '" . $admin['id'] . "'";
if ($report_type == 'periode' && !empty($start_date)) {
    $count_query .= " AND DATE(date) BETWEEN '$start_date' AND '$end_date'";
}
$count_result = mysqli_query($conn, $count_query);
$total_transactions = mysqli_fetch_assoc($count_result)['total_transactions'];

// Data for charts
$chart_labels = [];
$chart_data = [];
$chart_margin_data = [];

if ($chart_type == 'daily') {
    // Daily sales data
    $chart_query = "SELECT DATE(date) as day, SUM(total_price) as total, SUM(margin_total) as margin 
                   FROM transactions WHERE fid_admin = '" . $admin['id'] . "'";
    if ($report_type == 'periode' && !empty($start_date)) {
        $chart_query .= " AND DATE(date) BETWEEN '$start_date' AND '$end_date'";
    }
    $chart_query .= " GROUP BY DATE(date) ORDER BY DATE(date)";
} elseif ($chart_type == 'monthly') {
    // Monthly sales data
    $chart_query = "SELECT DATE_FORMAT(date, '%Y-%m') as month, SUM(total_price) as total, SUM(margin_total) as margin 
                   FROM transactions WHERE fid_admin = '" . $admin['id'] . "'";
    if ($report_type == 'periode' && !empty($start_date)) {
        $chart_query .= " AND DATE(date) BETWEEN '$start_date' AND '$end_date'";
    }
    $chart_query .= " GROUP BY DATE_FORMAT(date, '%Y-%m') ORDER BY DATE_FORMAT(date, '%Y-%m')";
} else {
    // Weekly sales data
    $chart_query = "SELECT YEARWEEK(date) as week, SUM(total_price) as total, SUM(margin_total) as margin 
                   FROM transactions WHERE fid_admin = '" . $admin['id'] . "'";
    if ($report_type == 'periode' && !empty($start_date)) {
        $chart_query .= " AND DATE(date) BETWEEN '$start_date' AND '$end_date'";
    }
    $chart_query .= " GROUP BY YEARWEEK(date) ORDER BY YEARWEEK(date)";
}
$userId = $_SESSION['id'];
$userQuery = "SELECT username, image FROM admin WHERE id = $userId";
$userResult = mysqli_query($conn, $userQuery);
$userData = mysqli_fetch_assoc($userResult);
$profilePicture = $userData['image'] ?? 'default.jpg';
$chart_result = mysqli_query($conn, $chart_query);
while ($row = mysqli_fetch_assoc($chart_result)) {
    if ($chart_type == 'daily') {
        $chart_labels[] = date('d M', strtotime($row['day']));
    } elseif ($chart_type == 'monthly') {
        $chart_labels[] = date('M Y', strtotime($row['month'] . '-01'));
    } else {
        $chart_labels[] = 'Week ' . substr($row['week'], 4) . ' ' . substr($row['week'], 0, 4);
    }
    $chart_data[] = $row['total'];
    $chart_margin_data[] = $row['margin'];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediPOS - Laporan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }

            .print-content,
            .print-content * {
                visibility: visible;
            }

            .print-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 20px;
                background-color: white;
                color: black;
            }

            .no-print {
                display: none !important;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }

            th,
            td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: left;
            }

            th {
                background-color: #f2f2f2 !important;
                -webkit-print-color-adjust: exact;
            }

            .print-header {
                text-align: center;
                margin-bottom: 20px;
            }

            .print-title {
                font-size: 1.5rem;
                font-weight: bold;
                margin-bottom: 10px;
            }

            .print-summary {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                margin-bottom: 20px;
            }

            .print-summary-item {
                border-left: 4px solid #6b46c1;
                padding: 10px;
            }

            .print-charts {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin-bottom: 20px;
            }

            .print-chart {
                height: 300px;
            }
        }
    </style>
</head>

<body class="text-gray-200">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="sidebar w-64 flex flex-col p-5 space-y-8 no-print">
            <!-- Logo -->
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-lg bg-purple-500 flex items-center justify-center">
                    <span class="material-icons text-white">local_pharmacy</span>
                </div>
                <h1 class="text-xl font-bold text-purple-300">MediPOS</h1>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 flex flex-col space-y-2">
                <a href="dashboard.php" class="nav-item flex items-center p-3 space-x-3">
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
                <a href="reports.php" class="nav-item active flex items-center p-3 space-x-3">
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
                <div class="flex justify-between items-center mb-8 no-print">
                    <div>
                        <h2 class="text-2xl font-bold text-white">Laporan & Grafik</h2>
                        <p class="text-purple-300">Analisis penjualan dan transaksi</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        
                    </div>
                </div>

                <!-- Filter Form -->
                <div class="bg-[#2A2540] rounded-xl shadow-lg p-6 mb-8 no-print">
                    <h2 class="text-lg font-semibold text-white mb-4">Filter Laporan</h2>
                    <form method="GET" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-purple-300 mb-2" for="report-type">
                                    Jenis Laporan
                                </label>
                                <select name="report_type" id="report-type" class="bg-[#1E1B2E] border border-[#3B3360] rounded-lg w-full py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    <option value="keseluruhan" <?= $report_type == 'keseluruhan' ? 'selected' : '' ?>>Laporan Keseluruhan</option>
                                    <option value="periode" <?= $report_type == 'periode' ? 'selected' : '' ?>>Laporan Berdasarkan Periode</option>
                                </select>
                            </div>

                            <div id="dateRangeFields" class="<?= $report_type != 'periode' ? 'hidden' : '' ?>">
                                <label class="block text-sm font-medium text-purple-300 mb-2">Rentang Tanggal</label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    <input type="date" name="start_date" value="<?= $start_date ?>" class="bg-[#1E1B2E] border border-[#3B3360] rounded-lg w-full py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    <input type="date" name="end_date" value="<?= $end_date ?>" class="bg-[#1E1B2E] border border-[#3B3360] rounded-lg w-full py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-purple-300 mb-2" for="chart-type">
                                    Tipe Grafik
                                </label>
                                <select name="chart_type" id="chart-type" class="bg-[#1E1B2E] border border-[#3B3360] rounded-lg w-full py-2 px-3 text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    <option value="daily" <?= $chart_type == 'daily' ? 'selected' : '' ?>>Harian</option>
                                    <option value="weekly" <?= $chart_type == 'weekly' ? 'selected' : '' ?>>Mingguan</option>
                                    <option value="monthly" <?= $chart_type == 'monthly' ? 'selected' : '' ?>>Bulanan</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <?php if ($report_type == 'periode' && !empty($start_date)): ?>
                                <a href="reports.php" class="bg-[#3B3360] hover:bg-[#4A406B] text-white font-bold py-2 px-4 rounded-lg transition">
                                    Reset
                                </a>
                            <?php endif; ?>
                            <button type="submit" class="bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-6 rounded-lg transition">
                                Terapkan Filter
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Print Content Area -->
                <div id="printContent" class="print-content hidden">
                    <div class="print-header">
                        <div class="print-title">Laporan Transaksi MediPOS</div>
                        <div class="print-subtitle">
                            <?php if ($report_type == 'periode' && !empty($start_date)): ?>
                                Periode: <?= date('d M Y', strtotime($start_date)) ?> - <?= date('d M Y', strtotime($end_date)) ?>
                            <?php else: ?>
                                Laporan Keseluruhan
                            <?php endif; ?>
                        </div>
                        <div class="print-date"><?= date('d F Y H:i') ?></div>
                    </div>

                    <div class="print-summary">
                        <div class="print-summary-item">
                            <h3>Total Transaksi</h3>
                            <p><?= $total_transactions ?></p>
                        </div>
                        <div class="print-summary-item">
                            <h3>Total Pendapatan</h3>
                            <p>Rp <?= number_format($total_income, 0, ',', '.') ?></p>
                        </div>
                        <div class="print-summary-item">
                            <h3>Total Margin</h3>
                            <p>Rp <?= number_format($total_margin, 0, ',', '.') ?></p>
                        </div>
                    </div>

                    <div class="print-charts">
                        <div class="print-chart">
                            <h3>Grafik Penjualan</h3>
                            <canvas id="printSalesChart"></canvas>
                        </div>
                        <div class="print-chart">
                            <h3>Grafik Margin</h3>
                            <canvas id="printMarginChart"></canvas>
                        </div>
                    </div>

                    <h3>Daftar Transaksi</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tanggal</th>
                                <th>Kasir</th>
                                <th>Member</th>
                                <th>Total</th>
                                <th>Metode</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php mysqli_data_seek($transactions, 0); ?>
                            <?php while ($transaction = mysqli_fetch_assoc($transactions)): ?>
                                <tr>
                                    <td><?= $transaction['id'] ?></td>
                                    <td><?= date('d M Y H:i', strtotime($transaction['date'])) ?></td>
                                    <td><?= $transaction['admin_name'] ?></td>
                                    <td><?= $transaction['member_name'] ?? '-' ?></td>
                                    <td>Rp <?= number_format($transaction['total_price'], 0, ',', '.') ?></td>
                                    <td><?= ucfirst($transaction['payment_method']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Statistik -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="stat-card p-6 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-purple-300 mb-1">Total Transaksi</h3>
                                <p class="text-2xl font-bold text-white"><?= $total_transactions ?></p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-blue-500 bg-opacity-20 flex items-center justify-center">
                                <span class="material-icons text-blue-400">receipt</span>
                            </div>
                        </div>
                        <p class="text-xs text-green-400 mt-2"><?= $report_type == 'periode' && !empty($start_date) ? 'Periode terpilih' : 'Keseluruhan' ?></p>
                    </div>

                    <div class="stat-card p-6 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-purple-300 mb-1">Total Pendapatan</h3>
                                <p class="text-2xl font-bold text-white">Rp <?= number_format($total_income, 0, ',', '.') ?></p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-purple-500 bg-opacity-20 flex items-center justify-center">
                                <span class="material-icons text-purple-400">attach_money</span>
                            </div>
                        </div>
                        <p class="text-xs text-green-400 mt-2"><?= $report_type == 'periode' && !empty($start_date) ? 'Periode terpilih' : 'Keseluruhan' ?></p>
                    </div>

                    <div class="stat-card p-6 rounded-xl">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-purple-300 mb-1">Total Margin</h3>
                                <p class="text-2xl font-bold text-white">Rp <?= number_format($total_margin, 0, ',', '.') ?></p>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-green-500 bg-opacity-20 flex items-center justify-center">
                                <span class="material-icons text-green-400">trending_up</span>
                            </div>
                        </div>
                        <a href="#" class="text-xs text-purple-400 hover:text-purple-300 mt-2 inline-block transition">Detail margin</a>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="bg-[#2A2540] p-6 rounded-xl shadow-lg mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-white">Visualisasi Data</h3>
                        <div class="flex space-x-2 no-print">
                            <button onclick="preparePrint()" class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded-lg text-sm flex items-center space-x-1 transition">
                                <span class="material-icons text-sm">print</span>
                                <span>Cetak</span>
                            </button>
                            <button onclick="downloadPDF()" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg text-sm flex items-center space-x-1 transition">
                                <span class="material-icons text-sm">picture_as_pdf</span>
                                <span>PDF</span>
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Sales Chart -->
                        <div>
                            <h4 class="font-medium text-purple-300 mb-3">Grafik Penjualan</h4>
                            <div class="chart-container">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>

                        <!-- Margin Chart -->
                        <div>
                            <h4 class="font-medium text-purple-300 mb-3">Grafik Margin</h4>
                            <div class="chart-container">
                                <canvas id="marginChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transaction Table -->
                <div class="bg-[#2A2540] rounded-xl shadow-lg overflow-hidden">
                   <div class="px-6 py-4 border-b border-[#3B3360] flex justify-between items-center no-print">
    <h3 class="font-semibold text-lg text-white">Daftar Transaksi</h3>
    <div class="flex space-x-2 items-center">
        <div class="relative mr-2">
            <span class="material-icons absolute left-3 top-1/2 transform -translate-y-1/2 text-purple-300">search</span>
            <input type="text" id="searchTransaksi" placeholder="Cari transaksi..." 
                   class="bg-[#2A2540] pl-10 pr-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 text-white" 
                   style="min-width:180px;">
        </div>
</div>
                            <button onclick="preparePrint()" class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-1 rounded-lg text-sm flex items-center space-x-1 transition">
                                <span class="material-icons text-sm">print</span>
                                <span>Cetak</span>
                            </button>
                            <button onclick="downloadPDF()" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg text-sm flex items-center space-x-1 transition">
                                <span class="material-icons text-sm">picture_as_pdf</span>
                                <span>PDF</span>
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-[#3B3360]">
                            <thead class="bg-[#2A2540]">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-purple-300 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-purple-300 uppercase tracking-wider">Tanggal</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-purple-300 uppercase tracking-wider">Kasir</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-purple-300 uppercase tracking-wider">Member</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-purple-300 uppercase tracking-wider">Total</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-purple-300 uppercase tracking-wider">Metode</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-purple-300 uppercase tracking-wider no-print">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-[#2A2540] divide-y divide-[#3B3360]">
                                <?php mysqli_data_seek($transactions, 0); ?>
                                <?php while ($transaction = mysqli_fetch_assoc($transactions)): ?>
                                    <tr class="hover:bg-[#3B3360] transition transaksi-row">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white transaksi-id">#TRX-<?= $transaction['id'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-200 transaksi-date"><?= date('d M Y, H:i', strtotime($transaction['date'])) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-200 transaksi-admin"><?= $transaction['admin_name'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-200 transaksi-member"><?= $transaction['member_name'] ?? '-' ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-white transaksi-total">Rp <?= number_format($transaction['total_price'], 0, ',', '.') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm transaksi-method">
                                            <span class="px-2 py-1 <?= $transaction['payment_method'] == 'tunai' ? 'bg-green-900 bg-opacity-30 text-green-400' : 'bg-blue-900 bg-opacity-30 text-blue-400'; ?> rounded-full text-xs">
                                                <?= ucfirst($transaction['payment_method']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-200 no-print">
                                            <button onclick="showDetail(<?= $transaction['id'] ?>)" class="text-purple-400 hover:text-purple-300 transition">
                                                <span class="material-icons">visibility</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal for Transaction Details -->
    <div id="detailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 no-print">
        <div class="bg-[#2A2540] rounded-xl shadow-lg w-full max-w-2xl p-6 relative border border-[#3B3360]">
            <button onclick="closeModal()" class="absolute top-4 right-4 text-gray-400 hover:text-white text-xl transition">&times;</button>
            <h3 class="text-xl font-bold mb-4 text-white">Detail Transaksi</h3>
            <div class="mb-4 grid grid-cols-2 gap-4">
                <div class="text-gray-300"><strong class="text-purple-300">ID:</strong> <span id="modalTransactionId"></span></div>
                <div class="text-gray-300"><strong class="text-purple-300">Tanggal:</strong> <span id="modalDate"></span></div>
                <div class="text-gray-300"><strong class="text-purple-300">Kasir:</strong> <span id="modalAdmin"></span></div>
                <div class="text-gray-300"><strong class="text-purple-300">Member:</strong> <span id="modalMember"></span></div>
                <div class="text-gray-300"><strong class="text-purple-300">Total:</strong> <span id="modalTotal"></span></div>
                <div class="text-gray-300"><strong class="text-purple-300">Margin:</strong> <span id="modalMargin"></span></div>
                <div class="text-gray-300"><strong class="text-purple-300">Metode:</strong> <span id="modalMethod"></span></div>
                <div class="text-gray-300"><strong class="text-purple-300">Dibayar:</strong> <span id="modalPaid"></span></div>
                <div class="text-gray-300"><strong class="text-purple-300">Kembalian:</strong> <span id="modalChange"></span></div>
            </div>
            <h4 class="font-semibold mb-2 text-purple-300">Produk</h4>
            <div class="overflow-x-auto">
                <table class="w-full border border-[#3B3360]">
                    <thead>
                        <tr class="bg-[#3B3360]">
                            <th class="px-4 py-2 text-left text-purple-300">Nama Produk</th>
                            <th class="px-4 py-2 text-left text-purple-300">Harga</th>
                            <th class="px-4 py-2 text-left text-purple-300">Qty</th>
                            <th class="px-4 py-2 text-left text-purple-300">Margin</th>
                            <th class="px-4 py-2 text-left text-purple-300">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="modalProducts" class="divide-y divide-[#3B3360]"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Google Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
      // Fitur search transaksi
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchTransaksi');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const keyword = this.value.toLowerCase();
            document.querySelectorAll('.transaksi-row').forEach(function(row) {
                const id = row.querySelector('.transaksi-id').textContent.toLowerCase();
                const date = row.querySelector('.transaksi-date').textContent.toLowerCase();
                const admin = row.querySelector('.transaksi-admin').textContent.toLowerCase();
                const member = row.querySelector('.transaksi-member').textContent.toLowerCase();
                const total = row.querySelector('.transaksi-total').textContent.toLowerCase();
                const method = row.querySelector('.transaksi-method').textContent.toLowerCase();
                if (
                    id.includes(keyword) ||
                    date.includes(keyword) ||
                    admin.includes(keyword) ||
                    member.includes(keyword) ||
                    total.includes(keyword) ||
                    method.includes(keyword))
                {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
        // Show/hide date range based on report type
        document.getElementById('report-type').addEventListener('change', function() {
            const dateRangeFields = document.getElementById('dateRangeFields');
            if (this.value === 'periode') {
                dateRangeFields.classList.remove('hidden');
            } else {
                dateRangeFields.classList.add('hidden');
            }
        });

        // Initialize charts
        const salesChartCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesChartCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Total Penjualan',
                    data: <?= json_encode($chart_data) ?>,
                    backgroundColor: 'rgba(155, 135, 245, 0.7)',
                    borderColor: 'rgba(155, 135, 245, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            },
                            color: '#9CA3AF'
                        },
                        grid: {
                            color: 'rgba(156, 163, 175, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#9CA3AF'
                        },
                        grid: {
                            color: 'rgba(156, 163, 175, 0.1)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#E5E7EB'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Rp ' + context.raw.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });

        const marginChartCtx = document.getElementById('marginChart').getContext('2d');
        const marginChart = new Chart(marginChartCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Total Margin',
                    data: <?= json_encode($chart_margin_data) ?>,
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    tension: 0.1,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            },
                            color: '#9CA3AF'
                        },
                        grid: {
                            color: 'rgba(156, 163, 175, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#9CA3AF'
                        },
                        grid: {
                            color: 'rgba(156, 163, 175, 0.1)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#E5E7EB'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Rp ' + context.raw.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });

        // Print functionality
        function preparePrint() {
            // Show print content
            document.getElementById('printContent').classList.remove('hidden');

            // Create print charts
            createPrintCharts();

            // Wait a moment for charts to render then print
            setTimeout(() => {
                window.print();
                document.getElementById('printContent').classList.add('hidden');
            }, 500);
        }

        function createPrintCharts() {
            // Sales Chart for Print
            const printSalesCtx = document.getElementById('printSalesChart').getContext('2d');
            new Chart(printSalesCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: [{
                        label: 'Total Penjualan',
                        data: <?= json_encode($chart_data) ?>,
                        backgroundColor: 'rgba(107, 70, 193, 0.7)',
                        borderColor: 'rgba(107, 70, 193, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            enabled: false
                        }
                    }
                }
            });
        }

        // Download PDF function
        function downloadPDF() {
            const params = new URLSearchParams(window.location.search);
            window.location.href = `../service/download_report.php?${params.toString()}`;
        }

        // Transaction detail modal functions
        function showDetail(transactionId) {
            console.log('Fetching details for transaction:', transactionId);

            const modalProducts = document.getElementById('modalProducts');
            modalProducts.innerHTML = '<tr><td colspan="5" class="px-4 py-2 text-center text-gray-500">Memuat data...</td></tr>';
            document.getElementById('detailModal').classList.remove('hidden');

            fetch(`../service/get_transaction_detail.php?id=${transactionId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Failed to load transaction details');
                    }

                    console.log('Transaction details:', data);

                    // Populate modal fields
                    document.getElementById('modalTransactionId').textContent = data.data.id;
                    document.getElementById('modalDate').textContent = new Date(data.data.date).toLocaleString('id-ID');
                    document.getElementById('modalAdmin').textContent = data.data.admin_name || '-';
                    document.getElementById('modalMember').textContent = data.data.member_name || '-';
                    document.getElementById('modalTotal').textContent = 'Rp ' + (data.data.total_price?.toLocaleString('id-ID') || '0');
                    document.getElementById('modalMargin').textContent = 'Rp ' + (data.data.margin_total?.toLocaleString('id-ID') || '0');
                    document.getElementById('modalMethod').textContent = data.data.payment_method ?
                        data.data.payment_method.charAt(0).toUpperCase() + data.data.payment_method.slice(1) : '-';
                    document.getElementById('modalPaid').textContent = 'Rp ' + (data.data.paid_amount?.toLocaleString('id-ID') || '0');
                    document.getElementById('modalChange').textContent = 'Rp ' + (data.data.kembalian?.toLocaleString('id-ID') || '0');

                    // Populate products
                    modalProducts.innerHTML = '';
                    if (data.data.details && data.data.details.length > 0) {
                        data.data.details.forEach(item => {
                            const row = document.createElement('tr');
                            row.className = 'hover:bg-gray-50';
                            row.innerHTML = `
                                <td class="px-4 py-2">${item.product_name || '-'}</td>
                                <td class="px-4 py-2">Rp ${item.price?.toLocaleString('id-ID') || '0'}</td>
                                <td class="px-4 py-2">${item.quantity || '0'}</td>
                                <td class="px-4 py-2">Rp ${item.margin?.toLocaleString('id-ID') || '0'}</td>
                                <td class="px-4 py-2">Rp ${item.subtotal?.toLocaleString('id-ID') || '0'}</td>
                            `;
                            modalProducts.appendChild(row);
                        });
                    } else {
                        const row = document.createElement('tr');
                        row.className = 'hover:bg-gray-50';
                        row.innerHTML = `
                            <td colspan="5" class="px-4 py-2 text-center text-gray-500">
                                Tidak ada detail produk yang tercatat dalam database
                            </td>
                        `;
                        modalProducts.appendChild(row);

                        console.warn('No product details found for transaction', data);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    modalProducts.innerHTML = `
                        <tr>
                            <td colspan="5" class="px-4 py-2 text-center text-red-500">
                                Gagal memuat detail: ${error.message}<br>
                                <small>Silakan cek console untuk detail lebih lanjut</small>
                            </td>
                        </tr>
                    `;
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