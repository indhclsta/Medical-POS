<?php
session_start();
include 'connection.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID transaksi tidak valid']);
    exit;
}

$transaction_id = intval($_GET['id']);

// Ambil data member dari transaksi
$query = "SELECT m.phone 
          FROM transactions t
          JOIN member m ON t.fid_member = m.id
          WHERE t.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $transaction_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Transaksi bukan dari member']);
    exit;
}

$data = $result->fetch_assoc();
$phone = $data['phone'];

// Normalisasi nomor telepon
$phone = preg_replace('/[^0-9]/', '', $phone); // Hapus semua karakter non-digit

// Konversi ke format WhatsApp (62...)
if (substr($phone, 0, 1) === '0') {
    $phone = '62' . substr($phone, 1);
} elseif (substr($phone, 0, 3) === '+62') {
    $phone = '62' . substr($phone, 3);
} elseif (substr($phone, 0, 2) !== '62') {
    $phone = '62' . $phone;
}

echo json_encode(['success' => true, 'phone' => $phone]);
