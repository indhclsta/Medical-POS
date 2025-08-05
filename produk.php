<?php
session_start();
require './service/connection.php';
$email = $_SESSION['email'];
require './service/connection.php';
require_once __DIR__ . '/vendor/autoload.php';

$query = mysqli_query($conn, "SELECT * FROM admin WHERE email = '$email'");
$admin = mysqli_fetch_assoc($query);

// Set data untuk tampilan
$username = $admin['username'];
$image = !empty($admin['image']) ? 'uploads/' . $admin['image'] : 'default.jpg';

// Ambil Data Kategori untuk dropdown
$resultKategori = mysqli_query($conn, "SELECT * FROM category");

if (!$resultKategori) {
    die("Query gagal: " . mysqli_error($conn));
}

use Picqer\Barcode\BarcodeGeneratorPNG;
// Menambahkan produk baru
if (isset($_POST['tambah_produk'])) {
    $namaProduk = mysqli_real_escape_string($conn, $_POST['namaProduk']);
    $deskripsiProduk = mysqli_real_escape_string($conn, $_POST['deskripsiProduk']);
    $stokProduk = (int)$_POST['stokProduk'];
    $exp = !empty($_POST['exp']) ? $_POST['exp'] : NULL;
    $hargaModalProduk = (float)$_POST['hargaModalProduk'];
    $hargaJualProduk = (float)$_POST['hargaJualProduk'];
    $fid_category = (int)$_POST['fid_category'];
    $gambarProduk = $_FILES['gambarProduk'];
    
    // Cek apakah nama produk sudah ada
    $check_query = "SELECT id FROM products WHERE product_name = '$namaProduk'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        echo "<script>alert('Nama produk sudah ada! Silakan gunakan nama yang berbeda.'); window.location.href='produk.php';</script>";
        exit();
    }
    
    // Simpan gambar produk
    $imageFileName = '';
    if ($gambarProduk['error'] === UPLOAD_ERR_OK) {
        $imageFileName = uniqid() . '_' . basename($gambarProduk['name']);
        $imagePath = 'uploads/' . $imageFileName;
        if (!move_uploaded_file($gambarProduk['tmp_name'], $imagePath)) {
            echo "<script>alert('Gagal mengupload gambar!'); window.location.href='produk.php';</script>";
            exit();
        }
    } else {
        echo "<script>alert('Gambar produk wajib diupload!'); window.location.href='produk.php';</script>";
        exit();
    }
    
    // Cek apakah ada input barcode manual
    $barcodeCode = !empty($_POST['barcodeInput']) ? $_POST['barcodeInput'] : rand(1000000000, 9999999999);
    
    // Generate barcode image
    try {
        $generator = new BarcodeGeneratorPNG();
        $barcodeImage = $generator->getBarcode($barcodeCode, $generator::TYPE_CODE_128);
        $barcodeFileName = uniqid() . '.png';
        $barcodePath = 'uploads/barcodes/' . $barcodeFileName;
        file_put_contents($barcodePath, $barcodeImage);
    } catch (Exception $e) {
        echo "<script>alert('Gagal generate barcode!'); window.location.href='produk.php';</script>";
        exit();
    }
    
    // Simpan produk ke database
    $margin = $hargaJualProduk - $hargaModalProduk;
    $query = "INSERT INTO products (product_name, description, qty, starting_price, selling_price, margin, fid_category, image, barcode, barcode_image, exp)
          VALUES ('$namaProduk', '$deskripsiProduk', $stokProduk, $hargaModalProduk, $hargaJualProduk, $margin, $fid_category, '$imageFileName', '$barcodeCode', '$barcodeFileName', " . ($exp ? "'$exp'" : "NULL") . ")";
    
    if (mysqli_query($conn, $query)) {
        echo "<script>alert('Produk berhasil ditambahkan!'); window.location.href='produk.php';</script>";
    } else {
        // Hapus file yang sudah diupload jika query gagal
        if (file_exists('uploads/' . $imageFileName)) unlink('uploads/' . $imageFileName);
        if (file_exists('uploads/barcodes/' . $barcodeFileName)) unlink('uploads/barcodes/' . $barcodeFileName);
        
        echo "<script>alert('Gagal menambah produk: " . mysqli_error($conn) . "'); window.location.href='produk.php';</script>";
    }
}

