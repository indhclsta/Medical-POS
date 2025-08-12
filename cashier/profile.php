<?php 
require_once '../service/connection.php';
session_start();
if (!isset($_SESSION['email'])) {
    header("Location: ../service/login.php");
    exit();
}

// Get current user data
$userId = $_SESSION['id'];
$userQuery = "SELECT * FROM admin WHERE id = $userId";
$userResult = mysqli_query($conn, $userQuery);
$userData = mysqli_fetch_assoc($userResult);

// Handle profile picture upload
$profilePicture = $userData['image'] ?? 'default.jpg';
$uploadSuccess = false;
$uploadError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
    $targetDir = "../uploads/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $fileName = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
    $targetFile = $targetDir . $fileName;
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
    // Check if image file is a actual image
    $check = getimagesize($_FILES['profile_picture']['tmp_name']);
    if ($check !== false) {
        // Check file size (max 2MB)
        if ($_FILES['profile_picture']['size'] <= 2000000) {
            // Allow certain file formats
            if ($imageFileType == "jpg" || $imageFileType == "png" || $imageFileType == "jpeg") {
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetFile)) {
                    // Update database using 'image' column
                    $updateQuery = "UPDATE admin SET image = '$fileName' WHERE id = $userId";
                    if (mysqli_query($conn, $updateQuery)) {
                        $profilePicture = $fileName;
                        $uploadSuccess = true;
                        // Delete old picture if not default
                        if ($userData['image'] != 'default.jpg' && file_exists($targetDir . $userData['image'])) {
                            unlink($targetDir . $userData['image']);
                        }
                    } else {
                        $uploadError = "Gagal menyimpan ke database";
                    }
                } else {
                    $uploadError = "Gagal mengupload file";
                }
            } else {
                $uploadError = "Hanya file JPG, JPEG, PNG yang diizinkan";
            }
        } else {
            $uploadError = "Ukuran file terlalu besar (maksimal 2MB)";
        }
    } else {
        $uploadError = "File bukan gambar";
    }
}

