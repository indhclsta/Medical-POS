<?php
session_start();

// Check authentication
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

// Get form data
$id = $_POST['id'];
$username = trim($_POST['username']);
$email = trim($_POST['email']);
$password = $_POST['password'];
$role = 'cashier'; // Fixed role as cashier
$status = $_POST['status'];

// Validate inputs
if (empty($username) || empty($email)) {
    $_SESSION['error'] = "Username dan email wajib diisi";
    header("location: manage_admin.php");
    exit();
}

// Check if email already exists (excluding current admin)
$stmt = $conn->prepare("SELECT id FROM admin WHERE email = ? AND id != ?");
$stmt->bind_param("si", $email, $id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $_SESSION['error'] = "Email sudah digunakan oleh admin lain";
    header("location: manage_admin.php");
    exit();
}
$stmt->close();

// Get current admin data
$stmt = $conn->prepare("SELECT image FROM admin WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$current_data = $result->fetch_assoc();
$stmt->close();

$image = $current_data['image'];

// Handle file upload if new image is provided
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $target_dir = "../uploads/";
    $file_ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $file_ext;
    $target_file = $target_dir . $new_filename;
    
    // Check if image file is a actual image
    $check = getimagesize($_FILES["image"]["tmp_name"]);
    if ($check === false) {
        $_SESSION['error'] = "File bukan gambar";
        header("location: manage_admin.php");
        exit();
    }
    
    // Check file size (max 2MB)
    if ($_FILES["image"]["size"] > 2000000) {
        $_SESSION['error'] = "Ukuran file terlalu besar (maks 2MB)";
        header("location: manage_admin.php");
        exit();
    }
    
    // Allow certain file formats
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($file_ext, $allowed_ext)) {
        $_SESSION['error'] = "Hanya file JPG, JPEG, PNG & GIF yang diperbolehkan";
        header("location: manage_admin.php");
        exit();
    }
    
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        // Delete old image if it's not the default
        if ($image !== 'default.jpg' && file_exists("../uploads/" . $image)) {
            unlink("../uploads/" . $image);
        }
        $image = $new_filename;
    }
}

// Prepare SQL query
if (!empty($password)) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE admin SET username = ?, email = ?, password = ?, image = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $username, $email, $hashed_password, $image, $status, $id);
} else {
    $stmt = $conn->prepare("UPDATE admin SET username = ?, email = ?, image = ?, status = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $username, $email, $image, $status, $id);
}

if ($stmt->execute()) {
    $_SESSION['success'] = "Data kasir berhasil diperbarui";
} else {
    $_SESSION['error'] = "Gagal memperbarui data kasir: " . $conn->error;
}

$stmt->close();
$conn->close();
header("location: manage_admin.php");
exit();
?>