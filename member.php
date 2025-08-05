<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include './service/connection.php';

// Check if user is admin
$email = $_SESSION['email'] ?? '';
$admin = null;

// Use prepared statement for admin check
$stmt = $conn->prepare("SELECT * FROM admin WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

if (!$admin) {
    header("Location: login.php");
    exit();
}

$username = htmlspecialchars($admin['username']);
$image = !empty($admin['image']) ? 'uploads/' . htmlspecialchars($admin['image']) : 'default.jpg';

// Initialize form data
$formData = [
    'id' => '',
    'name' => '',
    'phone' => '',
    'point' => 0,
    'status' => 'active'
];

$errors = [
    'name' => '',
    'phone' => '',
    'general' => ''
];

// Check inactive members (5 minutes)
$inactive_query = "UPDATE member SET status = 'non-active' 
                  WHERE status = 'active' 
                  AND last_active < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
if (!mysqli_query($conn, $inactive_query)) {
    error_log("Failed to update inactive members: " . mysqli_error($conn));
}

// Process form submissions
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
            $errors['name'] = "Nama harus diisi";
            $valid = false;
        }
        
        // Clean phone number - remove all non-digit characters
        $cleaned_phone = preg_replace('/[^0-9]/', '', $formData['phone']);
        
        if (empty($cleaned_phone)) {
            $errors['phone'] = "Nomor telepon harus diisi";
            $valid = false;
        } elseif (strlen($cleaned_phone) < 10 || strlen($cleaned_phone) > 15) {
            $errors['phone'] = "Harus 10-15 digit";
            $valid = false;
        } else {
            // Check for duplicate phone using prepared statement
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
                $_SESSION['duplicate_error'] = "Nomor ini sudah digunakan oleh: " . htmlspecialchars($duplicate['name']);
                $_SESSION['form_data'] = $formData;
                header("Location: member.php" . ($formData['id'] ? "?edit={$formData['id']}" : "?add=1"));
                exit();
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
                          status = ?
                          WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("sssi", $formData['name'], $cleaned_phone, $formData['status'], $formData['id']);
                $message = "Data member berhasil diperbarui";
            }
            
            if ($stmt->execute()) {
                $_SESSION['message'] = $message;
                $_SESSION['message_type'] = 'success';
            } else {
                error_log("Database error: " . $stmt->error);
                $_SESSION['message'] = "Terjadi kesalahan database";
                $_SESSION['message_type'] = 'error';
            }
            header("Location: member.php");
            exit();
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
                    error_log("Delete failed: " . $stmt->error);
                    $_SESSION['message'] = "Gagal menghapus member";
                    $_SESSION['message_type'] = 'error';
                }
            }
        }
        header("Location: member.php");
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
            error_log("Activation failed: " . $stmt->error);
            $_SESSION['message'] = "Gagal mengaktifkan member";
            $_SESSION['message_type'] = 'error';
        }
        header("Location: member.php");
        exit();
    }
}

// Get all members
$query = "SELECT * FROM member ORDER BY id ASC";
$members = mysqli_query($conn, $query);
if (!$members) {
    error_log("Failed to fetch members: " . mysqli_error($conn));
    $members = [];
} else {
    $members = mysqli_fetch_all($members, MYSQLI_ASSOC);
}

// Check edit mode
$editMode = isset($_GET['edit']);
if ($editMode) {
    $id = intval($_GET['edit']);
    $query = "SELECT * FROM member WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $formData = array_merge($formData, $result->fetch_assoc());
    } else {
        $editMode = false;
        $_SESSION['message'] = "Member tidak ditemukan";
        $_SESSION['message_type'] = 'error';
        header("Location: member.php");
        exit();
    }
}

