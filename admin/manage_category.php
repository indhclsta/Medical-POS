<?php
session_start();
require '../service/connection.php';
$email = $_SESSION['email'];

// Buat folder upload jika belum ada
$uploadDir = '../uploads/categories/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Tambah Kategori
if (isset($_POST['tambah_kategori'])) {
    $nama = $_POST['namaKategori'];
    $imageName = '';

    // Handle file upload
    if (!empty($_FILES['category_image']['name'])) {
        $fileName = basename($_FILES['category_image']['name']);
        $targetFilePath = $uploadDir . uniqid() . '_' . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
        
        // Validasi file
        $allowTypes = array('jpg','png','jpeg','gif');
        $maxFileSize = 2 * 1024 * 1024; // 2MB
        
        if ($_FILES['category_image']['size'] > $maxFileSize) {
            $_SESSION['message'] = "Ukuran file terlalu besar (maks. 2MB)";
            $_SESSION['message_type'] = 'error';
        } elseif (!in_array(strtolower($fileType), $allowTypes)) {
            $_SESSION['message'] = "Hanya file JPG, JPEG, PNG & GIF yang diperbolehkan";
            $_SESSION['message_type'] = 'error';
        } elseif (move_uploaded_file($_FILES['category_image']['tmp_name'], $targetFilePath)) {
            $imageName = str_replace('../uploads/', '', $targetFilePath);
        } else {
            $_SESSION['message'] = "Terjadi kesalahan saat mengupload gambar";
            $_SESSION['message_type'] = 'error';
        }
    }

    // Cek apakah kategori sudah ada
    $check_query = "SELECT * FROM category WHERE category = '$nama'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $_SESSION['message'] = "Kategori sudah ada!";
        $_SESSION['message_type'] = 'error';
    } else {
        $query = "INSERT INTO category (category, image) VALUES ('$nama', '$imageName')";
        if (mysqli_query($conn, $query)) {
            $_SESSION['message'] = "Kategori berhasil ditambahkan!";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Gagal menambah kategori!";
            $_SESSION['message_type'] = 'error';
        }
    }
    header("Location: manage_category.php");
    exit();
}

$query = mysqli_query($conn, "SELECT * FROM admin WHERE email = '$email'");
$admin = mysqli_fetch_assoc($query);

// Set data untuk tampilan
$username = $admin['username'];
$image = !empty($admin['image']) ? '../uploads/' . $admin['image'] : '../assets/default.jpg';

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
        $_SESSION['message'] = "Kategori tidak bisa dihapus karena masih ada produk yang terkait!";
        $_SESSION['message_type'] = 'error';
    } else {
        // Hapus gambar terkait jika ada
        $get_image_query = "SELECT image FROM category WHERE id = $id";
        $image_result = mysqli_query($conn, $get_image_query);
        $image_data = mysqli_fetch_assoc($image_result);
        
        if (!empty($image_data['image'])) {
            @unlink('../uploads/' . $image_data['image']);
        }
        
        $query = "DELETE FROM category WHERE id = $id";
        if (mysqli_query($conn, $query)) {
            $_SESSION['message'] = "Kategori berhasil dihapus!";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Gagal menghapus kategori! Error: ".mysqli_error($conn);
            $_SESSION['message_type'] = 'error';
        }
    }
    header("Location: manage_category.php");
    exit();
}

