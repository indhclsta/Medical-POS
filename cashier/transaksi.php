<?php
require_once '../service/connection.php';
session_start();

// Security checks
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../service/login.php");
    exit();
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
    
    // Handle search
    $searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
    if ($searchTerm !== '') {
        $productQuery = $conn->prepare("
            SELECT p.*, c.category 
            FROM products p 
            JOIN category c ON p.fid_category = c.id
            WHERE (p.exp IS NULL OR p.exp >= CURDATE())
            AND (p.product_name LIKE ? OR p.barcode LIKE ?)
        ");
        $searchParam = "%{$searchTerm}%";
        $productQuery->bind_param('ss', $searchParam, $searchParam);
        $productQuery->execute();
        $products = $productQuery->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        // Get all non-expired products with category names and barcodes
        $productQuery = $conn->query("
            SELECT p.*, c.category 
            FROM products p 
            JOIN category c ON p.fid_category = c.id
            WHERE p.exp IS NULL OR p.exp >= CURDATE()
        ");
        $products = $productQuery->fetch_all(MYSQLI_ASSOC);
    }
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
                'stock' => $product['qty'] // Save initial stock for validation
            ];
        }
        
        // Reset cart timer on add
        $_SESSION['cart_expiry'] = time() + 300;
        $_SESSION['success_message'] = "Produk berhasil ditambahkan ke keranjang";
        header("Location: transaksi.php");
        exit;
    }
}

