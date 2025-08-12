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
require '../service/connection.php';
$email = $_SESSION['email'];
require_once __DIR__ . '/../vendor/autoload.php';

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get total products
$totalQuery = "SELECT COUNT(*) as total FROM products";
$totalResult = mysqli_query($conn, $totalQuery);
$totalData = mysqli_fetch_assoc($totalResult)['total'];
$totalPages = ceil($totalData / $limit);

// Get products with pagination
$queryProduk = "SELECT p.*, c.category as category_name FROM products p 
                LEFT JOIN category c ON p.fid_category = c.id 
                ORDER BY p.id DESC LIMIT $limit OFFSET $offset";
$resultProduk = mysqli_query($conn, $queryProduk);

// Get categories for dropdown
$queryKategori = "SELECT * FROM category ORDER BY category";
$resultKategori = mysqli_query($conn, $queryKategori);

// Get user data for profile
$queryUser = "SELECT username, image FROM admin WHERE email = '$email'";
$resultUser = mysqli_query($conn, $queryUser);
$user = mysqli_fetch_assoc($resultUser);
$username = $user['username'];
$image = !empty($user['image']) ? '../uploads/' . $user['image'] : '../assets/images/default-profile.png';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create Product
    if (isset($_POST['tambah_produk'])) {
        $productName = mysqli_real_escape_string($conn, $_POST['namaProduk']);
        $description = mysqli_real_escape_string($conn, $_POST['deskripsiProduk']);
        $categoryId = (int)$_POST['fid_category'];
        $stock = (int)$_POST['stokProduk'];
        $purchasePrice = (int)$_POST['hargaModalProduk'];
        $sellingPrice = (int)$_POST['hargaJualProduk'];
        $expiryDate = !empty($_POST['exp']) ? mysqli_real_escape_string($conn, $_POST['exp']) : null;
        $margin = $sellingPrice - $purchasePrice;

        // Handle barcode
        $barcode = !empty($_POST['barcodeInput']) ? mysqli_real_escape_string($conn, $_POST['barcodeInput']) : uniqid();

        // Check for duplicate product name
        $cekNama = mysqli_query($conn, "SELECT id FROM products WHERE product_name = '$productName'");
        if (mysqli_num_rows($cekNama) > 0) {
            $_SESSION['error'] = "Nama produk sudah ada, tidak boleh duplikat.";
            header("Location: manage_product.php");
            exit();
        }

        // Check for duplicate barcode
        $cekBarcode = mysqli_query($conn, "SELECT id FROM products WHERE barcode = '$barcode'");
        if (mysqli_num_rows($cekBarcode) > 0) {
            $_SESSION['error'] = "Barcode sudah ada, tidak boleh duplikat.";
            header("Location: manage_product.php");
            exit();
        }

        // Handle file upload
        $imageName = '';
        if (!empty($_FILES['gambarProduk']['name'])) {
            $imageName = time() . '_' . basename($_FILES['gambarProduk']['name']);
            $targetDir = "../uploads/";
            $targetFile = $targetDir . $imageName;
            move_uploaded_file($_FILES['gambarProduk']['tmp_name'], $targetFile);
        }

        // Generate barcode image
        $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
        $barcodeImage = 'barcode_' . time() . '.png';
        file_put_contents('../uploads/barcodes/' . $barcodeImage, $generator->getBarcode($barcode, $generator::TYPE_CODE_128));

        $query = "INSERT INTO products (product_name, description, fid_category, qty, starting_price, selling_price, margin, exp, image, barcode, barcode_image) 
                  VALUES ('$productName', '$description', $categoryId, $stock, $purchasePrice, $sellingPrice, $margin, " .
            ($expiryDate ? "'$expiryDate'" : "NULL") . ", " .
            ($imageName ? "'$imageName'" : "NULL") . ", '$barcode', '$barcodeImage')";

        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = "Produk berhasil ditambahkan";
            header("Location: manage_product.php");
            exit();
        } else {
            $_SESSION['error'] = "Gagal menambahkan produk: " . mysqli_error($conn);
        }
    }

    // Update Product
    if (isset($_POST['update_produk'])) {
        $productId = (int)$_POST['produk_id'];
        $productName = mysqli_real_escape_string($conn, $_POST['namaProdukEdit']);
        $description = mysqli_real_escape_string($conn, $_POST['deskripsiProdukEdit']);
        $categoryId = (int)$_POST['fid_category_edit'];
        $stock = (int)$_POST['stokProdukEdit'];
        $purchasePrice = (int)$_POST['hargaModalProdukEdit'];
        $sellingPrice = (int)$_POST['hargaJualProdukEdit'];
        $expiryDate = !empty($_POST['expEdit']) ? mysqli_real_escape_string($conn, $_POST['expEdit']) : null;
        $margin = $sellingPrice - $purchasePrice;
        $barcode = !empty($_POST['barcodeEdit']) ? mysqli_real_escape_string($conn, $_POST['barcodeEdit']) : uniqid();

        // Check for duplicate product name (excluding current product)
        $cekNama = mysqli_query($conn, "SELECT id FROM products WHERE product_name = '$productName' AND id != $productId");
        if (mysqli_num_rows($cekNama) > 0) {
            $_SESSION['error'] = "Nama produk sudah ada, tidak boleh duplikat.";
            header("Location: manage_product.php?edit=$productId");
            exit();
        }

        // Check for duplicate barcode (excluding current product)
        $cekBarcode = mysqli_query($conn, "SELECT id FROM products WHERE barcode = '$barcode' AND id != $productId");
        if (mysqli_num_rows($cekBarcode) > 0) {
            $_SESSION['error'] = "Barcode sudah ada, tidak boleh duplikat.";
            header("Location: manage_product.php?edit=$productId");
            exit();
        }

        // Handle existing image
        $existingImage = $_POST['existing_image'];
        $imageName = $existingImage;

        // Handle new image upload
        if (!empty($_FILES['gambarProdukEdit']['name'])) {
            // Delete old image if exists
            if (!empty($existingImage) && file_exists("../uploads/$existingImage")) {
                unlink("../uploads/$existingImage");
            }

            $imageName = time() . '_' . basename($_FILES['gambarProdukEdit']['name']);
            $targetDir = "../uploads/";
            $targetFile = $targetDir . $imageName;
            move_uploaded_file($_FILES['gambarProdukEdit']['tmp_name'], $targetFile);
        }

        // Handle barcode update
        $existingBarcodeImage = $_POST['existing_barcode_image'];
        $barcodeImage = $existingBarcodeImage;

        if (!empty($barcode) && $barcode != $_POST['current_barcode']) {
            // Generate new barcode image
            $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
            $barcodeImage = 'barcode_' . time() . '.png';
            file_put_contents('../uploads/barcodes/' . $barcodeImage, $generator->getBarcode($barcode, $generator::TYPE_CODE_128));

            // Delete old barcode image if exists
            if (!empty($existingBarcodeImage) && file_exists("../uploads/barcodes/$existingBarcodeImage")) {
                unlink("../uploads/barcodes/$existingBarcodeImage");
            }
        }

        $query = "UPDATE products SET 
                  product_name = '$productName',
                  description = '$description',
                  fid_category = $categoryId,
                  qty = $stock,
                  starting_price = $purchasePrice,
                  selling_price = $sellingPrice,
                  margin = $margin,
                  exp = " . ($expiryDate ? "'$expiryDate'" : "NULL") . ",
                  image = " . ($imageName ? "'$imageName'" : "NULL") . ",
                  barcode = '$barcode',
                  barcode_image = '$barcodeImage'
                  WHERE id = $productId";

        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = "Produk berhasil diperbarui";
            header("Location: manage_product.php");
            exit();
        } else {
            $_SESSION['error'] = "Gagal memperbarui produk: " . mysqli_error($conn);
        }
    }
}

