<?php
require_once '../service/connection.php';
session_start();

// Security checks
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../service/login.php");
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]);
}

header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");

if (!isset($_SESSION['logged_in'])) {
    header("Location: ../service/login.php");
    exit;
}

if ($_SESSION['role'] !== 'cashier') {
    header("Location: ../unauthorized.php");
    exit;
}

if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] || 
    $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
    session_destroy();
    header("Location: ../service/login.php");
    exit;
}

// Initialize cart timer if not exists
if (!isset($_SESSION['cart_expiry'])) {
    $_SESSION['cart_expiry'] = time() + 300; // 5 minutes from now
}

// Check if cart has expired
if (time() > $_SESSION['cart_expiry']) {
    unset($_SESSION['cart']);
    unset($_SESSION['cart_expiry']);
    $_SESSION['error_message'] = "Keranjang belanja telah kadaluarsa karena tidak ada aktivitas selama 5 menit";
}

// Get products (excluding expired ones)
$products = [];
$categories = [];
try {
    // Get categories
    $catQuery = $conn->query("SELECT * FROM category");
    $categories = $catQuery->fetch_all(MYSQLI_ASSOC);
    
    // Get non-expired products with category names
    $productQuery = $conn->query("
        SELECT p.*, c.category 
        FROM products p 
        JOIN category c ON p.fid_category = c.id
        WHERE p.exp IS NULL OR p.exp >= CURDATE()
    ");
    $products = $productQuery->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error_message'] = "Terjadi kesalahan saat mengambil data produk";
}

// Initialize cart in session if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
    $_SESSION['cart_expiry'] = time() + 300; // Reset timer when new cart is created
}

// Handle add to cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $productId = $_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    
    // Find product in database
    $product = null;
    foreach ($products as $p) {
        if ($p['id'] == $productId) {
            $product = $p;
            break;
        }
    }
    
    if ($product && $quantity > 0) {
        // Check stock availability
        if ($product['qty'] < $quantity) {
            $_SESSION['error_message'] = "Stok tidak mencukupi! Stok tersedia: {$product['qty']}";
            header("Location: transaksi.php");
            exit;
        }
        
        // Check if product already in cart
        $found = false;
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] == $productId) {
                // Check if total quantity exceeds stock
                if (($item['quantity'] + $quantity) > $product['qty']) {
                    $_SESSION['error_message'] = "Total jumlah melebihi stok yang tersedia!";
                    header("Location: transaksi.php");
                    exit;
                }
                $item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $_SESSION['cart'][] = [
                'id' => $productId,
                'name' => $product['product_name'],
                'price' => $product['selling_price'],
                'quantity' => $quantity,
                'image' => $product['image'],
                'stock' => $product['qty'] // Simpan stok awal untuk validasi
            ];
        }
        
        // Reset cart timer on add
        $_SESSION['cart_expiry'] = time() + 300;
        $_SESSION['success_message'] = "Produk berhasil ditambahkan ke keranjang";
        header("Location: transaksi.php");
        exit;
    }
}

// Handle remove from cart
if (isset($_GET['remove_from_cart'])) {
    $index = (int)$_GET['remove_from_cart'];
    if (isset($_SESSION['cart'][$index])) {
        array_splice($_SESSION['cart'], $index, 1);
        // Reset cart timer on remove
        $_SESSION['cart_expiry'] = time() + 300;
        $_SESSION['success_message'] = "Produk berhasil dihapus dari keranjang";
    }
    header("Location: transaksi.php");
    exit;
}

