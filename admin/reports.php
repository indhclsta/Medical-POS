<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("location:../service/login.php");
    exit();
}

// Check for super admin role
if ($_SESSION['role'] !== 'super_admin') {
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
$image = !empty($admin['image']) ? '../uploads/' . $admin['image'] : 'default.jpg';

// Capture filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'keseluruhan';
$chart_type = isset($_GET['chart_type']) ? $_GET['chart_type'] : 'daily';

// Query for transaction data
$query = "SELECT t.*, a.username as admin_name, m.name as member_name 
          FROM transactions t
          LEFT JOIN admin a ON t.fid_admin = a.id
          LEFT JOIN member m ON t.fid_member = m.id";

// Add date filter if selected
if ($report_type == 'periode' && !empty($start_date) && !empty($end_date)) {
    $query .= " WHERE DATE(t.date) BETWEEN '$start_date' AND '$end_date'";
}

$query .= " ORDER BY t.date DESC";
$transactions = mysqli_query($conn, $query);

// Calculate total income
$total_query = "SELECT SUM(total_price) as total_income FROM transactions";
if ($report_type == 'periode' && !empty($start_date)) {
    $total_query .= " WHERE DATE(date) BETWEEN '$start_date' AND '$end_date'";
}
$total_result = mysqli_query($conn, $total_query);
$total_income = mysqli_fetch_assoc($total_result)['total_income'];

// Calculate total margin
$margin_query = "SELECT SUM(margin_total) as total_margin FROM transactions";
if ($report_type == 'periode' && !empty($start_date)) {
    $margin_query .= " WHERE DATE(date) BETWEEN '$start_date' AND '$end_date'";
}
$margin_result = mysqli_query($conn, $margin_query);
$total_margin = mysqli_fetch_assoc($margin_result)['total_margin'];

// Calculate transaction count
$count_query = "SELECT COUNT(*) as total_transactions FROM transactions";
if ($report_type == 'periode' && !empty($start_date)) {
    $count_query .= " WHERE DATE(date) BETWEEN '$start_date' AND '$end_date'";
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
                   FROM transactions";
    if ($report_type == 'periode' && !empty($start_date)) {
        $chart_query .= " WHERE DATE(date) BETWEEN '$start_date' AND '$end_date'";
    }
    $chart_query .= " GROUP BY DATE(date) ORDER BY DATE(date)";
} elseif ($chart_type == 'monthly') {
    // Monthly sales data
    $chart_query = "SELECT DATE_FORMAT(date, '%Y-%m') as month, SUM(total_price) as total, SUM(margin_total) as margin 
                   FROM transactions";
    if ($report_type == 'periode' && !empty($start_date)) {
        $chart_query .= " WHERE DATE(date) BETWEEN '$start_date' AND '$end_date'";
    }
    $chart_query .= " GROUP BY DATE_FORMAT(date, '%Y-%m') ORDER BY DATE_FORMAT(date, '%Y-%m')";
} else {
    // Weekly sales data
    $chart_query = "SELECT YEARWEEK(date) as week, SUM(total_price) as total, SUM(margin_total) as margin 
                   FROM transactions";
    if ($report_type == 'periode' && !empty($start_date)) {
        $chart_query .= " WHERE DATE(date) BETWEEN '$start_date' AND '$end_date'";
    }
    $chart_query .= " GROUP BY YEARWEEK(date) ORDER BY YEARWEEK(date)";
}

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
    <title>Laporan - MediPOS</title>
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

        #detailModal {
            transition: opacity 0.3s ease;
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

<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="sidebar w-64 px-4 py-8 shadow-lg fixed h-full no-print">
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
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg">
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
                <a href="reports.php" class="flex items-center px-4 py-3 rounded-lg nav-active">
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
            <header class="bg-white shadow-sm no-print">
                <div class="flex justify-between items-center px-6 py-4">
                    <h2 class="text-xl font-semibold text-gray-800">Laporan & Grafik</h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500" id="currentDateTime"></span>
                        <div class="relative">
                            <img src="<?= htmlspecialchars($image) ?>"
                                alt="Profile"
                                class="w-8 h-8 rounded-full border-2 border-purple-500">
                            <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full"></span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <!-- Filter Form -->
                <div class="bg-white rounded-xl shadow-md p-6 mb-6 border-l-4 border-purple-600 no-print">
                    <h2 class="text-xl font-bold mb-4 text-gray-800">Filter Laporan</h2>
                    <form method="GET" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="report-type">
                                    Jenis Laporan
                                </label>
                                <select name="report_type" id="report-type" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    <option value="keseluruhan" <?= $report_type == 'keseluruhan' ? 'selected' : '' ?>>Laporan Keseluruhan</option>
                                    <option value="periode" <?= $report_type == 'periode' ? 'selected' : '' ?>>Laporan Berdasarkan Periode</option>
                                </select>
                            </div>

                            <div id="dateRangeFields" class="<?= $report_type != 'periode' ? 'hidden' : '' ?>">
                                <label class="block text-gray-700 text-sm font-bold mb-2">Rentang Tanggal</label>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    <input type="date" name="start_date" value="<?= $start_date ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    <input type="date" name="end_date" value="<?= $end_date ?>" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                </div>
                            </div>

                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2" for="chart-type">
                                    Tipe Grafik
                                </label>
                                <select name="chart_type" id="chart-type" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                                    <option value="daily" <?= $chart_type == 'daily' ? 'selected' : '' ?>>Harian</option>
                                    <option value="weekly" <?= $chart_type == 'weekly' ? 'selected' : '' ?>>Mingguan</option>
                                    <option value="monthly" <?= $chart_type == 'monthly' ? 'selected' : '' ?>>Bulanan</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3">
                            <?php if ($report_type == 'periode' && !empty($start_date)): ?>
                                <a href="reports.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                    Reset
                                </a>
                            <?php endif; ?>
                            <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-6 rounded focus:outline-none focus:shadow-outline">
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
                                <th>Admin</th>
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

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="stat-card bg-white rounded-lg p-6 shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm">Total Transaksi</h3>
                                <p class="text-2xl font-bold text-purple-600"><?= $total_transactions ?></p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-receipt text-purple-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card bg-white rounded-lg p-6 shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm">Total Pendapatan</h3>
                                <p class="text-2xl font-bold text-purple-600">Rp <?= number_format($total_income, 0, ',', '.') ?></p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-money-bill-wave text-purple-600"></i>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card bg-white rounded-lg p-6 shadow">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-gray-500 text-sm">Total Margin</h3>
                                <p class="text-2xl font-bold text-purple-600">Rp <?= number_format($total_margin, 0, ',', '.') ?></p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-chart-line text-purple-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
                    <div class="px-6 py-4 border-b border-gray-200 bg-purple-50">
                        <h3 class="font-semibold text-lg text-purple-800">Visualisasi Data</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Sales Chart -->
                            <div>
                                <h4 class="font-medium text-gray-700 mb-3">Grafik Penjualan</h4>
                                <div class="chart-container">
                                    <canvas id="salesChart"></canvas>
                                </div>
                            </div>

                            <!-- Margin Chart -->
                            <div>
                                <h4 class="font-medium text-gray-700 mb-3">Grafik Margin</h4>
                                <div class="chart-container">
                                    <canvas id="marginChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transaction Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-purple-50 flex justify-between items-center no-print">
                        <h3 class="font-semibold text-lg text-purple-800">Daftar Transaksi</h3>
                        <div class="flex space-x-3">
                            <button onclick="preparePrint()" class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-1 px-3 rounded text-sm flex items-center">
                                <i class="fas fa-print mr-2"></i> Cetak Laporan
                            </button>
                            <button onclick="downloadPDF()" class="bg-red-600 hover:bg-red-700 text-white font-medium py-1 px-3 rounded text-sm flex items-center">
                                <i class="fas fa-file-pdf mr-2"></i> Export PDF
                            </button>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Metode</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider no-print">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php mysqli_data_seek($transactions, 0); ?>
                                <?php while ($transaction = mysqli_fetch_assoc($transactions)): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= $transaction['id'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d M Y H:i', strtotime($transaction['date'])) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $transaction['admin_name'] ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $transaction['member_name'] ?? '-' ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Rp <?= number_format($transaction['total_price'], 0, ',', '.') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= ucfirst($transaction['payment_method']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 no-print">
                                            <button onclick="showDetail(<?= $transaction['id'] ?>)" class="text-purple-600 hover:text-purple-900">
                                                <i class="fas fa-eye mr-1"></i> Detail
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal for Transaction Details -->
    <div id="detailModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50 no-print">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 relative">
            <button onclick="closeModal()" class="absolute top-2 right-2 text-gray-500 hover:text-gray-700 text-xl">&times;</button>
            <h3 class="text-xl font-bold mb-4">Detail Transaksi</h3>
            <div class="mb-4 grid grid-cols-2 gap-4">
                <div><strong>ID:</strong> <span id="modalTransactionId"></span></div>
                <div><strong>Tanggal:</strong> <span id="modalDate"></span></div>
                <div><strong>Admin:</strong> <span id="modalAdmin"></span></div>
                <div><strong>Member:</strong> <span id="modalMember"></span></div>
                <div><strong>Total:</strong> <span id="modalTotal"></span></div>
                <div><strong>Margin:</strong> <span id="modalMargin"></span></div>
                <div><strong>Metode:</strong> <span id="modalMethod"></span></div>
                <div><strong>Dibayar:</strong> <span id="modalPaid"></span></div>
                <div><strong>Kembalian:</strong> <span id="modalChange"></span></div>
            </div>
            <h4 class="font-semibold mb-2">Produk</h4>
            <table class="w-full border">
                <thead>
                    <tr>
                        <th class="px-4 py-2">Nama Produk</th>
                        <th class="px-4 py-2">Harga</th>
                        <th class="px-4 py-2">Qty</th>
                        <th class="px-4 py-2">Margin</th>
                        <th class="px-4 py-2">Subtotal</th>
                    </tr>
                </thead>
                <tbody id="modalProducts"></tbody>
            </table>
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
                            }
                        }
                    }
                },
                plugins: {
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

            // Margin Chart for Print
            const printMarginCtx = document.getElementById('printMarginChart').getContext('2d');
            new Chart(printMarginCtx, {
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