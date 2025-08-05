<?php
session_start();
require './service/connection.php';
$email = $_SESSION['email'];

// Tambah Kategori
if (isset($_POST['tambah_kategori'])) {
    $nama = $_POST['namaKategori'];
    
    // Cek apakah kategori sudah ada
    $check_query = "SELECT * FROM category WHERE category = '$nama'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        echo "<script>alert('Kategori sudah ada!'); window.location.href='kategori.php';</script>";
    } else {
        $query = "INSERT INTO category (category) VALUES ('$nama')";
        if (mysqli_query($conn, $query)) {
            echo "<script>alert('Kategori berhasil ditambahkan!'); window.location.href='kategori.php';</script>";
        } else {
            echo "<script>alert('Gagal menambah kategori!');</script>";
        }
    }
}

$query = mysqli_query($conn, "SELECT * FROM admin WHERE email = '$email'");
$admin = mysqli_fetch_assoc($query);

// Set data untuk tampilan
$username = $admin['username'];
$image = !empty($admin['image']) ? 'uploads/' . $admin['image'] : 'default.jpg';

// Hapus Kategori
if (isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    
    // Cek apakah ada produk yang menggunakan kategori ini
    $check_product_query = "SELECT COUNT(*) as total FROM products WHERE fid_category = $id";
    $check_product_result = mysqli_query($conn, $check_product_query);
    
    if (!$check_product_result) {
        die("Query error: " . mysqli_error($conn));
    }
    
    $product_count = mysqli_fetch_assoc($check_product_result)['total'];
    
    if ($product_count > 0) {
        echo "<script>
                alert('Kategori tidak bisa dihapus karena masih ada produk yang terkait!');
                window.location.href='kategori.php';
              </script>";
    } else {
        $query = "DELETE FROM category WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            echo "<script>
                    alert('Kategori berhasil dihapus!');
                    window.location.href='kategori.php';
                  </script>";
        } else {
            echo "<script>
                    alert('Gagal menghapus kategori! Error: ".mysqli_error($conn)."');
                  </script>";
        }
    }
}

// Update Kategori
if (isset($_POST['update_kategori'])) {
    $id = $_POST['kategori_id'];
    $nama = $_POST['namaKategoriEdit'];
    
    // Cek apakah kategori sudah ada (kecuali kategori yang sedang diupdate)
    $check_query = "SELECT * FROM category WHERE category = '$nama' AND id != $id";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        echo "<script>alert('Kategori sudah ada!'); window.location.href='kategori.php';</script>";
    } else {
        $query = "UPDATE category SET category = '$nama' WHERE id = $id";
        
        if (mysqli_query($conn, $query)) {
            echo "<script>alert('Kategori berhasil diperbarui!'); window.location.href='kategori.php';</script>";
        } else {
            echo "<script>alert('Gagal memperbarui kategori!');</script>";
        }
    }
}

// Pagination
$results_per_page = 5;
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$offset = ($page - 1) * $results_per_page;

// Get total number of categories
$total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM category");
$total_row = mysqli_fetch_assoc($total_query);
$total_pages = ceil($total_row['total'] / $results_per_page);

