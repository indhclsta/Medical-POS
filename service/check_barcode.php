<?php
session_start();
include 'connection.php';

$barcode = $_GET['barcode'] ?? '';

// Query untuk mencari produk berdasarkan barcode
$query = "SELECT id FROM products WHERE barcode = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $barcode);
$stmt->execute();
$result = $stmt->get_result();

$response = [
    'success' => false,
    'product_id' => null
];

if ($result->num_rows > 0) {
    $product = $result->fetch_assoc();
    $response = [
        'success' => true,
        'product_id' => $product['id']
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
?>