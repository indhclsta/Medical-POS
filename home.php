<?php 
session_start();
if (!isset($_SESSION['email'])) {
    header("location:./service/login.php");
    exit();
}

include './service/connection.php';
$email = $_SESSION['email'];

$query = mysqli_query($conn, "SELECT * FROM admin WHERE email = '$email'");
$admin = mysqli_fetch_assoc($query);

$username = $admin['username'];
$image = !empty($admin['image']) ? 'uploads/' . $admin['image'] : 'default.jpg';

// Query untuk statistik penjualan hari ini
$today = date('Y-m-d');
$sales_today_query = mysqli_query($conn, "SELECT SUM(total_price) as total FROM transactions WHERE DATE(date) = '$today'");
$sales_today = mysqli_fetch_assoc($sales_today_query);

// Query untuk total transaksi hari ini
$transactions_today_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM transactions WHERE DATE(date) = '$today'");
$transactions_today = mysqli_fetch_assoc($transactions_today_query);

// Query untuk produk terjual hari ini
$products_sold_query = mysqli_query($conn, "SELECT SUM(quantity) as total FROM transaction_details td 
                                          JOIN transactions t ON td.transaction_id = t.id 
                                          WHERE DATE(t.date) = '$today'");
$products_sold = mysqli_fetch_assoc($products_sold_query);

// Query untuk stok menipis (qty < 10)
$low_stock_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM products WHERE qty < 10");
$low_stock = mysqli_fetch_assoc($low_stock_query);

// Query untuk data penjualan bulanan
$monthly_sales = [];
for ($i = 1; $i <= 12; $i++) {
    $month = str_pad($i, 2, '0', STR_PAD_LEFT);
    $query = mysqli_query($conn, "SELECT SUM(total_price) as total FROM transactions WHERE MONTH(date) = '$i' AND YEAR(date) = YEAR(CURDATE())");
    $result = mysqli_fetch_assoc($query);
    $monthly_sales[] = $result['total'] ? (int)$result['total'] : 0;
}

// Query untuk cek apakah ada stok menipis
$low_stock_exists = ($low_stock['total'] ?? 0) > 0;

// Query untuk produk terpopuler
$popular_products_query = mysqli_query($conn, "SELECT p.product_name, SUM(td.quantity) as total_sold 
                                             FROM transaction_details td 
                                             JOIN products p ON td.product_id = p.id 
                                             GROUP BY p.product_name 
                                             ORDER BY total_sold DESC 
                                             LIMIT 4");
$popular_products = [];
while ($row = mysqli_fetch_assoc($popular_products_query)) {
    $popular_products[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartCash Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1F2937;
        }

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

        .main-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 20px 90px;
            width: 100%;
            box-sizing: border-box;
        }

        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            width: 100%;
            max-width: 1200px;
        }

        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: 1fr;
            }
        }

        .chart-container {
            position: relative;
            width: 100%;
            height: 400px;
            max-width: 100%;
        }

        .card {
            padding: 20px;
            border-radius: 12px;
            background-color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
        }
        
        .product-card {
            background-color: white;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            transition: all 0.3s ease;
            border-left: 4px solid #61892F;
        }
        
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card {
            background: linear-gradient(135deg, #ffffff 0%, #F1F9E4 100%);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            border-top: 3px solid #61892F;
        }
        
        .notification {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); }
            100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
        }
    </style>