// Mengupdate produk
if (isset($_POST['update_produk'])) {
    $produk_id = (int)$_POST['produk_id'];
    $namaProdukEdit = mysqli_real_escape_string($conn, $_POST['namaProdukEdit']);
    $deskripsiProdukEdit = mysqli_real_escape_string($conn, $_POST['deskripsiProdukEdit']);
    $stokProdukEdit = (int)$_POST['stokProdukEdit'];
    $expEdit = !empty($_POST['expEdit']) ? $_POST['expEdit'] : NULL;
    $hargaModalProdukEdit = (float)$_POST['hargaModalProdukEdit'];
    $hargaJualProdukEdit = (float)$_POST['hargaJualProdukEdit'];
    $fid_category_edit = (int)$_POST['fid_category_edit'];
    $gambarProdukEdit = $_FILES['gambarProdukEdit'];
    $existing_image = $_POST['existing_image'];
    
    // Cek apakah nama produk sudah digunakan oleh produk lain
    $check_query = "SELECT id FROM products WHERE product_name = '$namaProdukEdit' AND id != $produk_id";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        echo "<script>alert('Nama produk sudah digunakan oleh produk lain!'); window.location.href='produk.php';</script>";
        exit();
    }
    
    // Generate barcode baru
    $barcodeCode = rand(1000000000, 9999999999);
    $barcodeFileName = '';
    
    try {
        $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
        $barcodeImage = $generator->getBarcode($barcodeCode, $generator::TYPE_CODE_128);
        $barcodeFileName = uniqid() . '.png';
        $barcodeImagePath = 'uploads/barcodes/' . $barcodeFileName;
        file_put_contents($barcodeImagePath, $barcodeImage);
    } catch (Exception $e) {
        echo "<script>alert('Gagal generate barcode baru!'); window.location.href='produk.php';</script>";
        exit();
    }
    
    // Update gambar jika ada file gambar baru
    if (!empty($gambarProdukEdit['name'])) {
        $imageFileName = uniqid() . '_' . basename($gambarProdukEdit['name']);
        $imagePath = 'uploads/' . $imageFileName;
        move_uploaded_file($gambarProdukEdit['tmp_name'], $imagePath);
    } else {
        $imageFileName = $_POST['existing_image']; // Ambil gambar lama jika tidak ada gambar baru
    }
    
    // Hitung margin baru
    $margin = $hargaJualProdukEdit - $hargaModalProdukEdit;
    
    // Query untuk memperbarui produk di database
    $query = "UPDATE products SET 
        product_name = '$namaProdukEdit', 
        description = '$deskripsiProdukEdit', 
        qty = $stokProdukEdit,
        starting_price = $hargaModalProdukEdit, 
        selling_price = $hargaJualProdukEdit, 
        margin = $margin,
        fid_category = $fid_category_edit,
        image = '$imageFileName', 
        barcode = '$barcodeCode', 
        barcode_image = '$barcodeFileName',
        exp = " . ($expEdit ? "'$expEdit'" : "NULL") . "
        WHERE id = $produk_id";
    
    if (mysqli_query($conn, $query)) {
        // Hapus barcode lama jika berhasil update
        $oldBarcodePath = 'uploads/barcodes/' . $_POST['existing_barcode_image'];
        if (file_exists($oldBarcodePath)) {
            unlink($oldBarcodePath);
        }
        
        echo "<script>alert('Produk berhasil diperbarui!'); window.location.href='produk.php';</script>";
    } else {
        // Hapus file yang baru diupload jika query gagal
        if ($imageFileName !== $existing_image && file_exists('uploads/' . $imageFileName)) {
            unlink('uploads/' . $imageFileName);
        }
        if (file_exists('uploads/barcodes/' . $barcodeFileName)) {
            unlink('uploads/barcodes/' . $barcodeFileName);
        }
        
        echo "<script>alert('Gagal memperbarui produk: " . mysqli_error($conn) . "');</script>";
    }
}