// Handle quantity update in cart
if (isset($_GET['update_quantity'])) {
    $index = (int)$_GET['index'];
    $newQty = (int)$_GET['quantity'];
    
    if (isset($_SESSION['cart'][$index])) {
        // Get original stock from when product was added to cart
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
    header("Location: transaksi.php");
    exit;
}

// Format currency
function format_currency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Calculate cart total
$cartTotal = 0;
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartTotal += $item['price'] * $item['quantity'];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Baru - MediPOS</title>
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
        .category-card {
            padding: 10px 16px;
            border-radius: 8px;
            background-color: white;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            font-size: 14px;
            white-space: nowrap;
        }
        .category-card:hover {
            background-color: #7C3AED;
            color: white;
            transform: translateY(-2px);
            border-color: #7C3AED;
        }
        .category-card.active {
            background-color: #7C3AED;
            color: white;
            border-color: #7C3AED;
        }
        .cart-sidebar {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100%;
            background-color: white;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
            transition: right 0.3s ease;
            z-index: 1000;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }
        .cart-sidebar.open {
            right: 0;
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
        .cart-total {
            font-weight: bold;
            font-size: 1.2em;
            margin: 16px 0;
            text-align: right;
        }
        .cart-toggle {
            position: fixed;
            right: 20px;
            top: 80px;
            background-color: #7C3AED;
            color: white;
            border: none;
            border-radius: 50%;
            width: 56px;
            height: 56px;
            font-size: 1.2em;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        .cart-toggle:hover {
            transform: scale(1.1);
            background-color: #6B21A8;
        }
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #EF4444;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .product-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
            background-color: white;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(124, 58, 237, 0.1);
            border-color: #8b5cf6;
        }
        .product-image {
            height: 180px;
            width: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        .product-card:hover .product-image {
            transform: scale(1.03);
        }
        .product-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background-color: #7C3AED;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            z-index: 1;
        }
        .low-stock {
            background-color: #F59E0B;
        }
        .out-of-stock {
            background-color: #EF4444;
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
        .empty-cart {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 0;
            color: #9ca3af;
        }
        .empty-cart i {
            font-size: 48px;
            margin-bottom: 16px;
        }
        .checkout-btn {
            transition: all 0.3s;
        }
        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(124, 58, 237, 0.2);
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
                    <a href="dashboard_kasir.php" class="block py-2 px-3 mb-1 rounded hover:bg-purple-100 transition-colors">
                        <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                    </a>
                    <a href="transaksi.php" class="block py-2 px-3 mb-1 rounded active-menu">
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
                    <i class="fas fa-cash-register mr-2 text-purple-600"></i> Transaksi Baru
                </h2>
                <div class="flex items-center space-x-4">
                    <button id="scanButton" class="flex items-center gap-2 py-2 px-4 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                        <i class="fas fa-barcode text-xl"></i> Scan Produk
                    </button>
                    <span class="text-sm text-gray-500">
                        <i class="far fa-calendar-alt mr-1"></i> <?= date('d F Y') ?>
                    </span>
                </div>
            </header>

            <!-- Transaction Content -->
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

                <!-- Kategori -->
                <div class="category-container flex overflow-x-auto space-x-2 p-1 my-4 pb-2">
                    <div class="category-card active" onclick="filterByCategory('all')">
                        <i class="fas fa-boxes mr-2"></i> Semua Produk
                    </div>
                    <?php foreach ($categories as $category): ?>
                        <div class="category-card" onclick="filterByCategory('<?= htmlspecialchars($category['category']) ?>')">
                            <?= htmlspecialchars($category['category']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Produk -->
                <div id="productList" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card relative" 
                             data-id="<?= $product['id'] ?>"
                             data-name="<?= htmlspecialchars($product['product_name']) ?>"
                             data-category="<?= htmlspecialchars($product['category']) ?>"
                             data-stock="<?= $product['qty'] ?>"
                             data-price="<?= $product['selling_price'] ?>"
                             data-image="<?= $product['image'] ?>">
                            
                            <?php if ($product['qty'] <= 5 && $product['qty'] > 0): ?>
                                <span class="product-badge low-stock">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    Stok <?= $product['qty'] ?>
                                </span>
                            <?php elseif ($product['qty'] == 0): ?>
                                <span class="product-badge out-of-stock">
                                    <i class="fas fa-times-circle mr-1"></i>
                                    Stok Habis
                                </span>
                            <?php endif; ?>
                            
                            <img src="../uploads/<?= htmlspecialchars($product['image']) ?>"
                                 alt="<?= htmlspecialchars($product['product_name']) ?>"
                                 class="product-image cursor-pointer"
                                 onclick="openModal(<?= $product['id'] ?>)">
                            
                            <div class="p-4">
                                <h3 class="font-semibold text-lg text-gray-800 mb-1 truncate" title="<?= htmlspecialchars($product['product_name']) ?>">
                                    <?= htmlspecialchars($product['product_name']) ?>
                                </h3>
                                <p class="text-sm text-gray-500 mb-2"><?= htmlspecialchars($product['category']) ?></p>
                                
                                <div class="flex justify-between items-center mb-3">
                                    <p class="font-bold text-purple-600 text-lg"><?= format_currency($product['selling_price']) ?></p>
                                    <?php if ($product['exp']): ?>
                                        <p class="text-xs text-gray-400">
                                            Exp: <?= date('d/m/Y', strtotime($product['exp'])) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex justify-between items-center gap-2">
                                    <div class="quantity-control">
                                        <button class="quantity-btn" onclick="updateQuantity(<?= $product['id'] ?>, -1)">
                                            <i class="fas fa-minus text-gray-600"></i>
                                        </button>
                                        <span id="quantity-<?= $product['id'] ?>" class="quantity-input">0</span>
                                        <button class="quantity-btn" onclick="updateQuantity(<?= $product['id'] ?>, 1)">
                                            <i class="fas fa-plus text-gray-600"></i>
                                        </button>
                                    </div>
                                    
                                    <form method="POST" onsubmit="return prepareQuantity(<?= $product['id'] ?>)">
                                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                        <input type="hidden" name="quantity" id="qty-<?= $product['id'] ?>" value="0">
                                        <input type="hidden" name="add_to_cart" value="1">
                                        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors <?= $product['qty'] == 0 ? 'opacity-50 cursor-not-allowed' : '' ?>" 
                                                <?= $product['qty'] == 0 ? 'disabled' : '' ?>>
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </main>
        </div>

        <!-- Cart Sidebar -->
        <button class="cart-toggle" onclick="toggleCart()">
            <i class="fas fa-shopping-cart"></i>
            <span class="cart-badge" id="cartBadge"><?= count($_SESSION['cart']) ?></span>
        </button>

        <div id="cartSidebar" class="cart-sidebar">
            <div class="flex justify-between items-center mb-4 sticky top-0 bg-white pb-4 border-b z-10">
                <h2 class="text-xl font-bold text-purple-800">
                    <i class="fas fa-shopping-cart mr-2"></i> Keranjang
                </h2>
                <div class="flex items-center gap-2">
                    <span class="cart-timer" id="cartTimer">
                        <i class="fas fa-clock"></i>
                        <span id="timeRemaining">05:00</span>
                    </span>
                    <button onclick="toggleCart()" class="text-gray-500 hover:text-gray-700 p-1">
                        <i class="fas fa-times text-lg"></i>
                    </button>
                </div>
            </div>
            
            <div id="cartItems" class="flex-1 overflow-y-auto">
                <?php if (empty($_SESSION['cart'])): ?>
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <p class="text-lg">Keranjang belanja kosong</p>
                        <p class="text-sm mt-2">Tambahkan produk untuk memulai transaksi</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $total = 0;
                    foreach ($_SESSION['cart'] as $index => $item): 
                        $itemTotal = $item['price'] * $item['quantity'];
                        $total += $itemTotal;
                    ?>
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
                                            <?= format_currency($itemTotal) ?>
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
                <?php endif; ?>
            </div>
            
            <?php if (!empty($_SESSION['cart'])): ?>
                <div class="sticky bottom-0 bg-white pt-4 border-t">
                    <div class="cart-total flex justify-between items-center">
                        <span class="font-semibold">Total:</span>
                        <span id="cartTotal" class="text-xl font-bold text-purple-600"><?= format_currency($total) ?></span>
                    </div>
                    
                    <form action="checkout.php" method="POST" class="w-full">
                        <button type="submit" class="block w-full py-3 bg-purple-600 text-white text-center rounded-lg hover:bg-purple-700 mt-4 mb-2 checkout-btn">
                            <i class="fas fa-credit-card mr-2"></i> Proses Pembayaran
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <!-- Scanner Modal -->
        <div id="scannerModal" class="fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
            <div class="bg-white p-6 rounded-lg max-w-md w-full relative">
                <button class="close absolute top-2 right-2 text-2xl text-purple-600" onclick="closeScannerModal()">&times;</button>
                <h2 class="text-xl font-semibold mb-4 text-center text-gray-800">Scan Barcode Produk</h2>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Gunakan scanner USB atau masukkan barcode manual:</label>
                    <input type="text" id="barcodeInput" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600" placeholder="Scan barcode..." autofocus>
                </div>
                
                <div id="scannerFeedback" class="text-center py-4 text-gray-600">
                    Arahkan scanner ke barcode produk
                </div>
            </div>
        </div>

        <!-- Product Detail Modal -->
        <div id="productModal" class="fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
            <div class="bg-white p-6 rounded-lg max-w-xl w-full relative">
                <button class="close absolute top-2 right-2 text-2xl text-purple-600 font-bold" onclick="closeModal()">&times;</button>
                <div id="modalProductDetails" class="text-center space-y-4">
                    <!-- Content will be filled by JavaScript -->
                </div>
            </div>
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
                    // Refresh page to clear expired cart
                    window.location.reload();
                    return;
                }
                
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                document.getElementById('timeRemaining').textContent = 
                    `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                // Change color when less than 1 minute
                if (remaining < 60) {
                    document.getElementById('cartTimer').classList.add('timer-warning');
                }
            }
            
            updateTimer();
            setInterval(updateTimer, 1000);
        }
        
        // Cart functionality
        function toggleCart() {
            document.getElementById('cartSidebar').classList.toggle('open');
        }
        
        // Product filtering
        function filterByCategory(category) {
            document.querySelectorAll('.category-card').forEach(card => card.classList.remove('active'));
            event.target.classList.add('active');
            
            document.querySelectorAll('.product-card').forEach(product => {
                const productCategory = product.getAttribute('data-category');
                const showProduct = (category === 'all' || productCategory === category);
                product.style.display = showProduct ? 'block' : 'none';
            });
        }
        
        // Quantity management
        function updateQuantity(productId, amount) {
            const productElement = document.querySelector(`.product-card[data-id="${productId}"]`);
            const quantitySpan = document.getElementById('quantity-' + productId);
            const stock = parseInt(productElement.getAttribute('data-stock'));
            
            let currentQuantity = parseInt(quantitySpan.textContent) + amount;
            
            if (currentQuantity < 0) currentQuantity = 0;
            if (currentQuantity > stock) {
                showAlert(`Stok tidak mencukupi! Stok tersedia: ${stock}`);
                currentQuantity = stock;
            }
            
            quantitySpan.textContent = currentQuantity;
            document.getElementById('qty-' + productId).value = currentQuantity;
        }
        
        function prepareQuantity(productId) {
            const qty = parseInt(document.getElementById('quantity-' + productId).textContent);
            if (qty <= 0) {
                showAlert("Jumlah harus lebih dari 0!");
                return false;
            }
            
            const stock = parseInt(document.querySelector(`.product-card[data-id="${productId}"]`).getAttribute('data-stock'));
            if (qty > stock) {
                showAlert(`Stok tidak mencukupi! Stok tersedia: ${stock}`);
                return false;
            }
            
            return true;
        }
        
        function showAlert(message, type = 'error') {
            const alertBox = document.createElement('div');
            alertBox.className = `alert-box ${type === 'success' ? 'bg-green-100 border-l-4 border-green-500 text-green-700' : 'bg-red-100 border-l-4 border-red-500 text-red-700'}`;
            alertBox.innerHTML = `
                <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'} mr-2"></i>
                <span>${message}</span>
            `;
            document.body.appendChild(alertBox);
            
            setTimeout(() => {
                alertBox.remove();
            }, 3000);
        }
        
        // Product modal
        function openModal(productId) {
            const product = document.querySelector(`.product-card[data-id='${productId}']`);
            const modal = document.getElementById('productModal');
            const modalDetails = document.getElementById('modalProductDetails');
            
            const name = product.getAttribute('data-name');
            const category = product.getAttribute('data-category');
            const stock = product.getAttribute('data-stock');
            const price = product.getAttribute('data-price');
            const image = product.getAttribute('data-image');
            
            modalDetails.innerHTML = `
                <img src="../uploads/${image}" alt="${name}" class="w-full h-48 object-cover rounded-lg mb-4">
                <h2 class="text-2xl font-bold text-gray-800">${name}</h2>
                <p class="text-purple-600 font-medium">Kategori: ${category}</p>
                <p class="text-gray-700">Stok: ${stock}</p>
                <p class="text-xl text-purple-600 font-bold my-3">${formatCurrency(price)}</p>
                <div class="flex justify-center gap-4 mt-4">
                    <div class="quantity-control">
                        <button class="quantity-btn" onclick="updateQuantity(${productId}, -1)">
                            <i class="fas fa-minus text-gray-600"></i>
                        </button>
                        <span id="modal-quantity-${productId}" class="quantity-input">0</span>
                        <button class="quantity-btn" onclick="updateQuantity(${productId}, 1)">
                            <i class="fas fa-plus text-gray-600"></i>
                        </button>
                    </div>
                    <form method="POST" onsubmit="return prepareQuantity(${productId})">
                        <input type="hidden" name="product_id" value="${productId}">
                        <input type="hidden" name="quantity" id="modal-qty-${productId}" value="0">
                        <input type="hidden" name="add_to_cart" value="1">
                        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                            <i class="fas fa-cart-plus mr-2"></i> Tambahkan
                        </button>
                    </form>
                </div>
            `;
            
            // Sync quantity with main view
            const mainQuantity = document.getElementById('quantity-' + productId).textContent;
            document.getElementById('modal-quantity-' + productId).textContent = mainQuantity;
            document.getElementById('modal-qty-' + productId).value = mainQuantity;
            
            modal.classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('productModal').classList.add('hidden');
        }
        
        // Scanner functionality
        function openScanner() {
            document.getElementById('scannerModal').classList.remove('hidden');
            document.getElementById('barcodeInput').focus();
        }
        
        function closeScannerModal() {
            document.getElementById('scannerModal').classList.add('hidden');
        }
        
        // Handle barcode input
        document.getElementById('barcodeInput').addEventListener('input', function(e) {
            // Simulate USB scanner behavior (quick input)
            setTimeout(() => {
                if (this.value.length > 3) { // Assume barcode has at least 4 characters
                    processBarcode(this.value);
                    this.value = '';
                }
            }, 100);
        });
        
        function processBarcode(barcode) {
            // Find product by barcode
            fetch(`./service/check_barcode.php?barcode=${encodeURIComponent(barcode)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const product = document.querySelector(`.product-card[data-id="${data.product_id}"]`);
                        
                        if (product) {
                            // Highlight product
                            product.classList.add('scan-highlight');
                            setTimeout(() => {
                                product.classList.remove('scan-highlight');
                            }, 2000);
                            
                            // Set quantity to 1
                            document.getElementById(`quantity-${data.product_id}`).textContent = '1';
                            document.getElementById(`qty-${data.product_id}`).value = '1';
                            
                            // Submit the form
                            const form = product.querySelector('form');
                            if (form && prepareQuantity(data.product_id)) {
                                form.submit();
                            }
                        }
                    } else {
                        document.getElementById('scannerFeedback').innerHTML = `
                            <div class="text-red-500">
                                <i class="fas fa-times-circle"></i> Produk tidak ditemukan
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('scannerFeedback').innerHTML = `
                        <div class="text-red-500">
                            <i class="fas fa-times-circle"></i> Error memproses barcode
                        </div>
                    `;
                });
        }
        
        function formatCurrency(amount) {
            return 'Rp ' + amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            startCartTimer();
            document.getElementById('scanButton').addEventListener('click', openScanner);
        });
    </script>
</body>
</html>