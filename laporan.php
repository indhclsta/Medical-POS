<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Transaksi - SmartCash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <style>
        .sidebar {
            transition: transform 0.3s ease;
            border-radius: 0 20px 20px 0;
            width: 250px;
            background-color: #779341;
            position: fixed;
            top: 50px;
            left: 0;
            height: calc(100% - 50px);
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }
        .sidebar-hidden {
            transform: translateX(-100%);
        }
        .sidebar a {
            display: block;
            padding: 10px;
            text-decoration: none;
            color: black;
            transition: background 0.3s;
        }
        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        .sidebar a:focus {
            background-color: transparent;
            outline: none;
        }
        a, button {
            outline: none;
        }
        .content {
            transition: margin-left 0.3s ease;
            margin-left: 0;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .sidebar-open .content {
            margin-left: 250px;
        }
        .hidden {
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: opacity 0.3s ease, transform 0.3s ease, visibility 0.3s ease;
        }
        .show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        table {
            border-radius: 10px;
            overflow: hidden;
            width: 100%;
        }
        th, td {
            text-align: center;
            padding: 12px 16px;
        }
        .search-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 1rem;
        }
        .search-container input {
            width: 250px;
        }
        .search-container button {
            margin-left: 1rem;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            width: 300px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .modal input {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .modal button {
            padding: 10px 20px;
            background-color: #779341;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body class="bg-[#F1F9E4] font-sans">

    <!-- Header -->
    <div class="container mx-auto p-4">
        <div class="flex items-center mb-8 relative">
            <i id="menuToggle" class="fas fa-bars text-2xl mr-4 cursor-pointer"></i>
            <h1 class="text-3xl font-bold text-black">Smart <span class="text-[#779341]">Cash</span></h1>
            <div class="ml-auto flex items-center space-x-6">
                <!-- Cart Icon with Item Count -->
                <div class="relative">
                    <i id="cartIcon" class="fas fa-shopping-cart text-2xl cursor-pointer"></i>
                    <span id="cartCount" class="absolute top-0 right-0 bg-red-600 text-white text-xs rounded-full px-2 py-1">0</span>
                </div>
                <i id="profileIcon" class="fas fa-user-circle text-4xl cursor-pointer"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Laporan Section -->
    <div class="content">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold">Laporan Transaksi</h2>
            <div class="search-container">
                
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full bg-white rounded-lg shadow-lg">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th class="py-2 px-4">Tanggal Transaksi</th>
                        <th class="py-2 px-4">Produk</th>
                        <th class="py-2 px-4">Harga Jual</th>
                        <th class="py-2 px-4">Harga Keuntungan</th>
                        <th class="py-2 px-4">Modal</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Example Data -->
                    <tr>
                        <td class="py-2 px-4 text-center">2025-03-16</td>
                        <td class="py-2 px-4">Produk A</td>
                        <td class="py-2 px-4">Rp 200.000</td>
                        <td class="py-2 px-4">Rp 50.000</td>
                        <td class="py-2 px-4">Rp 150.000</td>
                    </tr>
                    <tr>
                        <td class="py-2 px-4 text-center">2025-03-17</td>
                        <td class="py-2 px-4">Produk B</td>
                        <td class="py-2 px-4">Rp 300.000</td>
                        <td class="py-2 px-4">Rp 100.000</td>
                        <td class="py-2 px-4">Rp 200.000</td>
                    </tr>
                    <!-- Add more rows here -->
                </tbody>
            </table>
        </div>

        <!-- Download Button -->
        <div class="mt-4 text-center">
            <button id="downloadBtn" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 transition">
                <i class="fas fa-download"></i> Unduh Laporan
            </button>
        </div>
    </div>

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar sidebar-hidden">
        <ul class="space-y-4">
            <li><a href="home.php" class="block text-black font-semibold">Beranda</a></li>
            <li><a href="kategori.php" class="block text-black font-semibold">Kategori</a></li>
            <li><a href="produk.php" class="block text-black font-semibold">Produk</a></li>
            <li><a href="member.php" class="block text-black font-semibold">Member</a></li>
            <li><a href="admin.php" class="block text-black font-semibold">Admin</a></li>
            <li><a href="keranjang.php" class="block text-black font-semibold p-2">Keranjang</a></li>
            <li><a href="#" class="block text-black font-semibold p-2">Laporan</a></li>
            <li><a href="#" class="block text-red-600 font-semibold p-2">Logout</a></li>
        </ul>
    </div>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('sidebar-hidden');
            sidebar.classList.toggle('sidebar-open');
        });

        // Download Laporan (For now, this just alerts)
        document.getElementById('downloadBtn').addEventListener('click', function() {
            alert("Laporan berhasil diunduh!");
            // You can implement the actual download functionality here
        });
    </script>

</body>
</html>
