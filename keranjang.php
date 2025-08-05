<?php
session_start();
include './service/connection.php';

// Set cart expiration time (10 seconds)
$cart_expiration = 5000; // 10 seconds

// Check if cart should be cleared
if (isset($_SESSION['cart_last_activity'])) {
    $inactive_time = time() - $_SESSION['cart_last_activity'];
    if ($inactive_time > $cart_expiration) {
        unset($_SESSION['cart']);
        $_SESSION['error'] = "Keranjang telah kadaluarsa karena tidak ada aktivitas selama 10 detik";
    }
}

// Handle expired parameter
if (isset($_GET['expired'])) {
    unset($_SESSION['cart']);
    header("Location: keranjang.php");
    exit();
}

// Update last activity time
$_SESSION['cart_last_activity'] = time();

// Error and success messages
if (isset($_SESSION['error'])) {
    echo '<div class="bg-red-100 text-red-700 p-2 rounded mb-4">' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}
if (isset($_SESSION['success'])) {
    echo '<div class="bg-green-100 text-green-700 p-2 rounded mb-4">' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
}

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Calculate total quantity and subtotal
$total_quantity = 0;
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $total_quantity += $item['jumlah'] ?? 0;
    if (isset($item['harga']) && isset($item['jumlah'])) {
        $subtotal += $item['harga'] * $item['jumlah'];
    }
}