// Ambil Data Kategori dengan pagination
$kategoriData = mysqli_query($conn, "SELECT * FROM category LIMIT $offset, $results_per_page");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Produk - SmartCash</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
            transition: background 0.3s;
        }

        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
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
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .table-container {
            width: 100%;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #61892F;
            color: white;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        
        .pagination a {
            color: #61892F;
            padding: 8px 16px;
            text-decoration: none;
            border: 1px solid #ddd;
            margin: 0 4px;
        }
        
        .pagination a.active {
            background-color: #61892F;
            color: white;
            border: 1px solid #61892F;
        }
        
        .pagination a:hover:not(.active) {
            background-color: #ddd;
        }
    </style>
</head>
<body class="bg-[#F1F9E4]">
    <div class="container mx-auto p-4">
        <div class="flex items-center mb-8 relative border-b pb-4 border-[#D1D5DB]">
            <i id="menuToggle" class="fas fa-bars text-2xl mr-4 cursor-pointer text-[#61892F]"></i>
            <h1 class="text-3xl font-bold text-[#1F2937]">Smart <span class="text-[#61892F]">Cash</span></h1>
            <div class="ml-auto flex items-center space-x-4">
                <a href="profil.php">
                    <img src="<?= htmlspecialchars($image) ?>" alt="Foto Profil" class="w-10 h-10 rounded-full object-cover inline-block">
                    <span class="text-[#1F2937] font-medium"><?= htmlspecialchars($username) ?></span>
                </a>
            </div>
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
    
    <div class="container mx-auto px-4 py-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold text-gray-700">Kategori Produk</h2>
            <button id="addCategoryBtn" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition">
                <i class="fas fa-plus"></i> Tambah Kategori
            </button>
        </div>

        <!-- Table Kategori -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="table-container">
                <table class="w-full">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Kategori</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = $offset + 1;
                        while ($row = mysqli_fetch_assoc($kategoriData)) { ?>
                            <tr>
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($row['category']) ?></td>
                                <td>
                                    <button class="bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600 transition" 
                                            onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['category']) ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="bg-red-500 text-white p-2 rounded-md hover:bg-red-600 transition" 
                                            onclick="hapusKategori(<?= $row['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="pagination p-4">
                <?php if ($page > 1): ?>
                    <a href="kategori.php?page=<?= $page - 1 ?>">&laquo;</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="kategori.php?page=<?= $i ?>" <?= ($i == $page) ? 'class="active"' : '' ?>><?= $i ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="kategori.php?page=<?= $page + 1 ?>">&raquo;</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Tambah -->
    <div id="modalTambahKategori" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg w-96">
            <h2 class="text-xl font-bold text-gray-700 mb-4">Tambah Kategori</h2>
            <form method="POST">
                <input type="text" name="namaKategori" placeholder="Nama Kategori" required class="w-full px-3 py-2 border rounded-md mb-4">
                <div class="flex justify-end">
                    <button type="button" id="batalTambahKategori" class="bg-red-600 text-white px-4 py-2 rounded-md mr-2">Batal</button>
                    <button type="submit" name="tambah_kategori" class="bg-green-600 text-white px-4 py-2 rounded-md">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit -->
    <div id="modalEditKategori" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg w-96">
            <h2 class="text-xl font-bold text-gray-700 mb-4">Edit Kategori</h2>
            <form method="POST">
                <input type="hidden" name="kategori_id" id="kategoriId">
                <input type="text" name="namaKategoriEdit" id="namaKategoriEdit" required class="w-full px-3 py-2 border rounded-md mb-4">
                <div class="flex justify-end">
                    <button type="button" id="batalEditKategori" class="bg-red-600 text-white px-4 py-2 rounded-md mr-2">Batal</button>
                    <button type="submit" name="update_kategori" class="bg-green-600 text-white px-4 py-2 rounded-md">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('sidebar-hidden');
        });

        document.getElementById('addCategoryBtn').addEventListener('click', function() {
            document.getElementById('modalTambahKategori').classList.remove('hidden');
        });

        document.getElementById('batalTambahKategori').addEventListener('click', function() {
            document.getElementById('modalTambahKategori').classList.add('hidden');
        });

        document.getElementById('batalEditKategori').addEventListener('click', function() {
            document.getElementById('modalEditKategori').classList.add('hidden');
        });

        function openEditModal(id, name) {
            document.getElementById('kategoriId').value = id;
            document.getElementById('namaKategoriEdit').value = name;
            document.getElementById('modalEditKategori').classList.remove('hidden');
        }

        function hapusKategori(id) {
    if (confirm("Yakin ingin menghapus kategori ini?\n\nJika masih ada produk yang terkait dengan kategori ini, penghapusan tidak akan dilakukan.")) {
        window.location.href = "kategori.php?hapus=" + id;
    }
}
    </script>
</body>
</html>