<?php
session_start();
include '../service/connection.php';

// Check if user is cashier
if (!isset($_SESSION['email'])) {
    header("Location: ../service/login.php");
    exit();
}

// Initialize variables
$error = '';
$success = '';
$formData = [
    'id' => '',
    'name' => '',
    'phone' => '',
    'point' => 0,
    'status' => 'active'
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['simpan'])) {
        // Sanitize and validate input
        $formData['id'] = $_POST['id'] ?? null;
        $formData['name'] = trim($_POST['name'] ?? '');
        $formData['phone'] = trim($_POST['phone'] ?? '');
        $formData['point'] = intval($_POST['point'] ?? 0);
        $formData['status'] = in_array($_POST['status'] ?? '', ['active', 'non-active']) ? $_POST['status'] : 'active';
        
        // Validate inputs
        $valid = true;
        
        if (empty($formData['name'])) {
            $_SESSION['error'] = "Nama harus diisi";
            $valid = false;
        }
        
        // Clean phone number
        $cleaned_phone = preg_replace('/[^0-9]/', '', $formData['phone']);
        
        if (empty($cleaned_phone)) {
            $_SESSION['error'] = "Nomor telepon harus diisi";
            $valid = false;
        } elseif (strlen($cleaned_phone) < 10 || strlen($cleaned_phone) > 15) {
            $_SESSION['error'] = "Nomor telepon harus 10-15 digit";
            $valid = false;
        } else {
            // Check for duplicate phone
            $checkQuery = "SELECT name FROM member WHERE phone = ?";
            if ($formData['id']) {
                $checkQuery .= " AND id != ?";
            }
            
            $stmt = $conn->prepare($checkQuery);
            if ($formData['id']) {
                $stmt->bind_param("si", $cleaned_phone, $formData['id']);
            } else {
                $stmt->bind_param("s", $cleaned_phone);
            }
            $stmt->execute();
            $checkResult = $stmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $duplicate = $checkResult->fetch_assoc();
                $_SESSION['error'] = "Nomor ini sudah digunakan oleh: " . htmlspecialchars($duplicate['name']);
                $valid = false;
            }
        }
        
        if ($valid) {
            if (empty($formData['id'])) {
                // Insert new member
                $query = "INSERT INTO member (name, phone, point, status, last_active) 
                          VALUES (?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssis", $formData['name'], $cleaned_phone, $formData['point'], $formData['status']);
                $message = "Member baru berhasil ditambahkan";
            } else {
                // Update existing member
                $query = "UPDATE member SET 
                          name = ?,
                          phone = ?,
                          status = ?,
                          last_active = IF(status = 'active', NOW(), last_active)
                          WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssi", $formData['name'], $cleaned_phone, $formData['status'], $formData['id']);
                $message = "Data member berhasil diperbarui";
            }
            
            if ($stmt->execute()) {
                $_SESSION['message'] = $message;
                $_SESSION['message_type'] = 'success';
                header("Location: manage_member.php");
                exit();
            } else {
                $_SESSION['error'] = "Terjadi kesalahan database: " . $stmt->error;
            }
        }
    }
    elseif (isset($_POST['hapus'])) {
        // Handle member deletion with status check
        $id = intval($_POST['id']);
        
        // First check member status
        $status_query = "SELECT status FROM member WHERE id = ?";
        $stmt = $conn->prepare($status_query);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $_SESSION['message'] = "Member tidak ditemukan";
            $_SESSION['message_type'] = 'error';
        } else {
            $member = $result->fetch_assoc();
            
            if ($member['status'] == 'active') {
                $_SESSION['message'] = "Tidak bisa menghapus member yang sedang aktif";
                $_SESSION['message_type'] = 'error';
            } else {
                // Proceed with deletion
                $delete_query = "DELETE FROM member WHERE id = ?";
                $stmt = $conn->prepare($delete_query);
                $stmt->bind_param("i", $id);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = "Member berhasil dihapus";
                    $_SESSION['message_type'] = 'success';
                } else {
                    $_SESSION['message'] = "Gagal menghapus member";
                    $_SESSION['message_type'] = 'error';
                }
            }
        }
        header("Location: manage_member.php");
        exit();
    } elseif (isset($_POST['activate'])) {
        // Handle member activation
        $id = intval($_POST['id']);
        $query = "UPDATE member SET status = 'active', last_active = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Member berhasil diaktifkan kembali";
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = "Gagal mengaktifkan member";
            $_SESSION['message_type'] = 'error';
        }
        header("Location: manage_member.php");
        exit();
    }
}

// If we get here, there was an error - store data in session and redirect back
$_SESSION['form_data'] = $formData;
header("Location: manage_member.php" . ($formData['id'] ? "?edit={$formData['id']}" : "?add=1"));
exit();