</head>
<body class="bg-[#F1F9E4]">
    <div class="container mx-auto p-4">
        <div class="flex items-center mb-8 relative border-b pb-4 border-[#D1D5DB]">
            <i id="menuToggle" class="fas fa-bars text-2xl mr-4 cursor-pointer text-[#61892F] hover:text-[#4a6e24]"></i>
            <h1 class="text-3xl font-bold text-[#1F2937]">Smart <span class="text-[#61892F]">Cash</span></h1>
            <div class="ml-auto flex items-center space-x-4">
                <div class="text-[#6B7280] text-sm hidden md:block" id="clock"></div>
                <a href="profil.php" class="flex items-center hover:opacity-80 transition">
                    <img src="<?= htmlspecialchars($image) ?>" alt="Foto Profil" class="w-10 h-10 rounded-full object-cover inline-block border-2 border-[#61892F]">
                    <span class="text-[#1F2937] font-medium ml-2"><?= htmlspecialchars($username) ?></span>
                </a>
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


    <div class="main-content">
    <div class="container mx-auto px-4">
        <!-- Warning Alert - Hanya muncul jika ada stok menipis -->
        <?php if ($low_stock_exists): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-lg flex items-start">
            <i class="fas fa-exclamation-circle mr-2 mt-1"></i>
            <div>
                <p class="font-bold">Pengingat!</p>
                <p>Ada <?= $low_stock['total'] ?> produk yang stoknya hampir habis, periksa daftar produk sekarang.</p>
                <a href="produk.php?filter=low_stock" class="text-[#61892F] font-semibold inline-block mt-2">Lihat Daftar Produk</a>
            </div>
        </div>
        <?php endif; ?>

            <!-- Welcome Card -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-6 border-l-4 border-[#61892F]">
                <h2 class="text-2xl font-bold mb-2 text-[#1F2937]">Selamat Datang di Smart Cashier, <?= htmlspecialchars($username) ?>!</h2>
                <p class="text-[#6B7280]">Kelola penjualan, stok produk, dan transaksi dengan mudah dan efisien menggunakan aplikasi Smart Cashier. Semua dalam satu sistem yang cepat dan responsif.</p>
                <div class="mt-3 flex items-center text-sm text-[#61892F]">
                    <i class="fas fa-calendar-day mr-2"></i>
                    <span id="currentDate"></span>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-[#6B7280] text-sm">Penjualan Hari Ini</h3>
                            <p class="text-2xl font-bold text-[#61892F]">Rp <?= number_format($sales_today['total'] ?? 0, 0, ',', '.') ?></p>
                        </div>
                        <div class="bg-[#E8F5D6] p-3 rounded-full">
                            <i class="fas fa-wallet text-[#61892F]"></i>
                        </div>
                    </div>
                    
                </div>
                
                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-[#6B7280] text-sm">Total Transaksi Hari Ini</h3>
                            <p class="text-2xl font-bold text-[#61892F]"><?= $transactions_today['total'] ?? 0 ?></p>
                        </div>
                        <div class="bg-[#E8F5D6] p-3 rounded-full">
                            <i class="fas fa-receipt text-[#61892F]"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-[#6B7280] text-sm">Produk Terjual Hari Ini</h3>
                            <p class="text-2xl font-bold text-[#61892F]"><?= $products_sold['total'] ?? 0 ?></p>
                        </div>
                        <div class="bg-[#E8F5D6] p-3 rounded-full">
                            <i class="fas fa-shopping-basket text-[#61892F]"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card <?= $low_stock_exists ? 'notification' : '' ?>">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-[#6B7280] text-sm">Stok Menipis</h3>
                            <p class="text-2xl font-bold <?= ($low_stock['total'] > 0) ? 'text-red-500' : 'text-[#61892F]' ?>"><?= $low_stock['total'] ?? 0 ?> Produk</p>
                        </div>
                        <div class="<?= ($low_stock['total'] > 0) ? 'bg-red-100' : 'bg-[#E8F5D6]' ?> p-3 rounded-full">
                            <i class="fas <?= ($low_stock['total'] > 0) ? 'fa-exclamation text-red-500' : 'fa-check text-[#61892F]' ?>"></i>
                        </div>
                    </div>
                    <div class="mt-2 text-xs <?= ($low_stock['total'] > 0) ? 'text-red-500' : 'text-green-600' ?> flex items-center">
                        <i class="fas <?= ($low_stock['total'] > 0) ? 'fa-arrow-down' : 'fa-arrow-up' ?> mr-1"></i>
                        <span><?= ($low_stock['total'] > 0) ? 'Perlu restock segera' : 'Stok aman' ?></span>
                    </div>
                </div>
            </div>

            <!-- Charts Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="card">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-[#1F2937]">Statistik Penjualan</h2>
                        <select id="salesFilter" class="border border-gray-300 rounded-md p-2 text-[#1F2937] text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#61892F]">
                            <option value="daily">Harian</option>
                            <option value="weekly">Mingguan</option>
                            <option value="monthly" selected>Bulanan</option>
                            <option value="yearly">Tahunan</option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                    <div class="mt-4 flex items-center text-sm text-[#6B7280]">
                        <i class="fas fa-info-circle mr-2 text-[#61892F]"></i>
                        <span>Klik pada grafik untuk melihat detail</span>
                    </div>
                </div>

                <div class="card">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold text-[#1F2937]">Produk Terpopuler</h2>
                        <div class="flex space-x-2">
                            <button id="pieChartBtn" ></button>
                            <button id="doughnutChartBtn"></button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="popularProductsChart"></canvas>
                    </div>
                    <div class="mt-4 grid grid-cols-2 md:grid-cols-4 gap-2 text-center text-xs">
                        <?php foreach ($popular_products as $index => $product): ?>
                            <div class="flex items-center justify-center">
                                <span class="w-3 h-3 <?= $index == 0 ? 'bg-[#61892F]' : ($index == 1 ? 'bg-[#A4C639]' : ($index == 2 ? 'bg-[#F4F4F4]' : 'bg-[#6B7280]')) ?> rounded-full mr-1"></span>
                                <span><?= htmlspecialchars($product['product_name']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Update clock and date
    function updateClock() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        const dateString = now.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        
        document.getElementById('clock').textContent = timeString;
        document.getElementById('currentDate').textContent = dateString;
    }
    
    setInterval(updateClock, 1000);
    updateClock();
    
    // Enhanced Sales Chart with real data
    const monthlySalesData = <?= json_encode($monthly_sales) ?>;
    const popularProductsData = {
        labels: <?= json_encode(array_column($popular_products, 'product_name')) ?>,
        data: <?= json_encode(array_column($popular_products, 'total_sold')) ?>
    };
    
    // Calculate daily sales data from transactions table
    const dailySalesData = {
        labels: ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'],
        data: <?= json_encode(getWeeklySalesData($conn)) ?> // You need to implement this function
    };
    
    // Calculate weekly sales data from transactions table
    const weeklySalesData = {
        labels: ['Minggu 1', 'Minggu 2', 'Minggu 3', 'Minggu 4'],
        data: <?= json_encode(getMonthlyWeekSalesData($conn)) ?> // You need to implement this function
    };
    
    // Calculate yearly sales data from transactions table
    const yearlySalesData = {
        labels: ['2019', '2020', '2021', '2022', '2023', '2024'],
        data: <?= json_encode(getYearlySalesData($conn)) ?> // You need to implement this function
    };

    const salesData = {
        daily: dailySalesData,
        weekly: weeklySalesData,
        monthly: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'],
            data: monthlySalesData
        },
        yearly: yearlySalesData
    };

    const salesCtx = document.getElementById('salesChart').getContext('2d');
    let salesChart = new Chart(salesCtx, {
        type: 'bar',
        data: {
            labels: salesData.monthly.labels,
            datasets: [{
                label: 'Total Penjualan',
                data: salesData.monthly.data,
                backgroundColor: '#61892F',
                borderColor: '#1F2937',
                borderWidth: 1,
                borderRadius: 6,
                hoverBackgroundColor: '#4a6e24'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Rp ' + context.raw.toLocaleString('id-ID');
                        }
                    }
                },
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + (value / 1000000).toLocaleString('id-ID') + ' jt';
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            onClick: function(evt, elements) {
                if (elements.length > 0) {
                    const index = elements[0].index;
                    const label = this.data.labels[index];
                    const value = this.data.datasets[0].data[index];
                    
                    Swal.fire({
                        title: `Detail Penjualan ${label}`,
                        html: `Total penjualan: <b>Rp ${value.toLocaleString('id-ID')}</b>`,
                        icon: 'info',
                        confirmButtonColor: '#61892F'
                    });
                }
            }
        }
    });

    document.getElementById('salesFilter').addEventListener('change', function() {
        let selectedOption = this.value;
        salesChart.data.labels = salesData[selectedOption].labels;
        salesChart.data.datasets[0].data = salesData[selectedOption].data;
        salesChart.update();
    });

    // Enhanced Products Chart with real data
    const productsCtx = document.getElementById('popularProductsChart').getContext('2d');
    let popularProductsChart = new Chart(productsCtx, {
        type: 'doughnut',
        data: {
            labels: popularProductsData.labels,
            datasets: [{
                data: popularProductsData.data,
                backgroundColor: ['#61892F', '#A4C639', '#F1F9E4', '#6B7280'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.raw / total) * 100);
                            return `${context.label}: ${context.raw} item (${percentage}%)`;
                        }
                    }
                },
                datalabels: {
                    display: false
                }
            },
            cutout: '65%',
            onClick: function(evt, elements) {
                if (elements.length > 0) {
                    const index = elements[0].index;
                    const label = this.data.labels[index];
                    const value = this.data.datasets[0].data[index];
                    const total = this.data.datasets[0].data.reduce((a, b) => a + b, 0);
                    const percentage = Math.round((value / total) * 100);
                    
                    Swal.fire({
                        title: `Detail Produk ${label}`,
                        html: `Terjual <b>${value} item</b> (${percentage}% dari total penjualan)`,
                        icon: 'info',
                        confirmButtonColor: '#61892F'
                    });
                }
            }
        },
        plugins: [ChartDataLabels]
    });

    // Chart type toggle buttons
    document.getElementById('pieChartBtn').addEventListener('click', function() {
        popularProductsChart.config.type = 'pie';
        popularProductsChart.update();
        this.classList.add('bg-[#61892F]', 'text-white');
        this.classList.remove('bg-gray-200', 'text-[#1F2937]');
        document.getElementById('doughnutChartBtn').classList.add('bg-gray-200', 'text-[#1F2937]');
        document.getElementById('doughnutChartBtn').classList.remove('bg-[#61892F]', 'text-white');
    });

    document.getElementById('doughnutChartBtn').addEventListener('click', function() {
        popularProductsChart.config.type = 'doughnut';
        popularProductsChart.update();
        this.classList.add('bg-[#61892F]', 'text-white');
        this.classList.remove('bg-gray-200', 'text-[#1F2937]');
        document.getElementById('pieChartBtn').classList.add('bg-gray-200', 'text-[#1F2937]');
        document.getElementById('pieChartBtn').classList.remove('bg-[#61892F]', 'text-white');
    });

    // Sidebar toggle
    document.getElementById('menuToggle').addEventListener('click', function() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('sidebar-hidden');
    });

    // Logout confirmation
    document.getElementById("logoutBtn").addEventListener("click", function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'Anda yakin ingin logout?',
            text: "Anda akan diarahkan ke halaman login.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#61892F',
            confirmButtonText: 'Ya, logout!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Logging out...',
                    text: 'Harap tunggu...',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false,
                    willClose: () => {
                        window.location.href = "service/logout.php";
                    }
                });
            }
        });
    });

    // Low stock notification click event
    document.querySelector('.stat-card.notification').addEventListener('click', function() {
        Swal.fire({
            title: 'Produk dengan Stok Menipis',
            html: `Ada <b><?= $low_stock['total'] ?? 0 ?> produk</b> yang stoknya hampir habis. <br><br>
                  <a href="produk.php?filter=low_stock" class="text-[#61892F] font-semibold">Lihat daftar produk</a>`,
            icon: 'warning',
            confirmButtonColor: '#61892F'
        });
    });

    // You'll need to add these PHP functions to your code:
