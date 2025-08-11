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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            transition: all 0.3s;
        }
        .active-menu {
            background-color: #7C3AED;
            color: white;
        }
        .active-menu:hover {
            background-color: #6B21A8;
        }
        .quantity-control {
            display: flex;
            align-items: center;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
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
        }
        .quantity-btn:hover {
            background-color: #e5e7eb;
        }
        .quantity-input {
            width: 40px;
            text-align: center;
            border: none;
            border-left: 1px solid #e5e7eb;
            border-right: 1px solid #e5e7eb;
            font-weight: 600;
        }
        .cart-timer {
            background-color: #fef3c7;
            color: #92400e;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
        }
        .timer-warning {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .alert-box {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background-color: #7C3AED;
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
        }
        .input-money:focus {
            border-color: #7C3AED;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2);
        }
        .valid {
            border-color: #10B981;
            background-color: #ECFDF5;
        }
        .invalid {
            border-color: #EF4444;
            background-color: #FEE2E2;
        }
        .cart-item {
            display: flex;
            padding: 12px;
            border-radius: 8px;
            background-color: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
            margin-bottom: 8px;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar Navigation -->
        <div class="sidebar w-64 bg-white shadow-lg">
            <div class="p-4 border-b">
                <h1 class="text-xl font-bold text-purple-800">Medi<span class="text-purple-600">POS</span></h1>
                <p class="text-sm text-gray-500">Kasir Dashboard</p>
            </div>
            <div class="p-4">
                <div class="flex items-center space-x-3 mb-6">
                    <div class="w-10 h-10 rounded-full bg-purple-200 flex items-center justify-center">
                        <i class="fas fa-user text-purple-800"></i>
                    </div>
                    <div>
                        <p class="font-medium"><?= htmlspecialchars($_SESSION['username']) ?></p>
                        <p class="text-xs text-gray-500">Kasir</p>
                    </div>
                </div>
                
                <nav>
                    <a href="dashboard.php" class="block py-2 px-3 mb-1 rounded hover:bg-purple-100 transition-colors">
                        <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                    </a>
                    <a href="transaksi.php" class="block py-2 px-3 mb-1 rounded hover:bg-purple-100 transition-colors">
                        <i class="fas fa-cash-register mr-2"></i> Transaksi Baru
                    </a>
                    <a href="daftar_transaksi.php" class="block py-2 px-3 mb-1 rounded hover:bg-purple-100 transition-colors">
                        <i class="fas fa-list mr-2"></i> Daftar Transaksi
                    </a>
                    <a href="produk.php" class="block py-2 px-3 mb-1 rounded hover:bg-purple-100 transition-colors">
                        <i class="fas fa-boxes mr-2"></i> Kelola Produk
                    </a>
                    <hr class="my-3 border-gray-200">
                    <a href="../service/logout.php" class="block py-2 px-3 mb-1 rounded hover:bg-red-100 text-red-600 transition-colors">
                        <i class="fas fa-sign-out-alt mr-2"></i> Logout
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Header -->
            <header class="bg-white shadow-sm p-4 flex justify-between items-center">
                <h2 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-credit-card mr-2 text-purple-600"></i> Proses Pembayaran
                </h2>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-500">
                        <i class="far fa-calendar-alt mr-1"></i> <?= date('d F Y') ?>
                    </span>
                </div>
            </header>

            <!-- Checkout Content -->
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
                    <div class="alert-box bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <span><?= $_SESSION['error_message'] ?></span>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <div class="flex flex-col lg:flex-row gap-6">
                    <!-- Cart Section -->
                    <div class="w-full lg:w-2/3 bg-white p-6 rounded-lg shadow">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-bold text-purple-800">
                                <i class="fas fa-shopping-cart mr-2"></i> Keranjang Belanja
                            </h3>
                            <div class="cart-timer" id="cartTimer">
                                <i class="fas fa-clock"></i>
                                <span id="timeRemaining"><?= floor(($_SESSION['cart_expiry'] - time()) / 60) ?>:<?= str_pad(($_SESSION['cart_expiry'] - time()) % 60, 2, '0', STR_PAD_LEFT) ?></span>
                            </div>
                        </div>

                        <div class="space-y-3 mb-6" style="max-height: 50vh; overflow-y: auto;">
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
                                                            <i class="fas fa-minus text-gray-600"></i>
                                                        </a>
                                                        <span class="quantity-input"><?= $item['quantity'] ?></span>
                                                        <a href="?update_quantity=1&index=<?= $index ?>&quantity=<?= $item['quantity']+1 ?>" 
                                                           class="quantity-btn">
                                                            <i class="fas fa-plus text-gray-600"></i>
                                                        </a>
                                                    </div>
                                                    
                                                    <a href="?remove_from_cart=<?= $index ?>" 
                                                       class="text-red-500 hover:text-red-700 p-2 ml-2">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </a>
                                                </div>
                                                <p class="text-sm text-gray-500 mt-1">
                                                    <?= format_currency($item['price']) ?> per item
                                                </p>
                                                <p class="text-xs text-gray-400 mt-1">
                                                    Stok tersedia saat ditambahkan: <?= $item['stock'] ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fas fa-shopping-cart text-4xl mb-3"></i>
                                    <p class="text-lg">Keranjang belanja kosong</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="border-t pt-4">
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
                    <div class="w-full lg:w-1/3 bg-white p-6 rounded-lg shadow">
                        <h3 class="text-lg font-bold text-purple-800 mb-4">
                            <i class="fas fa-credit-card mr-2"></i> Pembayaran
                        </h3>

                        <form action="" method="GET" class="mb-6">
                            <label class="block text-gray-700 mb-2">Nomor Telepon Member</label>
                            <div class="flex">
                                <input type="text" name="phone" id="phone" 
                                       class="border p-2 rounded-l-lg w-full focus:outline-none focus:ring-2 focus:ring-purple-600" 
                                       placeholder="08xxx" 
                                       value="<?= isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : '' ?>">
                                <button type="submit" name="check_member" 
                                        class="bg-purple-600 text-white px-4 rounded-r-lg hover:bg-purple-700 transition-colors">
                                    Cek
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
                                <label class="block text-gray-700 mb-2">Jumlah Uang</label>
                                <input name="amount" id="amount" 
                                       class="w-full p-3 border rounded-lg input-money focus:outline-none focus:ring-2 focus:ring-purple-600" 
                                       type="number"
                                       required
                                       min="0"
                                       step="1"
                                       placeholder="Masukkan jumlah uang">
                                <p id="amount-feedback" class="text-sm mt-1 hidden"></p>
                            </div>

                            <div class="mb-4">
                                <label class="block text-gray-700 mb-2">Metode Pembayaran</label>
                                <select name="payment_method" class="w-full p-3 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600" required>
                                    <option value="tunai">Tunai</option>
                                    <option value="qris">QRIS</option>
                                </select>
                            </div>

                            <div class="mb-6">
                                <label class="block text-gray-700 mb-2">Kembalian</label>
                                <input name="change" id="change" 
                                       class="w-full p-3 border rounded-lg bg-gray-100" 
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
                feedback.classList.remove("hidden", "text-red-500");
                feedback.classList.add("text-green-600");
            } else {
                this.classList.remove("valid");
                this.classList.add("invalid");
                const kurang = total - amount;
                feedback.textContent = `Kurang: ${formatCurrency(kurang)}`;
                feedback.classList.remove("hidden", "text-green-600");
                feedback.classList.add("text-red-500");
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