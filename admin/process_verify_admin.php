<?php
session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'super_admin') {
    header("location:../service/login.php");
    exit();
}

// CSRF protection
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Token CSRF tidak valid";
    header("location: manage_admin.php");
    exit();
}

include '../service/connection.php';

$id = $_GET['id'];

// Verify admin
$stmt = $conn->prepare("UPDATE admin SET status = 'active' WHERE id = ? AND role = 'cashier'");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    $_SESSION['success'] = "Kasir berhasil diverifikasi";
} else {
    $_SESSION['error'] = "Gagal memverifikasi kasir: " . $conn->error;
}

$stmt->close();
$conn->close();
header("location: manage_admin.php");
exit();
?>