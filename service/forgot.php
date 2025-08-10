<?php
error_reporting(0);
session_start();
require "connection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    $stmt = $conn->prepare("SELECT * FROM admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $token = bin2hex(random_bytes(50));
        $exp_time = date("Y-m-d H:i:s", strtotime("+1 hour"));

        $stmt = $conn->prepare("UPDATE admin SET reset_token = ?, reset_expiry = ? WHERE email = ?");
        $stmt->bind_param("sss", $token, $exp_time, $email);
        
        if (!$stmt->execute()) {
            $_SESSION['error'] = "Gagal menyimpan token reset.";
            header("Location: forgot.php");
            exit;
        }

        $reset_link = "http://localhost/kasir_apotek/service/reset_password.php?token=" . urlencode($token);

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'indahcalistaexcella@gmail.com';
            $mail->Password = 'yghy ebab lfnq nyht';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            
            $mail->setFrom('indahcalistaexcella@gmail.com', 'Admin');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = "Reset Password - MediPOS";
            $mail->Body = "
                <h2>Reset Password MediPOS</h2>
                <p>Silakan klik link berikut untuk reset password Anda:</p>
                <p><a href='$reset_link' style='background-color: #7C3AED; color: white; padding: 10px 15px; border-radius: 5px;'>Reset Password</a></p>
                <p>Atau salin dan buka link ini: <br>$reset_link</p>
                <p>Link berlaku 1 jam.</p>
            ";
            $mail->AltBody = "Reset password link: $reset_link";

            if ($mail->send()) {
                $_SESSION['success'] = "Email reset password telah dikirim ke $email.";
                header("Location: forgot.php");
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Gagal mengirim email reset.";
            header("Location: forgot.php");
            exit;
        }
    } else {
        $_SESSION['error'] = "Email tidak ditemukan dalam sistem.";
        header("Location: forgot.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - MediPOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&family=Poppins:wght@400;700&display=swap" rel="stylesheet">
    <style>
        .font-outfit { font-family: 'Outfit', sans-serif; }
        .font-poppins { font-family: 'Poppins', sans-serif; }
        .bg-custom { background-color: #F3E8FF; }
        .text-primary { color: #7C3AED; }
        .btn-primary {
            background-color: #7C3AED;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            background-color: #6B21A8;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .form-input:focus {
            border-color: #7C3AED;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2);
        }
    </style>
</head>
<body class="bg-custom font-outfit">
    <div class="flex min-h-screen items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-4xl font-bold text-center mb-2 font-poppins">Medi<span class="text-primary">POS</span></h1>
            <h2 class="text-xl text-center font-semibold mb-6 font-poppins">Lupa Password</h2>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                    <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="mb-4 p-3 bg-green-100 border border-green-400 text-green-700 rounded">
                    <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <div class="mb-4">
                    <label for="email" class="block mb-2 text-gray-600">Email</label>
                    <input type="email" id="email" name="email"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none form-input"
                           placeholder="Masukkan email Anda" required>
                </div>
                <button type="submit" class="w-full py-2 rounded-lg btn-primary text-white font-semibold">
                    <i class="fas fa-paper-plane mr-2"></i> Kirim Link Reset
                </button>
            </form>

            <div class="mt-4 text-center">
                <a href="login.php" class="text-primary hover:underline text-sm">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>
