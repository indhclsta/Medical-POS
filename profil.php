<?php
session_start();
include './service/connection.php';

// Cek login
if (!isset($_SESSION['id'])) {
    header('Location: ./service/login.php');
    exit;
}

$id = $_SESSION['id'];

// Ambil data user
$query = mysqli_query($conn, "SELECT * FROM admin WHERE id = $id");
$user = mysqli_fetch_assoc($query);
$image = $user['image'];

// Handle form update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = htmlspecialchars($_POST['username']);
    $email = htmlspecialchars($_POST['email']);

    // Upload foto
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $target_dir = "uploads/";
        $ext = pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $new_filename = "image_" . $id . "_" . time() . "." . $ext;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            if (!empty($image) && file_exists("uploads/" . $image)) {
                unlink("uploads/" . $image);
            }
            $image = $new_filename;
        }
    }

    // Update database
    mysqli_query($conn, "UPDATE admin SET username='$username', email='$email', image='$image' WHERE id=$id");

    // Update session
    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;

    header("Location: profil.php");
    exit;
}

// Avatar fallback
$avatar = (!empty($user['image']) && file_exists("uploads/" . $user['image']))
    ? "uploads/" . $user['image']
    : "https://ui-avatars.com/api/?name=" . urlencode($user['username']) . "&background=779341&color=fff";
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Profil - SmartCash</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
</head>
<body class="bg-[#F1F9E4] text-[#1F2937]">

<!-- Header -->
<div class="p-4 flex items-center justify-between shadow">
    <div class="flex items-center space-x-4">
        <!-- Tombol Back -->
        <a href="javascript:history.back()" class="text-[#779341] hover:text-[#5c742c]">
            <i class="fas fa-arrow-left text-2xl"></i>
        </a>
        <h1 class="text-2xl font-bold">Smart <span class="text-[#779341]">Cash</span></h1>
    </div>
    <div class="flex items-center space-x-3">
        <span><?= htmlspecialchars($_SESSION['username']) ?></span>
        <img src="<?= $avatar ?>" class="w-10 h-10 rounded-full object-cover border-2 border-[#779341]">
        <a href="./service/logout.php" class="text-red-600 hover:text-red-800 text-sm font-semibold">
            <i class="fas fa-sign-out-alt mr-1"></i> Logout
        </a>
    </div>
</div>

<!-- Main Content -->
<div class="max-w-5xl mx-auto px-4 py-8">
    <h2 class="text-2xl font-bold mb-6">Profil Pengguna</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- Foto Profil -->
        <div class="bg-white rounded-xl p-6 shadow">
            <h3 class="text-lg font-semibold mb-4">Foto Profil</h3>
            <div class="flex flex-col items-center">
                <img src="<?= $avatar ?>" alt="Foto Profil" class="w-32 h-32 rounded-full object-cover mb-4">
                <p class="text-gray-500 text-sm text-center">Pilih foto baru jika ingin mengganti.</p>
            </div>
        </div>

        <!-- Form Update Profil -->
        <div class="bg-white rounded-xl p-6 shadow">
            <h3 class="text-lg font-semibold mb-4">Edit Informasi</h3>
            <form method="POST" enctype="multipart/form-data">
                <label class="block mb-2 text-sm font-medium">Username</label>
                <input type="text" name="username" required
                       value="<?= htmlspecialchars($user['username']) ?>"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 mb-4">

                <label class="block mb-2 text-sm font-medium">Email</label>
                <input type="email" name="email" required
                       value="<?= htmlspecialchars($user['email']) ?>"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 mb-4">

                <label class="block mb-2 text-sm font-medium">Foto Profil (Opsional)</label>
                <input type="file" name="image" accept="image/*"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 mb-4">

                <button type="submit"
                        class="w-full bg-[#779341] text-white font-semibold py-2 rounded-lg hover:bg-[#5e752b] transition">
                    Simpan Perubahan
                </button>
            </form>
        </div>

    </div>
</div>

</body>
</html>
