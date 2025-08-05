<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include '../service/connection.php';

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
        header("Location: manage_member.php");
        exit();
    }
}

// Get form data from session if exists
if (isset($_SESSION['form_data'])) {
    $formData = array_merge($formData, $_SESSION['form_data']);
    unset($_SESSION['form_data']);
}

// Get error messages from session
if (isset($_SESSION['error'])) {
    $errors['general'] = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Member - MediPOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #6b46c1;
            --secondary: #805ad5;
            --dark: #2d3748;
            --light: #f8fafc;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light);
        }
        
        .sidebar {
            background-color: var(--primary);
            color: white;
        }
        
        .sidebar a:hover {
            background-color: var(--secondary);
        }
        
        .nav-active {
            background-color: var(--secondary);
        }
        
        .badge-active {
            background-color: #dcfce7;
            color: #166534;
        }
        
        .badge-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .action-btn {
            transition: all 0.2s;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
        
        .form-input {
            transition: all 0.3s;
        }
        
        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.2);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="sidebar w-64 px-4 py-8 shadow-lg fixed h-full">
            <div class="flex items-center justify-center mb-8">
                <h1 class="text-2xl font-bold">
                    <span class="text-white">Medi</span><span class="text-purple-300">POS</span>
                </h1>
            </div>
            
            <div class="flex items-center px-4 py-3 mb-6 rounded-lg bg-purple-900">
                <img src="<?= $image ?>" alt="Profile" class="w-10 h-10 rounded-full object-cover border-2 border-purple-500">
                <div class="ml-3">
                    <p class="font-medium text-white"><?= $username ?></p>
                    <p class="text-xs text-purple-200">Admin</p>
                </div>
            </div>

            <nav class="mt-8">
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                </a>
                <a href="manage_member.php" class="flex items-center px-4 py-3 rounded-lg nav-active">
                    <i class="fas fa-users mr-3"></i> Kelola Member
                </a>
                <a href="manage_product.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-boxes mr-3"></i> Kelola Produk
                </a>
                <a href="transaksi.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-shopping-cart mr-3"></i> Transaksi
                </a>
                <a href="laporan.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-chart-bar mr-3"></i> Laporan
                </a>
                <a href="../service/logout.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800 mt-8 text-red-200">
                    <i class="fas fa-sign-out-alt mr-3"></i> Logout
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="ml-64 flex-1 overflow-y-auto">
            <header class="bg-white shadow-sm">
                <div class="flex justify-between items-center px-6 py-4">
                    <h2 class="text-xl font-semibold text-gray-800">Kelola Member</h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500" id="currentDateTime"></span>
                        <div class="relative">
                            <img src="<?= $image ?>" alt="Profile" class="w-8 h-8 rounded-full border-2 border-purple-500">
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <!-- Header and Add Button -->
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Daftar Member</h2>
                        <p class="text-gray-600">Kelola data member untuk sistem loyalty program</p>
                    </div>
                    <a href="manage_member.php?add=1" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg flex items-center">
                        <i class="fas fa-plus mr-2"></i> Tambah Member
                    </a>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="mb-6 p-4 rounded-lg <?= $_SESSION['message_type'] == 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' ?>">
                        <?= $_SESSION['message'] ?>
                    </div>
                    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                <?php endif; ?>

                <!-- Form Modal -->
                <?php if (isset($_GET['add']) || $editMode): ?>
                <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
                        <div class="px-6 py-4 border-b border-gray-200 bg-purple-50">
                            <h3 class="text-lg font-semibold text-purple-800">
                                <?= $editMode ? 'Edit Member' : 'Tambah Member Baru' ?>
                            </h3>
                        </div>
                        
                        <form action="proses_member.php" method="POST" class="p-6">
                            <input type="hidden" name="id" value="<?= $formData['id'] ?>">
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Member</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($formData['name']) ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md form-input" required>
                                <?php if (!empty($errors['name'])): ?>
                                    <p class="text-sm text-red-600 mt-1"><?= $errors['name'] ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Telepon</label>
                                <input type="text" name="phone" value="<?= htmlspecialchars($formData['phone']) ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md form-input" required
                                       placeholder="Contoh: 081234567890">
                                <?php if (!empty($errors['phone'])): ?>
                                    <p class="text-sm text-red-600 mt-1"><?= $errors['phone'] ?></p>
                                <?php endif; ?>
                                <?php if (!empty($errors['general'])): ?>
                                    <p class="text-sm text-red-600 mt-1"><?= $errors['general'] ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($editMode): ?>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md form-input">
                                    <option value="active" <?= $formData['status'] == 'active' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="non-active" <?= $formData['status'] == 'non-active' ? 'selected' : '' ?>>Non-Aktif</option>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="flex justify-end space-x-3 mt-6">
                                <button type="submit" name="simpan" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-md">
                                    <i class="fas fa-save mr-2"></i> Simpan
                                </button>
                                <a href="manage_member.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md">
                                    Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Member Table -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-purple-600 text-white">
                                <tr>
                                    <th class="py-3 px-4 text-left">No</th>
                                    <th class="py-3 px-4 text-left">Nama</th>
                                    <th class="py-3 px-4 text-left">Telepon</th>
                                    <th class="py-3 px-4 text-center">Point</th>
                                    <th class="py-3 px-4 text-center">Status</th>
                                    <th class="py-3 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (!empty($members)): ?>
                                    <?php foreach ($members as $index => $member): ?>
                                    <tr class="hover:bg-purple-50">
                                        <td class="py-4 px-4"><?= $index + 1 ?></td>
                                        <td class="py-4 px-4 font-medium"><?= htmlspecialchars($member['name']) ?></td>
                                        <td class="py-4 px-4"><?= htmlspecialchars($member['phone']) ?></td>
                                        <td class="py-4 px-4 text-center"><?= number_format($member['point']) ?></td>
                                        <td class="py-4 px-4 text-center">
                                            <span class="inline-block px-3 py-1 rounded-full text-xs <?= $member['status'] == 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                                <?= ucfirst($member['status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-center space-x-2">
                                            <a href="manage_member.php?edit=<?= $member['id'] ?>" class="action-btn inline-block text-purple-600 hover:text-purple-800" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="proses_member.php" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus member ini?')">
                                                <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                                <button type="submit" name="hapus" 
                                                        class="action-btn text-red-600 hover:text-red-800 <?= $member['status'] == 'active' ? 'opacity-50 cursor-not-allowed' : '' ?>"
                                                        title="Hapus"
                                                        <?= $member['status'] == 'active' ? 'disabled' : '' ?>>
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                            <?php if($member['status'] == 'non-active'): ?>
                                            <form action="proses_member.php" method="POST" class="inline">
                                                <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                                <button type="submit" name="activate" class="action-btn text-blue-600 hover:text-blue-800" title="Aktifkan">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-gray-500">
                                            <i class="fas fa-users-slash text-3xl mb-2"></i>
                                            <p class="text-lg">Tidak ada data member</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Update date and time
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('currentDateTime').textContent = now.toLocaleDateString('id-ID', options);
        }
        
        setInterval(updateDateTime, 1000);
        updateDateTime();
    </script>
</body>
</html>