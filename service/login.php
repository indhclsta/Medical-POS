<?php
require_once 'connection.php';

// Calculate base URL dynamically
$base_url = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/service', '', dirname($_SERVER['SCRIPT_NAME']));
$base_url = rtrim($base_url, '/');

// Secure session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => false,    // Set to true in production with HTTPS
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true
    ]);
}

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

// Redirect if already logged in
if (isset($_SESSION['logged_in'])) {
    $redirect = ($_SESSION['role'] == 'super_admin') 
              ? $base_url . '/admin/dashboard.php' 
              : $base_url . '/cashier/dashboard.php';
    header("Location: $redirect");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Input validation
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Email dan password harus diisi!";
        header("Location: " . $base_url . "/service/login.php");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Format email tidak valid!";
        header("Location: " . $base_url . "/service/login.php");
        exit();
    }

    try {
        $stmt = $conn->prepare("SELECT id, email, username, password, role, status 
                               FROM admin WHERE email = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();

            if (password_verify($password, $row['password'])) {
                if ($row['status'] !== 'active') {
                    $_SESSION['error'] = "Akun Anda belum aktif!";
                    header("Location: " . $base_url . "/service/login.php");
                    exit();
                }

                // Set secure session data
                $_SESSION = [
                    'id' => $row['id'],
                    'email' => $row['email'],
                    'username' => $row['username'],
                    'role' => $row['role'],
                    'logged_in' => true,
                    'last_activity' => time(),
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT']
                ];

                session_regenerate_id(true);

                // Redirect based on role
                $redirect = ($row['role'] == 'super_admin') 
                          ? $base_url . '/admin/dashboard.php' 
                          : $base_url . '/cashier/dashboard.php';
                header("Location: $redirect");
                exit();
            }
        }
        
        // Log failed attempt
        error_log("Failed login attempt for email: " . $email . " - IP: " . $_SERVER['REMOTE_ADDR']);
        $_SESSION['error'] = "Email atau password salah!";
        header("Location: " . $base_url . "/service/login.php");
        exit();

    } catch (Exception $e) {
        error_log("Login system error: " . $e->getMessage() . " - IP: " . $_SERVER['REMOTE_ADDR']);
        $_SESSION['error'] = "Terjadi kesalahan sistem. Silakan coba lagi nanti.";
        header("Location: " . $base_url . "/service/login.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SmartCash</title>
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
            <h1 class="text-4xl font-bold text-center mb-2 font-poppins">Smart <span class="text-primary">Cash</span></h1>
            <h2 class="text-xl text-center font-semibold mb-6 font-poppins">Sign In</h2>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                    <?= htmlspecialchars($_SESSION['error']); ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" autocomplete="off">
                <div class="mb-4">
                    <label for="email" class="block mb-2 text-gray-600">Email</label>
                    <input type="email" id="email" name="email"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none form-input"
                           placeholder="Masukkan email"
                           required
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>
                <div class="mb-4">
                    <label for="password" class="block mb-2 text-gray-600">Password</label>
                    <input type="password" id="password" name="password"
                           class="w-full px-3 py-2 rounded-lg border border-gray-300 focus:outline-none form-input"
                           placeholder="Masukkan password"
                           required>
                </div>
                <div class="text-right mb-6">
                    <a href="forgot.php" class="text-primary text-sm hover:underline">Lupa Password?</a>
                </div>
                <button type="submit" class="w-full py-2 rounded-lg btn-primary text-white font-semibold">
                    <i class="fas fa-sign-in-alt mr-2"></i> Masuk
                </button>
            </form>
        </div>
    </div>
</body>
</html>