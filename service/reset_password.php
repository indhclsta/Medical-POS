<?php
session_start();
require 'connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['token'], $_POST['new_password'], $_POST['confirm_password'])) {
    $token = $_POST['token'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        echo "<script>alert('Password tidak cocok!'); window.location.href='reset_password.php?token=" . htmlspecialchars($token) . "';</script>";
        exit;
    }

    // Hash password baru
    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

    // Cek token di database
    $now = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("SELECT id FROM admin WHERE reset_token = ? AND reset_expiry > ?");
    $stmt->bind_param("ss", $token, $now);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        die("Token tidak ditemukan atau expired.<br>Token yang digunakan: $token");
    }

    $row = $result->fetch_assoc();
    $admin_id = $row['id'];

    // Update password dan hapus token
    $stmt = $conn->prepare("UPDATE admin SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $admin_id);

    if ($stmt->execute()) {
        echo "<script>alert('Password berhasil diubah! Silakan login kembali.'); window.location.href='login.php';</script>";
        exit;
    } else {
        die("Error saat update password: " . $stmt->error);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - SmartCash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&family=Poppins:wght@400;700&display=swap" rel="stylesheet"/>
    <style>
        .font-outfit { font-family: 'Outfit', sans-serif; }
        .font-poppins { font-family: 'Poppins', sans-serif; }
        .bg-custom { background-color: #F1F9E4; }
        .text-primary { color: #779341; }
        .btn-primary { background-color: #779341; color: white; }
        .btn-primary:hover { background-color: #5e762f; }
        .form-label { color: #4A5568; }
        .form-input { border-color: #D1D5DB; }
    </style>
</head>
<body class="bg-custom font-outfit">
    <div class="flex min-h-screen">
        <div class="w-full md:w-1/2 flex flex-col items-center justify-center container">
            <div class="header">
                <h1 class="text-4xl font-bold font-poppins">Smart <span class="text-primary">Cash</span></h1>
            </div>
            <div class="bg-white rounded-lg shadow-lg p-8 w-full max-w-md mt-16 form-container">
                <h2 class="text-xl font-bold mb-6 font-poppins">Reset <span class="text-primary">Password</span></h2>
                <form method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                    <div class="form-group">
                        <label class="block form-label mb-2" for="new_password">New Password</label>
                        <input class="w-full px-3 py-2 border rounded-lg form-input" id="new_password" name="new_password" type="password" required/>
                    </div>
                    <div class="form-group">
                        <label class="block form-label mb-2" for="confirm_password">Confirm Password</label>
                        <input class="w-full px-3 py-2 border rounded-lg form-input" id="confirm_password" name="confirm_password" type="password" required/>
                    </div>
                    <button type="submit" class="w-full py-2 rounded-lg btn-primary transition duration-300">Change Password</button>
                </form>
            </div>
        </div>
        <div class="hidden md:flex md:w-1/2 bg-primary items-center justify-center relative rounded-tl-[50px] bg-[#779341]">
            <div class="illustration">
            <img src="../image/icon.png" alt="Icon" class="w-full h-auto" height="400" src="icon.png" width="400"/>
            </div>
        </div>
    </div>
</body>
</html>