// Update Kategori
if (isset($_POST['update_kategori'])) {
    $id = $_POST['kategori_id'];
    $nama = $_POST['namaKategoriEdit'];
    $imageUpdate = '';
    
    // Handle file upload jika ada gambar baru
    if (!empty($_FILES['category_image_edit']['name'])) {
        $fileName = basename($_FILES['category_image_edit']['name']);
        $targetFilePath = $uploadDir . uniqid() . '_' . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
        
        $allowTypes = array('jpg','png','jpeg','gif');
        $maxFileSize = 2 * 1024 * 1024; // 2MB
        
        if ($_FILES['category_image_edit']['size'] > $maxFileSize) {
            $_SESSION['message'] = "Ukuran file terlalu besar (maks. 2MB)";
            $_SESSION['message_type'] = 'error';
        } elseif (!in_array(strtolower($fileType), $allowTypes)) {
            $_SESSION['message'] = "Hanya file JPG, JPEG, PNG & GIF yang diperbolehkan";
            $_SESSION['message_type'] = 'error';
        } elseif (move_uploaded_file($_FILES['category_image_edit']['tmp_name'], $targetFilePath)) {
            $imageName = str_replace('../uploads/', '', $targetFilePath);
            $imageUpdate = ", image = '$imageName'";
            
            // Hapus gambar lama jika ada
            $oldImageQuery = mysqli_query($conn, "SELECT image FROM category WHERE id = $id");
            $oldImage = mysqli_fetch_assoc($oldImageQuery)['image'];
            if (!empty($oldImage)) {
                @unlink('../uploads/' . $oldImage);
            }
        } else {
            $_SESSION['message'] = "Terjadi kesalahan saat mengupload gambar";
            $_SESSION['message_type'] = 'error';
        }
    }
    
    // Cek apakah kategori sudah ada (kecuali kategori yang sedang diupdate)
    $check_query = "SELECT * FROM category WHERE category = '$nama' AND id != $id";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $_SESSION['message'] = "Kategori sudah ada!";
        $_SESSION['message_type'] = 'error';
    } else {
        $query = "UPDATE category SET category = '$nama' $imageUpdate WHERE id = $id";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['message'] = "Kategori berhasil diperbarui!";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Gagal memperbarui kategori!";
            $_SESSION['message_type'] = 'error';
        }
    }
    header("Location: manage_category.php");
    exit();
}

// Pagination
$results_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $results_per_page;

// Get total number of categories
$total_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM category");
$total_row = mysqli_fetch_assoc($total_query);
$total_pages = ceil($total_row['total'] / $results_per_page);

