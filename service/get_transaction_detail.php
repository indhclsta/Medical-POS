<?php
require_once 'connection.php';

// Cek apakah parameter id ada
if (!isset($_GET['id'])) {
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['error' => 'ID transaksi tidak ditemukan']);
    exit();
}

$transactionId = $_GET['id'];

// Query untuk mendapatkan detail transaksi
$transactionQuery = "SELECT t.*, a.username as admin_name, m.name as member_name 
                    FROM transactions t
                    LEFT JOIN admin a ON t.fid_admin = a.id
                    LEFT JOIN member m ON t.fid_member = m.id
                    WHERE t.id = $transactionId";
$transactionResult = mysqli_query($conn, $transactionQuery);
$transaction = mysqli_fetch_assoc($transactionResult);

if (!$transaction) {
    header("HTTP/1.1 404 Not Found");
    echo json_encode(['error' => 'Transaksi tidak ditemukan']);
    exit();
}

// Query untuk mendapatkan item transaksi
$itemsQuery = "SELECT td.*, p.product_name, p.margin 
          FROM transaction_details td
          JOIN products p ON td.product_id = p.id
          WHERE td.transaction_id = $transactionId";
$itemsResult = mysqli_query($conn, $itemsQuery);
$items = [];
while ($item = mysqli_fetch_assoc($itemsResult)) {
    $items[] = $item;
}

// Gabungkan data
$response = [
    ...$transaction,
    'details' => $items
];

header('Content-Type: application/json');
echo json_encode($response);
?>