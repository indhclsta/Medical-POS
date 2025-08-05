<?php
session_start();
require '../service/connection.php';

// Cek role super admin
if ($_SESSION['role'] !== 'super_admin') {
    header("Location: admin.php");
    exit();
}

// Validasi ID admin
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "ID admin tidak valid";
    header("Location: admin.php");
    exit();
}

$admin_id = (int)$_GET['id'];

// Cek apakah form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        // Update status jadi 'active'
        $stmt = $conn->prepare("UPDATE admin SET status='active', verified_by=?, verified_at=NOW() WHERE id=?");
        $stmt->bind_param("ii", $_SESSION['id'], $admin_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Admin berhasil diaktifkan!";
            
            // Kirim notifikasi ke admin yang diverifikasi
            $admin = $conn->query("SELECT email FROM admin WHERE id=$admin_id")->fetch_assoc();
            $to = $admin['email'];
            $subject = "Akun Anda Telah Aktif";
            $message = "Akun Anda sudah dapat digunakan untuk login:\n\nEmail: $to";
            mail($to, $subject, $message);
        } else {
            $_SESSION['error'] = "Gagal memverifikasi admin";
        }
    } elseif ($action === 'reject') {
        // Update status jadi 'inactive'
        $stmt = $conn->prepare("UPDATE admin SET status='inactive' WHERE id=?");
        $stmt->bind_param("i", $admin_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Admin berhasil dinonaktifkan!";
        } else {
            $_SESSION['error'] = "Gagal menonaktifkan admin";
        }
    }
    
    header("Location: admin.php");
    exit();
}

// Ambil data admin untuk ditampilkan di form
$admin = $conn->query("SELECT * FROM admin WHERE id=$admin_id")->fetch_assoc();
if (!$admin) {
    $_SESSION['error'] = "Admin tidak ditemukan";
    header("Location: admin.php");
    exit();
}
?>
<html>
<head>
    <title>Verifikasi Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <h1 class="text-2xl font-bold mb-4">Verifikasi Admin</h1>
        
        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="mb-4">
                <img src="../uploads/<?= htmlspecialchars($admin['image'] ?? 'default.jpg') ?>" 
                     class="w-20 h-20 rounded-full mx-auto">
            </div>
            
            <div class="mb-4">
                <p><strong>Username:</strong> <?= htmlspecialchars($admin['username']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($admin['email']) ?></p>
                <p><strong>Tanggal Daftar:</strong> <?= htmlspecialchars($admin['created_at']) ?></p>
            </div>
            
            <form method="POST">
                <div class="mb-4">
                    <label class="block mb-2">
                        <input type="radio" name="action" value="approve" checked> 
                        Setujui (Aktifkan Akun)
                    </label>
                    <label class="block">
                        <input type="radio" name="action" value="reject"> 
                        Tolak (Nonaktifkan Akun)
                    </label>
                </div>
                
                <button type="submit" 
                        class="bg-green-500 text-white px-4 py-2 rounded">
                    Proses Verifikasi
                </button>
                <a href="admin.php" class="bg-gray-500 text-white px-4 py-2 rounded ml-2">
                    Batal
                </a>
            </form>
        </div>
    </div>
</body>
</html>