// Handle edit request
$editProduct = null;
if (isset($_GET['edit'])) {
    $productId = (int)$_GET['edit'];
    $query = "SELECT * FROM products WHERE id = $productId";
    $result = mysqli_query($conn, $query);
    $editProduct = mysqli_fetch_assoc($result);

    if (!$editProduct) {
        $_SESSION['error'] = "Produk tidak ditemukan";
        header("Location: manage_product.php");
        exit();
    }
}

// Handle delete request
if (isset($_GET['hapus'])) {
    $productId = (int)$_GET['hapus'];
    // Check product stock
    $query = "SELECT qty, image, barcode_image FROM products WHERE id = $productId";
    $result = mysqli_query($conn, $query);
    $product = mysqli_fetch_assoc($result);

    if ($product['qty'] > 0) {
        $_SESSION['error'] = "Produk tidak bisa dihapus karena stok masih ada.";
        header("Location: manage_product.php");
        exit();
    }

    if (!empty($product['image'])) {
        $imagePath = "../uploads/" . $product['image'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }

    if (!empty($product['barcode_image'])) {
        $barcodePath = "../uploads/barcodes/" . $product['barcode_image'];
        if (file_exists($barcodePath)) {
            unlink($barcodePath);
        }
    }

    // Delete from database
    $query = "DELETE FROM products WHERE id = $productId";
    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Produk berhasil dihapus";
    } else {
        $_SESSION['error'] = "Gagal menghapus produk: " . mysqli_error($conn);
    }
    header("Location: manage_product.php");
    exit();
}

