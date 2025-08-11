<?php
session_start();
include 'connection.php';

// Debugging: Check all available session
error_log("Session data: " . print_r($_SESSION, true));

// Strict security checks
if (!isset($_SESSION['email'], $_SESSION['role'], $_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Session tidak lengkap, silakan login kembali";
    header("Location: ../service/login.php");
    exit();
}

if ($_SESSION['role'] !== 'cashier') {
    $_SESSION['error_message'] = "Akses ditolak: Hanya kasir yang bisa melakukan transaksi";
    header("Location: ../cashier/dashboard.php");
    exit();
}

// Validate cart exists
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    $_SESSION['error_message'] = "Keranjang belanja kosong";
    header("Location: ../cashier/transaksi.php");
    exit();
}

$cashier_id = $_SESSION['user_id'];

// Validate input data
$required_fields = ['total', 'amount', 'payment_method'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        $_SESSION['error_message'] = "Field $field harus diisi";
        header("Location: ../cashier/checkout.php");
        exit();
    }
}

// Process data
$total = (float)$_POST['total'];
$amount = (float)$_POST['amount'];
$payment_method = $_POST['payment_method'];
$phone = !empty($_POST['phone']) ? $_POST['phone'] : null;
$discount = !empty($_POST['discount']) ? (float)$_POST['discount'] : 0.00;
$change = $amount - $total;

// Validate payment method
$allowed_payment_methods = ['tunai', 'qris'];
if (!in_array($payment_method, $allowed_payment_methods)) {
    $_SESSION['error_message'] = "Metode pembayaran tidak valid. Harus tunai atau qris";
    header("Location: ../cashier/checkout.php");
    exit();
}

// Validate payment amount
if ($amount < $total) {
    $_SESSION['error_message'] = "Nominal pembayaran kurang! Kurang " . format_currency($total - $amount);
    header("Location: ../cashier/checkout.php");
    exit();
}

function format_currency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Debug payment method
error_log("Processing transaction with payment method: " . $payment_method);

// Start transaction
$conn->begin_transaction();

try {
    // 1. Save main transaction
    $stmt = $conn->prepare("INSERT INTO transactions 
                          (fid_admin, total_price, payment_method, paid_amount, kembalian, final_total, points, discount, fid_member) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    // Find member if phone exists
    $member_id = null;
    if ($phone) {
        $stmt_member = $conn->prepare("SELECT id FROM member WHERE phone = ? AND status = 'active' LIMIT 1");
        $stmt_member->bind_param("s", $phone);
        $stmt_member->execute();
        $member_result = $stmt_member->get_result();
        if ($member_result->num_rows > 0) {
            $member = $member_result->fetch_assoc();
            $member_id = $member['id'];
        }
    }
    
    // Calculate points (1 point per Rp10,000)
    $points_earned = floor($total / 10000);
    
    $stmt->bind_param("isdiiidis", 
        $cashier_id,
        $total,
        $payment_method,
        $amount,
        $change,
        $total,
        $points_earned,
        $discount,
        $member_id
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Gagal menyimpan transaksi: " . $stmt->error);
    }
    
    $transaction_id = $conn->insert_id;
    
    // 2. Save transaction details and update stock
    foreach ($_SESSION['cart'] as $item) {
        // Validate item
        if (!isset($item['id'], $item['quantity'], $item['price'], $item['name'])) {
            throw new Exception("Format item keranjang tidak valid");
        }
        
        $product_id = (int)$item['id'];
        $quantity = (int)$item['quantity'];
        $price = (float)$item['price'];
        $subtotal = $price * $quantity;
        
        // Check product stock
        $stmt_check = $conn->prepare("SELECT qty FROM products WHERE id = ?");
        $stmt_check->bind_param("i", $product_id);
        $stmt_check->execute();
        $result = $stmt_check->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Produk tidak ditemukan");
        }
        
        $product = $result->fetch_assoc();
        if ($product['qty'] < $quantity) {
            throw new Exception("Stok produk " . $item['name'] . " tidak mencukupi");
        }
        
        // Save transaction detail
        $stmt_detail = $conn->prepare("INSERT INTO transaction_details 
                                     (transaction_id, product_id, quantity, subtotal, harga) 
                                     VALUES (?, ?, ?, ?, ?)");
        $stmt_detail->bind_param("iiiid", $transaction_id, $product_id, $quantity, $subtotal, $price);
        
        if (!$stmt_detail->execute()) {
            throw new Exception("Gagal menyimpan detail transaksi: " . $stmt_detail->error);
        }
        
        // Update product stock
        $stmt_stock = $conn->prepare("UPDATE products SET qty = qty - ? WHERE id = ?");
        $stmt_stock->bind_param("ii", $quantity, $product_id);
        
        if (!$stmt_stock->execute()) {
            throw new Exception("Gagal update stok produk: " . $stmt_stock->error);
        }
    }
    
    // 3. Update member points if exists
    if ($member_id) {
        $stmt_points = $conn->prepare("UPDATE member SET point = point + ? WHERE id = ?");
        $stmt_points->bind_param("ii", $points_earned, $member_id);
        
        if (!$stmt_points->execute()) {
            throw new Exception("Gagal update poin member: " . $stmt_points->error);
        }
    }
    
    // 4. Log the transaction activity
    $activity = "Melakukan transaksi #$transaction_id dengan total Rp " . number_format($total, 0, ',', '.');
    $stmt_log = $conn->prepare("INSERT INTO activity_logs (admin_id, username, activity) VALUES (?, ?, ?)");
    $stmt_log->bind_param("iss", $cashier_id, $_SESSION['username'], $activity);
    $stmt_log->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Prepare receipt data (optional for PDF generation)
    $receipt_data = [
        'transaction_id' => $transaction_id,
        'date' => date('d/m/Y H:i:s'),
        'cashier' => $_SESSION['username'],
        'member_phone' => $phone,
        'items' => $_SESSION['cart'],
        'subtotal' => array_reduce($_SESSION['cart'], function($carry, $item) {
            return $carry + ($item['price'] * $item['quantity']);
        }, 0),
        'discount' => $discount,
        'total' => $total,
        'amount_paid' => $amount,
        'change' => $change,
        'payment_method' => $payment_method
    ];
    
    // Clear cart
    unset($_SESSION['cart']);
    
    // Redirect to success page
    header("Location: ../service/sukses.php?id=$transaction_id");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = $e->getMessage();
    error_log("Error in transaction: " . $e->getMessage());
    header("Location: ../cashier/checkout.php");
    exit();
}