<?php
error_reporting(0); // Nonaktifkan error reporting untuk produksi
session_start();
include "connection.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    // Cek apakah email ada di database
    $stmt = $conn->prepare("SELECT * FROM admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $token = bin2hex(random_bytes(50)); // Token acak
        $exp_time = date("Y-m-d H:i:s", strtotime("+1 hour")); // Berlaku 1 jam

        // Simpan token ke database
        $stmt = $conn->prepare("UPDATE admin SET reset_token = ?, reset_expiry = ? WHERE email = ?");
        $stmt->bind_param("sss", $token, $exp_time, $email);
        
        if (!$stmt->execute()) {
            echo "<script>alert('Gagal menyimpan token reset. Silakan coba lagi.'); window.location.href='forgot.php';</script>";
            exit();
        }
        
        // Buat link reset
        $reset_link = "http://localhost/kasir/service/reset_password.php?token=" . urlencode($token);

        // Kirim Email dengan PHPMailer
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'indahcalistaexcella@gmail.com';
            $mail->Password = 'yghy ebab lfnq nyht';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;
            
            // Recipients
            $mail->setFrom('indahcalistaexcella@gmail.com', 'Admin');
            $mail->addAddress($email);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = "Reset Password - SmartCash";
            $mail->Body = "
                <h2>Reset Password SmartCash</h2>
                <p>Anda menerima email ini karena meminta reset password untuk akun SmartCash Anda.</p>
                <p>Silakan klik link berikut untuk mereset password Anda (link berlaku 1 jam):</p>
                <p><a href='$reset_link' style='background-color: #779341; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a></p>
                <p>Atau copy paste link berikut di browser Anda:<br>$reset_link</p>
                <p>Jika Anda tidak meminta reset password, abaikan email ini.</p>
            ";
            $mail->AltBody = "Silakan klik link berikut untuk mereset password Anda: $reset_link\n\nLink berlaku 1 jam.";

            if($mail->send()) {
                echo "<script>alert('Email reset password telah dikirim ke $email. Silakan cek inbox atau spam folder Anda.'); window.location.href='login.php';</script>";
                exit();
            }
        } catch (Exception $e) {
            echo "<script>alert('Gagal mengirim email reset. Silakan coba lagi.'); window.location.href='forgot.php';</script>";
            exit();
        }

        // Tutup koneksi
        $stmt->close();
        $conn->close();
    } else {
        echo "<script>alert('Email tidak ditemukan dalam sistem kami!'); window.location.href='forgot.php';</script>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - SmartCash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&family=Poppins:wght@400;700&display=swap" rel="stylesheet"/>
    <style>
        .font-outfit { font-family: 'Outfit', sans-serif; }
        .font-poppins { font-family: 'Poppins', sans-serif; }
        .bg-custom { background-color: #F1F9E4; }
        .text-primary { color: #779341; }
        .btn-primary { 
            background-color: #779341; 
            color: white;
            transition: all 0.3s ease;
        }
        .btn-primary:hover { 
            background-color: #5e762f;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .form-label { color: #4A5568; }
        .form-input { 
            border: 1px solid #D1D5DB;
            transition: all 0.3s ease;
        }
        .form-input:focus {
            border-color: #779341;
            box-shadow: 0 0 0 3px rgba(119, 147, 65, 0.2);
        }
        .illustration {
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
    </style>
</head>
<body class="bg-custom font-outfit">
    <div class="flex min-h-screen">
        <div class="w-full md:w-1/2 flex flex-col items-center justify-center container">
            <div class="header text-center">
                <h1 class="text-4xl font-bold font-poppins">Smart <span class="text-primary">Cash</span></h1>
            </div>
            <div class="bg-white rounded-lg shadow-lg p-8 w-full max-w-md mt-8 form-container transition-all duration-300 hover:shadow-xl">
                <h2 class="text-xl font-bold mb-6 font-poppins">Lupa <span class="text-primary">Password?</span></h2>
                <p class="mb-6 text-sm text-gray-600">Masukkan email Anda dan kami akan mengirimkan link untuk reset password.</p>
                <form method="POST">
                    <div class="form-group mb-4">
                        <label class="block form-label mb-2 font-medium" for="email">Alamat Email</label>
                        <input class="w-full px-3 py-2 border rounded-lg form-input focus:outline-none" 
                               id="email" name="email" 
                               placeholder="masukkan email anda" 
                               type="email" required
                               autocomplete="email"/>
                    </div>
                    <button type="submit" class="w-full py-2 mt-4 rounded-lg btn-primary font-medium">
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
        <div class="hidden md:flex md:w-1/2 bg-primary items-center justify-center relative rounded-tl-[50px] bg-[#779341]">
            <div class="illustration px-10">
                <img src="../image/icon.png" alt="SmartCash Illustration" class="w-full h-auto max-w-md"/>
            </div>
        </div>
    </div>
</body>
</html>