// Display success/error messages
if (isset($_SESSION['success'])) {
    $successMessage = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $errorMessage = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Produk - MediPOS</title>
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

        .product-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #E5E7EB;
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .modal {
            background-color: rgba(0, 0, 0, 0.5);
        }

        .badge-primary {
            background-color: #E9D5FF;
            color: #7E22CE;
        }

        .badge-active {
            background-color: #D1FAE5;
            color: #065F46;
        }

        .badge-warning {
            background-color: #FEF3C7;
            color: #92400E;
        }

        .badge-danger {
            background-color: #FEE2E2;
            color: #B91C1C;
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

            <!-- Profile Section -->
            <div class="flex items-center px-4 py-3 mb-6 rounded-lg bg-purple-900">
                <img src="<?= $image ?>" alt="Profile" class="w-10 h-10 rounded-full border-2 border-purple-300">
                <div class="ml-3">
                    <p class="font-medium text-white"><?= htmlspecialchars($username) ?></p>
                    <p class="text-xs text-purple-200">Super Admin</p>
                </div>
            </div>

            <nav class="mt-8">
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-tachometer-alt mr-3"></i>Dashboard
                </a>
                <a href="manage_admin.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-user-cog mr-3"></i>Kelola Kasir
                </a>
                <a href="manage_member.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-users mr-3"></i>Kelola Member
                </a>
                <a href="manage_category.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-tags mr-3"></i>Kategori Produk
                </a>
                <a href="manage_product.php" class="flex items-center px-4 py-3 rounded-lg nav-active">
                    <i class="fas fa-boxes mr-3"></i>Kelola Produk
                </a>
                <a href="reports.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-chart-bar mr-3"></i>Laporan & Grafik
                </a>
                <a href="system_logs.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-clipboard-list mr-3"></i>Log Sistem
                </a>
                <a href="../service/logout.php" class="flex items-center px-4 py-0 rounded-lg hover:bg-purple-800 mt-5 text-red-200">
                    <i class="fas fa-sign-out-alt mr-3"></i>Logout
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="ml-64 flex-1 overflow-y-auto">
            <header class="bg-white shadow-sm">
                <div class="flex justify-between items-center px-6 py-4">
                    <h2 class="text-xl font-semibold text-gray-800">Kelola Produk</h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500" id="currentDateTime"></span>
                        <div class="relative">
                            <img src="<?= $image ?>" alt="Profile" class="w-8 h-8 rounded-full border-2 border-purple-500">
                            <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full"></span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <!-- Success/Error Notification -->
                <?php if (!empty($successMessage)): ?>
                    <div class="fixed top-4 right-4 z-50">
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                            <span class="block sm:inline"><?= htmlspecialchars($successMessage) ?></span>
                            <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                                <svg class="fill-current h-6 w-6 text-green-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <title>Close</title>
                                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z" />
                                </svg>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMessage)): ?>
                    <div class="fixed top-4 right-4 z-50">
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                            <span class="block sm:inline"><?= htmlspecialchars($errorMessage) ?></span>
                            <span class="absolute top-0 bottom-0 right-0 px-4 py-3" onclick="this.parentElement.style.display='none'">
                                <svg class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <title>Close</title>
                                    <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z" />
                                </svg>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Header and Add Button -->
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Daftar Produk</h2>
                        <p class="text-gray-600">Kelola produk dan stok inventori</p>
                    </div>
                    <button id="addProductBtn" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center">
                        <i class="fas fa-plus mr-2"></i>Tambah Produk
                    </button>
                </div>

                <!-- Products Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-purple-600 text-white">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">No</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Gambar</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Produk</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Kategori</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Harga</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Stok</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Expired</th>
                                    <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider">Barcode</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if ($resultProduk && mysqli_num_rows($resultProduk) > 0): ?>
                                    <?php $no = $offset + 1; ?>
                                    <?php while ($row = mysqli_fetch_assoc($resultProduk)): ?>
                                        <?php
                                        $expiryClass = '';
                                        if (!empty($row['exp'])) {
                                            try {
                                                $expiryDate = new DateTime($row['exp']);
                                                $today = new DateTime();
                                                $interval = $today->diff($expiryDate);

                                                if ($expiryDate < $today) {
                                                    $expiryClass = 'badge-danger';
                                                } elseif ($interval->days <= 30) {
                                                    $expiryClass = 'badge-warning';
                                                }
                                            } catch (Exception $e) {
                                                $expiryClass = 'badge-warning';
                                            }
                                        }
                                        ?>
                                        <tr class="hover:bg-purple-50 transition-colors">
                                            <!-- No Column -->
                                            <td class="px-4 py-3 text-left font-semibold text-gray-700"><?= $no++ ?></td>
                                            <!-- Image Column -->
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <?php
                                                $imagePath = '../uploads/' . $row['image'];
                                                if (!empty($row['image']) && file_exists($imagePath)): ?>
                                                    <img class="product-image" src="<?= $imagePath ?>" alt="Product Image">
                                                <?php else: ?>
                                                    <div class="product-image bg-gray-100 flex items-center justify-center">
                                                        <i class="fas fa-box text-gray-400"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Product Info Column -->
                                            <td class="px-4 py-3">
                                                <div class="font-medium text-gray-900"><?= htmlspecialchars($row['product_name']) ?></div>
                                                <div class="text-xs text-gray-500 mt-1 line-clamp-2">
                                                    <?= !empty($row['description']) ? htmlspecialchars($row['description']) : '-' ?>
                                                </div>
                                            </td>

                                            <!-- Category Column -->
                                            <td class="px-4 py-3">
                                                <span class="badge-primary px-2 py-1 rounded-full text-xs">
                                                    <?= !empty($row['category_name']) ? htmlspecialchars($row['category_name']) : 'Uncategorized' ?>
                                                </span>
                                            </td>

                                            <!-- Price Column -->
                                            <td class="px-4 py-3">
                                                <div class="font-semibold">Rp <?= number_format($row['selling_price'], 0, ',', '.') ?></div>
                                                <div class="text-xs text-gray-500">Modal: Rp <?= number_format($row['starting_price'], 0, ',', '.') ?></div>
                                                <div class="text-xs text-blue-600 font-medium">Margin: Rp <?= number_format($row['margin'], 0, ',', '.') ?></div>
                                            </td>

                                            <!-- Stock Column -->
                                            <td class="px-4 py-3 text-center">
                                                <?php if ($row['qty'] > 10): ?>
                                                    <span class="badge-active px-2 py-1 rounded-full text-xs"><?= (int)$row['qty'] ?>pcs</span>
                                                <?php elseif ($row['qty'] > 0): ?>
                                                    <span class="badge-warning px-2 py-1 rounded-full text-xs"><?= (int)$row['qty'] ?>pcs</span>
                                                <?php else: ?>
                                                    <span class="badge-danger px-2 py-1 rounded-full text-xs">Habis</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Expiry Column -->
                                            <td class="px-4 py-3 text-center">
                                                <?php if (!empty($row['exp'])): ?>
                                                    <span class="<?= $expiryClass ?> px-2 py-1 rounded-full text-xs">
                                                        <?= date('d M Y', strtotime($row['exp'])) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-xs text-gray-500">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Barcode Column -->
                                            <td class="px-4 py-3 text-center">
                                                <?php
                                                $barcodePath = '../uploads/barcodes/' . $row['barcode_image'];
                                                if (!empty($row['barcode_image']) && file_exists($barcodePath)): ?>
                                                    <div class="flex flex-col items-center">
                                                        <img src="<?= $barcodePath ?>" alt="Barcode" class="h-8 w-auto">
                                                        <span class="text-xs text-gray-600 mt-1 truncate max-w-xs">
                                                            <?= !empty($row['barcode']) ? htmlspecialchars($row['barcode']) : 'N/A' ?>
                                                        </span>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-xs text-gray-500">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- Actions Column -->
                                            <td class="px-4 py-3 text-right">
                                                <div class="flex justify-end space-x-2">
                                                    <a href="manage_product.php?edit=<?= (int)$row['id'] ?>" class="text-purple-600 hover:text-purple-800 p-2 rounded-md hover:bg-purple-100 transition-colors">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="manage_product.php?hapus=<?= (int)$row['id'] ?>" class="text-red-600 hover:text-red-800 p-2 rounded-md hover:bg-red-100 transition-colors">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                            <i class="fas fa-box-open text-3xl mb-2 text-gray-300"></i>
                                            <p class="text-lg font-medium text-gray-400">Tidak ada produk ditemukan</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
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
                                    Menampilkan <span class="font-medium"><?= ($offset + 1) ?></span> sampai
                                    <span class="font-medium"><?= min($offset + $limit, $totalData) ?></span> dari
                                    <span class="font-medium"><?= $totalData ?></span> produk
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
                                            echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
                                        }
                                    }

                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $active = $i == $page ? 'bg-purple-100 border-purple-500 text-purple-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50';
                                        echo '<a href="?page=' . $i . '" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium ' . $active . '">' . $i . '</a>';
                                    }

                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
                                        }
                                        echo '<a href="?page=' . $totalPages . '" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">' . $totalPages . '</a>';
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

    <!-- Add Product Modal -->
    <div id="modalTambahProduk" class="modal fixed inset-0 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4">
            <!-- Modal Header -->
            <div class="px-5 py-3 border-b border-gray-200 bg-purple-600 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-md font-semibold text-white">
                            <i class="fas fa-plus-circle mr-2"></i>Tambah Produk
                        </h3>
                    </div>
                    <button onclick="closeModal()" class="text-purple-100 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Body -->
            <form action="manage_product.php" method="POST" enctype="multipart/form-data" class="p-4 space-y-4 overflow-y-auto" style="max-height: 70vh;">
                <!-- Product Name -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Produk*</label>
                    <div class="relative">
                        <input type="text" name="namaProduk" class="w-full px-3 py-2 pl-9 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent" required placeholder="Nama produk">
                        <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                            <i class="fas fa-tag text-gray-400 text-sm"></i>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Deskripsi*</label>
                    <textarea name="deskripsiProduk" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent h-20" required placeholder="Deskripsi singkat"></textarea>
                </div>

                <!-- Category -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kategori*</label>
                    <select name="fid_category" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent" required>
                        <option value="" disabled selected>Pilih kategori</option>
                        <?php mysqli_data_seek($resultKategori, 0); ?>
                        <?php while ($kategori = mysqli_fetch_assoc($resultKategori)): ?>
                            <option value="<?= $kategori['id'] ?>"><?= $kategori['category'] ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Stock -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stok*</label>
                    <input type="number" name="stokProduk" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent" required placeholder="Jumlah stok">
                </div>

                <!-- Prices Row -->
                <div class="grid grid-cols-2 gap-3">
                    <!-- Purchase Price -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga Modal*</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                <span class="text-gray-500 text-sm">Rp</span>
                            </div>
                            <input type="number" name="hargaModalProduk" class="w-full px-3 py-2 pl-10 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent" required placeholder="Harga modal">
                        </div>
                    </div>

                    <!-- Selling Price -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Harga Jual*</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                <span class="text-gray-500 text-sm">Rp</span>
                            </div>
                            <input type="number" name="hargaJualProduk" class="w-full px-3 py-2 pl-10 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent" required placeholder="Harga jual">
                        </div>
                    </div>
                </div>

                <!-- Expiry Date -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Kadaluarsa</label>
                    <input type="date" name="exp" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>

                <!-- Product Image -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gambar Produk*</label>
                    <input type="file" name="gambarProduk" class="w-full text-sm border border-gray-300 rounded-md file:mr-2 file:py-1.5 file:px-3 file:border-0 file:text-sm file:font-medium file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100" required>
                    <p class="text-xs text-gray-500 mt-1">Format: JPG/PNG (max 2MB)</p>
                </div>

                <!-- Barcode -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Barcode (Opsional)</label>
                    <input type="text" name="barcodeInput" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="Generate otomatis jika kosong">
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-end gap-2 pt-3">
                    <button type="button" onclick="closeModal()" class="px-3 py-1.5 text-sm bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-md flex items-center">
                        <i class="fas fa-times mr-1"></i>Batal
                    </button>
                    <button type="submit" name="tambah_produk" class="px-3 py-1.5 text-sm bg-purple-600 hover:bg-purple-700 text-white rounded-md flex items-center shadow-sm">
                        <i class="fas fa-save mr-1"></i>Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <?php if ($editProduct): ?>
        <div id="modalEditProduk" class="modal fixed inset-0 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4">
                <!-- Modal Header -->
                <div class="px-5 py-3 border-b border-gray-200 bg-purple-600 rounded-t-xl">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-md font-semibold text-white">
                                <i class="fas fa-edit mr-2"></i>Edit Produk
                            </h3>
                        </div>
                        <button onclick="closeModal()" class="text-purple-100 hover:text-white">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <!-- Modal Body -->
                <form action="manage_product.php" method="POST" enctype="multipart/form-data" class="p-4 space-y-4 overflow-y-auto" style="max-height: 70vh;">
                    <input type="hidden" name="produk_id" value="<?= $editProduct['id'] ?>">
                    <input type="hidden" name="existing_image" value="<?= $editProduct['image'] ?>">
                    <input type="hidden" name="existing_barcode_image" value="<?= $editProduct['barcode_image'] ?>">
                    <input type="hidden" name="current_barcode" value="<?= $editProduct['barcode'] ?>">

                    <!-- Product Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                            <i class="fas fa-tag text-purple-600 mr-2 text-sm"></i>Nama Produk*
                        </label>
                        <div class="relative">
                            <input type="text" name="namaProdukEdit" value="<?= htmlspecialchars($editProduct['product_name']) ?>"
                                class="w-full px-3 py-2 pl-9 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                required>
                            <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                <i class="fas fa-tag text-gray-400 text-sm"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                            <i class="fas fa-align-left text-purple-600 mr-2 text-sm"></i>Deskripsi*
                        </label>
                        <textarea name="deskripsiProdukEdit" class="w-full px-3 py-2 pl-9 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent h-20" required><?= htmlspecialchars($editProduct['description']) ?></textarea>
                    </div>

                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                            <i class="fas fa-tags text-purple-600 mr-2 text-sm"></i>Kategori*
                        </label>
                        <select name="fid_category_edit" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent" required>
                            <?php mysqli_data_seek($resultKategori, 0); ?>
                            <?php while ($category = mysqli_fetch_assoc($resultKategori)): ?>
                                <option value="<?= $category['id'] ?>" <?= ($category['id'] == $editProduct['fid_category']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['category']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Stock -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                            <i class="fas fa-boxes text-purple-600 mr-2 text-sm"></i>Stok*
                        </label>
                        <input type="number" name="stokProdukEdit" value="<?= $editProduct['qty'] ?>"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            required>
                    </div>

                    <!-- Prices Row -->
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Purchase Price -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                                <i class="fas fa-money-bill-wave text-purple-600 mr-2 text-sm"></i>Harga Modal*
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                    <span class="text-gray-500 text-sm">Rp</span>
                                </div>
                                <input type="number" name="hargaModalProdukEdit" value="<?= $editProduct['starting_price'] ?>"
                                    class="w-full px-3 py-2 pl-10 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                    required>
                            </div>
                        </div>

                        <!-- Selling Price -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                                <i class="fas fa-tag text-purple-600 mr-2 text-sm"></i>Harga Jual*
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                    <span class="text-gray-500 text-sm">Rp</span>
                                </div>
                                <input type="number" name="hargaJualProdukEdit" value="<?= $editProduct['selling_price'] ?>"
                                    class="w-full px-3 py-2 pl-10 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                    required>
                            </div>
                        </div>
                    </div>

                    <!-- Expiry Date -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                            <i class="fas fa-calendar-times text-purple-600 mr-2 text-sm"></i>Tanggal Kadaluarsa
                        </label>
                        <input type="date" name="expEdit" value="<?= $editProduct['exp'] ?>"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>

                    <!-- Product Image -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                            <i class="fas fa-image text-purple-600 mr-2 text-sm"></i>Gambar Produk
                        </label>
                        <div class="flex items-center gap-3">
                            <?php if (!empty($editProduct['image']) && file_exists('../uploads/' . $editProduct['image'])): ?>
                                <div class="flex-shrink-0">
                                    <img src="../uploads/<?= $editProduct['image'] ?>" alt="Gambar Produk" class="w-10 h-10 rounded-md object-cover border border-gray-200">
                                </div>
                            <?php endif; ?>
                            <input type="file" name="gambarProdukEdit"
                                class="w-full text-sm border border-gray-300 rounded-md file:mr-2 file:py-1.5 file:px-3 file:border-0 file:text-sm file:font-medium file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Format: JPG/PNG (max 2MB)</p>
                    </div>

                    <!-- Barcode -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1 flex items-center">
                            <i class="fas fa-barcode text-purple-600 mr-2 text-sm"></i>Barcode
                        </label>
                        <input type="text" name="barcodeEdit" value="<?= htmlspecialchars($editProduct['barcode']) ?>"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            placeholder="Generate otomatis jika kosong">
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-200">
                        <button type="button" onclick="closeModal()" class="px-3 py-1.5 text-sm bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-md flex items-center">
                            <i class="fas fa-times mr-1"></i>Batal
                        </button>
                        <button type="submit" name="update_produk" class="px-3 py-1.5 text-sm bg-purple-600 hover:bg-purple-700 text-white rounded-md flex items-center shadow-sm">
                            <i class="fas fa-save mr-1"></i>Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

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

        // Delete confirmation with better handling
        document.querySelectorAll('a[href*="hapus="]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();

                if (confirm('Yakin ingin menghapus produk ini?')) {
                    window.location.href = this.getAttribute('href');
                }
            });
        });

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