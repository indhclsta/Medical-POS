<?php
session_start();
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../service/login.php");
    exit();
}

include '../service/connection.php';

// Get admin data
$email = $_SESSION['email'];
$query = mysqli_query($conn, "SELECT * FROM admin WHERE email = '$email'");
$admin = mysqli_fetch_assoc($query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate username
    if (empty($username)) {
        $errors[] = "Username tidak boleh kosong";
    }
    
    // Validate password change if any field is filled
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        if (!password_verify($current_password, $admin['password'])) {
            $errors[] = "Password saat ini salah";
        }
        
        if (empty($new_password)) {
            $errors[] = "Password baru tidak boleh kosong";
        } elseif (strlen($new_password) < 8) {
            $errors[] = "Password baru minimal 8 karakter";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "Konfirmasi password tidak cocok";
        }
    }
    
    // Handle image upload
    $image_path = $admin['image'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['image']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = '../uploads/';
            $file_name = uniqid() . '_' . basename($_FILES['image']['name']);
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                // Delete old image if it's not the default
                if ($image_path && $image_path != 'default.jpg' && file_exists('../uploads/' . $image_path)) {
                    unlink('../uploads/' . $image_path);
                }
                $image_path = $file_name;
            } else {
                $errors[] = "Gagal mengupload gambar";
            }
        } else {
            $errors[] = "Format file tidak didukung (hanya JPEG, PNG, GIF)";
        }
    }
    
    // Update database if no errors
    if (empty($errors)) {
        $update_data = [
            "username = '$username'",
            "image = '$image_path'"
        ];
        
        // Update password if changed
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_data[] = "password = '$hashed_password'";
        }
        
        $update_query = "UPDATE admin SET " . implode(', ', $update_data) . " WHERE id = " . $admin['id'];
        
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['success_message'] = "Profil berhasil diperbarui";
            header("Location: profile.php");
            exit();
        } else {
            $errors[] = "Gagal memperbarui profil: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Super Admin - MediPOS</title>
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
        .bg-super-admin {
            background-color: #6b46c1;
        }
        .text-super-admin {
            color: #6b46c1;
        }
        .nav-active {
            background-color: #805ad5;
        }
        .profile-pic {
            transition: all 0.3s ease;
        }
        .profile-pic:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
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
                    <p class="font-medium text-white"><?= htmlspecialchars($admin['username']) ?></p>
                    <p class="text-xs text-purple-200">Super Admin</p>
                </div>
            </div>

            <nav class="mt-8">
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg nav-active">
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
                <a href="reports.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
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
            <header class="bg-white shadow-sm">
                <div class="flex justify-between items-center px-6 py-4">
                    <h2 class="text-xl font-semibold text-gray-800">Profil Super Admin</h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500" id="currentDateTime"></span>
                        <div class="relative">
                            <img src="../uploads/<?= htmlspecialchars($admin['image']) ?>" 
                                 alt="Profile" 
                                 class="w-8 h-8 rounded-full border-2 border-purple-500">
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <span class="block sm:inline"><?= $_SESSION['success_message'] ?></span>
                        <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                            <button onclick="this.parentElement.parentElement.style.display='none'">
                                <i class="fas fa-times"></i>
                            </button>
                        </span>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= $error ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="p-6">
                        <div class="flex flex-col md:flex-row gap-8">
                            <!-- Profile Picture Section -->
                            <div class="w-full md:w-1/3 flex flex-col items-center">
                                <div class="relative mb-4 profile-pic">
                                    <img src="../uploads/<?= htmlspecialchars($admin['image'] ?: 'default.jpg') ?>" 
                                         alt="Profile Picture" 
                                         class="w-48 h-48 rounded-full object-cover border-4 border-purple-200">
                                    <label for="image-upload" class="absolute bottom-0 right-0 bg-purple-600 text-white p-2 rounded-full cursor-pointer hover:bg-purple-700">
                                        <i class="fas fa-camera"></i>
                                    </label>
                                </div>
                                <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($admin['username']) ?></h3>
                                <p class="text-purple-600 mb-4">Super Admin</p>
                                <p class="text-sm text-gray-500 text-center">Terdaftar sejak: <?= date('d M Y', strtotime($admin['created_at'])) ?></p>
                            </div>

                            <!-- Profile Form Section -->
                            <div class="w-full md:w-2/3">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="file" id="image-upload" name="image" class="hidden" accept="image/*">
                                    
                                    <div class="mb-6">
                                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Informasi Akun</h3>
                                        
                                        <div class="mb-4">
                                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                            <input type="email" id="email" 
                                                   value="<?= htmlspecialchars($admin['email']) ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100 cursor-not-allowed" 
                                                   disabled>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                                            <input type="text" id="username" name="username" 
                                                   value="<?= htmlspecialchars($admin['username']) ?>" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                        </div>
                                    </div>

                                    <div class="mb-6">
                                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Ubah Password</h3>
                                        <p class="text-sm text-gray-500 mb-4">Biarkan kosong jika tidak ingin mengubah password</p>
                                        
                                        <div class="mb-4">
                                            <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Password Saat Ini</label>
                                            <input type="password" id="current_password" name="current_password" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">Password Baru</label>
                                            <input type="password" id="new_password" name="new_password" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password Baru</label>
                                            <input type="password" id="confirm_password" name="confirm_password" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                        </div>
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                                            Simpan Perubahan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Logs -->
                <div class="bg-white rounded-xl shadow-md mt-6 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-purple-50">
                        <h3 class="font-semibold text-lg text-purple-800">Aktivitas Terakhir</h3>
                    </div>
                    <div class="divide-y divide-gray-200">
                        <?php
                        $logs_query = mysqli_query($conn, "SELECT * FROM activity_logs WHERE admin_id = {$admin['id']} ORDER BY timestamp DESC LIMIT 5");
                        if (mysqli_num_rows($logs_query) > 0):
                            while ($log = mysqli_fetch_assoc($logs_query)):
                        ?>
                            <div class="px-6 py-4 hover:bg-purple-50">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-medium"><?= htmlspecialchars($log['activity']) ?></p>
                                    </div>
                                    <span class="text-xs text-purple-600 bg-purple-50 px-2 py-1 rounded">
                                        <?= date('H:i', strtotime($log['timestamp'])) ?>
                                    </span>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">
                                    <?= date('d M Y', strtotime($log['timestamp'])) ?>
                                </p>
                            </div>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <div class="px-6 py-4 text-center text-gray-500">
                                Tidak ada aktivitas terakhir
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="px-6 py-4 bg-purple-50 text-right">
                        <a href="system_logs.php" class="text-sm text-purple-600 hover:underline font-medium">Lihat semua aktivitas →</a>
                    </div>
                </div>
            </main>
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

        // Image upload preview
        document.getElementById('image-upload').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                const imgElement = document.querySelector('.profile-pic img');
                
                reader.onload = function(e) {
                    imgElement.src = e.target.result;
                }
                
                reader.readAsDataURL(this.files[0]);
            }
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