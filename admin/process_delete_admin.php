<?php
session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super_admin') {
    header("location:../service/login.php");
    exit();
}

// CSRF protection
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Token CSRF tidak valid";
    header("location: manage_admin.php");
    exit();
}

include '../service/connection.php';

$id = $_POST['id'];

// Delete admin
$stmt = $conn->prepare("DELETE FROM admin WHERE id = ? AND role = 'cashier'");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Kasir berhasil dihapus";
} else {
    $_SESSION['error'] = "Gagal menghapus kasir: " . $conn->error;
}

$stmt->close();
$conn->close();
header("location: manage_admin.php");
exit();
?>