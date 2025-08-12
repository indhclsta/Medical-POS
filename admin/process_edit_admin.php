
<?php
session_start();
// Check authentication
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super_admin') {
    header("location:../service/login.php");
    exit();
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Token CSRF tidak valid";
    header("location: manage_admin.php");
    exit();
}

include '../service/connection.php';

$id = $_POST['id'];
$username = trim($_POST['username']);

if (empty($username)) {
    $_SESSION['error'] = "Username wajib diisi";
    header("location: manage_admin.php");
    exit();
}

$stmt = $conn->prepare("UPDATE admin SET username = ? WHERE id = ?");
$stmt->bind_param("si", $username, $id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Username kasir berhasil diperbarui";
} else {
    $_SESSION['error'] = "Gagal memperbarui username kasir: " . $conn->error;
}

$stmt->close();
$conn->close();
header("location: manage_admin.php");
exit();
?>