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
$total = (int)$_POST['total'];
$amount = (int)$_POST['amount'];
$payment_method = $_POST['payment_method'];
$phone = !empty($_POST['phone']) ? $_POST['phone'] : null;
$discount = !empty($_POST['discount']) ? (float)$_POST['discount'] : 0.00;
$change = $amount - $total;

// Validate payment
if ($amount < $total) {
    $_SESSION['error_message'] = "Nominal pembayaran kurang! Kurang " . format_currency($total - $amount);
    header("Location: ../cashier/checkout.php");
    exit();
}

function format_currency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

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
    
    // 2. Save transaction details
    foreach ($_SESSION['cart'] as $item) {
        // Validate item
        if (!isset($item['id'], $item['quantity'], $item['price'], $item['name'])) {
            throw new Exception("Format keranjang tidak valid");
        }
        
        $product_id = $item['id'];
        $quantity = $item['quantity'];
        $price = $item['price'];
        $subtotal = $price * $quantity;
        
        // Save detail
        $stmt_detail = $conn->prepare("INSERT INTO transaction_details 
                                     (transaction_id, product_id, quantity, subtotal, harga) 
                                     VALUES (?, ?, ?, ?, ?)");
        $stmt_detail->bind_param("iiiid", $transaction_id, $product_id, $quantity, $subtotal, $price);
        
        if (!$stmt_detail->execute()) {
            throw new Exception("Gagal menyimpan detail transaksi: " . $stmt_detail->error);
        }
        
        // Update stock
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
    
    // Commit transaction
    $conn->commit();
    
    // Prepare receipt data
    $receipt_data = [
        'transaction_id' => $transaction_id,
        'date' => date('d/m/Y H:i:s'),
        'cashier' => $_SESSION['username'],
        'member_phone' => $phone,
        'items' => array_map(function($item) {
            return [
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'price' => $item['price'],
                'subtotal' => $item['price'] * $item['quantity']
            ];
        }, $_SESSION['cart']),
        'subtotal' => array_reduce($_SESSION['cart'], function($carry, $item) {
            return $carry + ($item['price'] * $item['quantity']);
        }, 0),
        'discount' => $discount,
        'discount_amount' => array_reduce($_SESSION['cart'], function($carry, $item) {
            return $carry + ($item['price'] * $item['quantity']);
        }, 0) * $discount,
        'total' => $total,
        'amount_paid' => $amount,
        'change' => $change
    ];
    
   
    
    // Clear cart data
    unset($_SESSION['cart'], $_SESSION['cart_expiry']);
    
    // Redirect to checkout to show receipt
    header("Location: ../service/sukses.php?id=$transaction_id");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = $e->getMessage();
    error_log("Error in transaction: " . $e->getMessage());
    header("Location: ../cashier/checkout.php");
    exit();
}