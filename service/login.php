<?php
require_once 'connection.php';

// Calculate base URL dynamically
$base_url = "http://" . $_SERVER['HTTP_HOST'] . str_replace('/service', '', dirname($_SERVER['SCRIPT_NAME'])) . '/';
$base_url = rtrim($base_url, '/');

// Start secure session
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_path' => '/',
        'cookie_secure' => false,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

// Redirect if already logged in
if (isset($_SESSION['logged_in'])) {
    $redirect = ($_SESSION['role'] == 'super_admin') 
              ? $base_url . '/admin/dashboard.php' 
              : $base_url . '/cashier/dashboard.php';
    header("Location: $redirect");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Input validation
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "Email dan password harus diisi!";
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

                // Set session data
                $_SESSION = [
                    'id' => $row['id'],
                    'email' => $row['email'],
                    'username' => $row['username'],
                    'role' => $row['role'],
                    'logged_in' => true,
                    'last_activity' => time()
                ];

                session_regenerate_id(true);

                // Redirect based on role with correct path
                $redirect = ($row['role'] == 'super_admin') 
                          ? $base_url . '/admin/dashboard.php' 
                          : $base_url . '/cashier/dashboard.php';
                header("Location: $redirect");
                exit();
            }
        }
        
        $_SESSION['error'] = "Email atau password salah!";
        header("Location: " . $base_url . "/service/login.php");
        exit();

    } catch (Exception $e) {
        error_log($e->getMessage());
        $_SESSION['error'] = "Terjadi kesalahan sistem";
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
            transition: background-color 0.3s ease;
        }
        .btn-primary:hover { 
            background-color: #5e762f; 
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .form-label { color: #4A5568; }
        .form-input { 
            border: 1px solid #D1D5DB;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-input:focus {
            border-color: #779341;
            box-shadow: 0 0 0 3px rgba(119, 147, 65, 0.2);
            outline: none;
        }
        .error-message {
            color: #DC2626;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body class="bg-custom font-outfit">
    <div class="flex min-h-screen">
        <div class="w-full md:w-1/2 flex flex-col items-center justify-center container">
            <div class="header">
                <h1 class="text-4xl font-bold font-poppins">Smart <span class="text-primary">Cash</span></h1>
            </div>
            <div class="bg-white rounded-lg shadow-lg p-8 w-full max-w-md mt-16 form-container">
                <h2 class="text-xl font-bold mb-6 font-poppins">Welcome to <span class="text-primary">SmartCash</span></h2>
                <h3 class="text-3xl font-bold mb-6 font-poppins">Sign In</h3>
                
                <!-- Tampilkan pesan error jika ada -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                        <?php 
                        echo $_SESSION['error']; 
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <form action="login.php" method="POST" autocomplete="off">
                    <div class="form-group mb-4">
                        <label class="block form-label mb-2" for="email">Email</label>
                        <input class="w-full px-3 py-2 border rounded-lg form-input" 
                               id="email" 
                               name="email" 
                               placeholder="Masukkan email" 
                               type="email" 
                               required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"/>
                    </div>
                    <div class="form-group mb-4">
                        <label class="block form-label mb-2" for="password">Password</label>
                        <input class="w-full px-3 py-2 border rounded-lg form-input" 
                               id="password" 
                               name="password" 
                               placeholder="Masukkan password" 
                               type="password" 
                               required/>
                    </div>
                    <div class="text-right mb-4">
                        <a href="forgot.php" class="text-primary text-sm hover:underline">Lupa Password?</a>
                    </div>
                    <button type="submit" class="w-full py-2 rounded-lg btn-primary font-semibold">
                        <i class="fas fa-sign-in-alt mr-2"></i> Masuk
                    </button>
                </form>
            </div>
        </div>
        <div class="hidden md:flex md:w-1/2 bg-primary items-center justify-center relative rounded-tl-[50px] bg-[#779341]">
            <div class="illustration">
                <img src="../image/icon.png" alt="SmartCash Icon" class="w-full h-auto max-w-md" width="400" height="400"/>
            </div>
        </div>
    </div>
</body>
</html>