// Ambil Data Kategori dengan pagination
$kategoriData = mysqli_query($conn, "SELECT * FROM category ORDER BY id DESC LIMIT $offset, $results_per_page");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kategori - MediPOS</title>
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
        .badge-danger {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .badge-primary {
            background-color: #e9d5ff;
            color: #6b21a8;
        }
        .form-input:focus {
            border-color: #6b46c1;
            box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.2);
        }
        .modal {
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
        }
        .pagination a.active {
            background-color: #6b46c1;
            color: white;
            border-color: #6b46c1;
        }
        .pagination a:hover:not(.active) {
            background-color: #e9d5ff;
        }
        .image-preview {
            max-width: 100px;
            max-height: 100px;
            display: none;
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
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
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
                <a href="manage_category.php" class="flex items-center px-4 py-3 rounded-lg nav-active">
                    <i class="fas fa-tags mr-3"></i>
                    Kategori Produk
                </a>
                <a href="manage_product.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-boxes mr-3"></i>
                    Kelola Produk
                </a>
                <a href="reports.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Laporan & Grafik
                </a>
                <a href="system_logs.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-clipboard-list mr-3"></i>
                    Log Sistem
                </a>
                <a href="settings.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-cog mr-3"></i>
                    Pengaturan
                </a>
                <a href="../service/logout.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800 mt-8 text-red-200">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="ml-64 flex-1 overflow-y-auto">
            <header class="bg-white shadow-sm">
                <div class="flex justify-between items-center px-6 py-4">
                    <h2 class="text-xl font-semibold text-gray-800">Kelola Kategori</h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500" id="currentDateTime"></span>
                        <div class="relative">
                            <img src="<?= $image ?>" 
                                 alt="Profile" 
                                 class="w-8 h-8 rounded-full border-2 border-purple-500">
                            <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full"></span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <!-- Header and Add Button -->
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Daftar Kategori</h2>
                        <p class="text-gray-600">Kelola kategori produk</p>
                    </div>
                    <button id="addCategoryBtn" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center">
                        <i class="fas fa-plus mr-2"></i> Tambah Kategori
                    </button>
                </div>

                <!-- Categories Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-purple-600 text-white">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">No</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider">Kategori</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php 
                                $no = $offset + 1;
                                if ($kategoriData && mysqli_num_rows($kategoriData) > 0) {
                                    while ($row = mysqli_fetch_assoc($kategoriData)) { 
                                        $categoryImage = !empty($row['image']) ? '../uploads/' . $row['image'] : '../assets/default-category.png';
                                ?>
                                <tr class="hover:bg-purple-50 transition-colors">
                                    <td class="px-4 py-3 whitespace-nowrap"><?= $no++ ?></td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <img class="h-10 w-10 rounded-full object-cover" 
                                                     src="<?= $categoryImage ?>" 
                                                     alt="<?= htmlspecialchars($row['category']) ?>"
                                                     onerror="this.src='../assets/default-category.png'">
                                            </div>
                                            <div class="ml-4">
                                                <span class="badge-primary px-3 py-1 rounded-full text-sm">
                                                    <?= htmlspecialchars($row['category']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex justify-end space-x-2">
                                            <button onclick="openEditModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['category']) ?>', '<?= $row['image'] ?>')"
                                               class="text-purple-600 hover:text-purple-800 p-2 rounded-md hover:bg-purple-100 transition-colors">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="hapusKategori(<?= $row['id'] ?>)"
                                               class="text-red-600 hover:text-red-800 p-2 rounded-md hover:bg-red-100 transition-colors">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    }
                                } else {
                                    echo '<tr>
                                        <td colspan="3" class="px-6 py-8 text-center text-gray-500">
                                            <i class="fas fa-tags text-3xl mb-2 text-gray-300"></i>
                                            <p class="text-lg font-medium text-gray-400">Tidak ada kategori ditemukan</p>
                                        </td>
                                    </tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <a href="?page=<?= max(1, $page - 1) ?>" class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Previous
                            </a>
                            <a href="?page=<?= min($total_pages, $page + 1) ?>" class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Next
                            </a>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700">
                                    Menampilkan <span class="font-medium"><?= ($offset + 1) ?></span> sampai <span class="font-medium"><?= min($offset + $results_per_page, $total_row['total']) ?></span> dari <span class="font-medium"><?= $total_row['total'] ?></span> kategori
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
                                    $endPage = min($total_pages, $page + 2);
                                    
                                    if ($startPage > 1) {
                                        echo '<a href="?page=1" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">1</a>';
                                        if ($startPage > 2) {
                                            echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
                                        }
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $active = $i == $page ? 'bg-purple-100 border-purple-500 text-purple-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50';
                                        echo '<a href="?page='.$i.'" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium '.$active.'">'.$i.'</a>';
                                    }
                                    
                                    if ($endPage < $total_pages) {
                                        if ($endPage < $total_pages - 1) {
                                            echo '<span class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-500">...</span>';
                                        }
                                        echo '<a href="?page='.$total_pages.'" class="relative inline-flex items-center px-4 py-2 border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">'.$total_pages.'</a>';
                                    }
                                    ?>
                                    
                                    <a href="?page=<?= min($total_pages, $page + 1) ?>" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 <?= $page >= $total_pages ? 'opacity-50 cursor-not-allowed' : '' ?>">
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

    <!-- Add Category Modal -->
    <div id="modalTambahKategori" class="modal fixed inset-0 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4">
            <!-- Modal Header -->
            <div class="px-5 py-3 border-b border-gray-200 bg-purple-600 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-md font-semibold text-white">
                            <i class="fas fa-plus-circle mr-2"></i> Tambah Kategori
                        </h3>
                    </div>
                    <button onclick="closeModal()" class="text-purple-100 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <!-- Modal Body -->
            <form action="manage_category.php" method="POST" enctype="multipart/form-data" class="p-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kategori*</label>
                    <div class="relative">
                        <input type="text" name="namaKategori" class="w-full px-3 py-2 pl-9 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent" required placeholder="Nama kategori">
                        <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                            <i class="fas fa-tag text-gray-400 text-sm"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gambar Kategori</label>
                    <div class="mt-1 flex items-center">
                        <span class="inline-block h-12 w-12 rounded-full overflow-hidden bg-gray-100">
                            <img id="categoryImagePreview" src="../assets/default-category.png" alt="" class="h-full w-full object-cover">
                        </span>
                        <label for="category_image" class="ml-5 bg-white py-2 px-3 border border-gray-300 rounded-md shadow-sm text-sm leading-4 font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 cursor-pointer">
                            <span>Pilih Gambar</span>
                            <input id="category_image" name="category_image" type="file" class="sr-only" accept="image/*" onchange="previewImage(this, 'categoryImagePreview')">
                        </label>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Format: JPG, PNG, GIF (Maks. 2MB)</p>
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-end gap-2 pt-3">
                    <button type="button" onclick="closeModal()" class="px-3 py-1.5 text-sm bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-md flex items-center">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <button type="submit" name="tambah_kategori" class="px-3 py-1.5 text-sm bg-purple-600 hover:bg-purple-700 text-white rounded-md flex items-center shadow-sm">
                        <i class="fas fa-save mr-1"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="modalEditKategori" class="modal fixed inset-0 hidden flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4">
            <!-- Modal Header -->
            <div class="px-5 py-3 border-b border-gray-200 bg-purple-600 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-md font-semibold text-white">
                            <i class="fas fa-edit mr-2"></i> Edit Kategori
                        </h3>
                    </div>
                    <button onclick="closeModal()" class="text-purple-100 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
            
            <!-- Modal Body -->
            <form action="manage_category.php" method="POST" enctype="multipart/form-data" class="p-4 space-y-4">
                <input type="hidden" name="kategori_id" id="kategoriId">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nama Kategori*</label>
                    <div class="relative">
                        <input type="text" name="namaKategoriEdit" id="namaKategoriEdit" class="w-full px-3 py-2 pl-9 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-transparent" required>
                        <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                            <i class="fas fa-tag text-gray-400 text-sm"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Gambar Kategori</label>
                    <div class="mt-1 flex items-center">
                        <span class="inline-block h-12 w-12 rounded-full overflow-hidden bg-gray-100">
                            <img id="editCategoryImagePreview" src="../assets/default-category.png" alt="" class="h-full w-full object-cover">
                        </span>
                        <label for="category_image_edit" class="ml-5 bg-white py-2 px-3 border border-gray-300 rounded-md shadow-sm text-sm leading-4 font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 cursor-pointer">
                            <span>Ubah Gambar</span>
                            <input id="category_image_edit" name="category_image_edit" type="file" class="sr-only" accept="image/*" onchange="previewImage(this, 'editCategoryImagePreview')">
                        </label>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Format: JPG, PNG, GIF (Maks. 2MB)</p>
                </div>

                <!-- Modal Footer -->
                <div class="flex justify-end gap-2 pt-3">
                    <button type="button" onclick="closeModal()" class="px-3 py-1.5 text-sm bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-md flex items-center">
                        <i class="fas fa-times mr-1"></i> Batal
                    </button>
                    <button type="submit" name="update_kategori" class="px-3 py-1.5 text-sm bg-purple-600 hover:bg-purple-700 text-white rounded-md flex items-center shadow-sm">
                        <i class="fas fa-save mr-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
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

        // Modal functions
        document.getElementById('addCategoryBtn').addEventListener('click', function() {
            document.getElementById('modalTambahKategori').classList.remove('hidden');
        });

        function openEditModal(id, name, image = '') {
            document.getElementById('kategoriId').value = id;
            document.getElementById('namaKategoriEdit').value = name;
            
            const preview = document.getElementById('editCategoryImagePreview');
            preview.src = image ? '../uploads/' + image : '../assets/default-category.png';
            
            document.getElementById('modalEditKategori').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('modalTambahKategori').classList.add('hidden');
            document.getElementById('modalEditKategori').classList.add('hidden');
        }

        function hapusKategori(id) {
            if (confirm('Yakin ingin menghapus kategori ini?\n\nJika masih ada produk yang terkait dengan kategori ini, penghapusan tidak akan dilakukan.')) {
                window.location.href = "manage_category.php?hapus=" + id;
            }
        }

        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            const file = input.files[0];
            const reader = new FileReader();

            reader.onload = function(e) {
                preview.src = e.target.result;
            }

            if (file) {
                reader.readAsDataURL(file);
            }
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target.classList.contains('modal')) {
                closeModal();
            }
        });

        // Show success/error message from session
        <?php if (isset($_SESSION['message'])): ?>
            alert("<?= $_SESSION['message'] ?>");
            <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
    </script>
</body>
</html>