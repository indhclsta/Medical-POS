<?php
require_once 'connection.php';

// Aktifkan error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set header JSON
header('Content-Type: application/json');

try {
    // Validasi input
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception("ID transaksi tidak valid");
    }

    $transactionId = (int)$_GET['id'];

    // 1. Ambil data transaksi utama
    $stmt = $conn->prepare("
        SELECT t.*, a.username as admin_name, m.name as member_name 
        FROM transactions t
        LEFT JOIN admin a ON t.fid_admin = a.id
        LEFT JOIN member m ON t.fid_member = m.id
        WHERE t.id = ?
    ");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $transactionId);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $transaction = $result->fetch_assoc();

    if (!$transaction) {
        // Debugging: Log available transaction IDs
        $debug = $conn->query("SELECT id FROM transactions ORDER BY id LIMIT 5");
        $sampleIds = $debug->fetch_all(MYSQLI_ASSOC);
        throw new Exception("Transaksi tidak ditemukan. Contoh ID yang ada: " . json_encode($sampleIds));
    }

    // 2. Ambil detail item transaksi dengan penanganan produk yang mungkin sudah dihapus
    $stmt = $conn->prepare("
        SELECT 
            td.id,
            td.product_id,
            COALESCE(p.product_name, 'Produk tidak tersedia') AS product_name,
            COALESCE(p.selling_price, 0) AS price,
            COALESCE(p.selling_price - p.starting_price, 0) AS margin,
            td.quantity,
            (td.quantity * COALESCE(p.selling_price, 0)) AS subtotal,
            (td.quantity * COALESCE(p.selling_price - p.starting_price, 0)) AS margin_total
        FROM transaction_details td
        LEFT JOIN products p ON td.product_id = p.id
        WHERE td.transaction_id = ?
    ");
    if (!$stmt) {
        throw new Exception("Prepare details failed: " . $conn->error);
    }
    $stmt->bind_param("i", $transactionId);
    if (!$stmt->execute()) {
        throw new Exception("Execute details failed: " . $stmt->error);
    }
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // 3. Format response
    $response = [
        'success' => true,
        'data' => [
            'id' => $transaction['id'],
            'date' => $transaction['date'],
            'admin_name' => $transaction['admin_name'],
            'member_name' => $transaction['member_name'] ?? '-',
            'total_price' => (float)$transaction['total_price'],
            'margin_total' => (float)$transaction['margin_total'],
            'payment_method' => $transaction['payment_method'],
            'paid_amount' => (float)$transaction['paid_amount'],
            'kembalian' => (float)$transaction['kembalian'],
            'details' => $items
        ]
    ];

    // Debugging
    error_log("Transaction details for ID $transactionId: " . json_encode($response));

    echo json_encode($response);
} catch (Exception $e) {
    error_log("Error in get_transaction_detail: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
