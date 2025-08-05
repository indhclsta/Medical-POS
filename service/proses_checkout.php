<?php
session_start();
require 'connection.php';

// 1. Simpan transaksi ke database
$sql = "INSERT INTO transactions (date, fid_admin, total_price, payment_method) 
        VALUES (NOW(), ?, ?, 'tunai')";
$stmt = $conn->prepare($sql);
$stmt->bind_param("id", $_SESSION['user_id'], $_POST['total']);
$stmt->execute();
$transaction_id = $conn->insert_id;

// 2. Simpan detail transaksi
foreach ($_SESSION['cart'] as $product_id => $item) {
    $sql_detail = "INSERT INTO transaction_details 
                  (transaction_id, product_id, quantity, subtotal) 
                  VALUES (?, ?, ?, ?)";
    $stmt_detail = $conn->prepare($sql_detail);
    $subtotal = $item['harga'] * $item['jumlah'];
    $stmt_detail->bind_param("iiid", $transaction_id, $product_id, $item['jumlah'], $subtotal);
    $stmt_detail->execute();
    
    // Update stok
    $sql_update = "UPDATE products SET qty = qty - ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ii", $item['jumlah'], $product_id);
    $stmt_update->execute();
}

// 3. Kosongkan keranjang dan redirect
unset($_SESSION['cart']);
header("Location: sukses.php?id=$transaction_id");
exit;
?>