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
$username = trim($_POST['username']);
$email = trim($_POST['email']);
$password = $_POST['password'];
$role = 'cashier'; // Fixed role as cashier

// Validate inputs
if (empty($username) || empty($email) || empty($password)) {
    $_SESSION['error'] = "Semua field wajib diisi";
    header("location: manage_admin.php");
    exit();
}

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM admin WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $_SESSION['error'] = "Email sudah terdaftar";
    header("location: manage_admin.php");
    exit();
}
$stmt->close();

// Handle file upload
$image = 'default.jpg';
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
        $image = $new_filename;
    }
}

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert new admin
$stmt = $conn->prepare("INSERT INTO admin (username, email, password, image, role, status) VALUES (?, ?, ?, ?, ?, 'pending')");
$stmt->bind_param("sssss", $username, $email, $hashed_password, $image, $role);

if ($stmt->execute()) {
    $_SESSION['success'] = "Kasir baru berhasil ditambahkan dan menunggu verifikasi";
} else {
    $_SESSION['error'] = "Gagal menambahkan kasir: " . $conn->error;
}

$stmt->close();
$conn->close();
header("location: manage_admin.php");
exit();
?>