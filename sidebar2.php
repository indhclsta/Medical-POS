<?php
session_start();
include './service/connection.php';

if (!isset($_SESSION['username'])) {
    header("Location: ./service/login.php");
    exit;
}
$username = $_SESSION['username'];
$email = $_SESSION['email'];

$query = mysqli_query($conn, "SELECT * FROM admin WHERE email = '$email'");
$admin = mysqli_fetch_assoc($query);

$username = $admin['username'];
$image = !empty($admin['image']) ? 'uploads/' . $admin['image'] : 'default.jpg';

$jumlahKeranjang = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $jumlahKeranjang += $item['jumlah'];
    }
}

$queryCategories = "SELECT * FROM category";
$resultCategories = mysqli_query($conn, $queryCategories);

$queryProducts = "
    SELECT p.id, p.product_name, p.exp, p.qty, p.starting_price, p.selling_price, p.margin, p.fid_category, p.image, p.description, p.barcode, p.barcode_image, c.category,
           CASE WHEN p.exp <= CURDATE() THEN 1 ELSE 0 END as is_expired
    FROM products p
    JOIN category c ON p.fid_category = c.id";
$resultProducts = mysqli_query($conn, $queryProducts);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Transaksi - SmartCash</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
    <style>
        .category-card {
            padding: 12px 20px;
            border-radius: 8px;
            background-color: white;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.3s ease;
            margin-right: 8px;  
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .category-card:hover {
            background-color: #779341;
            color: white;
            transform: scale(1.05);
        }
        .category-card.active {
            background-color: #779341;
            color: white;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
            z-index: 50;
        }
        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            max-width: 600px;
            width: 100%;
            position: relative;
        }
        .close {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 24px;
            cursor: pointer;
            color: #61892F;
            font-weight: bold;
        }
        .sidebar {
            transition: transform 0.3s ease, width 0.3s ease;
            border-radius: 0 20px 20px 0;
            width: 250px;
            background-color: #61892F;
            position: fixed;
            top: 50px;
            left: 0;
            height: calc(100% - 50px);
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }
        .sidebar-hidden {
            transform: translateX(-100%);
        }
        .sidebar a {
            display: block;
            padding: 10px;
            text-decoration: none;
            color: #ffffff;
            font-weight: 500;
            transition: all 0.3s;
        }
        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            transform: translateX(5px);
        }
        .alert-box {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            background-color: #f44336;
            color: white;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
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
        .alert-box i {
            margin-right: 10px;
        }
        .expired-product {
            position: relative;
            opacity: 0.7;
        }
        .expired-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #f44336;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            z-index: 1;
        }
        .disabled-product {
            pointer-events: none;
            opacity: 0.6;
        }
        .success-alert {
            background-color: #4CAF50 !important;
        }
        /* USB Scanner Styles */
        #usbScannerInput {
            position: absolute;
            opacity: 0;
            height: 0;
            width: 0;
        }
        .scanner-feedback {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1rem;
            margin: 1rem 0;
            text-align: center;
            min-height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .scanner-feedback-text {
            font-size: 1.25rem;
            font-weight: bold;
            color: #61892F;
            letter-spacing: 0.1em;
        }
        @keyframes scanHighlight {
            0% { background-color: rgba(46, 204, 113, 0.3); }
            100% { background-color: transparent; }
        }
        .scan-highlight {
            animation: scanHighlight 2s ease-out;
        }
    </style>
</head>
<body class="bg-[#F1F9E4] font-sans">

    <!-- Header -->
    <div class="container mx-auto p-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-4">
                <i id="menuToggle" class="fas fa-bars text-2xl mr-4 cursor-pointer text-[#61892F] hover:text-[#4a6e24]"></i>
                <h1 class="text-3xl font-bold">Smart <span class="text-[#779341]">Cash</span></h1>
            </div>
            <div class="flex items-center gap-4">
                <div class="relative">
                    <input type="text" id="searchInput" placeholder="Cari produk..." class="px-3 py-2 rounded-lg border w-48 focus:outline-none" oninput="searchProducts()">
                    <i class="fas fa-search absolute right-3 top-3 text-gray-400"></i>
                </div>
                <a href="keranjang.php" class="relative">
                    <svg class="w-6 h-6 text-[#61892F]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-1.4 5M17 13l1.4 5M6 18a2 2 0 100 4 2 2 0 000-4zm12 0a2 2 0 100 4 2 2 0 000-4z" />
                    </svg>
                    <?php if ($jumlahKeranjang > 0): ?>
                    <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                        <?= $jumlahKeranjang ?>
                    </span>
                    <?php endif; ?>
                </a>
                <a href="profil.php" class="flex items-center hover:opacity-80 transition">
                    <img src="<?= htmlspecialchars($image) ?>" alt="Foto Profil" class="w-10 h-10 rounded-full object-cover inline-block border-2 border-[#61892F]">
                    <span class="text-[#1F2937] font-medium ml-2"><?= htmlspecialchars($username) ?></span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main -->
    <div class="max-w-6xl mx-auto">
        <header class="flex justify-between items-center p-4 shadow-md rounded-lg">
            <h1 class="text-2xl font-bold text-gray-700">Halaman Transaksi</h1>
            <button id="scanButton" class="flex items-center gap-2 py-2 px-4 bg-[#779341] text-white rounded-lg hover:bg-green-700">
                <i class="fas fa-qrcode text-xl"></i> Scan Produk
            </button>
        </header>

        <!-- Kategori -->
        <div class="category-container flex overflow-x-auto space-x-4 p-1 my-4">
            <div class="category-card active" onclick="filterByCategory('all')">Semua Produk</div>
            <?php while ($category = mysqli_fetch_assoc($resultCategories)): ?>
                <div class="category-card" onclick="filterByCategory('<?= htmlspecialchars($category['category']) ?>')">
                    <?= htmlspecialchars($category['category']) ?>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Produk -->
        <div id="productList" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
            <?php while ($product = mysqli_fetch_assoc($resultProducts)): ?>
                <div class="product-box p-4 bg-white shadow-lg rounded-lg relative <?= $product['is_expired'] ? 'expired-product disabled-product' : '' ?>"
                     data-id="<?= $product['id'] ?>"
                     data-name="<?= htmlspecialchars($product['product_name']) ?>"
                     data-category="<?= htmlspecialchars($product['category']) ?>"
                     data-stock="<?= $product['qty'] ?>"
                     data-price="Rp. <?= number_format($product['selling_price'], 0, ',', '.') ?>"
                     data-description="Deskripsi:<?= htmlspecialchars($product['description']) ?>"
                     data-exp="<?= $product['exp'] ?>"
                     data-barcode="<?= $product['barcode'] ?>"
                     data-expired="<?= $product['is_expired'] ?>">
                    
                    <?php if ($product['is_expired']): ?>
                        <div class="expired-badge">EXPIRED</div>
                    <?php endif; ?>
                    
                    <img src="./uploads/<?= htmlspecialchars($product['image']) ?>"
                         alt="<?= htmlspecialchars($product['product_name']) ?>"
                         class="w-full h-40 object-cover rounded-md cursor-pointer"
                         onclick="openModal(<?= $product['id'] ?>)">
                    <h3 class="font-semibold mt-2 text-lg text-gray-800 text-center"><?= htmlspecialchars($product['product_name']) ?></h3>
                    <p class="text-sm text-gray-500 text-center">Kategori: <?= htmlspecialchars($product['category']) ?></p>
                    <p class="text-sm text-gray-500 text-center">Stok: <?= $product['qty'] ?></p>
                    <p class="text-sm text-gray-500 text-center">Exp: <?= date('d-m-Y', strtotime($product['exp'])) ?></p>
                    <p class="font-bold text-center text-[#779341] text-xl">Rp. <?= number_format($product['selling_price'], 0, ',', '.') ?></p>
                    
                    <?php if ($product['is_expired']): ?>
                        <button class="w-full mt-3 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed" disabled>Produk Expired</button>
                    <?php else: ?>
                        <div class="flex justify-center items-center gap-2 mt-2">
                            <button class="px-3 py-2 bg-red-500 text-white rounded-lg" onclick="updateQuantity(<?= $product['id'] ?>, -1)">-</button>
                            <span id="quantity-<?= $product['id'] ?>" class="text-lg font-semibold">0</span>
                            <button class="px-3 py-2 bg-green-500 text-white rounded-lg" onclick="updateQuantity(<?= $product['id'] ?>, 1)">+</button>
                        </div>
                        <form action="add_to_cart.php" method="POST">
                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                            <input type="hidden" name="product_name" value="<?= htmlspecialchars($product['product_name']) ?>">
                            <input type="hidden" name="category" value="<?= htmlspecialchars($product['category']) ?>">
                            <input type="hidden" name="price" value="<?= $product['selling_price'] ?>">
                            <input type="hidden" name="image" value="./uploads/<?= htmlspecialchars($product['image']) ?>">
                            <input type="hidden" name="qty" id="qty-<?= $product['id'] ?>" value="0">
                            <?php if ($product['qty'] == 0): ?>
                                <button type="button" class="w-full mt-3 py-2 bg-gray-400 text-white rounded-lg cursor-not-allowed" disabled>Stok Habis</button>
                            <?php else: ?>
                                <button type="submit" class="w-full mt-3 py-2 bg-[#779341] text-white rounded-lg hover:bg-green-700" onclick="return prepareQuantity(<?= $product['id'] ?>)">Tambah ke Keranjang</button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Modal Detail Produk -->
    <div id="productModal" class="modal fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="modal-content bg-white p-6 rounded-lg max-w-xl w-full relative">
            <button class="close absolute top-2 right-2 text-2xl text-[#61892F] font-bold" onclick="closeModal()">&times;</button>
            <div id="modalProductDetails" class="text-center space-y-4">
                <!-- Konten akan diisi lewat JavaScript -->
            </div>
        </div>
    </div>

    <!-- Modal Scan Barcode -->
    <div id="scanModal" class="modal hidden fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="modal-content bg-white p-4 rounded-lg max-w-md w-full relative">
            <button class="close absolute top-2 right-2 text-2xl text-[#61892F]" onclick="closeScanModal()">&times;</button>
            <h2 class="text-xl font-semibold mb-4 text-center text-gray-800">Scan Barcode Produk</h2>
            <div id="reader" style="width: 100%;"></div>
            <div id="scanResult" class="mt-4 text-center text-gray-700"></div>
        </div>
    </div>

    <!-- Modal Scanner USB -->
    <div id="usbScannerModal" class="modal hidden fixed top-0 left-0 w-full h-full bg-black bg-opacity-50 z-50 flex items-center justify-center">
        <div class="modal-content bg-white p-6 rounded-lg max-w-md w-full relative">
            <button class="close absolute top-2 right-2 text-2xl text-[#61892F]" onclick="closeUsbScannerModal()">&times;</button>
            <h2 class="text-xl font-semibold mb-4 text-center text-gray-800">Scanner USB</h2>
            <div class="mb-4 text-center">
                <p class="text-gray-600">Arahkan scanner ke barcode produk dan scan seperti biasa</p>
            </div>
            
            <div class="scanner-feedback">
                <div id="usbScannerFeedback" class="scanner-feedback-text"></div>
            </div>
            
            <div id="usbScannerStatus" class="text-center py-4">
                <i class="fas fa-circle-notch fa-spin text-[#61892F] text-2xl"></i>
                <p class="mt-2 text-gray-700">Menunggu input dari scanner USB...</p>
            </div>
            
            <input type="text" id="usbScannerInput" class="absolute opacity-0" autocomplete="off">
        </div>
    </div>

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar sidebar-hidden">
        <div class="mb-6 px-2">
            <h3 class="text-white font-bold text-lg mb-2">Menu Navigasi</h3>
            <div class="h-px bg-white bg-opacity-20 mb-3"></div>
        </div>
        <ul class="space-y-2">
            <li><a href="home.php" class="flex items-center"><i class="fas fa-home mr-3 w-5 text-center"></i> Beranda</a></li>
            <li><a href="kategori.php" class="flex items-center"><i class="fas fa-tags mr-3 w-5 text-center"></i> Kategori</a></li>
            <li><a href="produk.php" class="flex items-center"><i class="fas fa-box-open mr-3 w-5 text-center"></i> Produk</a></li>
            <li><a href="member.php" class="flex items-center"><i class="fas fa-users mr-3 w-5 text-center"></i> Member</a></li>
            <li><a href="admin.php" class="flex items-center"><i class="fas fa-user-shield mr-3 w-5 text-center"></i> Admin</a></li>
            <li><a href="keranjang.php" class="flex items-center"><i class="fas fa-shopping-cart mr-3 w-5 text-center"></i> Transaksi</a></li>
            <li><a href="laporan_input.php" class="flex items-center"><i class="fas fa-file-alt mr-3 w-5 text-center"></i> Laporan</a></li>
            <li class="mt-8 pt-4 border-t border-white border-opacity-20">
                <a href="./service/logout.php" id="logoutBtn" class="flex items-center text-red-200 font-semibold"><i class="fas fa-sign-out-alt mr-3 w-5 text-center"></i> Logout</a>
            </li>
        </ul>
    </div>

    <!-- Script -->
    <script>
    // Sidebar toggle
    const sidebar = document.getElementById('sidebar');
    const menuIcon = document.querySelector('.fa-bars');

    menuIcon.addEventListener('click', () => {
        sidebar.classList.toggle('sidebar-hidden');
    });

    // Alert system
    function showAlert(message, type = 'error') {
        const alertBox = document.createElement('div');
        alertBox.className = `alert-box ${type === 'success' ? 'success-alert' : ''}`;
        alertBox.innerHTML = `
            <i class="fas ${type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'}"></i>
            <span>${message}</span>
        `;
        document.body.appendChild(alertBox);
        
        setTimeout(() => {
            alertBox.remove();
        }, 3000);
    }

    // Product modal functions
    function openModal(productId) {
        const product = document.querySelector(`.product-box[data-id='${productId}']`);
        if (product.classList.contains('disabled-product')) {
            showAlert("Produk ini sudah expired dan tidak dapat dilihat detailnya");
            return;
        }

        const modal = document.getElementById('productModal');
        const modalDetails = document.getElementById('modalProductDetails');

        const name = product.getAttribute('data-name');
        const category = product.getAttribute('data-category');
        const stock = product.getAttribute('data-stock');
        const price = product.getAttribute('data-price');
        const barcode = product.getAttribute('data-barcode');
        const description = product.getAttribute('data-description');
        const exp = product.getAttribute('data-exp');

        // Format tanggal kadaluarsa
        const expDate = new Date(exp);
        const formattedExp = expDate.toLocaleDateString('id-ID', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });

        modalDetails.innerHTML = `
            <h2 class="text-2xl font-bold text-gray-800">${name}</h2>
            <p class="text-[#61892F] font-medium">Kategori: ${category}</p>
            <p class="text-gray-700">Stok: ${stock}</p>
            <p class="text-gray-700">Exp: ${formattedExp}</p>
            <p class="text-xl text-[#779341] font-bold">${price}</p>
            <p class="text-gray-600">${description}</p>
        `;

        modal.classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('productModal').classList.add('hidden');
    }

    // Quantity management
    function updateQuantity(productId, amount) {
        const productElement = document.querySelector(`.product-box[data-id="${productId}"]`);
        if (productElement.classList.contains('disabled-product')) {
            showAlert("Produk ini sudah expired dan tidak dapat ditambahkan");
            return;
        }

        const quantitySpan = document.getElementById('quantity-' + productId);
        const stock = parseInt(productElement.getAttribute('data-stock'));
        
        let currentQuantity = parseInt(quantitySpan.textContent) + amount;
        
        if (currentQuantity < 0) {
            currentQuantity = 0;
        }
        
        if (currentQuantity > stock) {
            showAlert(`Stok tidak mencukupi! Stok tersedia: ${stock}`);
            currentQuantity = stock;
        }
        
        quantitySpan.textContent = currentQuantity;
    }

    // Cart preparation
    function prepareQuantity(productId) {
        const productElement = document.querySelector(`.product-box[data-id="${productId}"]`);
        
        // Cek apakah produk sudah expired
        const isExpired = productElement.getAttribute('data-expired') === '1';
        if (isExpired) {
            showAlert("Produk ini sudah expired dan tidak dapat ditambahkan ke keranjang");
            return false;
        }

        const qty = parseInt(document.getElementById('quantity-' + productId).textContent);
        if (qty <= 0) {
            showAlert("Jumlah harus lebih dari 0!");
            return false;
        }

        const stock = parseInt(productElement.getAttribute('data-stock'));
        
        if (qty > stock) {
            showAlert(`Stok tidak mencukupi! Stok tersedia: ${stock}`);
            return false;
        }

        const existingTotal = <?= json_encode(array_reduce($_SESSION['cart'] ?? [], fn($carry, $item) => $carry + $item['jumlah'], 0)) ?>;

        if (existingTotal + qty > 10) {
            showAlert("Maksimal total 10 produk di keranjang!");
            return false;
        }

        document.getElementById('qty-' + productId).value = qty;
        return true;
    }

    // Product filtering
    function filterByCategory(category) {
        document.querySelectorAll('.category-card').forEach(card => card.classList.remove('active'));
        event.target.classList.add('active');

        document.querySelectorAll('.product-box').forEach(product => {
            const productCategory = product.getAttribute('data-category');
            const showProduct = (category === 'all' || productCategory === category);
            product.style.display = showProduct ? 'block' : 'none';
        });
    }

    // Product search
    function searchProducts() {
        const input = document.getElementById('searchInput').value.toLowerCase();
        document.querySelectorAll('.product-box').forEach(product => {
            const name = product.getAttribute('data-name').toLowerCase();
            const category = product.getAttribute('data-category').toLowerCase();
            product.style.display = (name.includes(input) || category.includes(input)) ? 'block' : 'none';
        });
    }

    // Barcode scanner
    const scanModal = document.getElementById("scanModal");
    let html5QrcodeScanner = null;

    // =============================================
    // IMPLEMENTASI SCANNER USB YANG DIPERBAIKI
    // =============================================
    let usbScannerModal = document.getElementById("usbScannerModal");
    let usbScannerInput = document.getElementById("usbScannerInput");
    let usbScannerFeedback = document.getElementById("usbScannerFeedback");
    let usbScannerStatus = document.getElementById("usbScannerStatus");
    let usbScannerTimeout;
    let scannedBarcode = '';
    let isScannerModalOpen = false;

    document.getElementById("scanButton").addEventListener("click", () => {
        if (confirm("Gunakan kamera (OK) atau scanner USB (Cancel)?")) {
            scanModal.classList.remove("hidden");
            startScanner();
        } else {
            openUsbScannerModal();
        }
    });

    function openUsbScannerModal() {
        usbScannerModal.classList.remove("hidden");
        isScannerModalOpen = true;
        scannedBarcode = '';
        usbScannerFeedback.textContent = '';
        
        usbScannerStatus.innerHTML = `
            <i class="fas fa-circle-notch fa-spin text-[#61892F] text-2xl"></i>
            <p class="mt-2 text-gray-700">Menunggu input dari scanner USB...</p>
        `;
        
        setTimeout(() => {
            usbScannerInput.focus();
        }, 100);
        
        document.addEventListener('keydown', handleUsbScannerInput);
    }

    function closeUsbScannerModal() {
        usbScannerModal.classList.add("hidden");
        isScannerModalOpen = false;
        document.removeEventListener('keydown', handleUsbScannerInput);
        clearTimeout(usbScannerTimeout);
    }

    function handleUsbScannerInput(e) {
        if (!isScannerModalOpen) return;
        
        // Abaikan tombol modifier
        if (e.ctrlKey || e.altKey || e.metaKey) return;
        
        // Jika tombol Enter (akhir scan)
        if (e.key === 'Enter') {
            e.preventDefault();
            if (scannedBarcode.length > 0) {
                processScannedBarcode(scannedBarcode);
            }
            return;
        }
        
        // Abaikan tombol khusus
        if (['Shift', 'Control', 'Alt', 'Meta', 'CapsLock', 'Tab', 'Escape'].includes(e.key)) {
            return;
        }
        
        // Tambahkan karakter ke barcode
        scannedBarcode += e.key;
        usbScannerFeedback.textContent = scannedBarcode;
        
        // Reset timeout
        clearTimeout(usbScannerTimeout);
        usbScannerTimeout = setTimeout(() => {
            if (scannedBarcode.length > 0) {
                processScannedBarcode(scannedBarcode);
            }
        }, 100);
    }

    function processScannedBarcode(barcode) {
        barcode = barcode.trim();
        
        if (barcode.length === 0) return;
        
        usbScannerStatus.innerHTML = `
            <i class="fas fa-check-circle text-green-500 text-2xl"></i>
            <p class="mt-2 text-gray-700">Memproses barcode: ${barcode}</p>
        `;
        
        findProductByBarcode(barcode);
        
        // Reset untuk scan berikutnya
        scannedBarcode = '';
        usbScannerFeedback.textContent = '';
        
        setTimeout(() => {
            closeUsbScannerModal();
        }, 2000);
    }

    function startScanner() {
        const qrRegionId = "reader";
        
        if (html5QrcodeScanner) {
            html5QrcodeScanner.clear().catch(error => {
                console.error("Failed to clear previous scanner:", error);
            });
        }

        html5QrcodeScanner = new Html5Qrcode(qrRegionId);

        const config = { 
            fps: 10, 
            qrbox: { width: 250, height: 250 },
            rememberLastUsedCamera: true,
            supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
        };

        html5QrcodeScanner.start(
            { facingMode: "environment" },
            config,
            onScanSuccess,
            onScanError
        ).catch(err => {
            console.error("Failed to start scanner:", err);
            showAlert("Gagal memulai scanner. Pastikan akses kamera diizinkan.");
            closeScanModal();
        });
    }

    function onScanSuccess(decodedText, decodedResult) {
        console.log(`Scan result: ${decodedText}`, decodedResult);
        document.getElementById("scanResult").innerHTML = `Barcode: ${decodedText}`;
        
        html5QrcodeScanner.stop().then(() => {
            console.log("Scanner stopped after successful scan");
            findProductByBarcode(decodedText);
            closeScanModal();
        }).catch(err => {
            console.error("Failed to stop scanner:", err);
            findProductByBarcode(decodedText);
            closeScanModal();
        });
    }

    function onScanError(errorMessage) {
        console.log(`Scan error: ${errorMessage}`);
    }

    async function findProductByBarcode(barcode) {
        try {
            const response = await fetch(`./service/check_barcode.php?barcode=${encodeURIComponent(barcode)}`);
            const data = await response.json();
            
            if (data.success && data.product_id) {
                const product = document.querySelector(`.product-box[data-id="${data.product_id}"]`);
                if (product) {
                    // Scroll dan highlight produk
                    product.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    product.classList.add('scan-highlight');
                    
                    // Cek status produk
                    if (data.is_expired) {
                        showAlert(`Produk "${data.product_name}" sudah expired`, "error");
                        return;
                    }
                    
                    if (data.is_out_of_stock) {
                        showAlert(`Stok produk "${data.product_name}" habis`, "error");
                        return;
                    }
                    
                    // Auto add to cart
                    const stock = parseInt(product.getAttribute('data-stock'));
                    const addButton = product.querySelector('button[type="submit"]');
                    
                    if (stock > 0 && addButton && !addButton.disabled) {
                        document.getElementById(`quantity-${data.product_id}`).textContent = 1;
                        document.getElementById(`qty-${data.product_id}`).value = 1;
                        addButton.click();
                        showAlert(`Produk "${data.product_name}" berhasil ditambahkan`, "success");
                    }
                    
                    return;
                }
            }
            
            showAlert(data.message || "Produk tidak ditemukan");
        } catch (error) {
            console.error("Error finding product by barcode:", error);
            showAlert("Terjadi kesalahan saat mencari produk");
        }
    }
    
    // Logout confirmation
    document.getElementById("logoutBtn").addEventListener("click", function(e) {
        e.preventDefault();
        if (confirm('Anda yakin ingin logout?')) {
            window.location.href = "./service/logout.php";
        }
    });

    // Check expired products on page load
    document.addEventListener('DOMContentLoaded', function() {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        
        document.querySelectorAll('.product-box').forEach(product => {
            const expDateStr = product.getAttribute('data-exp');
            const expDate = new Date(expDateStr);
            expDate.setHours(0, 0, 0, 0);
            
            if (expDate < today) {
                product.classList.add('expired-product', 'disabled-product');
            }
        });
    });
</script>
</body>
</html>