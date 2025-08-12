<?php
session_start();
include '../service/connection.php';

// Security checks
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../service/login.php");
    exit();
}

// Set cart expiration time (5 minutes)
$cart_expiration = 300;

// Check if cart should be cleared
if (isset($_SESSION['cart_expiry'])) {
    if (time() > $_SESSION['cart_expiry']) {
        unset($_SESSION['cart']);
        unset($_SESSION['cart_expiry']);
        $_SESSION['error_message'] = "Keranjang belanja telah kadaluarsa karena tidak ada aktivitas selama 5 menit";
        header("Location: transaksi.php");
        exit();
    }
} else {
    header("Location: transaksi.php");
    exit();
}

// Update last activity time
$_SESSION['cart_expiry'] = time() + $cart_expiration;

// Initialize cart if not exists or empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    $_SESSION['error_message'] = "Keranjang belanja kosong";
    header("Location: transaksi.php");
    exit();
}

// Calculate total quantity and subtotal
$total_quantity = 0;
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $total_quantity += $item['quantity'] ?? 0;
    $subtotal += $item['price'] * $item['quantity'];
}

// Check product stock and validate cart items
$cart_modified = false;
foreach ($_SESSION['cart'] as $index => $item) {
    $stmt = $conn->prepare("SELECT qty FROM products WHERE id = ?");
    $stmt->bind_param("i", $item['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $product_db = $result->fetch_assoc();

    if (!$product_db || $product_db['qty'] <= 0) {
        unset($_SESSION['cart'][$index]);
        $cart_modified = true;
        $_SESSION['error_message'] = "Beberapa produk telah habis dan dihapus dari keranjang";
    } else {
        // Check if quantity in cart exceeds stock
        if ($item['quantity'] > $product_db['qty']) {
            $_SESSION['cart'][$index]['quantity'] = $product_db['qty'];
            $cart_modified = true;
            $_SESSION['error_message'] = "Jumlah beberapa produk dikurangi karena stok terbatas";
        }
        $_SESSION['cart'][$index]['stock'] = $product_db['qty'];
    }
}

if ($cart_modified) {
    header('Location: transaksi.php');
    exit();
}

// Handle item removal
if (isset($_GET['remove_from_cart'])) {
    $index = (int)$_GET['remove_from_cart'];
    if (isset($_SESSION['cart'][$index])) {
        array_splice($_SESSION['cart'], $index, 1);
        $_SESSION['success_message'] = "Produk berhasil dihapus dari keranjang";
    }
    header("Location: checkout.php");
    exit();
}

// Handle quantity update
if (isset($_GET['update_quantity'])) {
    $index = (int)$_GET['index'];
    $newQty = (int)$_GET['quantity'];
    
    if (isset($_SESSION['cart'][$index])) {
        $originalStock = $_SESSION['cart'][$index]['stock'];
        
        if ($newQty <= 0) {
            array_splice($_SESSION['cart'], $index, 1);
            $_SESSION['success_message'] = "Produk berhasil dihapus dari keranjang";
        } elseif ($newQty <= $originalStock) {
            $_SESSION['cart'][$index]['quantity'] = $newQty;
            $_SESSION['success_message'] = "Jumlah produk berhasil diupdate";
        } else {
            $_SESSION['error_message'] = "Jumlah melebihi stok awal! Stok tersedia saat ditambahkan: $originalStock";
        }
        
        // Reset cart timer on any cart activity
        $_SESSION['cart_expiry'] = time() + 300;
    }
    header("Location: checkout.php");
    exit();
}

// Member check
$member_status = "";
$discount = 0;
$member_points = 0;
if (isset($_GET['check_member'])) {
    $phone = $_GET['phone'];
    $stmt = $conn->prepare("SELECT * FROM member WHERE phone = ? AND status = 'active'");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $member = $result->fetch_assoc();
        $member_points = $member['point'];
        $member_status = "<p class='text-green-600 mt-1'>✅ Member terdaftar! (Poin: $member_points)</p>";

        if ($member_points >= 1000) $discount = 0.15;
        elseif ($member_points >= 500) $discount = 0.10;
        elseif ($member_points >= 200) $discount = 0.05;
    } else {
        $member_status = "<p class='text-red-600 mt-1'>❌ Nomor tidak terdaftar atau tidak aktif.</p>";
    }
}

// Calculate final total (rounded up)
$total_after_discount = ceil($subtotal * (1 - $discount));
$userId = $_SESSION['id'];
$userQuery = "SELECT username, image FROM admin WHERE id = $userId";
$userResult = mysqli_query($conn, $userQuery);
$userData = mysqli_fetch_assoc($userResult);
$profilePicture = $userData['image'] ?? 'default.jpg';

// Format currency
function format_currency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - MediPOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
        }
        .sidebar {
            background-color: #6b46c1;
            color: white;
        }
        .sidebar a:hover {
            background-color: #805ad5;
        }
        .stat-card {
            border-left: 4px solid #6b46c1;
        }
        .bg-cashier {
            background-color: #6b46c1;
        }
        .text-cashier {
            color: #6b46c1;
        }
        .nav-active {
            background-color: #805ad5;
        }
        .cart-timer {
            background-color: rgba(251, 191, 36, 0.2);
            color: #d97706;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
        }
        .timer-warning {
            background-color: rgba(239, 68, 68, 0.2);
            color: #dc2626;
        }
        .alert-box {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background-color: #6b46c1;
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            display: flex;
            align-items: center;
            animation: slideIn 0.5s, fadeOut 0.5s 2.5s forwards;
        }
        @keyframes slideIn {
            from {right: -300px; opacity: 0;}
            to {right: 20px; opacity: 1;}
        }
        @keyframes fadeOut {
            from {opacity: 1;}
            to {opacity: 0;}
        }
        .input-money {
            transition: all 0.3s ease;
            border: 1px solid #d1d5db;
        }
        .input-money:focus {
            border-color: #6b46c1;
            box-shadow: 0 0 0 2px rgba(107, 70, 193, 0.2);
        }
        .valid {
            border-color: #10b981;
            background-color: rgba(16, 185, 129, 0.05);
        }
        .invalid {
            border-color: #ef4444;
            background-color: rgba(239, 68, 68, 0.05);
        }
        .cart-item {
            display: flex;
            padding: 12px;
            border-radius: 8px;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
            margin-bottom: 8px;
        }
        .quantity-control {
            display: flex;
            align-items: center;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            overflow: hidden;
            background-color: white;
        }
        .quantity-btn {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f3f4f6;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s;
            color: #4b5563;
        }
        .quantity-btn:hover {
            background-color: #e5e7eb;
        }
        .quantity-input {
            width: 40px;
            text-align: center;
            border-left: 1px solid #d1d5db;
            border-right: 1px solid #d1d5db;
            font-weight: 600;
            background-color: white;
        }
        .payment-method {
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .payment-method:hover {
            border-color: #6b46c1;
        }
        .payment-method.selected {
            border-color: #6b46c1;
            background-color: rgba(107, 70, 193, 0.05);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="sidebar w-64 px-4 py-8 shadow-lg fixed h-full">
            <div class="flex items-center justify-center mb-8">
                <h1 class="text-2xl font-bold">
                    <span class="text-white">Medi</span><span class="text-purple-300">POS</span>
                </h1>
            </div>
            
            <div class="flex items-center px-4 py-3 mb-6 rounded-lg bg-purple-900">
                <div class="w-10 h-10 rounded-full bg-purple-700 flex items-center justify-center">
                    <i class="fas fa-user-tie text-white"></i>
                </div>
                <div class="ml-3">
                    <p class="font-medium text-white"><?= htmlspecialchars($userData['username']) ?></p>
                    <p class="text-xs text-purple-200">Kasir</p>
                </div>
            </div>

            <nav class="mt-8">
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="transaksi.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-cash-register mr-3"></i>
                    Transaksi
                </a>
                <a href="checkout.php" class="flex items-center px-4 py-3 rounded-lg nav-active">
                    <i class="fas fa-shopping-cart mr-3"></i>
                    Checkout
                </a>
                <a href="manage_member.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-users mr-3"></i>
                    Member
                </a>
                <a href="reports.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Laporan
                </a>
                <a href="../service/logout.php" class="flex items-center px-4 py-0 rounded-lg hover:bg-purple-800 mt-5 text-red-200">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="ml-64 flex-1 overflow-y-auto">
            <header class="bg-white shadow-sm">
                <div class="flex justify-between items-center px-6 py-4">
                    <h2 class="text-xl font-semibold text-gray-800">Checkout</h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500" id="currentDateTime"></span>
                        <div class="relative">
                            <a href="profile.php">
                                <img src="<?= '../uploads/' . $profilePicture ?>" 
                                     alt="Profile" 
                                     class="w-8 h-8 rounded-full border-2 border-purple-500 cursor-pointer">
                                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full"></span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <!-- Success/Error Message -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert-box">
                        <i class="fas fa-check-circle mr-2"></i>
                        <span><?= $_SESSION['success_message'] ?></span>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert-box bg-red-500">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span><?= $_SESSION['error_message'] ?></span>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Cart Section -->
                    <div class="lg:col-span-2 bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-600">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-semibold text-gray-800">
                                <i class="fas fa-shopping-cart mr-2"></i> Keranjang Belanja
                            </h3>
                            <span class="text-sm text-purple-600"><?= count($_SESSION['cart'] ?? []) ?> item</span>
                        </div>

                        <div class="space-y-3 mb-6 max-h-[50vh] overflow-y-auto">
                            <?php if (!empty($_SESSION['cart'])): ?>
                                <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                                    <div class="cart-item">
                                        <div class="flex items-start gap-3 w-full">
                                            <img src="../uploads/<?= htmlspecialchars($item['image']) ?>" 
                                                 alt="<?= htmlspecialchars($item['name']) ?>" 
                                                 class="w-16 h-16 object-cover rounded-md flex-shrink-0">
                                            
                                            <div class="flex-1 min-w-0">
                                                <div class="flex justify-between items-start">
                                                    <h4 class="font-medium truncate" title="<?= htmlspecialchars($item['name']) ?>">
                                                        <?= htmlspecialchars($item['name']) ?>
                                                    </h4>
                                                    <span class="font-semibold whitespace-nowrap ml-2">
                                                        <?= format_currency($item['price'] * $item['quantity']) ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="flex items-center justify-between mt-3">
                                                    <div class="quantity-control">
                                                        <a href="?update_quantity=1&index=<?= $index ?>&quantity=<?= $item['quantity']-1 ?>" 
                                                           class="quantity-btn">
                                                            <i class="fas fa-minus text-xs"></i>
                                                        </a>
                                                        <span class="quantity-input"><?= $item['quantity'] ?></span>
                                                        <a href="?update_quantity=1&index=<?= $index ?>&quantity=<?= $item['quantity']+1 ?>" 
                                                           class="quantity-btn">
                                                            <i class="fas fa-plus text-xs"></i>
                                                        </a>
                                                    </div>
                                                    
                                                    <a href="?remove_from_cart=<?= $index ?>" 
                                                       class="text-red-600 hover:text-red-800 p-2 ml-2">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                                <p class="text-sm text-purple-600 mt-1">
                                                    <?= format_currency($item['price']) ?> per item
                                                </p>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    Stok tersedia saat ditambahkan: <?= $item['stock'] ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-400">
                                    <i class="fas fa-shopping-cart text-4xl mb-3"></i>
                                    <p class="text-lg">Keranjang belanja kosong</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="border-t border-gray-200 pt-4">
                            <?php if ($discount > 0): ?>
                                <div class="flex justify-between text-sm mb-1">
                                    <p>Subtotal:</p>
                                    <p><?= format_currency($subtotal) ?></p>
                                </div>
                                <div class="flex justify-between text-sm mb-1">
                                    <p>Diskon Member (<?= ($discount * 100) ?>%):</p>
                                    <p class="text-green-600">- <?= format_currency($subtotal * $discount) ?></p>
                                </div>
                            <?php endif; ?>
                            <div class="flex justify-between font-bold text-lg">
                                <p>Total Pembayaran:</p>
                                <p class="text-purple-600"><?= format_currency($total_after_discount) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Section -->
                    <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-purple-600">
                        <h3 class="text-lg font-semibold text-gray-800 mb-6">
                            <i class="fas fa-credit-card mr-2"></i> Pembayaran
                        </h3>

                        <form action="" method="GET" class="mb-6">
                            <label class="block text-gray-600 mb-2">Nomor Telepon Member</label>
                            <div class="flex">
                                <input type="text" name="phone" id="phone" 
                                       class="border border-gray-300 p-3 rounded-l-lg w-full focus:outline-none focus:ring-1 focus:ring-purple-500" 
                                       placeholder="08xxx" 
                                       value="<?= isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : '' ?>">
                                <button type="submit" name="check_member" 
                                        class="bg-purple-600 text-white px-4 rounded-r-lg hover:bg-purple-700 transition-colors">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <?php if ($member_status): ?>
                                <div class="mt-2"><?= $member_status ?></div>
                            <?php endif; ?>
                        </form>

                        <form action="../service/simpan_transaksi.php" method="POST" id="paymentForm">
                            <input type="hidden" name="total" id="total" value="<?= $total_after_discount ?>">
                            <input type="hidden" name="subtotal_before_discount" value="<?= $subtotal ?>">
                            <input type="hidden" name="discount" value="<?= $discount ?>">
                            <input type="hidden" name="phone" value="<?= isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : '' ?>">

                            <div class="mb-4">
                                <label class="block text-gray-600 mb-2">Jumlah Uang</label>
                                <input name="amount" id="amount" 
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-purple-500 input-money" 
                                       type="number"
                                       required
                                       min="0"
                                       step="1"
                                       placeholder="Masukkan jumlah uang">
                                <p id="amount-feedback" class="text-sm mt-1 hidden"></p>
                            </div>

                            <div class="mb-4">
                                <label class="block text-gray-600 mb-2">Metode Pembayaran</label>
                                <select name="payment_method" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-1 focus:ring-purple-500" required>
                                    <option value="">Pilih metode pembayaran</option>
                                    <option value="tunai">Tunai</option>
                                    <option value="qris">QRIS</option>
                                </select>
                            </div>

                            <div class="mb-6">
                                <label class="block text-gray-600 mb-2">Kembalian</label>
                                <input name="change" id="change" 
                                       class="w-full p-3 border border-gray-300 rounded-lg bg-gray-50" 
                                       type="number" 
                                       readonly>
                            </div>

                            <button type="submit" id="submit-btn" 
                                    class="w-full py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-bold text-lg"
                                    <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>
                                <i class="fas fa-check-circle mr-2"></i> Konfirmasi Pembayaran
                            </button>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Timer functionality
        function startCartTimer() {
            const expiryTime = <?= $_SESSION['cart_expiry'] ?? 'null' ?>;
            if (!expiryTime) return;
            
            function updateTimer() {
                const now = Math.floor(Date.now() / 1000);
                const remaining = expiryTime - now;
                
                if (remaining <= 0) {
                    document.getElementById('timeRemaining').textContent = "00:00";
                    window.location.reload();
                    return;
                }
                
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                document.getElementById('timeRemaining').textContent = 
                    `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                if (remaining < 60) {
                    document.getElementById('cartTimer').classList.add('timer-warning');
                }
            }
            
            updateTimer();
            setInterval(updateTimer, 1000);
        }
        
        // Real-time change calculation
        document.getElementById("amount").addEventListener("input", function() {
            const amount = parseFloat(this.value) || 0;
            const total = parseFloat(document.getElementById("total").value) || 0;
            const change = amount - total;
            
            document.getElementById("change").value = Math.max(0, change).toFixed(0);
            
            // Visual feedback
            const feedback = document.getElementById("amount-feedback");
            if (amount <= 0) {
                this.classList.remove("valid", "invalid");
                feedback.classList.add("hidden");
            } else if (amount >= total) {
                this.classList.remove("invalid");
                this.classList.add("valid");
                feedback.textContent = `Pembayaran mencukupi (Kembalian: ${formatCurrency(change)})`;
                feedback.classList.remove("hidden", "text-red-600");
                feedback.classList.add("text-green-600");
            } else {
                this.classList.remove("valid");
                this.classList.add("invalid");
                const kurang = total - amount;
                feedback.textContent = `Kurang: ${formatCurrency(kurang)}`;
                feedback.classList.remove("hidden", "text-green-600");
                feedback.classList.add("text-red-600");
            }
        });

        // Form validation
        document.getElementById("paymentForm").addEventListener("submit", function(e) {
            const amount = parseFloat(document.getElementById("amount").value) || 0;
            const total = parseFloat(document.getElementById("total").value) || 0;
            
            if (amount < total) {
                e.preventDefault();
                alert(`Nominal pembayaran kurang! Minimal: ${formatCurrency(total)}`);
                document.getElementById("amount").focus();
            } else if (amount <= 0) {
                e.preventDefault();
                alert("Masukkan jumlah uang yang valid!");
                document.getElementById("amount").focus();
            }
        });

        function formatCurrency(amount) {
            return 'Rp ' + amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            startCartTimer();
            
            // Focus on amount input when page loads
            if (document.getElementById("amount")) {
                document.getElementById("amount").focus();
            }
        });
    </script>
</body>
</html>