// Get user data for profile
$userId = $_SESSION['id'];
$userQuery = "SELECT username, image FROM admin WHERE id = $userId";
$userResult = mysqli_query($conn, $userQuery);
$userData = mysqli_fetch_assoc($userResult);
$profilePicture = $userData['image'] ?? 'default.jpg';

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaksi Baru - MediPOS</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .category-card {
            padding: 8px 16px;
            border-radius: 8px;
            background-color: #f3f4f6;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 8px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
            font-size: 14px;
            white-space: nowrap;
            color: #4b5563;
        }
        .category-card:hover {
            background-color: #e5e7eb;
            transform: translateY(-2px);
        }
        .category-card.active {
            background-color: #6b46c1;
            color: white;
            border-color: #6b46c1;
        }
        .product-card {
            transition: all 0.3s ease;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
            background-color: white;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(107, 70, 193, 0.1);
            border-color: #c4b5fd;
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
            background-color: #6b46c1;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            z-index: 1;
        }
        .low-stock {
            background-color: #f59e0b;
        }
        .out-of-stock {
            background-color: #ef4444;
        }
        .quantity-control {
            display: flex;
            align-items: center;
            border: 1px solid #d1d5db;
            border-radius: 6px;
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
            color: #4b5563;
        }
        .quantity-btn:hover {
            background-color: #e5e7eb;
        }
        .quantity-input {
            width: 40px;
            text-align: center;
            border: none;
            border-left: 1px solid #d1d5db;
            border-right: 1px solid #d1d5db;
            font-weight: 600;
            background-color: white;
            color: #111827;
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
            border-left: 1px solid #e5e7eb;
        }
        .cart-sidebar.open {
            right: 0;
        }
        .cart-item {
            display: flex;
            padding: 12px;
            border-radius: 8px;
            background-color: white;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            border: 1px solid #e5e7eb;
            margin-bottom: 8px;
        }
        .cart-total {
            font-weight: bold;
            font-size: 1.2em;
            margin: 16px 0;
            text-align: right;
            color: #111827;
        }
        .cart-toggle {
            position: fixed;
            right: 20px;
            top: 80px;
            background-color: #6b46c1;
            color: white;
            border: none;
            border-radius: 50%;
            width: 56px;
            height: 56px;
            font-size: 1.2em;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        .cart-toggle:hover {
            transform: scale(1.1);
            background-color: #5e3db3;
        }
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ef4444;
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
            background-color: #6b46c1;
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
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
            background-color: #6b46c1;
            color: white;
        }
        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(107, 70, 193, 0.2);
            background-color: #5e3db3;
        }
        .scan-highlight {
            animation: highlight 1.5s ease;
        }
        @keyframes highlight {
            0% { box-shadow: 0 0 0 0 rgba(107, 70, 193, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(107, 70, 193, 0); }
            100% { box-shadow: 0 0 0 0 rgba(107, 70, 193, 0); }
        }
        .scanner-modal {
            background-color: white;
            border: 1px solid #e5e7eb;
        }
        .product-modal {
            background-color: white;
            border: 1px solid #e5e7eb;
        }
        .payment-cash {
            background-color: #e6ffed;
            color: #38a169;
        }
        .payment-transfer {
            background-color: #ebf8ff;
            color: #3182ce;
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
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="transaksi.php" class="flex items-center px-4 py-3 rounded-lg nav-active">
                    <i class="fas fa-cash-register mr-3"></i>
                    Transaksi
                </a>
                <a href="manage_member.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-users mr-3"></i>
                    Kelola Member
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
                    <h2 class="text-xl font-semibold text-gray-800">Transaksi Baru</h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500" id="currentDateTime"></span>
                        <div class="relative">
                            <a href="profile.php">
                                <img src="../uploads/<?= htmlspecialchars($profilePicture) ?>" 
                                     alt="Profile" 
                                     class="w-8 h-8 rounded-full border-2 border-purple-500 cursor-pointer">
                                <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full"></span>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <!-- Search and Actions -->
                <div class="flex justify-between items-center mb-6">
                    <form method="GET" action="transaksi.php" class="relative w-96">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="search" placeholder="Cari produk..." 
                               value="<?= htmlspecialchars($searchTerm) ?>"
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <?php if ($searchTerm !== ''): ?>
                            <a href="transaksi.php" class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                    </form>
                    <button id="scanButton" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition">
                        <i class="fas fa-qrcode"></i>
                        <span>Scan Produk</span>
                    </button>
                </div>

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

                <!-- Categories -->
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

                <!-- Products -->
                <div id="productList" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                    <?php foreach ($products as $product): ?>
                        <div class="product-card relative" 
                             data-id="<?= $product['id'] ?>"
                             data-name="<?= htmlspecialchars($product['product_name']) ?>"
                             data-category="<?= htmlspecialchars($product['category']) ?>"
                             data-stock="<?= $product['qty'] ?>"
                             data-price="<?= $product['selling_price'] ?>"
                             data-image="<?= $product['image'] ?>"
                             data-barcode="<?= $product['barcode'] ?? '' ?>">
                            
                            <?php if ($product['qty'] <= 5 && $product['qty'] > 0): ?>
                                <span class="product-badge low-stock">
                                    <i class="fas fa-exclamation-triangle mr-1 text-xs"></i>
                                    Stok <?= $product['qty'] ?>
                                </span>
                            <?php elseif ($product['qty'] == 0): ?>
                                <span class="product-badge out-of-stock">
                                    <i class="fas fa-ban mr-1 text-xs"></i>
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
                                <p class="text-sm text-purple-600 mb-2"><?= htmlspecialchars($product['category']) ?></p>
                                
                                <div class="flex justify-between items-center mb-3">
                                    <p class="font-bold text-purple-600 text-lg"><?= format_currency($product['selling_price']) ?></p>
                                    <?php if ($product['exp']): ?>
                                        <p class="text-xs text-gray-500">
                                            Exp: <?= date('d/m/Y', strtotime($product['exp'])) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex justify-between items-center gap-2">
                                    <div class="quantity-control">
                                        <button class="quantity-btn" onclick="updateQuantity(<?= $product['id'] ?>, -1)">
                                            <i class="fas fa-minus text-xs"></i>
                                        </button>
                                        <span id="quantity-<?= $product['id'] ?>" class="quantity-input">0</span>
                                        <button class="quantity-btn" onclick="updateQuantity(<?= $product['id'] ?>, 1)">
                                            <i class="fas fa-plus text-xs"></i>
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
    </div>

    <!-- Cart Sidebar -->
    <button class="cart-toggle" onclick="toggleCart()">
        <i class="fas fa-shopping-cart"></i>
        <span class="cart-badge" id="cartBadge"><?= count($_SESSION['cart']) ?></span>
    </button>

    <div id="cartSidebar" class="cart-sidebar">
        <div class="flex justify-between items-center mb-4 sticky top-0 bg-white pb-4 border-b border-gray-200 z-10">
            <h2 class="text-xl font-bold text-gray-800">
                <i class="fas fa-shopping-cart mr-2"></i> Keranjang
            </h2>
            <div class="flex items-center gap-2">
                <span class="cart-timer" id="cartTimer">
                    <i class="fas fa-clock mr-1 text-sm"></i>
                    <span id="timeRemaining">05:00</span>
                </span>
                <button onclick="toggleCart()" class="text-gray-400 hover:text-gray-600 p-1">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <div id="cartItems" class="flex-1 overflow-y-auto">
            <?php if (empty($_SESSION['cart'])): ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart text-5xl"></i>
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
                                    <h4 class="font-medium text-gray-800 truncate" title="<?= htmlspecialchars($item['name']) ?>">
                                        <?= htmlspecialchars($item['name']) ?>
                                    </h4>
                                    <span class="font-semibold text-purple-600 whitespace-nowrap ml-2">
                                        <?= format_currency($itemTotal) ?>
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
                                       class="text-red-500 hover:text-red-700 p-2 ml-2">
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
            <?php endif; ?>
        </div>
        
        <?php if (!empty($_SESSION['cart'])): ?>
            <div class="sticky bottom-0 bg-white pt-4 border-t border-gray-200">
                <div class="cart-total flex justify-between items-center">
                    <span class="font-semibold text-gray-800">Total:</span>
                    <span id="cartTotal" class="text-xl font-bold text-purple-600"><?= format_currency($total) ?></span>
                </div>
                <form action="checkout.php" method="POST" class="w-full" onsubmit="return validateCartBeforeCheckout()">
                    <button type="submit" class="block w-full py-3 bg-purple-600 text-white text-center rounded-lg hover:bg-purple-700 mt-4 mb-2 checkout-btn">
                        <i class="fas fa-money-bill-wave mr-2"></i> Proses Pembayaran
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scanner Modal -->
    <div id="scannerModal" class="fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="scanner-modal p-6 rounded-lg max-w-md w-full relative">
            <button class="close absolute top-2 right-2 text-2xl text-purple-600" onclick="closeScannerModal()">&times;</button>
            <h2 class="text-xl font-semibold mb-4 text-center text-gray-800">Scan Barcode Produk</h2>
            
            <div class="mb-4">
                <label class="block text-gray-700 mb-2">Gunakan scanner USB atau masukkan barcode manual:</label>
                <input type="text" id="barcodeInput" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-gray-800" placeholder="Scan barcode..." autofocus>
            </div>
            
            <div id="scannerFeedback" class="text-center py-4 text-purple-600">
                <i class="fas fa-qrcode mr-2"></i> Arahkan scanner ke barcode produk
            </div>
        </div>
    </div>

    <!-- Product Detail Modal -->
    <div id="productModal" class="fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
        <div class="product-modal p-6 rounded-lg max-w-xl w-full relative">
            <button class="close absolute top-2 right-2 text-2xl text-purple-600 font-bold" onclick="closeModal()">&times;</button>
            <div id="modalProductDetails" class="text-center space-y-4">
                <!-- Content will be filled by JavaScript -->
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
            
            const searchTerm = document.querySelector('input[name="search"]').value.toLowerCase();
            
            document.querySelectorAll('.product-card').forEach(product => {
                const productCategory = product.getAttribute('data-category');
                const productName = product.getAttribute('data-name').toLowerCase();
                const showProduct = (category === 'all' || productCategory === category) && 
                                   (searchTerm === '' || productName.includes(searchTerm));
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
            alertBox.className = `alert-box ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
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
            const barcode = product.getAttribute('data-barcode');
            
            modalDetails.innerHTML = `
                <img src="../uploads/${image}" alt="${name}" class="w-full h-48 object-cover rounded-lg mb-4">
                <h2 class="text-2xl font-bold text-gray-800">${name}</h2>
                <p class="text-purple-600 font-medium">Kategori: ${category}</p>
                <p class="text-gray-600">Stok: ${stock}</p>
                ${barcode ? `<p class="text-gray-600">Barcode: ${barcode}</p>` : ''}
                <p class="text-xl text-purple-600 font-bold my-3">${formatCurrency(price)}</p>
                <div class="flex justify-center gap-4 mt-4">
                    <div class="quantity-control">
                        <button class="quantity-btn" onclick="updateQuantity(${productId}, -1)">
                            <i class="fas fa-minus text-xs"></i>
                        </button>
                        <span id="modal-quantity-${productId}" class="quantity-input">0</span>
                        <button class="quantity-btn" onclick="updateQuantity(${productId}, 1)">
                            <i class="fas fa-plus text-xs"></i>
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
            document.getElementById('scannerFeedback').innerHTML = 
                '<i class="fas fa-qrcode mr-2"></i> Arahkan scanner ke barcode produk';
        }

        function closeScannerModal() {
            document.getElementById('scannerModal').classList.add('hidden');
        }

        // Handle barcode input with debounce for USB scanner
        let barcodeTimer;
        document.getElementById('barcodeInput').addEventListener('input', function(e) {
            clearTimeout(barcodeTimer);
            barcodeTimer = setTimeout(() => {
                if (this.value.length >= 3) { // Minimum 3 characters for barcode
                    processBarcode(this.value.trim());
                    this.value = '';
                }
            }, 100); // 100ms debounce
        });

        function processBarcode(barcode) {
            showFeedback('Processing barcode...', 'loading');
            
            // Gunakan URL absolut yang benar
            const apiUrl = '../service/check_barcode.php?barcode=' + encodeURIComponent(barcode);
            
            fetch(apiUrl)
                .then(response => {
                    // Pastikan response adalah JSON
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        return response.text().then(text => {
                            throw new Error(`Invalid response: ${text.substring(0, 100)}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Proses data produk
                        const productId = data.data.id;
                        const productElement = document.querySelector(`[data-id="${productId}"]`);
                        
                        if (productElement) {
                            // Update UI
                            document.getElementById(`quantity-${productId}`).textContent = 1;
                            document.getElementById(`qty-${productId}`).value = 1;
                            
                            // Submit form
                            setTimeout(() => {
                                productElement.querySelector('form').submit();
                            }, 300);
                            
                            showFeedback(`Found: ${data.data.name}`, 'success');
                        } else {
                            showFeedback('Product not in current view', 'warning');
                        }
                    } else {
                        showFeedback(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Barcode error:', error);
                    showFeedback(`Error: ${error.message}`, 'error');
                });
        }

        function showFeedback(message, type = 'info') {
            const feedback = document.getElementById('scannerFeedback');
            let icon = '';
            
            switch(type) {
                case 'success':
                    icon = '<i class="fas fa-check-circle mr-2"></i>';
                    feedback.className = 'text-center py-4 text-green-500';
                    break;
                case 'error':
                    icon = '<i class="fas fa-exclamation-circle mr-2"></i>';
                    feedback.className = 'text-center py-4 text-red-500';
                    break;
                case 'warning':
                    icon = '<i class="fas fa-exclamation-triangle mr-2"></i>';
                    feedback.className = 'text-center py-4 text-yellow-500';
                    break;
                case 'loading':
                    icon = '<i class="fas fa-circle-notch mr-2 animate-spin"></i>';
                    feedback.className = 'text-center py-4 text-purple-600';
                    break;
                default:
                    icon = '<i class="fas fa-info-circle mr-2"></i>';
                    feedback.className = 'text-center py-4 text-purple-600';
            }
            
            feedback.innerHTML = icon + message;
        }
        
        function formatCurrency(amount) {
            return 'Rp ' + amount.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
        
        // Validasi sebelum checkout
        function validateCartBeforeCheckout() {
            if (<?= count($_SESSION['cart']) ?> === 0) {
                showAlert('Keranjang belanja kosong, tidak bisa proses pembayaran!');
                return false;
            }
            return true;
        }

        // Update current date and time
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('currentDateTime').textContent = now.toLocaleDateString('id-ID', options);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            startCartTimer();
            updateDateTime();
            setInterval(updateDateTime, 60000); // Update every minute
            
            document.getElementById('scanButton').addEventListener('click', openScanner);
            
            // Logout confirmation
            document.querySelector('a[href="../service/logout.php"]').addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Anda yakin ingin logout?')) {
                    window.location.href = this.getAttribute('href');
                }
            });
        });
    </script>
</body>
</html>