// Handle form submission for profile update
$updateSuccess = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    
    // Check if password is being updated
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $updateQuery = "UPDATE admin SET username = '$username', email = '$email', password = '$password' WHERE id = $userId";
    } else {
        $updateQuery = "UPDATE admin SET username = '$username', email = '$email' WHERE id = $userId";
    }
    
    if (mysqli_query($conn, $updateQuery)) {
        $updateSuccess = true;
        // Refresh user data
        $userResult = mysqli_query($conn, $userQuery);
        $userData = mysqli_fetch_assoc($userResult);
        // Update session
        $_SESSION['username'] = $userData['username'];
        $_SESSION['email'] = $userData['email'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Kasir - MediPOS</title>
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
        .bg-cashier {
            background-color: #6b46c1;
        }
        .text-cashier {
            color: #6b46c1;
        }
        .nav-active {
            background-color: #805ad5;
        }
        .profile-card {
            border-left: 4px solid #6b46c1;
        }
        .input-field:focus {
            border-color: #6b46c1;
            box-shadow: 0 0 0 2px rgba(107, 70, 193, 0.3);
        }
        .activity-item {
            transition: all 0.2s ease;
        }
        .activity-item:hover {
            background-color: #f3f4f6;
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
                    <i class="fas fa-user-tie text-white"></i>
                </div>
                <div class="ml-3">
                    <p class="font-medium text-white"><?= htmlspecialchars($userData['username']) ?></p>
                    <p class="text-xs text-purple-200">Kasir</p>
                </div>
            </div>

            <nav class="mt-8">
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="transaksi.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-cash-register mr-3"></i>
                    Transaksi
                </a>
                <a href="manage_member.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-users mr-3"></i>
                    Kelola Member
                </a>
                <a href="reports.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Laporan
                </a>
                <a href="profile.php" class="flex items-center px-4 py-3 rounded-lg nav-active">
                    <i class="fas fa-user-circle mr-3"></i>
                    Profile
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
                    <h2 class="text-xl font-semibold text-gray-800">Profile Kasir</h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500" id="currentDateTime"></span>
                        <div class="relative">
                            <a href="profile.php">
                                <img src="<?= '../uploads/' . $profilePicture ?>" 
                                     alt="Profile" 
                                     class="w-8 h-8 rounded-full border-2 border-purple-500 cursor-pointer">
                                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full"></span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <!-- Success/Error Messages -->
                <?php if ($updateSuccess): ?>
                <div class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded-lg flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    Profile berhasil diperbarui!
                </div>
                <?php endif; ?>
                
                <?php if ($uploadSuccess): ?>
                <div class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded-lg flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    Foto profil berhasil diubah!
                </div>
                <?php endif; ?>
                
                <?php if (!empty($uploadError)): ?>
                <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded-lg flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= htmlspecialchars($uploadError) ?>
                </div>
                <?php endif; ?>

                <!-- Profile Card -->
                <div class="bg-white rounded-xl shadow-md p-6 mb-6 border-l-4 border-purple-600">
                    <div class="flex flex-col md:flex-row gap-8">
                        <!-- Avatar Section -->
                        <div class="md:w-1/3 flex flex-col items-center">
                            <div class="relative mb-4">
                                <?php if (!empty($profilePicture) && file_exists("../uploads/" . $profilePicture)): ?>
                                    <img src="../uploads/<?= $profilePicture ?>" 
                                         class="w-32 h-32 rounded-full object-cover border-2 border-purple-500">
                                <?php else: ?>
                                    <div class="w-32 h-32 rounded-full bg-purple-100 flex items-center justify-center">
                                        <i class="fas fa-user text-4xl text-purple-500"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Upload Button -->
                                <form method="POST" enctype="multipart/form-data" class="absolute bottom-0 right-0">
                                    <label for="profile-upload" class="cursor-pointer">
                                        <div class="bg-purple-600 hover:bg-purple-700 p-2 rounded-full flex items-center justify-center">
                                            <i class="fas fa-pencil-alt text-white text-xs"></i>
                                        </div>
                                    </label>
                                    <input id="profile-upload" type="file" name="profile_picture" class="hidden" 
                                           accept="image/jpeg, image/png" onchange="this.form.submit()">
                                </form>
                            </div>
                            
                            <h3 class="text-xl font-bold text-gray-800 mb-1"><?= htmlspecialchars($userData['username']) ?></h3>
                            <p class="text-sm text-purple-600 mb-4">Kasir</p>
                            <div class="w-full bg-gray-200 h-px my-4"></div>
                            <p class="text-sm text-gray-500 text-center">
                                Bergabung sejak <?= date('d M Y', strtotime($userData['created_at'])) ?>
                            </p>
                        </div>
                        
                        <!-- Form Section -->
                        <div class="md:w-2/3">
                            <form method="POST" action="">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Username</label>
                                        <input 
                                            type="text" 
                                            name="username" 
                                            value="<?= htmlspecialchars($userData['username']) ?>" 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none input-field"
                                            required
                                        >
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Email</label>
                                        <input 
                                            type="email" 
                                            name="email" 
                                            value="<?= htmlspecialchars($userData['email']) ?>" 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none input-field"
                                            required
                                        >
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-600 mb-1">Password Baru</label>
                                        <input 
                                            type="password" 
                                            name="password" 
                                            placeholder="Kosongkan jika tidak ingin mengubah"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none input-field"
                                        >
                                        <p class="text-xs text-gray-500 mt-1">Minimal 8 karakter</p>
                                    </div>
                                    
                                    <div class="pt-4">
                                        <button 
                                            type="submit" 
                                            name="update_profile"
                                            class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg flex items-center space-x-2 transition"
                                        >
                                            <i class="fas fa-save"></i>
                                            <span>Simpan Perubahan</span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Log -->
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Aktivitas Terakhir</h3>
                    <div class="space-y-3">
                        <div class="p-4 rounded-lg flex items-start activity-item border border-gray-100">
                            <div class="bg-purple-100 p-2 rounded-full mr-3">
                                <i class="fas fa-sign-in-alt text-purple-600 text-sm"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-700">Anda login ke sistem</p>
                                <p class="text-xs text-gray-500"><?= date('d M Y, H:i') ?></p>
                            </div>
                        </div>
                        
                       
                            
                        </div>
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