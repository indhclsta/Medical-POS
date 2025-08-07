<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("location:../service/login.php");
    exit();
}

// Hanya role kasir yang boleh akses
if ($_SESSION['role'] !== 'kasir') {
    header("location:../unauthorized.php");
    exit();
}

include '../service/connection.php';

$email = $_SESSION['email'];

// Ambil data admin
$query = mysqli_query($conn, "SELECT * FROM admin WHERE email = '$email'");
$admin = mysqli_fetch_assoc($query);

$username = $admin['username'];
$id_admin = $admin['id'];

// Statistik
$trans_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM transactions WHERE fid_admin = $id_admin"));
$product_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM products"));

// Riwayat transaksi terakhir kasir ini
$recent_trans = mysqli_query($conn, "SELECT id, date, total FROM transactions WHERE fid_admin = $id_admin ORDER BY date DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Kasir - MediPOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 font-sans">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-purple-700 text-white p-6">
            <div class="text-2xl font-bold mb-8">
                <span class="text-white">Medi</span><span class="text-purple-300">POS</span>
            </div>
            <div class="mb-6">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center">
                        <i class="fas fa-user text-white"></i>
                    </div>
                    <div>
                        <p class="font-semibold"><?= htmlspecialchars($username) ?></p>
                        <p class="text-sm text-purple-200">Kasir</p>
                    </div>
                </div>
            </div>
            <nav class="space-y-3">
                <a href="kasir_dashboard.php" class="block px-4 py-2 rounded bg-purple-800"><i class="fas fa-home mr-2"></i> Dashboard</a>
                <a href="transaksi.php" class="block px-4 py-2 rounded hover:bg-purple-600"><i class="fas fa-cash-register mr-2"></i> Transaksi Baru</a>
                <a href="produk.php" class="block px-4 py-2 rounded hover:bg-purple-600"><i class="fas fa-boxes mr-2"></i> Lihat Produk</a>
                <a href="../service/logout.php" class="block px-4 py-2 rounded hover:bg-purple-600 text-red-200 mt-4"><i class="fas fa-sign-out-alt mr-2"></i> Logout</a>
            </nav>
        </aside>

        <!-- Content -->
        <main class="flex-1 p-8 overflow-y-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Halo, <?= htmlspecialchars($username) ?>!</h1>
                <p class="text-gray-600">Selamat datang di dashboard kasir MediPOS.</p>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded shadow">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-500">Total Transaksi</p>
                            <p class="text-2xl font-bold text-purple-700"><?= $trans_count['total'] ?></p>
                        </div>
                        <i class="fas fa-receipt text-purple-700 text-3xl"></i>
                    </div>
                </div>
                <div class="bg-white p-6 rounded shadow">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-500">Total Produk</p>
                            <p class="text-2xl font-bold text-purple-700"><?= $product_count['total'] ?></p>
                        </div>
                        <i class="fas fa-box text-purple-700 text-3xl"></i>
                    </div>
                </div>
                <div class="bg-white p-6 rounded shadow">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm text-gray-500">Akses Terakhir</p>
                            <p class="text-lg text-gray-700"><?= date('d M Y H:i') ?></p>
                        </div>
                        <i class="fas fa-clock text-purple-700 text-3xl"></i>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-white p-6 rounded shadow">
                <h2 class="text-lg font-semibold text-purple-700 mb-4">Transaksi Terakhir</h2>
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b text-sm text-gray-600">
                            <th class="py-2">ID</th>
                            <th class="py-2">Tanggal</th>
                            <th class="py-2">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($recent_trans)) : ?>
                        <tr class="border-b text-sm hover:bg-purple-50">
                            <td class="py-2"><?= $row['id'] ?></td>
                            <td class="py-2"><?= date('d M Y H:i', strtotime($row['date'])) ?></td>
                            <td class="py-2">Rp <?= number_format($row['total'], 0, ',', '.') ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($recent_trans) == 0): ?>
                        <tr>
                            <td colspan="3" class="text-center text-gray-400 py-4">Belum ada transaksi</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>
