<?php
// Pastikan tidak ada output sebelum header
if (ob_get_level()) ob_clean();

// Error reporting di awal
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__.'/barcode_errors.log');

require_once 'connection.php';

// Set headers pertama kali
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

function sendResponse($success, $message = '', $data = []) {
    http_response_code($success ? 200 : 400);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

try {
    // Validasi method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception("Method not allowed", 405);
    }

    // Validasi parameter
    if (!isset($_GET['barcode']) || empty($barcode = trim($_GET['barcode']))) {
        throw new Exception("Barcode parameter is required", 400);
    }

    // Validasi panjang barcode
    if (strlen($barcode) < 3) {
        throw new Exception("Barcode too short (min 3 characters)", 400);
    }

    $cleanBarcode = $conn->real_escape_string($barcode);

    // Query produk
    $query = $conn->prepare("SELECT p.id, p.product_name, p.selling_price, 
                            p.qty as stock, p.image, c.category, p.barcode, p.exp
                            FROM products p
                            JOIN category c ON p.fid_category = c.id
                            WHERE TRIM(p.barcode) = ? 
                            AND (p.exp IS NULL OR p.exp >= CURDATE())
                            AND p.qty > 0");
    
    if (!$query) throw new Exception("Database error: ".$conn->error, 500);
    
    $query->bind_param("s", $cleanBarcode);
    if (!$query->execute()) throw new Exception("Query error: ".$query->error, 500);
    
    $result = $query->get_result();
    
    if ($result->num_rows > 0) {
        $product = $result->fetch_assoc();
        sendResponse(true, 'Product found', [
            'id' => $product['id'],
            'name' => $product['product_name'],
            'price' => $product['selling_price'],
            'stock' => $product['stock'],
            'image' => $product['image'],
            'category' => $product['category'],
            'barcode' => $product['barcode'],
            'expiry' => $product['exp']
        ]);
    } else {
        // Cek masalah stok/kadaluarsa
        $check = $conn->prepare("SELECT product_name, 
                                exp < CURDATE() as expired, 
                                qty <= 0 as out_of_stock 
                                FROM products WHERE TRIM(barcode) = ?");
        $check->bind_param("s", $cleanBarcode);
        $check->execute();
        $problem = $check->get_result()->fetch_assoc();
        
        if ($problem) {
            if ($problem['expired']) throw new Exception("Product expired", 400);
            if ($problem['out_of_stock']) throw new Exception("Product out of stock", 400);
        }
        throw new Exception("Product not found", 404);
    }
} catch (Exception $e) {
    sendResponse(false, $e->getMessage());
} finally {
    if (isset($conn)) $conn->close();
}