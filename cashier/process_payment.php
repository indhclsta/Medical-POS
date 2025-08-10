<?php
session_start();
require_once '../service/connection.php';

// Security checks
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../service/login.php");
    exit();
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error_message'] = "Invalid CSRF token";
    header("Location: checkout.php");
    exit();
}

// Validate required fields
if (empty($_POST['amount']) || empty($_POST['payment_method']) || !isset($_POST['total'])) {
    $_SESSION['error_message'] = "Data pembayaran tidak lengkap";
    header("Location: checkout.php");
    exit();
}

// Validate amounts
$amount = (int) str_replace('.', '', $_POST['amount']);
$total = (int) str_replace('.', '', $_POST['total']);
$change = $amount - $total;

if ($amount <= 0 || $total <= 0 || $change < 0) {
    $_SESSION['error_message'] = "Jumlah pembayaran tidak valid";
    header("Location: checkout.php");
    exit();
}

// Get additional data
$payment_method = $_POST['payment_method'];
$phone = !empty($_POST['phone']) ? $_POST['phone'] : null;
$discount = isset($_POST['discount']) ? (float)$_POST['discount'] : 0;
$subtotal_before_discount = isset($_POST['subtotal_before_discount']) ? (int)$_POST['subtotal_before_discount'] : $total;

// Start transaction
$conn->begin_transaction();

try {
    // 1. Create transaction record
    $invoice_number = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    $cashier_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("INSERT INTO transactions (
        invoice_number, 
        cashier_id, 
        member_phone, 
        subtotal, 
        discount, 
        total, 
        amount_paid, 
        change_amount, 
        payment_method,
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
    
    $stmt->bind_param("sisddidds", 
        $invoice_number,
        $cashier_id,
        $phone,
        $subtotal_before_discount,
        $discount,
        $total,
        $amount,
        $change,
        $payment_method
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Gagal menyimpan transaksi: " . $stmt->error);
    }
    
    $transaction_id = $conn->insert_id;
    $stmt->close();

    // 2. Save transaction items and update product stock
    foreach ($_SESSION['cart'] as $item) {
        // Insert transaction item
        $product_id = $item['id'];
        $quantity = $item['quantity'];
        $price = $item['price'];
        $subtotal = $quantity * $price;
        
        $stmt = $conn->prepare("INSERT INTO transaction_items (
            transaction_id, 
            product_id, 
            quantity, 
            price, 
            subtotal
        ) VALUES (?, ?, ?, ?, ?)");
        
        $stmt->bind_param("iiidd", $transaction_id, $product_id, $quantity, $price, $subtotal);
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal menyimpan item transaksi: " . $stmt->error);
        }
        $stmt->close();
        
        // Update product stock
        $stmt = $conn->prepare("UPDATE products SET qty = qty - ? WHERE id = ?");
        $stmt->bind_param("ii", $quantity, $product_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal mengupdate stok produk: " . $stmt->error);
        }
        $stmt->close();
    }

    // 3. Update member points if applicable
    if ($phone && $discount > 0) {
        // Calculate points earned (1 point per Rp 10,000 spent)
        $points_earned = floor($total / 10000);
        
        $stmt = $conn->prepare("UPDATE member SET point = point + ? WHERE phone = ?");
        $stmt->bind_param("is", $points_earned, $phone);
        
        if (!$stmt->execute()) {
            throw new Exception("Gagal mengupdate poin member: " . $stmt->error);
        }
        $stmt->close();
    }

    // Commit transaction
    $conn->commit();
    
    // 4. Prepare success data for receipt
    $_SESSION['receipt_data'] = [
        'invoice_number' => $invoice_number,
        'date' => date('Y-m-d H:i:s'),
        'items' => $_SESSION['cart'],
        'subtotal' => $subtotal_before_discount,
        'discount' => $discount,
        'total' => $total,
        'amount_paid' => $amount,
        'change' => $change,
        'payment_method' => $payment_method,
        'cashier' => $_SESSION['username'],
        'member_phone' => $phone
    ];
    
    // Clear cart
    unset($_SESSION['cart']);
    unset($_SESSION['cart_expiry']);
    
    // Redirect to receipt page
    header("Location: receipt.php");
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    error_log("Payment processing error: " . $e->getMessage());
    $_SESSION['error_message'] = "Terjadi kesalahan saat memproses pembayaran: " . $e->getMessage();
    header("Location: checkout.php");
    exit();
}
?>