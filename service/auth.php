<?php
session_start();
include 'connection.php'; // koneksi ke DB

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $query = mysqli_query($conn, "SELECT * FROM admin WHERE email='$email' LIMIT 1");
    $user = mysqli_fetch_assoc($query);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // Redirect berdasarkan role
        if ($user['role'] === 'super_admin') {
            header("Location: ../admin/dashboard.php");
        } elseif ($user['role'] === 'cashier') {
            header("Location: ../cashier/dashboard.php");
        } else {
            echo "Role tidak dikenali.";
        }
    } else {
        echo "Login gagal. Email atau password salah.";
    }
}
?>
