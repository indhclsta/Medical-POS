<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['email'])) {
    header("location:../service/login.php");
    exit();
}

// Check for cashier role
if ($_SESSION['role'] !== 'cashier') {
    header("location:../unauthorized.php");
    exit();
}

// Database connection
include '../service/connection.php';
$email = $_SESSION['email'];

// Get admin data
$query = mysqli_query($conn, "SELECT * FROM admin WHERE email = '$email'");
$admin = mysqli_fetch_assoc($query);

// Set variables
$username = $admin['username'];
$image = !empty($admin['image']) ? '../uploads/' . $admin['image'] : 'default.jpg';

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Member - MediPOS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #1E1B2E;
            font-family: 'Inter', sans-serif;
        }
        .sidebar {
            background: linear-gradient(180deg, #2A2540 0%, #1E1B2E 100%);
            border-right: 1px solid #3B3360;
        }
        .nav-item {
            transition: all 0.2s ease;
            border-radius: 0.5rem;
        }
        .nav-item:hover {
            background-color: rgba(155, 135, 245, 0.1);
        }
        .nav-item.active {
            background-color: #9B87F5;
            color: white;
        }
        .nav-item.active:hover {
            background-color: #8A75E5;
        }
        .stat-card {
            background: linear-gradient(135deg, #2A2540 0%, #3B3360 100%);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .table-row:hover {
            background-color: rgba(155, 135, 245, 0.05);
        }
        .badge-active {
            background-color: rgba(74, 222, 128, 0.1);
            color: #4ADE80;
        }
        .badge-inactive {
            background-color: rgba(248, 113, 113, 0.1);
            color: #F87171;
        }
        .form-input {
            background-color: #2A2540;
            border: 1px solid #3B3360;
            color: white;
            border-radius: 0.375rem;
            padding: 0.5rem 0.75rem;
            width: 100%;
        }
        .form-input:focus {
            outline: none;
            border-color: #9B87F5;
            box-shadow: 0 0 0 3px rgba(155, 135, 245, 0.2);
        }
        .modal-content {
            background-color: #2A2540;
            border: 1px solid #3B3360;
        }
        .modal-header {
            border-bottom: 1px solid #3B3360;
            background-color: #3B3360;
        }
    </style>
</head>
<body class="text-gray-200">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="sidebar w-64 flex flex-col p-5 space-y-8">
            <!-- Logo -->
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-lg bg-purple-500 flex items-center justify-center">
                    <span class="material-icons text-white">local_pharmacy</span>
                </div>
                <h1 class="text-xl font-bold text-purple-300">MediPOS</h1>
            </div>
            
            <!-- Navigation -->
            <nav class="flex-1 flex flex-col space-y-2">
                <a href="dashboard.php" class="nav-item flex items-center p-3 space-x-3">
                    <span class="material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
                <a href="transaksi.php" class="nav-item flex items-center p-3 space-x-3">
                    <span class="material-icons">point_of_sale</span>
                    <span>Transaksi</span>
                </a>
                <a href="manage_member.php" class="nav-item active flex items-center p-3 space-x-3">
                    <span class="material-icons">people</span>
                    <span>Member</span>
                </a>
                <a href="reports.php" class="nav-item flex items-center p-3 space-x-3">
                    <span class="material-icons">insert_chart</span>
                    <span>Laporan</span>
                </a>
            </nav>
            
            <!-- User & Logout -->
            <div class="mt-auto">
                <div class="flex items-center p-3 space-x-3 rounded-lg bg-[#3B3360]">
                    <div class="w-10 h-10 rounded-full bg-purple-500 flex items-center justify-center">
                        <span class="material-icons">person</span>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium"><?php echo htmlspecialchars($username); ?></p>
                        <p class="text-xs text-purple-300">Kasir</p>
                    </div>
                    <a href="../service/logout.php" class="text-red-400 hover:text-red-300 transition">
                        <span class="material-icons">logout</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8 overflow-y-auto">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h2 class="text-2xl font-bold text-white">Kelola Member</h2>
                        <p class="text-purple-300">Kelola data member untuk sistem loyalty program</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <span class="material-icons absolute left-3 top-1/2 transform -translate-y-1/2 text-purple-300">search</span>
                            <input type="text" placeholder="Cari member..." class="bg-[#2A2540] pl-10 pr-4 py-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                        <a href="manage_member.php?add=1" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition">
                            <span class="material-icons">add</span>
                            <span>Tambah Member</span>
                        </a>
                    </div>
                </div>
                
                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="mb-6 p-4 rounded-lg <?= $_SESSION['message_type'] == 'error' ? 'bg-red-500 text-white' : 'bg-green-500 text-white' ?>">
                        <?= $_SESSION['message'] ?>
                    </div>
                    <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
                <?php endif; ?>

                <!-- Form Modal -->
                <?php if (isset($_GET['add']) || $editMode): ?>
                <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div class="modal-content rounded-lg shadow-xl w-full max-w-md">
                        <div class="modal-header px-6 py-4 rounded-t-lg">
                            <h3 class="text-lg font-semibold text-white">
                                <?= $editMode ? 'Edit Member' : 'Tambah Member Baru' ?>
                            </h3>
                        </div>
                        
                        <form action="proses_member.php" method="POST" class="p-6">
                            <input type="hidden" name="id" value="<?= $formData['id'] ?>">
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-purple-300 mb-1">Nama Member</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($formData['name']) ?>" 
                                       class="form-input" required>
                                <?php if (!empty($errors['name'])): ?>
                                    <p class="text-sm text-red-400 mt-1"><?= $errors['name'] ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-purple-300 mb-1">Nomor Telepon</label>
                                <input type="text" name="phone" value="<?= htmlspecialchars($formData['phone']) ?>" 
                                       class="form-input" required
                                       placeholder="Contoh: 081234567890">
                                <?php if (!empty($errors['phone'])): ?>
                                    <p class="text-sm text-red-400 mt-1"><?= $errors['phone'] ?></p>
                                <?php endif; ?>
                                <?php if (!empty($errors['general'])): ?>
                                    <p class="text-sm text-red-400 mt-1"><?= $errors['general'] ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($editMode): ?>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-purple-300 mb-1">Status</label>
                                <select name="status" class="form-input">
                                    <option value="active" <?= $formData['status'] == 'active' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="non-active" <?= $formData['status'] == 'non-active' ? 'selected' : '' ?>>Non-Aktif</option>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="flex justify-end space-x-3 mt-6">
                                <button type="submit" name="simpan" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-lg flex items-center space-x-2 transition">
                                    <span class="material-icons">save</span>
                                    <span>Simpan</span>
                                </button>
                                <a href="manage_member.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition">
                                    Batal
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Member Table -->
                <div class="bg-[#2A2540] rounded-xl shadow-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-[#3B3360] text-purple-300">
                                <tr>
                                    <th class="py-3 px-4 text-left">No</th>
                                    <th class="py-3 px-4 text-left">Nama</th>
                                    <th class="py-3 px-4 text-left">Telepon</th>
                                    <th class="py-3 px-4 text-center">Point</th>
                                    <th class="py-3 px-4 text-center">Status</th>
                                    <th class="py-3 px-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#3B3360]">
                                <?php if (!empty($members)): ?>
                                    <?php foreach ($members as $index => $member): ?>
                                    <tr class="hover:bg-[#3B3360] transition">
                                        <td class="py-4 px-4"><?= $index + 1 ?></td>
                                        <td class="py-4 px-4 font-medium"><?= htmlspecialchars($member['name']) ?></td>
                                        <td class="py-4 px-4"><?= htmlspecialchars($member['phone']) ?></td>
                                        <td class="py-4 px-4 text-center"><?= number_format($member['point']) ?></td>
                                        <td class="py-4 px-4 text-center">
                                            <span class="inline-block px-3 py-1 rounded-full text-xs font-medium <?= $member['status'] == 'active' ? 'badge-active' : 'badge-inactive' ?>">
                                                <?= ucfirst($member['status']) ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4 text-center space-x-2">
                                            <a href="manage_member.php?edit=<?= $member['id'] ?>" class="inline-block text-purple-400 hover:text-purple-300 transition" title="Edit">
                                                <span class="material-icons">edit</span>
                                            </a>
                                            <form action="proses_member.php" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus member ini?')">
                                                <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                                <button type="submit" name="hapus" 
                                                        class="text-red-400 hover:text-red-300 transition <?= $member['status'] == 'active' ? 'opacity-50 cursor-not-allowed' : '' ?>"
                                                        title="Hapus"
                                                        <?= $member['status'] == 'active' ? 'disabled' : '' ?>>
                                                    <span class="material-icons">delete</span>
                                                </button>
                                            </form>
                                            <?php if($member['status'] == 'non-active'): ?>
                                            <form action="proses_member.php" method="POST" class="inline">
                                                <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                                <button type="submit" name="activate" class="text-blue-400 hover:text-blue-300 transition" title="Aktifkan">
                                                    <span class="material-icons">refresh</span>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-gray-400">
                                            <span class="material-icons text-4xl mb-2">people_alt</span>
                                            <p class="text-lg">Tidak ada data member</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
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

        // Logout confirmation
        document.querySelector('a[href="../service/logout.php"]').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Anda yakin ingin logout?')) {
                window.location.href = this.getAttribute('href');
            }
        });
    </script>
</body>
</html>