</script>
</body>
</html>

<?php
// Add these functions to your PHP code
function getWeeklySalesData($conn) {
    $data = array_fill(0, 7, 0); // Initialize array for 7 days
    
    // Get sales data for the current week
    $query = mysqli_query($conn, "SELECT DAYOFWEEK(date) as day, SUM(total_price) as total 
                                FROM transactions 
                                WHERE YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)
                                GROUP BY DAYOFWEEK(date)");
    
    while ($row = mysqli_fetch_assoc($query)) {
        // Adjust index (MySQL returns 1=Sunday, we want 0=Monday)
        $index = ($row['day'] + 5) % 7;
        $data[$index] = (int)$row['total'];
    }
    
    return $data;
}

function getMonthlyWeekSalesData($conn) {
    $data = array_fill(0, 4, 0); // Initialize array for 4 weeks
    
    // Get sales data for the current month by week
    $query = mysqli_query($conn, "SELECT WEEK(date, 1) - WEEK(DATE_SUB(date, INTERVAL DAYOFMONTH(date)-1 DAY), 1) + 1 as week, 
                                SUM(total_price) as total 
                                FROM transactions 
                                WHERE YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())
                                GROUP BY week");
    
    while ($row = mysqli_fetch_assoc($query)) {
        $index = $row['week'] - 1;
        if ($index >= 0 && $index < 4) {
            $data[$index] = (int)$row['total'];
        }
    }
    
    return $data;
}

function getYearlySalesData($conn) {
    $years = ['2019', '2020', '2021', '2022', '2023', '2024'];
    $data = array_fill(0, count($years), 0);
    
    // Get sales data for each year
    $query = mysqli_query($conn, "SELECT YEAR(date) as year, SUM(total_price) as total 
                                FROM transactions 
                                WHERE YEAR(date) BETWEEN 2019 AND 2024
                                GROUP BY YEAR(date)");
    
    while ($row = mysqli_fetch_assoc($query)) {
        $index = array_search($row['year'], $years);
        if ($index !== false) {
            $data[$index] = (int)$row['total'];
        }
    }
    
    return $data;
}
?>