// Menghapus produk
if (isset($_GET['hapus'])) {
    $idHapus = (int)$_GET['hapus'];

    // Cek stok produk terlebih dahulu
    $queryCekStok = mysqli_query($conn, "SELECT qty FROM products WHERE id = $idHapus");
    $stokProduk = mysqli_fetch_assoc($queryCekStok)['qty'];

    if ($stokProduk > 0) {
        echo "<script>alert('Produk tidak dapat dihapus karena masih memiliki stok!'); window.location.href='produk.php';</script>";
        exit();
    }

    // Hapus data gambar & barcode dari folder
    $querySelect = mysqli_query($conn, "SELECT image, barcode_image FROM products WHERE id = $idHapus");
    if ($row = mysqli_fetch_assoc($querySelect)) {
        $gambar = 'uploads/' . $row['image'];
        $barcode = 'uploads/barcodes/' . $row['barcode_image'];

        if (file_exists($gambar)) unlink($gambar);
        if (file_exists($barcode)) unlink($barcode);
    }

    // Hapus dari database
    $queryHapus = mysqli_query($conn, "DELETE FROM products WHERE id = $idHapus");

    if ($queryHapus) {
        echo "<script>alert('Produk berhasil dihapus!'); window.location.href='produk.php';</script>";
        exit;
    } else {
        echo "<script>alert('Gagal menghapus produk!'); window.location.href='produk.php';</script>";
    }
}

