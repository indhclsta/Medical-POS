<?php
session_start();
include '../service/connection.php';

if (!isset($_SESSION['email'])) {
    header("location: ../service/login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Permintaan tidak valid.");
}

$id = intval($_GET['id']);
$current_email = $_SESSION['email'];
$current_role = $_SESSION['role'];

// Ambil data admin yang akan dihapus
$stmt = $conn->prepare("SELECT email, role FROM admin WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin) {
    die("Admin tidak ditemukan.");
}

// Cek izin penghapusan
if ($current_role !== 'super_admin') {
    die("Hanya Super Admin yang bisa menghapus admin.");
}

if ($admin['email'] === $current_email) {
    die("Tidak bisa menghapus akun sendiri.");
}

if ($admin['role'] === 'super_admin') {
    die("Tidak bisa menghapus Super Admin.");
}

// Lakukan penghapusan
$delete_stmt = $conn->prepare("DELETE FROM admin WHERE id = ?");
$delete_stmt->bind_param("i", $id);

if ($delete_stmt->execute()) {
    $_SESSION['success'] = "Admin berhasil dihapus!";
} else {
    $_SESSION['error'] = "Gagal menghapus admin.";
}

$delete_stmt->close();
$conn->close();

header("Location: admin.php");
exit();
?>