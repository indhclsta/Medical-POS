<?php
session_start();
include '../service/connection.php';


$error = '';
$success = '';

if (isset($_POST['add_admin'])) {
    // Validasi dan sanitasi input
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $username = htmlspecialchars(strip_tags($_POST['username']));
    $password_plain = $_POST['password'];
    $role = 'cashier'; // Default role adalah kasir
    $status = 'pending'; // Default status pending untuk verifikasi

    // Validasi email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
    } elseif (strlen($password_plain) < 8) {
        $error = "Password harus minimal 8 karakter!";
    } else {
        // Cek email unik menggunakan prepared statement
        $check = $conn->prepare("SELECT id FROM admin WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        
        if ($check->num_rows > 0) {
            $error = "Email sudah digunakan!";
        } else {
            // Handle upload gambar dengan lebih aman
            $uploadDir = "../uploads/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $imageName = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
                $file_type = $_FILES['image']['type'];
                $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                
                // Validasi tipe file
                if (!array_key_exists($file_type, $allowed_types) || !in_array($file_ext, $allowed_types)) {
                    $error = "Hanya file gambar (JPEG, PNG, GIF) yang diizinkan!";
                } else {
                    // Generate nama file unik
                    $imageName = uniqid('admin_', true) . '.' . $allowed_types[$file_type];
                    $target = $uploadDir . $imageName;
                    
                    if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                        $error = "Gagal mengupload gambar!";
                    }
                }
            } else {
                $imageName = 'default.jpg';
            }

            if (empty($error)) {
                // Hash password
                $password = password_hash($password_plain, PASSWORD_DEFAULT);
                
                // Insert data dengan prepared statement
                $sql = "INSERT INTO admin (email, username, password, image, role, status) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssss", $email, $username, $password, $imageName, $role, $status);
                
                if ($stmt->execute()) {
                    $success = "Admin berhasil ditambahkan! Menunggu verifikasi.";
                    $_POST = array(); // Reset form
                    
                    // Kirim notifikasi ke Super Admin
                    $notifMessage = "Admin baru membutuhkan verifikasi:\n\n";
                    $notifMessage .= "Username: $username\n";
                    $notifMessage .= "Email: $email\n";
                    // mail('superadmin@example.com', 'Verifikasi Admin Baru', $notifMessage);
                } else {
                    $error = "Gagal menyimpan data ke database: " . $conn->error;
                }
                $stmt->close();
            }
        }
        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Admin - SmartCash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <style>
        .font-outfit { font-family: 'Outfit', sans-serif; }
        .font-poppins { font-family: 'Poppins', sans-serif; }
        .bg-custom { background-color: #F1F9E4; }
        .text-primary { color: #779341; }
        .btn-primary {
            background-color: #779341;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #5e762f;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .form-label { color: #4A5568; }
        .form-input { 
            border: 1px solid #D1D5DB;
            transition: border-color 0.3s ease;
        }
        .form-input:focus {
            border-color: #779341;
            outline: none;
            box-shadow: 0 0 0 3px rgba(119, 147, 65, 0.2);
        }
        .error-message {
            color: #DC2626;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        #previewImage {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-custom font-outfit">
    <div class="flex min-h-screen">
        <!-- Bagian Form -->
        <div class="w-full md:w-1/2 flex flex-col items-center justify-center p-4">
            <!-- Notifikasi -->
            <?php if (!empty($error)): ?>
                <div class="w-full max-w-md mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="w-full max-w-md mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-lg shadow-lg p-8 w-full max-w-md">
                <div class="header text-center mb-6">
                    <h1 class="text-4xl font-bold font-poppins">
                        Smart <span class="text-primary">Cash</span>
                    </h1>
                </div>
                
                <h2 class="text-xl font-bold mb-2 font-poppins text-center">Tambah Admin Baru</h2>
                <p class="text-gray-600 text-center mb-6">Isi form berikut untuk menambahkan admin</p>
                
                <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label class="block form-label mb-1 font-medium">Email</label>
                        <input type="email" name="email" 
                               class="w-full px-3 py-2 border rounded-lg form-input" 
                               placeholder="contoh@email.com" 
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                               required>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block form-label mb-1 font-medium">Username</label>
                            <input type="text" name="username" 
                                   class="w-full px-3 py-2 border rounded-lg form-input" 
                                   placeholder="Username" 
                                   value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>"
                                   required>
                        </div>
                        <div>
                            <label class="block form-label mb-1 font-medium">Password</label>
                            <input type="password" name="password" 
                                   class="w-full px-3 py-2 border rounded-lg form-input" 
                                   placeholder="Minimal 8 karakter" 
                                   required>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block form-label mb-1 font-medium">Foto Profil</label>
                        <div class="flex items-center space-x-4">
                            <div class="flex-1">
                                <input type="file" name="image" 
                                       class="w-full px-3 py-2 border rounded-lg form-input" 
                                       accept="image/jpeg, image/png, image/gif"
                                       onchange="previewImage(this)">
                            </div>
                            <div class="w-16 h-16 rounded-full border-2 border-gray-300 overflow-hidden">
                                <img id="previewImage" src="../uploads/default.jpg" alt="Preview" class="w-full h-full object-cover">
                            </div>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Format: JPEG, PNG, GIF (Maks. 2MB)</p>
                    </div>
                    
                    <button type="submit" name="add_admin" class="w-full py-3 rounded-lg btn-primary font-medium mt-6">
                        <i class="fas fa-user-plus mr-2"></i> Tambah Admin
                    </button>
                    
                    <div class="text-center mt-4">
                        <a href="admin.php" class="text-primary hover:underline inline-flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i> Kembali ke Daftar Admin
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bagian Gambar (Desktop Only) -->
        <div class="hidden md:flex md:w-1/2 bg-[#779341] items-center justify-center relative">
            <div class="text-center p-8">
                <img id="sidePreviewImage" src="../uploads/default.jpg" 
                     class="w-64 h-64 object-cover rounded-full border-4 border-white shadow-lg mb-6">
                <h3 class="text-xl font-bold text-white mb-2">Tambahkan Admin Baru</h3>
                <p class="text-white/90">Admin baru akan memiliki status <span class="font-semibold">"Pending"</span> hingga diverifikasi</p>
            </div>
        </div>
    </div>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('previewImage');
            const sidePreview = document.getElementById('sidePreviewImage');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    if (sidePreview) sidePreview.src = e.target.result;
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>