// Ambil Data Produk dengan Join kategori
$resultProduk = mysqli_query($conn, "SELECT p.*, c.category AS category_name 
                                    FROM products p 
                                    LEFT JOIN category c ON p.fid_category = c.id");

// Ambil Data Produk untuk Edit
if (isset($_GET['edit'])) {
    $idProduk = $_GET['edit'];
    $queryEdit = "SELECT * FROM products WHERE id = $idProduk";
    $resultEdit = mysqli_query($conn, $queryEdit);
    $rowEdit = mysqli_fetch_assoc($resultEdit);
}

// Pagination Configuration
$perPage = 5; // Jumlah item per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1; // Halaman saat ini
$page = max($page, 1); // Pastikan tidak kurang dari 1

// Hitung offset
$offset = ($page - 1) * $perPage;

// Query untuk mengambil data produk dengan pagination
$queryProduk = "SELECT p.*, c.category AS category_name 
                FROM products p 
                LEFT JOIN category c ON p.fid_category = c.id
                LIMIT $perPage OFFSET $offset";
$resultProduk = mysqli_query($conn, $queryProduk);

// Hitung total data produk
$totalQuery = "SELECT COUNT(*) as total FROM products";
$totalResult = mysqli_query($conn, $totalQuery);
$totalData = mysqli_fetch_assoc($totalResult)['total'];

// Hitung total halaman
$totalPages = ceil($totalData / $perPage);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produk - SmartCash</title>
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
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        /* New styles for improved table */
        .product-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .product-table th {
            position: sticky;
            top: 0;
            background-color: #61892F;
            color: white;
            font-weight: 600;
            text-align: left;
            padding: 12px 16px;
        }
        
        .product-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }
        
        .product-table tr:hover {
            background-color: #f9fafb;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .stock-high {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .stock-medium {
            background-color: #fef9c3;
            color: #854d0e;
        }
        
        .stock-low {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .expired-soon {
            background-color: #ffedd5;
            color: #9a3412;
        }
        
        .expired {
            background-color: #fee2e2;
            color: #b91c1c;
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

        <!-- Main Content -->
        <div class="max-w-6xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Data Produk</h2>
                <button id="addProductBtn" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md flex items-center gap-2 transition-colors">
                    <i class="fas fa-plus"></i> Tambah Produk
                </button>
            </div>

            <!-- Improved Product Table -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-[#61892F]">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Gambar</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Produk</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Kategori</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Harga</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Stok</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Expired</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Barcode</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($row = mysqli_fetch_assoc($resultProduk)) { 
                                $expiryClass = '';
                                if (!empty($row['exp'])) {
                                    $expiryDate = new DateTime($row['exp']);
                                    $today = new DateTime();
                                    $interval = $today->diff($expiryDate);
                                    
                                    if ($expiryDate < $today) {
                                        $expiryClass = 'expired'; // Already expired
                                    } elseif ($interval->days <= 30) {
                                        $expiryClass = 'expired-soon'; // Expiring soon (within 30 days)
                                    }
                                }
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <!-- Gambar Produk -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex-shrink-0 h-16 w-16 mx-auto">
                                            <img class="h-16 w-16 rounded-md object-cover" src="uploads/<?php echo $row['image']; ?>" alt="Gambar Produk">
                                        </div>
                                    </td>
                                    
                                    <!-- Nama dan Deskripsi Produk -->
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?php echo $row['product_name']; ?></div>
                                        <div class="text-sm text-gray-500 mt-1 line-clamp-2"><?php echo $row['description']; ?></div>
                                    </td>
                                    
                                    <!-- Kategori -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <?php echo $row['category_name']; ?>
                                        </span>
                                    </td>
                                    
                                    <!-- Harga -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 font-semibold"><?php echo 'Rp ' . number_format($row['selling_price'], 0, ',', '.'); ?></div>
                                        <div class="text-xs text-gray-500">Modal: <?php echo 'Rp ' . number_format($row['starting_price'], 0, ',', '.'); ?></div>
                                        <div class="text-xs text-blue-600 font-medium">Margin: <?php echo 'Rp ' . number_format($row['margin'], 0, ',', '.'); ?></div>
                                    </td>
                                    
                                    <!-- Stok -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($row['qty'] > 10): ?>
                                            <span class="status-badge stock-high">
                                                <?php echo $row['qty']; ?> pcs
                                            </span>
                                        <?php elseif ($row['qty'] > 0): ?>
                                            <span class="status-badge stock-medium">
                                                <?php echo $row['qty']; ?> pcs
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge stock-low">
                                                Habis
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Tanggal Expired -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if (!empty($row['exp'])): ?>
                                            <span class="status-badge <?php echo $expiryClass; ?>">
                                                <?php echo date('d M Y', strtotime($row['exp'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Barcode -->
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if (!empty($row['barcode'])): ?>
                                            <div class="flex flex-col items-center">
                                                <img src="uploads/barcodes/<?php echo $row['barcode_image']; ?>" alt="Barcode" class="h-10 w-auto">
                                                <span class="text-xs text-gray-600 mt-1"><?php echo $row['barcode']; ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- Aksi -->
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex justify-end space-x-2">
                                            <button onclick="openEditModal(<?php echo $row['id']; ?>)" 
                                                    class="text-indigo-600 hover:text-indigo-900 p-2 rounded-md hover:bg-indigo-50">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?hapus=<?php echo $row['id']; ?>" 
                                               onclick="return confirm('Yakin ingin menghapus produk ini?')"
                                               class="text-red-600 hover:text-red-900 p-2 rounded-md hover:bg-red-50">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination Controls -->
            <div class="flex justify-center mt-6">
                <nav class="inline-flex rounded-md shadow">
                    <ul class="flex items-center space-x-2">
                        <!-- Previous Button -->
                        <li>
                            <a href="?page=<?= max(1, $page - 1) ?>" 
                               class="px-3 py-1 border rounded-md <?= $page <= 1 ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-white hover:bg-gray-100' ?>">
                                &laquo; Prev
                            </a>
                        </li>

                        <!-- Page Numbers -->
                        <?php 
                        // Tampilkan maksimal 5 halaman di sekitar halaman aktif
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        // Jika di awal, tampilkan lebih banyak di akhir
                        if ($page <= 3) {
                            $endPage = min(5, $totalPages);
                        }
                        
                        // Jika di akhir, tampilkan lebih banyak di awal
                        if ($page >= $totalPages - 2) {
                            $startPage = max(1, $totalPages - 4);
                        }
                        
                        // Tampilkan tombol halaman pertama jika tidak terlihat
                        if ($startPage > 1) {
                            echo '<li><a href="?page=1" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100">1</a></li>';
                            if ($startPage > 2) {
                                echo '<li><span class="px-3 py-1">...</span></li>';
                            }
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            $active = $i == $page ? 'bg-green-600 text-white' : 'bg-white hover:bg-gray-100';
                            echo '<li><a href="?page='.$i.'" class="px-3 py-1 border rounded-md '.$active.'">'.$i.'</a></li>';
                        }
                        
                        // Tampilkan tombol halaman terakhir jika tidak terlihat
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<li><span class="px-3 py-1">...</span></li>';
                            }
                            echo '<li><a href="?page='.$totalPages.'" class="px-3 py-1 border rounded-md bg-white hover:bg-gray-100">'.$totalPages.'</a></li>';
                        }
                        ?>
                        
                        <!-- Next Button -->
                        <li>
                            <a href="?page=<?= min($totalPages, $page + 1) ?>" 
                               class="px-3 py-1 border rounded-md <?= $page >= $totalPages ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-white hover:bg-gray-100' ?>">
                                Next &raquo;
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </div>

        <!-- Modal Tambah Produk -->
        <div id="modalTambahProduk" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded-lg w-96">
                <h2 class="text-xl font-semibold mb-4">Tambah Produk</h2>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Kolom Kiri -->
                        <div>
                            <!-- Input Nama Produk -->
                            <label for="namaProduk" class="block text-sm font-medium text-gray-700">Nama Produk</label>
                            <input type="text" id="namaProduk" name="namaProduk" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                        </div>

                        <div>
                            <!-- Input Stok Produk -->
                            <label for="stokProduk" class="block text-sm font-medium text-gray-700">Stok Produk</label>
                            <input type="number" id="stokProduk" name="stokProduk" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                        </div>

                        <div>
                            <!-- Input Harga Modal Produk -->
                            <label for="hargaModalProduk" class="block text-sm font-medium text-gray-700">Harga Modal</label>
                            <input type="number" id="hargaModalProduk" name="hargaModalProduk" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                        </div>

                        <div>
                            <!-- Dropdown Kategori -->
                            <label for="fid_category" class="block text-sm font-medium text-gray-700">Kategori</label>
                            <select id="fid_category" name="fid_category" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                                <?php 
                                // Reset pointer result kategori
                                mysqli_data_seek($resultKategori, 0);
                                while ($kategori = mysqli_fetch_assoc($resultKategori)) { ?>
                                    <option value="<?= $kategori['id'] ?>"><?= $kategori['category'] ?></option>
                                <?php } ?>
                            </select>
                        </div>

                        <!-- Kolom Kanan -->
                        <div>
                            <!-- Input Deskripsi Produk -->
                            <label for="deskripsiProduk" class="block text-sm font-medium text-gray-700">Deskripsi Produk</label>
                            <textarea id="deskripsiProduk" name="deskripsiProduk" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required></textarea>
                        </div>

                        <div>
                            <!-- Input Harga Jual Produk -->
                            <label for="hargaJualProduk" class="block text-sm font-medium text-gray-700">Harga Jual</label>
                            <input type="number" id="hargaJualProduk" name="hargaJualProduk" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                        </div>

                        <div>
                            <!-- Input Tanggal Kadaluarsa -->
                            <label for="exp" class="block text-sm font-medium text-gray-700">Tanggal Kadaluarsa</label>
                            <input type="date" id="exp" name="exp" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>

                        <div>
                            <!-- Input Gambar Produk -->
                            <label for="gambarProduk" class="block text-sm font-medium text-gray-700">Gambar Produk</label>
                            <input type="file" id="gambarProduk" name="gambarProduk" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                        </div>

                        <div class="md:col-span-2">
                            <!-- Input Barcode (Opsional) -->
                            <label for="barcodeInput" class="block text-sm font-medium text-gray-700">Kode Barcode (Opsional)</label>
                            <input type="text" id="barcodeInput" name="barcodeInput" placeholder="Kosongkan untuk generate otomatis" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                        </div>
                    </div>

                    <div class="flex justify-end mt-6">
                        <button type="submit" name="tambah_produk" class="bg-green-600 text-white px-4 py-2 rounded-md">Tambah Produk</button>
                        <button type="button" onclick="closeModal()" class="ml-4 bg-gray-300 text-gray-700 px-4 py-2 rounded-md">Tutup</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Edit Produk --> 
        <?php if (isset($rowEdit)) {
            // Fetch categories again to use in the edit modal
            $resultKategori = mysqli_query($conn, "SELECT * FROM category");
            if (!$resultKategori) {
                die("Query gagal: " . mysqli_error($conn));
            }
        ?>
        <div id="modalEditProduk" class="fixed inset-0 z-50 bg-black bg-opacity-50 flex items-center justify-center">
            <div class="bg-white p-6 rounded-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-lg">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Edit Produk</h2>
                <form method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <input type="hidden" name="produk_id" value="<?php echo $rowEdit['id']; ?>">
                    <input type="hidden" name="existing_image" value="<?php echo $rowEdit['image']; ?>">
                    <input type="hidden" name="existing_barcode_image" value="<?php echo $rowEdit['barcode_image']; ?>">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Produk</label>
                        <input type="text" name="namaProdukEdit" value="<?php echo $rowEdit['product_name']; ?>" placeholder="Nama Produk" required class="w-full p-2 border rounded-md focus:outline-none focus:ring focus:border-green-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi Produk</label>
                        <textarea name="deskripsiProdukEdit" placeholder="Deskripsi Produk" required class="w-full p-2 border rounded-md h-24 resize-none focus:outline-none focus:ring focus:border-green-500"><?php echo $rowEdit['description']; ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stok Produk</label>
                                                <input type="number" name="stokProdukEdit" value="<?php echo $rowEdit['qty']; ?>" placeholder="Stok Produk" required class="w-full p-2 border rounded-md focus:outline-none focus:ring focus:border-green-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Kadaluarsa</label>
                        <input type="date" name="expEdit" value="<?php echo $rowEdit['exp']; ?>" class="w-full p-2 border rounded-md focus:outline-none focus:ring focus:border-green-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga Modal</label>
                        <input type="number" name="hargaModalProdukEdit" value="<?php echo $rowEdit['starting_price']; ?>" placeholder="Harga Modal" required class="w-full p-2 border rounded-md focus:outline-none focus:ring focus:border-green-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga Jual</label>
                        <input type="number" name="hargaJualProdukEdit" value="<?php echo $rowEdit['selling_price']; ?>" placeholder="Harga Jual" required class="w-full p-2 border rounded-md focus:outline-none focus:ring focus:border-green-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                        <select name="fid_category_edit" class="w-full p-2 border rounded-md focus:outline-none focus:ring focus:border-green-500" required>
                            <?php 
                            while ($category = mysqli_fetch_assoc($resultKategori)) { 
                                $selected = ($category['id'] == $rowEdit['fid_category']) ? 'selected' : ''; 
                            ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $selected; ?>>
                                    <?php echo $category['category']; ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gambar Produk</label>
                        <div class="flex flex-col md:flex-row items-start md:items-center gap-6">
                            <?php if (!empty($rowEdit['image'])) { ?>
                                <div>
                                    <p class="text-xs text-gray-600">Gambar Saat Ini</p>
                                    <img src="uploads/<?php echo $rowEdit['image']; ?>" alt="Gambar Produk" class="w-24 h-24 object-cover rounded-md border mt-1">
                                </div>
                            <?php } ?>
                            <div class="flex-1 w-full">
                                <p class="text-xs text-gray-600 mb-1">Upload Gambar Baru</p>
                                <input type="file" name="gambarProdukEdit" class="w-full p-2 border rounded-md focus:outline-none focus:ring focus:border-green-500">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Barcode Produk</label>
                        <input type="text" name="barcodeEdit" value="<?php echo $rowEdit['barcode']; ?>" placeholder="Barcode" class="w-full p-2 border rounded-md focus:outline-none focus:ring focus:border-green-500">
                    </div>
                    
                    <div class="md:col-span-2 flex justify-end mt-4 gap-3">
                        <button type="button" onclick="closeModal()" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-md transition duration-200">Batal</button>
                        <button type="submit" name="update_produk" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md transition duration-200">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
        <?php } ?>

        <!-- Sidebar Navigation -->
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
    </div>

    <script>
        // Menampilkan Modal Tambah Produk
        document.getElementById('addProductBtn').addEventListener('click', function() {
            document.getElementById('modalTambahProduk').classList.remove('hidden');
        });

        // Menutup Modal
        function closeModal() {
            document.getElementById('modalTambahProduk').classList.add('hidden');
            if (document.getElementById('modalEditProduk')) {
                document.getElementById('modalEditProduk').classList.add('hidden');
            }
        }

        // Menampilkan Modal Edit Produk
        function openEditModal(id) {
            window.location.href = "?edit=" + id;
        }

        // Toggle Sidebar
        document.getElementById('menuToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('sidebar-hidden');
        });

        // Logout Confirmation
        document.getElementById('logoutBtn').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Apakah Anda yakin ingin logout?')) {
                window.location.href = 'logout.php';
            }
        });

        // Show edit modal if edit parameter exists
        <?php if (isset($_GET['edit'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('modalEditProduk').classList.remove('hidden');
            });
        <?php endif; ?>
    </script>
</body>
</html>