// Check product stock and validate cart items
$cart_modified = false;
foreach ($_SESSION['cart'] as $id => $item) {
    $stmt = $conn->prepare("SELECT qty FROM products WHERE id = ?");
    $stmt->bind_param("i", $item['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $produk_db = $result->fetch_assoc();

    if (!$produk_db || $produk_db['qty'] <= 0) {
        unset($_SESSION['cart'][$id]);
        $cart_modified = true;
        $_SESSION['error'] = "Beberapa produk telah habis dan dihapus dari keranjang";
    } else {
        // Periksa apakah jumlah di keranjang melebihi stok
        if ($item['jumlah'] > $produk_db['qty']) {
            $_SESSION['cart'][$id]['jumlah'] = $produk_db['qty'];
            $cart_modified = true;
            $_SESSION['error'] = "Jumlah beberapa produk dikurangi karena stok terbatas";
        }
        $_SESSION['cart'][$id]['stok'] = $produk_db['qty'];
    }
}

if ($cart_modified) {
    header('Location: keranjang.php');
    exit();
}

// Handle item removal
if (isset($_GET['hapus'])) {
    unset($_SESSION['cart'][$_GET['hapus']]);
    $_SESSION['success'] = "Produk berhasil dihapus dari keranjang";
    header('Location: keranjang.php');
    exit();
}

// Handle quantity update
if (isset($_GET['update']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $jumlah = intval($_GET['jumlah']);

    // Validasi stok sebelum update
    $stmt = $conn->prepare("SELECT qty FROM products WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['cart'][$id]['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $produk_db = $result->fetch_assoc();
    
    if (!$produk_db) {
        $_SESSION['error'] = "Produk tidak ditemukan dalam sistem";
        header('Location: keranjang.php');
        exit();
    }
    
    $stok_tersedia = $produk_db['qty'] ?? 0;
    
    // Validasi 1: Tidak boleh melebihi stok yang tersedia
    if ($jumlah > $stok_tersedia) {
        $_SESSION['error'] = "Jumlah melebihi stok yang tersedia! Stok tersedia: $stok_tersedia";
        header('Location: keranjang.php');
        exit();
    }
    
    // Validasi 2: Maksimal 10 quantity di keranjang
    $new_total_quantity = $total_quantity - $_SESSION['cart'][$id]['jumlah'] + $jumlah;
    if ($new_total_quantity > 10) {
        $_SESSION['error'] = "Maksimal total 10 quantity di keranjang!";
        header('Location: keranjang.php');
        exit();
    }
    
    // Validasi 3: Jumlah harus positif
    if ($jumlah <= 0) {
        $_SESSION['error'] = "Jumlah harus lebih dari 0!";
        header('Location: keranjang.php');
        exit();
    }

    // Jika semua validasi passed, update jumlah
    $_SESSION['cart'][$id]['jumlah'] = $jumlah;
    $_SESSION['cart'][$id]['stok'] = $stok_tersedia;
    $_SESSION['success'] = "Jumlah produk berhasil diupdate";
    
    header('Location: keranjang.php');
    exit();
}

// Member check
$member_status = "";
$discount = 0;
$member_points = 0;
if (isset($_GET['cek_member'])) {
    $phone = $_GET['phone'];
    $stmt = $conn->prepare("SELECT * FROM member WHERE phone = ? AND status = 'active'");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $cek = $stmt->get_result();

    if ($cek->num_rows > 0) {
        $member = $cek->fetch_assoc();
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Keranjang - SmartCash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <style>
        .card {
            max-width: 100%;
            margin: 0 auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .cart-counter {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #779341;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .quantity-display {
            min-width: 30px;
            text-align: center;
        }
        .cart-summary {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 1rem;
            border-top: 1px solid #e2e8f0;
        }
        .input-money {
            transition: all 0.3s ease;
        }
        .input-money:focus {
            border-color: #779341;
            box-shadow: 0 0 0 3px rgba(119, 147, 65, 0.2);
        }
        .valid {
            border-color: #10B981;
            background-color: #ECFDF5;
        }
        .invalid {
            border-color: #EF4444;
            background-color: #FEE2E2;
        }
    </style>
</head>
<body class="bg-[#F1F9E4] font-sans">
    <div class="container mx-auto p-4">
        <div class="flex items-center mb-8 relative">
            <i onclick="goBack()" class="fas fa-arrow-left text-2xl mr-4 cursor-pointer text-[#61892F]"></i>
            <h1 class="text-3xl font-bold text-black">Smart <span class="text-[#779341]"> Cash</span></h1>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row space-y-4 lg:space-y-0 lg:space-x-4 px-4 lg:px-12">
        <!-- Cart Section -->
        <div class="card w-full lg:w-2/3 bg-white relative">
            <h2 class="text-xl font-bold mb-4">Keranjang Belanja</h2>

            <?php if (!empty($_SESSION['cart'])): ?>
                <div id="cart-timer" class="bg-yellow-100 text-yellow-800 p-2 rounded mb-4">
                     Keranjang akan otomatis kosong dalam: <span id="countdown">10</span> detik
                </div>
            <?php endif; ?>

            <?php if ($total_quantity >= 10): ?>
                <div class="bg-yellow-100 text-yellow-800 p-2 rounded mb-4">
                    Anda telah mencapai batas maksimal 10 quantity di keranjang.
                </div>
            <?php endif; ?>

            <div class="overflow-x-auto" style="max-height: 60vh; overflow-y: auto;">
                <table class="w-full text-left">
                    <thead>
                        <tr>
                            <th class="py-2">Produk</th>
                            <th class="py-2">Harga</th>
                            <th class="py-2">Jumlah</th>
                            <th class="py-2">Subtotal</th>
                            <th class="py-2">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($_SESSION['cart'])): ?>
                            <?php foreach ($_SESSION['cart'] as $id => $item): ?>
                                <tr class="border-b">
                                    <td class="py-2 flex items-center">
                                        <img src="<?= htmlspecialchars($item['gambar']) ?>" alt="<?= htmlspecialchars($item['nama']) ?>" class="w-12 h-12 mr-2" />
                                        <div>
                                            <p class="font-bold"><?= htmlspecialchars($item['nama']) ?></p>
                                            <p class="text-sm text-gray-500"><?= htmlspecialchars($item['kategori']) ?></p>
                                        </div>
                                    </td>
                                    <td class="py-2">Rp. <?= number_format($item['harga']) ?></td>
                                    <td class="py-2">
                                        <div class="quantity-control">
                                            <button onclick="updateJumlah(<?= $id ?>, <?= $item['jumlah'] - 1 ?>)"
                                                class="px-2 py-1 bg-red-500 text-white rounded"
                                                <?= $item['jumlah'] <= 1 ? 'disabled' : '' ?>>
                                                -
                                            </button>
                                            <span class="quantity-display"><?= $item['jumlah'] ?></span>
                                            <button onclick="updateJumlah(<?= $id ?>, <?= $item['jumlah'] + 1 ?>)"
                                                class="px-2 py-1 bg-green-500 text-white rounded"
                                                <?= ($total_quantity >= 10 || $item['jumlah'] >= $item['stok']) ? 'disabled' : '' ?>>
                                                +
                                            </button>
                                        </div>
                                    </td>
                                    <td class="py-2 text-green-600 font-semibold">Rp. <?= number_format($item['harga'] * $item['jumlah']) ?></td>
                                    <td class="py-2"><a href="?hapus=<?= $id ?>" class="text-red-500">Hapus</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">Keranjang kosong</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="cart-summary">
                <div class="mt-4">
                    <?php if ($discount > 0): ?>
                        <div class="flex justify-between text-sm mb-1">
                            <p>Subtotal:</p>
                            <p>Rp. <?= number_format($subtotal) ?></p>
                        </div>
                        <div class="flex justify-between text-sm mb-1">
                            <p>Diskon Member (<?= ($discount * 100) ?>%):</p>
                            <p class="text-green-600">- Rp. <?= number_format($subtotal * $discount) ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="flex justify-between font-bold text-lg">
                        <p>Total Pembayaran:</p>
                        <p class="text-red-500">Rp <?= number_format($total_after_discount) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Section -->
        <div class="card w-full lg:w-1/3 bg-white">
            <h2 class="text-xl font-bold mb-4">Info Pembayaran</h2>
            <form action="" method="GET">
                <label class="block mb-1">Nomor Telepon Member</label>
                <div class="flex">
                    <input type="text" name="phone" id="phone" class="border p-2 rounded w-full" placeholder="08xxx" 
                           value="<?= isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : '' ?>">
                    <button type="submit" name="cek_member" class="bg-blue-500 text-white px-4 rounded-r">Cek</button>
                </div>
                <?= $member_status ?>
            </form>

            <form action="./service/simpan_transaksi.php" method="POST" id="paymentForm">
                <input type="hidden" name="total" id="total" value="<?= $total_after_discount ?>">
                <input type="hidden" name="subtotal_before_discount" value="<?= $subtotal ?>">
                <input type="hidden" name="discount" value="<?= $discount ?>">
                <input type="hidden" name="phone" value="<?= isset($_GET['phone']) ? htmlspecialchars($_GET['phone']) : '' ?>">

                <div class="mt-4 mb-4">
                    <label class="block mb-1">Jumlah Uang</label>
                    <input name="nominal" id="nominal" 
                           class="w-full p-2 border rounded input-money" 
                           type="number"
                           required
                           min="0"
                           step="1"
                           placeholder="Masukkan jumlah uang">
                    <p id="amount-feedback" class="text-sm mt-1 hidden"></p>
                </div>

                <div class="mb-4">
                    <label class="block mb-1">Metode Pembayaran</label>
                    <select name="metode" class="w-full p-2 border rounded" required>
                        <option value="tunai">Tunai</option>
                        <option value="qris">QRIS</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block mb-1">Kembalian</label>
                    <input name="kembalian" id="kembalian" class="w-full p-2 border rounded bg-gray-100" type="number" readonly>
                </div>

                <button type="submit" id="submit-btn" class="w-full py-2 bg-green-600 text-white rounded hover:bg-green-700 transition" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>
                    <i></i> Bayar
                </button>
            </form>
        </div>
    </div>

    <script>
        function goBack() {
            window.history.back();
        }

        function updateJumlah(id, jumlah) {
            // Dapatkan stok tersedia dari data atribut
            const stokTersedia = parseInt(document.querySelector(`tr[data-id="${id}"]`).getAttribute('data-stok'));
            
            // Dapatkan jumlah saat ini
            const currentJumlah = parseInt(document.querySelector(`tr[data-id="${id}"] .quantity-display`).textContent);
            const newJumlah = currentJumlah + jumlah;
            
            // Validasi stok
            if (newJumlah > stokTersedia) {
                alert(`Jumlah melebihi stok yang tersedia! Stok tersedia: ${stokTersedia}`);
                return;
            }
            
            // Validasi minimal jumlah
            if (newJumlah <= 0) {
                return;
            }
            
            // Kirim permintaan update
            window.location.href = "?update=true&id=" + id + "&jumlah=" + newJumlah;
        }

        // Real-time change calculation
        document.getElementById("nominal").addEventListener("input", function() {
            const nominal = parseFloat(this.value) || 0;
            const total = parseFloat(document.getElementById("total").value) || 0;
            const kembalian = nominal - total;
            
            document.getElementById("kembalian").value = Math.max(0, kembalian).toFixed(0);
            
            // Visual feedback
            const feedback = document.getElementById("amount-feedback");
            if (nominal <= 0) {
                this.classList.remove("valid", "invalid");
                feedback.classList.add("hidden");
            } else if (nominal >= total) {
                this.classList.remove("invalid");
                this.classList.add("valid");
                feedback.textContent = `Pembayaran mencukupi (Kembalian: Rp ${kembalian.toLocaleString('id-ID')})`;
                feedback.classList.remove("hidden", "text-red-500");
                feedback.classList.add("text-green-600");
            } else {
                this.classList.remove("valid");
                this.classList.add("invalid");
                const kurang = total - nominal;
                feedback.textContent = `Kurang: Rp ${kurang.toLocaleString('id-ID')}`;
                feedback.classList.remove("hidden", "text-green-600");
                feedback.classList.add("text-red-500");
            }
        });

        // Form validation
        document.getElementById("paymentForm").addEventListener("submit", function(e) {
            const nominal = parseFloat(document.getElementById("nominal").value) || 0;
            const total = parseFloat(document.getElementById("total").value) || 0;
            
            if (nominal < total) {
                e.preventDefault();
                alert(`Nominal pembayaran kurang! Minimal: Rp ${total.toLocaleString('id-ID')}`);
                document.getElementById("nominal").focus();
            } else if (nominal <= 0) {
                e.preventDefault();
                alert("Masukkan jumlah uang yang valid!");
                document.getElementById("nominal").focus();
            }
        });

        // Initialize countdown timer for cart expiration
        document.addEventListener("DOMContentLoaded", function() {
            <?php if (!empty($_SESSION['cart'])): ?>
                let secondsLeft = 5000;
                
                function updateCountdown() {
                    document.getElementById("countdown").textContent = secondsLeft;
                    
                    if (secondsLeft <= 0) {
                        window.location.href = "keranjang.php?expired=1";
                    } else {
                        secondsLeft--;
                        setTimeout(updateCountdown, 1000);
                    }
                }
                
                updateCountdown();
                
                // Reset timer on any interaction
                document.addEventListener('click', function() {
                    secondsLeft = 5000;
                });
                document.addEventListener('keypress', function() {
                    secondsLeft = 5000;
                });
            <?php endif; ?>

            // Focus on nominal input when payment section is visible
            if (document.getElementById("nominal")) {
                document.getElementById("nominal").focus();
            }
        });
    </script>
</body>
</html>