// Get form data from session if exists
if (isset($_SESSION['form_data'])) {
    $formData = array_merge($formData, $_SESSION['form_data']);
    unset($_SESSION['form_data']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member - SmartCash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #1F2937;
        }

        .sidebar {
            transition: transform 0.3s ease, width 0.3s ease;
            border-radius: 0 20px 20px 0;
            width: 250px;
            background-color: #61892F;
            position: fixed;
            top: 50px;
            left: 0;
            height: calc(100% - 50px);
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }

        .sidebar-hidden {
            transform: translateX(-100%);
        }

        .sidebar a {
            display: block;
            padding: 10px;
            text-decoration: none;
            color: #ffffff;
            font-weight: 500;
            transition: all 0.3s;
        }

        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            transform: translateX(5px);
        }
        .error-message { color: #dc2626; font-size: 0.8rem; }
        .modal { background-color: rgba(0,0,0,0.5); }
        .status-active { background-color: #dcfce7; color: #166534; }
        .status-inactive { background-color: #fee2e2; color: #991b1b; }
        .btn-disabled { opacity: 0.5; cursor: not-allowed; }
    </style>
</head>
<body class="bg-[#F1F9E4]">
    <div class="container mx-auto p-4">
        <div class="flex items-center mb-8 relative border-b pb-4 border-[#D1D5DB]">
            <i id="menuToggle" class="fas fa-bars text-2xl mr-4 cursor-pointer text-[#61892F] hover:text-[#4a6e24]"></i>
            <h1 class="text-3xl font-bold text-[#1F2937]">Smart <span class="text-[#61892F]">Cash</span></h1>
            <div class="ml-auto flex items-center space-x-4">
                <a href="profil.php" class="flex items-center hover:opacity-80 transition">
                    <img src="<?= $image ?>" alt="Foto Profil" class="w-10 h-10 rounded-full object-cover inline-block border-2 border-[#61892F]">
                    <span class="text-[#1F2937] font-medium ml-2"><?= $username ?></span>
                </a>
            </div>
        </div>

        <!-- Sidebar -->
        <div id="sidebar" class="sidebar sidebar-hidden">
            <div class="mb-6 px-2">
                <h3 class="text-white font-bold text-lg mb-2">Menu Navigasi</h3>
                <div class="h-px bg-white bg-opacity-20 mb-3"></div>
            </div>
            <ul class="space-y-2">
                <li><a href="home.php" class="flex items-center"><i class="fas fa-home mr-3 w-5 text-center"></i> Beranda</a></li>
                <li><a href="kategori.php" class="flex items-center"><i class="fas fa-tags mr-3 w-5 text-center"></i> Kategori</a></li>
                <li><a href="produk.php" class="flex items-center"><i class="fas fa-box-open mr-3 w-5 text-center"></i> Produk</a></li>
                <li><a href="member.php" class="flex items-center"><i class="fas fa-users mr-3 w-5 text-center"></i> Member</a></li>
                <li><a href="admin.php" class="flex items-center"><i class="fas fa-user-shield mr-3 w-5 text-center"></i> Admin</a></li>
                <li><a href="transaksi.php" class="flex items-center"><i class="fas fa-shopping-cart mr-3 w-5 text-center"></i> Transaksi</a></li>
                <li><a href="laporan_input.php" class="flex items-center"><i class="fas fa-file-alt mr-3 w-5 text-center"></i> Laporan</a></li>
                <li class="mt-8 pt-4 border-t border-white border-opacity-20">
                    <a href="logout.php" class="flex items-center text-red-200 font-semibold"><i class="fas fa-sign-out-alt mr-3 w-5 text-center"></i> Logout</a>
                </li>
            </ul>
        </div>

        <!-- Data Member Section -->
        <div class="flex-1 p-6 max-w-screen-xl mx-auto">
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-2xl font-bold text-[#779341]">Daftar Member</h1>
                <a href="member.php?add=1" class="bg-[#779341] hover:bg-green-600 text-white font-bold py-2 px-4 rounded">
                    <i class="fas fa-plus mr-1"></i> Tambah Member
                </a>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['message'])): ?>
                <div class="mb-4 p-3 rounded <?= $_SESSION['message_type'] == 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' ?>">
                    <?= $_SESSION['message'] ?>
                </div>
                <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
            <?php endif; ?>

            <!-- Form Modal -->
            <?php if (isset($_GET['add']) || $editMode): ?>
            <div class="modal fixed inset-0 flex items-center justify-center">
                <div class="modal-content bg-white p-6 rounded-lg w-96">
                    <form id="memberForm" action="member.php" method="POST">
                        <h2 class="text-xl font-bold mb-4">
                            <?= $editMode ? 'Edit Member' : 'Tambah Member' ?>
                        </h2>
                        
                        <input type="hidden" name="id" value="<?= $formData['id'] ?>">
                        <input type="hidden" name="point" value="<?= $formData['point'] ?>">
                        <input type="hidden" name="status" value="<?= $formData['status'] ?>">

                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">Nama</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($formData['name']) ?>" 
                                   class="w-full p-2 border rounded" required>
                        </div>

                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">Telepon</label>
                            <input type="text" name="phone" value="<?= htmlspecialchars($formData['phone']) ?>" 
                                   class="w-full p-2 border rounded" required
                                   placeholder="Contoh: 081234567890">
                            <?php if (isset($_SESSION['duplicate_error'])): ?>
                                <div class="error-message mt-2 p-2 bg-red-50 rounded flex items-start">
                                    <i class="fas fa-exclamation-circle mt-1 mr-2 text-red-600"></i>
                                    <span><?= $_SESSION['duplicate_error'] ?></span>
                                </div>
                                <?php unset($_SESSION['duplicate_error']); ?>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($editMode): ?>
                        <div class="mb-4">
                            <label class="block text-sm font-medium mb-1">Status</label>
                            <select name="status" class="w-full p-2 border rounded">
                                <option value="active" <?= $formData['status'] == 'active' ? 'selected' : '' ?>>Aktif</option>
                                <option value="non-active" <?= $formData['status'] == 'non-active' ? 'selected' : '' ?>>Non-Aktif</option>
                            </select>
                        </div>
                        <?php else: ?>
                            <input type="hidden" name="status" value="active">
                        <?php endif; ?>

                        <div class="flex justify-between mt-6">
                            <button type="submit" name="simpan" class="bg-[#779341] text-white px-4 py-2 rounded">
                                Simpan
                            </button>
                            <a href="member.php" class="bg-gray-300 px-4 py-2 rounded">
                                Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Member Table -->
            <div class="overflow-x-auto rounded-lg shadow">
                <table class="w-full bg-white border border-gray-200">
                    <thead class="bg-[#779341] text-white">
                        <tr>
                            <th class="py-3 px-4 w-12">No</th>
                            <th class="py-3 px-4 text-left">Nama</th>
                            <th class="py-3 px-4">Telepon</th>
                            <th class="py-3 px-4">Point</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 w-32">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($members)): ?>
                            <?php foreach ($members as $index => $row): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-3 px-4 text-center"><?= $index + 1 ?></td>
                                <td class="py-3 px-4"><?= htmlspecialchars($row['name']) ?></td>
                                <td class="py-3 px-4 text-center"><?= htmlspecialchars($row['phone']) ?></td>
                                <td class="py-3 px-4 text-center"><?= number_format($row['point']) ?></td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-3 py-1 rounded-full text-xs <?= $row['status'] == 'active' ? 'status-active' : 'status-inactive' ?>">
                                        <?= ucfirst($row['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-center space-x-1">
                                    <a href="member.php?edit=<?= $row['id'] ?>" class="text-yellow-500 hover:text-yellow-700">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form action="member.php" method="POST" class="inline" onsubmit="return confirmDelete(this, <?= $row['status'] == 'active' ? 'true' : 'false' ?>)">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="hapus" 
                                                class="text-red-500 hover:text-red-700 <?= $row['status'] == 'active' ? 'btn-disabled' : '' ?>"
                                                <?= $row['status'] == 'active' ? 'disabled' : '' ?>>
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                    <?php if($row['status'] == 'non-active'): ?>
                                    <form action="member.php" method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" name="activate" class="text-blue-500 hover:text-blue-700">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="py-4 text-center text-gray-500">
                                    <i class="fas fa-users-slash mr-2"></i>
                                    Tidak ada data member
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
            // Sidebar toggle
            document.getElementById('menuToggle').addEventListener('click', function() {
                const sidebar = document.getElementById('sidebar');
                sidebar.classList.toggle('sidebar-hidden');
            });

            // Client-side validation
            document.getElementById('memberForm')?.addEventListener('submit', function(e) {
                const phone = this.elements['phone'].value.trim();
                const cleanedPhone = phone.replace(/\D/g, '');
                const phoneError = document.querySelector('input[name="phone"] + .error-message');
                
                // Clear previous error
                if (phoneError) {
                    phoneError.style.display = 'none';
                }
                
                // Validate phone length
                if (cleanedPhone.length < 10 || cleanedPhone.length > 15) {
                    e.preventDefault();
                    showError(this.elements['phone'], 'Nomor telepon harus 10-15 digit angka');
                    return false;
                }
                
                // Update phone value with cleaned version
                this.elements['phone'].value = cleanedPhone;
                return true;
            });
            
            function showError(input, message) {
                // Remove any existing error classes
                input.classList.remove('border-gray-300');
                input.classList.add('border-red-500');
                
                // Find or create error message element
                let errorElement = input.nextElementSibling;
                if (!errorElement || !errorElement.classList.contains('error-message')) {
                    errorElement = document.createElement('div');
                    errorElement.className = 'error-message mt-1';
                    input.parentNode.insertBefore(errorElement, input.nextSibling);
                }
                
                // Set error message
                errorElement.innerHTML = `<i class="fas fa-exclamation-circle mr-1"></i><span>${message}</span>`;
                errorElement.style.display = 'flex';
                
                // Focus on the input
                input.focus();
            }
            
            function confirmDelete(form, isActive) {
                if (isActive) {
                    alert('Tidak bisa menghapus member yang sedang aktif');
                    return false;
                }
                return confirm('Hapus member ini?');
            }
            
            // Auto-focus phone field if error exists
            <?php if ($errors['phone']): ?>
                document.addEventListener('DOMContentLoaded', function() {
                    const phoneInput = document.querySelector('input[name="phone"]');
                    if (phoneInput) {
                        phoneInput.focus();
                    }
                });
            <?php endif; ?>
        </script>
    </div>
</body>
</html>