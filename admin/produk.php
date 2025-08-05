<?php
session_start();
require '../service/connection.php';
$email = $_SESSION['email'];
require_once __DIR__ . '/../vendor/autoload.php';

// Get admin data
$query = mysqli_query($conn, "SELECT * FROM admin WHERE email = '$email'");
$admin = mysqli_fetch_assoc($query);

// Set display data
$username = $admin['username'];
$image = !empty($admin['image']) ? '../uploads/' . $admin['image'] : '../assets/default.jpg';

// Get categories for dropdown
$resultKategori = mysqli_query($conn, "SELECT * FROM category");
if (!$resultKategori) {
    die("Query failed: " . mysqli_error($conn));
}

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total products count
$totalQuery = mysqli_query($conn, "SELECT COUNT(*) as total FROM products");
$totalData = mysqli_fetch_assoc($totalQuery)['total'];
$totalPages = ceil($totalData / $limit);

// Get products data with category info
$resultProduk = mysqli_query($conn, "
    SELECT p.*, c.category as category_name 
    FROM products p 
    LEFT JOIN category c ON p.fid_category = c.id 
    ORDER BY p.id DESC 
    LIMIT $limit OFFSET $offset
");

if (!$resultProduk) {
    die("Products query failed: " . mysqli_error($conn));
}

use Picqer\Barcode\BarcodeGeneratorPNG;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - MediPOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6b46c1;
            --secondary: #805ad5;
            --dark: #2d3748;
            --light: #f8fafc;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light);
        }
        
        .sidebar {
            background-color: var(--primary);
            color: white;
            width: 16rem;
        }
        
        .sidebar a:hover {
            background-color: var(--secondary);
        }
        
        .nav-active {
            background-color: var(--secondary);
        }
        
        .badge-active {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .badge-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .action-btn {
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
        
        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.2);
        }
        
        .product-table th {
            background-color: var(--primary);
            color: white;
            position: sticky;
            top: 0;
        }
        
        .modal {
            background-color: rgba(0,0,0,0.5);
        }
        
        .table-container {
            max-height: calc(100vh - 250px);
            overflow-y: auto;
        }
        
        .main-content {
            margin-left: 16rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex flex-col md:flex-row h-screen">
        <!-- Sidebar -->
        <div class="sidebar px-4 py-8 shadow-lg md:h-full">
            <div class="flex items-center justify-center mb-8">
                <h1 class="text-2xl font-bold">
                    <span class="text-white">Medi</span><span class="text-purple-300">POS</span>
                </h1>
            </div>
            
            <div class="flex items-center px-4 py-3 mb-6 rounded-lg bg-purple-900">
                <img src="<?= $image ?>" alt="Profile" class="w-10 h-10 rounded-full object-cover border-2 border-purple-500">
                <div class="ml-3">
                    <p class="font-medium text-white"><?= $username ?></p>
                    <p class="text-xs text-purple-200">Admin</p>
                </div>
            </div>

            <nav class="mt-8">
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                </a>
                <a href="manage_category.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-tags mr-3"></i> Kategori
                </a>
                <a href="manage_product.php" class="flex items-center px-4 py-3 rounded-lg nav-active">
                    <i class="fas fa-boxes mr-3"></i> Produk
                </a>
                <a href="manage_member.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-users mr-3"></i> Member
                </a>
                <a href="transaksi.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-shopping-cart mr-3"></i> Transaksi
                </a>
                <a href="laporan.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-chart-bar mr-3"></i> Laporan
                </a>
                <a href="../service/logout.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800 mt-8 text-red-200">
                    <i class="fas fa-sign-out-alt mr-3"></i> Logout
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content flex-1 overflow-y-auto">
            <header class="bg-white shadow-sm">
                <div class="flex justify-between items-center px-6 py-4">
                    <h2 class="text-xl font-semibold text-gray-800">Kelola Produk</h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500" id="currentDateTime"></span>
                        <div class="relative">
                            <img src="<?= $image ?>" alt="Profile" class="w-8 h-8 rounded-full border-2 border-purple-500">
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <!-- Header and Add Button -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Daftar Produk</h2>
                        <p class="text-gray-600">Kelola data produk dan inventori</p>
                    </div>
                    <button id="addProductBtn" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center">
                        <i class="fas fa-plus mr-2"></i> Tambah Produk
                    </button>
                </div>

                <!-- Table Container -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-purple-600">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Gambar</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Produk</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Kategori</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-white uppercase tracking-wider">Harga</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">Stok</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">Kadaluarsa</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium text-white uppercase tracking-wider">Barcode</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-white uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                if ($resultProduk && mysqli_num_rows($resultProduk) > 0) {
                                    while ($row = mysqli_fetch_assoc($resultProduk)) { 
                                        $expiryClass = '';
                                        if (!empty($row['exp'])) {
                                            try {
                                                $expiryDate = new DateTime($row['exp']);
                                                $today = new DateTime();
                                                $interval = $today->diff($expiryDate);
                                                
                                                if ($expiryDate < $today) {
                                                    $expiryClass = 'badge-inactive';
                                                } elseif ($interval->days <= 30) {
                                                    $expiryClass = 'badge-warning';
                                                }
                                            } catch (Exception $e) {
                                                $expiryClass = 'badge-warning';
                                            }
                                        }
                                ?>
                                <tr class="hover:bg-purple-50">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="flex-shrink-0 h-12 w-12">
                                            <?php if (!empty($row['image']) && file_exists('../uploads/' . $row['image'])): ?>
                                                <img class="h-12 w-12 rounded-md object-cover" src="../uploads/<?= htmlspecialchars($row['image']) ?>" alt="Product Image">
                                            <?php else: ?>
                                                <div class="h-12 w-12 rounded-md bg-gray-200 flex items-center justify-center">
                                                    <i class="fas fa-box text-gray-400"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($row['product_name']) ?></div>
                                        <div class="text-sm text-gray-500 mt-1 line-clamp-2">
                                            <?= !empty($row['description']) ? htmlspecialchars($row['description']) : 'No description' ?>
                                        </div>
                                    </td>
                                    
                                    <td class="px-4 py-3">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800">
                                            <?= !empty($row['category_name']) ? htmlspecialchars($row['category_name']) : 'Uncategorized' ?>
                                        </span>
                                    </td>
                                    
                                    <td class="px-4 py-3">
                                        <div class="font-semibold">Rp <?= number_format($row['selling_price'], 0, ',', '.') ?></div>
                                        <div class="text-xs text-gray-500">Modal: Rp <?= number_format($row['starting_price'], 0, ',', '.') ?></div>
                                        <div class="text-xs text-blue-600 font-medium">Margin: Rp <?= number_format($row['margin'], 0, ',', '.') ?></div>
                                    </td>
                                    
                                    <td class="px-4 py-3 text-center">
                                        <?php if ($row['qty'] > 10): ?>
                                            <span class="badge-active px-2 py-1 rounded-full text-xs">
                                                <?= (int)$row['qty'] ?> pcs
                                            </span>
                                        <?php elseif ($row['qty'] > 0): ?>
                                            <span class="badge-warning px-2 py-1 rounded-full text-xs">
                                                <?= (int)$row['qty'] ?> pcs
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-inactive px-2 py-1 rounded-full text-xs">
                                                Habis
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="px-4 py-3 text-center">
                                        <?php if (!empty($row['exp'])): ?>
                                            <span class="<?= $expiryClass ?> px-2 py-1 rounded-full text-xs">
                                                <?= date('d M Y', strtotime($row['exp'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="px-4 py-3 text-center">
                                        <?php if (!empty($row['barcode_image']) && file_exists('../uploads/' . $row['barcode_image'])): ?>
                                            <div class="flex flex-col items-center">
                                                <img src="../uploads/<?= htmlspecialchars($row['barcode_image']) ?>" alt="Barcode" class="h-8 w-auto">
                                                <span class="text-xs text-gray-600 mt-1"><?= !empty($row['barcode']) ? htmlspecialchars($row['barcode']) : 'N/A' ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex justify-end space-x-1">
                                            <a href="manage_product.php?edit=<?= (int)$row['id'] ?>" class="action-btn text-purple-600 hover:text-purple-800 p-1 rounded-md">
                                                <i class="fas fa-edit text-sm"></i>
                                            </a>
                                            <a href="manage_product.php?hapus=<?= (int)$row['id'] ?>" 
                                               onclick="return confirm('Yakin ingin menghapus produk ini?')"
                                               class="action-btn text-red-600 hover:text-red-800 p-1 rounded-md">
                                                <i class="fas fa-trash text-sm"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    }
                                } else {
                                    echo '<tr>
                                        <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                            <i class="fas fa-box-open text-3xl mb-2"></i>
                                            <p class="text-lg">Tidak ada produk ditemukan</p>
                                        </td>
                                    </tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <a href="?page=<?= max(1, $page - 1) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </a>
                            <a href="?page=<?= min($totalPages, $page + 1) ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
                            </a>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Showing <span class="font-medium"><?= ($offset + 1) ?></span> to <span class="font-medium"><?= min($offset + $limit, $totalData) ?></span> of <span class="font-medium"><?= $totalData ?></span> results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <a href="?page=<?= max(1, $page - 1) ?>" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?= $page <= 1 ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                        <span class="sr-only">Previous</span>
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                    
                                    <?php 
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    if ($startPage > 1) {
                                        echo '<a href="?page=1" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
                                        if ($startPage > 2) {
                                            echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                                        }
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $active = $i == $page ? 'bg-purple-100 border-purple-500 text-purple-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50';
                                        echo '<a href="?page='.$i.'" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium '.$active.'">'.$i.'</a>';
                                    }
                                    
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700">...</span>';
                                        }
                                        echo '<a href="?page='.$totalPages.'" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">'.$totalPages.'</a>';
                                    }
                                    ?>
                                    
                                    <a href="?page=<?= min($totalPages, $page + 1) ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?= $page >= $totalPages ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                        <span class="sr-only">Next</span>
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal Tambah Produk -->
    <div id="modalTambahProduk" class="modal fixed inset-0 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
            <div class="px-6 py-4 border-b border-gray-200 bg-purple-50">
                <h3 class="text-lg font-semibold text-purple-800">Tambah Produk Baru</h3>
            </div>
            
            <form action="proses_produk.php" method="POST" enctype="multipart/form-data" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Kolom Kiri -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Produk*</label>
                        <input type="text" name="namaProduk" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi*</label>
                        <textarea name="deskripsiProduk" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500 h-24" required></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kategori*</label>
                        <select name="fid_category" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                            <?php 
                            mysqli_data_seek($resultKategori, 0); // Reset pointer
                            while ($kategori = mysqli_fetch_assoc($resultKategori)) { ?>
                                <option value="<?= $kategori['id'] ?>"><?= $kategori['category'] ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <!-- Kolom Kanan -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stok*</label>
                        <input type="number" name="stokProduk" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga Modal*</label>
                        <input type="number" name="hargaModalProduk" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga Jual*</label>
                        <input type="number" name="hargaJualProduk" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Kadaluarsa</label>
                        <input type="date" name="exp" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                <!-- Full Width -->
                <div class="md:col-span-2 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gambar Produk*</label>
                        <input type="file" name="gambarProduk" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Barcode (Opsional)</label>
                        <input type="text" name="barcodeInput" placeholder="Kosongkan untuk generate otomatis" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                <div class="md:col-span-2 flex justify-end mt-4 gap-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Batal
                    </button>
                    <button type="submit" name="tambah_produk" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 flex items-center">
                        <i class="fas fa-save mr-2"></i> Simpan Produk
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Produk -->
    <?php if (isset($_GET['edit'])) { 
        $idProduk = $_GET['edit'];
        $queryEdit = "SELECT * FROM products WHERE id = $idProduk";
        $resultEdit = mysqli_query($conn, $queryEdit);
        $rowEdit = mysqli_fetch_assoc($resultEdit);
        
        // Fetch categories again for edit modal
        $resultKategoriEdit = mysqli_query($conn, "SELECT * FROM category");
    ?>
    <div id="modalEditProduk" class="modal fixed inset-0 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200 bg-purple-50">
                <h3 class="text-lg font-semibold text-purple-800">Edit Produk</h3>
            </div>
            
            <form action="proses_produk.php" method="POST" enctype="multipart/form-data" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="produk_id" value="<?= $rowEdit['id'] ?>">
                <input type="hidden" name="existing_image" value="<?= $rowEdit['image'] ?>">
                <input type="hidden" name="existing_barcode_image" value="<?= $rowEdit['barcode_image'] ?>">

                <!-- Kolom Kiri -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Produk*</label>
                        <input type="text" name="namaProdukEdit" value="<?= htmlspecialchars($rowEdit['product_name']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi*</label>
                        <textarea name="deskripsiProdukEdit" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500 h-24" required><?= htmlspecialchars($rowEdit['description']) ?></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kategori*</label>
                        <select name="fid_category_edit" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                            <?php while ($category = mysqli_fetch_assoc($resultKategoriEdit)) { 
                                $selected = ($category['id'] == $rowEdit['fid_category']) ? 'selected' : '';
                            ?>
                                <option value="<?= $category['id'] ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($category['category']) ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <!-- Kolom Kanan -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Stok*</label>
                        <input type="number" name="stokProdukEdit" value="<?= $rowEdit['qty'] ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga Modal*</label>
                        <input type="number" name="hargaModalProdukEdit" value="<?= $rowEdit['starting_price'] ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga Jual*</label>
                        <input type="number" name="hargaJualProdukEdit" value="<?= $rowEdit['selling_price'] ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Kadaluarsa</label>
                        <input type="date" name="expEdit" value="<?= $rowEdit['exp'] ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                <!-- Full Width -->
                <div class="md:col-span-2 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gambar Produk</label>
                        <div class="flex items-center gap-4">
                            <?php if (!empty($rowEdit['image']) && file_exists('../uploads/' . $rowEdit['image'])) { ?>
                                <div class="flex-shrink-0">
                                    <img src="../uploads/<?= $rowEdit['image'] ?>" alt="Gambar Produk" class="h-16 w-16 rounded-md object-cover">
                                </div>
                            <?php } ?>
                            <input type="file" name="gambarProdukEdit" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Barcode</label>
                        <input type="text" name="barcodeEdit" value="<?= htmlspecialchars($rowEdit['barcode']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                <div class="md:col-span-2 flex justify-end mt-4 gap-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Batal
                    </button>
                    <button type="submit" name="update_produk" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 flex items-center">
                        <i class="fas fa-save mr-2"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php } ?>

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

        // Modal functions
        document.getElementById('addProductBtn').addEventListener('click', function() {
            document.getElementById('modalTambahProduk').classList.remove('hidden');
        });

        function closeModal() {
            document.getElementById('modalTambahProduk').classList.add('hidden');
            if (document.getElementById('modalEditProduk')) {
                document.getElementById('modalEditProduk').classList.add('hidden');
            }
        }

        // Show edit modal if edit parameter exists
        <?php if (isset($_GET['edit'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('modalEditProduk').classList.remove('hidden');
            });
        <?php endif; ?>

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal();
            }
        });
    </script>
</body>
</html>