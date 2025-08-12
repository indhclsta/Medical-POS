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
    <title>MediPOS - Profile Kasir</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background-color: #1E1B2E;
            font-family: 'Inter', sans-serif;
        }
        .sidebar {
            background: linear-gradient(180deg, #2A2540 0%, #1E1B2E 100%);
            border-right: 1px solid #3B3360;
        }
        .nav-item {
            transition: all 0.2s ease;
            border-radius: 0.5rem;
        }
        .nav-item:hover {
            background-color: rgba(155, 135, 245, 0.1);
        }
        .nav-item.active {
            background-color: #9B87F5;
            color: white;
        }
        .nav-item.active:hover {
            background-color: #8A75E5;
        }
        .profile-card {
            background: linear-gradient(135deg, #2A2540 0%, #3B3360 100%);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .input-field {
            background-color: #2A2540;
            border: 1px solid #3B3360;
            transition: all 0.3s ease;
        }
        .input-field:focus {
            border-color: #9B87F5;
            box-shadow: 0 0 0 2px rgba(155, 135, 245, 0.3);
        }
    </style>
</head>
<body class="text-gray-200">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="sidebar w-64 flex flex-col p-5 space-y-8">
            <!-- Logo -->
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-lg bg-purple-500 flex items-center justify-center">
                    <span class="material-icons text-white">local_pharmacy</span>
                </div>
                <h1 class="text-xl font-bold text-purple-300">MediPOS</h1>
            </div>
            
            <!-- Navigation -->
           <nav class="flex-1 flex flex-col space-y-2">
                <a href="dashboard.php" class="nav-item active flex items-center p-3 space-x-3">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="transaksi.php" class="nav-item flex items-center p-3 space-x-3">
                    <span class="material-icons">point_of_sale</span>
                    <span>Transaksi</span>
                </a>
                <a href="manage_member.php" class="nav-item flex items-center p-3 space-x-3">
                    <span class="material-icons">people</span>
                    <span>Member</span>
                </a>
                <a href="reports.php" class="nav-item flex items-center p-3 space-x-3">
                    <span class="material-icons">insert_chart</span>
                    <span>Laporan</span>
                </a>
            </nav>
            
            <!-- User & Logout -->
            <div class="mt-auto">
                <div class="flex items-center p-3 space-x-3 rounded-lg bg-[#3B3360]">
                    <?php if (!empty($profilePicture) && file_exists("../uploads/" . $profilePicture)): ?>
                        <img src="../uploads/<?php echo $profilePicture; ?>" class="w-10 h-10 rounded-full object-cover">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center">
                            <span class="material-icons">person</span>
                        </div>
                    <?php endif; ?>
                    <div class="flex-1">
                        <p class="font-medium"><?php echo $_SESSION['username']; ?></p>
                        <p class="text-xs text-purple-300">Kasir</p>
                    </div>
                    <a href="../service/logout.php" class="text-red-400 hover:text-red-300 transition">
                        <span class="material-icons">logout</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8 overflow-y-auto">
            <div class="max-w-4xl mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h2 class="text-2xl font-bold text-white">Profile Kasir</h2>
                        <p class="text-purple-300">Kelola informasi akun Anda</p>
                    </div>
                </div>
                
                <?php if ($updateSuccess): ?>
                <div class="mb-6 p-4 bg-green-900 bg-opacity-30 text-green-400 rounded-lg flex items-center">
                    <span class="material-icons mr-2">check_circle</span>
                    Profile berhasil diperbarui!
                </div>
                <?php endif; ?>
                
                <?php if ($uploadSuccess): ?>
                <div class="mb-6 p-4 bg-green-900 bg-opacity-30 text-green-400 rounded-lg flex items-center">
                    <span class="material-icons mr-2">check_circle</span>
                    Foto profil berhasil diubah!
                </div>
                <?php endif; ?>
                
                <?php if (!empty($uploadError)): ?>
                <div class="mb-6 p-4 bg-red-900 bg-opacity-30 text-red-400 rounded-lg flex items-center">
                    <span class="material-icons mr-2">error</span>
                    <?php echo $uploadError; ?>
                </div>
                <?php endif; ?>
                
                <!-- Profile Card -->
                <div class="profile-card p-6 rounded-xl">
                    <div class="flex flex-col md:flex-row gap-8">
                        <!-- Avatar Section -->
                        <div class="md:w-1/3 flex flex-col items-center">
                            <div class="relative mb-4">
                                <?php if (!empty($profilePicture) && file_exists("../uploads/" . $profilePicture)): ?>
                                    <img src="../uploads/<?php echo $profilePicture; ?>" 
                                         class="w-32 h-32 rounded-full object-cover border-2 border-purple-500">
                                <?php else: ?>
                                    <div class="w-32 h-32 rounded-full bg-purple-500 bg-opacity-20 flex items-center justify-center">
                                        <span class="material-icons text-6xl text-purple-400">person</span>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Upload Button -->
                                <form method="POST" enctype="multipart/form-data" class="absolute bottom-0 right-0">
                                    <label for="profile-upload" class="cursor-pointer">
                                        <div class="bg-purple-500 hover:bg-purple-600 p-2 rounded-full flex items-center justify-center">
                                            <span class="material-icons text-white text-sm">edit</span>
                                        </div>
                                    </label>
                                    <input id="profile-upload" type="file" name="profile_picture" class="hidden" 
                                           accept="image/jpeg, image/png" onchange="this.form.submit()">
                                </form>
                            </div>
                            
                            <h3 class="text-xl font-bold text-white mb-1"><?php echo $userData['username']; ?></h3>
                            <p class="text-sm text-purple-300 mb-4">Kasir</p>
                            <div class="w-full bg-[#3B3360] h-px my-4"></div>
                            <p class="text-sm text-gray-400 text-center">
                                Bergabung sejak <?php echo date('d M Y', strtotime($userData['created_at'])); ?>
                            </p>
                        </div>
                        
                        <!-- Form Section -->
                        <div class="md:w-2/3">
                            <form method="POST" action="">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-purple-300 mb-1">Username</label>
                                        <input 
                                            type="text" 
                                            name="username" 
                                            value="<?php echo htmlspecialchars($userData['username']); ?>" 
                                            class="input-field w-full px-4 py-2 rounded-lg focus:outline-none"
                                            required
                                        >
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-purple-300 mb-1">Email</label>
                                        <input 
                                            type="email" 
                                            name="email" 
                                            value="<?php echo htmlspecialchars($userData['email']); ?>" 
                                            class="input-field w-full px-4 py-2 rounded-lg focus:outline-none"
                                            required
                                        >
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-purple-300 mb-1">Password Baru</label>
                                        <input 
                                            type="password" 
                                            name="password" 
                                            placeholder="Kosongkan jika tidak ingin mengubah"
                                            class="input-field w-full px-4 py-2 rounded-lg focus:outline-none"
                                        >
                                        <p class="text-xs text-gray-500 mt-1">Minimal 8 karakter</p>
                                    </div>
                                    
                                    <div class="pt-4">
                                        <button 
                                            type="submit" 
                                            name="update_profile"
                                            class="bg-purple-500 hover:bg-purple-600 text-white px-6 py-2 rounded-lg flex items-center space-x-2 transition"
                                        >
                                            <span class="material-icons">save</span>
                                            <span>Simpan Perubahan</span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Log -->
                <div class="mt-8">
                    <h3 class="text-lg font-semibold text-white mb-4">Aktivitas Terakhir</h3>
                    <div class="space-y-3">
                        <div class="bg-[#2A2540] p-4 rounded-lg flex items-start">
                            <div class="bg-purple-500 bg-opacity-20 p-2 rounded-full mr-3">
                                <span class="material-icons text-purple-400 text-sm">login</span>
                            </div>
                            <div>
                                <p class="text-sm">Anda login ke sistem</p>
                                <p class="text-xs text-gray-500"><?php echo date('d M Y, H:i'); ?></p>
                            </div>
                        </div>
                        
                        <div class="bg-[#2A2540] p-4 rounded-lg flex items-start">
                            <div class="bg-blue-500 bg-opacity-20 p-2 rounded-full mr-3">
                                <span class="material-icons text-blue-400 text-sm">receipt</span>
                            </div>
                            <div>
                                <p class="text-sm">Melakukan transaksi #TRX-12345</p>
                                <p class="text-xs text-gray-500"><?php echo date('d M Y, H:i', strtotime('-1 hour')); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Google Icons -->
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</body>
</html>