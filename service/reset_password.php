<?php
session_start();
require 'connection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['token'], $_POST['new_password'], $_POST['confirm_password'])) {
    $token = $_POST['token'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        $_SESSION['error'] = 'Password tidak cocok!';
        header("Location: reset_password.php?token=" . urlencode($token));
        exit;
    }

    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    $now = date("Y-m-d H:i:s");

    $stmt = $conn->prepare("SELECT id FROM admin WHERE reset_token = ? AND reset_expiry > ?");
    $stmt->bind_param("ss", $token, $now);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        $_SESSION['error'] = "Token tidak ditemukan atau sudah kedaluwarsa.";
        header("Location: reset_password.php");
        exit;
    }

    $row = $result->fetch_assoc();
    $admin_id = $row['id'];

    $stmt = $conn->prepare("UPDATE admin SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $admin_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Password berhasil diubah!';
        header("Location: login.php");
        exit;
    } else {
        $_SESSION['error'] = "Gagal mengubah password. Silakan coba lagi.";
        header("Location: reset_password.php?token=" . urlencode($token));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MediPOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&family=Poppins:wght@400;700&display=swap" rel="stylesheet">
    <style>
        .font-outfit {
            font-family: 'Outfit', sans-serif;
        }

        .font-poppins {
            font-family: 'Poppins', sans-serif;
        }

        .bg-custom {
            background-color: #F3E8FF;
        }

        .text-primary {
            color: #7C3AED;
        }

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
            <h2 class="text-xl text-center font-semibold mb-6 font-poppins">Reset Password</h2>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                    <?= htmlspecialchars($_SESSION['error']);
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">

                <div class="mb-4">
                    <label for="new_password" class="block mb-2 text-gray-600">Password Baru</label>
                    <div class="relative">
                        <input type="password" id="new_password" name="new_password"
                            class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none form-input"
                            placeholder="Masukkan password baru" required>
                        <span class="absolute inset-y-0 right-3 flex items-center cursor-pointer" onclick="togglePassword('new_password', 'eyeIconNew')">
                            <i id="eyeIconNew" class="fas fa-eye text-gray-500"></i>
                        </span>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="confirm_password" class="block mb-2 text-gray-600">Konfirmasi Password</label>
                    <div class="relative">
                        <input type="password" id="confirm_password" name="confirm_password"
                            class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none form-input"
                            placeholder="Ulangi password" required>
                        <span class="absolute inset-y-0 right-3 flex items-center cursor-pointer" onclick="togglePassword('confirm_password', 'eyeIconConfirm')">
                            <i id="eyeIconConfirm" class="fas fa-eye text-gray-500"></i>
                        </span>
                    </div>
                </div>

                <button type="submit" class="w-full py-2 rounded-lg btn-primary text-white font-semibold">
                    <i class="fas fa-lock mr-2"></i> Ubah Password
                </button>
            </form>
        </div>
    </div>
    <script>
        function togglePassword(inputId, iconId) {
            var passwordInput = document.getElementById(inputId);
            var eyeIcon = document.getElementById(iconId);
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>

</html>