<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['email'])) {
    header("location:../service/login.php");
    exit();
}

// Check for super admin role
if ($_SESSION['role'] !== 'super_admin') {
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

// Fitur search member
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search !== '') {
    $query = "SELECT * FROM member WHERE name LIKE ? OR phone LIKE ? ORDER BY id ASC";
    $stmt = $conn->prepare($query);
    $search_param = "%$search%";
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $members = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $members = [];
    }
    $stmt->close();
} else {
    $query = "SELECT * FROM member ORDER BY id ASC";
    $result = mysqli_query($conn, $query);
    if ($result) {
        $members = mysqli_fetch_all($result, MYSQLI_ASSOC);
    } else {
        $members = [];
    }
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8fafc;
        }

        .sidebar {
            background-color: #6b46c1;
            color: white;
        }

        .sidebar a:hover {
            background-color: #805ad5;
        }

        .stat-card {
            border-left: 4px solid #6b46c1;
        }

        .bg-super-admin {
            background-color: #6b46c1;
        }

        .text-super-admin {
            color: #6b46c1;
        }

        .nav-active {
            background-color: #805ad5;
        }

        .badge-active {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .form-input:focus {
            border-color: #6b46c1;
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
                <div class="w-10 h-10 rounded-full bg-purple-700 flex items-center justify-center">
                    <i class="fas fa-user-shield text-white"></i>
                </div>
                <div class="ml-3">
                    <p class="font-medium text-white"><?= htmlspecialchars($username) ?></p>
                    <p class="text-xs text-purple-200">Super Admin</p>
                </div>
            </div>

            <nav class="mt-8">
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="manage_admin.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-user-cog mr-3"></i>
                    Kelola Kasir
                </a>
                <a href="manage_member.php" class="flex items-center px-4 py-3 rounded-lg nav-active">
                    <i class="fas fa-users mr-3"></i>
                    Kelola Member
                </a>
                <a href="manage_category.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-tags mr-3"></i>
                    Kategori Produk
                </a>
                <a href="manage_product.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-boxes mr-3"></i>
                    Kelola Produk
                </a>
                <a href="reports.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-chart-bar mr-3"></i>
                    Laporan & Grafik
                </a>
                <a href="system_logs.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-clipboard-list mr-3"></i>
                    Log Sistem
                </a>
                
                <a href="../service/logout.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800 mt-8 text-red-200">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    Logout
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
                            <img src="<?= $image ?>"
                                alt="Profile"
                                class="w-8 h-8 rounded-full border-2 border-purple-500">
                            <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full"></span>
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
                    <form method="GET" class="flex gap-2">
                        <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Cari nama/no. telepon member..." class="form-input px-3 py-2 rounded border border-gray-300 focus:outline-none focus:ring">
                        <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700"><i class="fas fa-search"></i> Cari</button>
                    </form>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="mb-6 p-4 rounded-lg <?= $_SESSION['message_type'] == 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' ?>">
                        <?= $_SESSION['message'] ?>
                    </div>
                    <?php unset($_SESSION['message']);
                    unset($_SESSION['message_type']); ?>
                <?php endif; ?>

                <!-- Form Modal -->
                <!-- Form tambah member dihapus dari admin -->
                <!-- Modal/form tambah member sudah dihapus, endif juga dihapus untuk mencegah error syntax -->

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
                                                <a href="manage_member.php?edit=<?= $member['id'] ?>" class="inline-block text-purple-600 hover:text-purple-800" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form action="proses_member.php" method="POST" class="inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus member ini?')">
                                                    <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                                    <button type="submit" name="hapus"
                                                        class="text-red-600 hover:text-red-800 <?= $member['status'] == 'active' ? 'opacity-50 cursor-not-allowed' : '' ?>"
                                                        title="Hapus"
                                                        <?= $member['status'] == 'active' ? 'disabled' : '' ?>>
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </form>
                                                <?php if ($member['status'] == 'non-active'): ?>
                                                    <form action="proses_member.php" method="POST" class="inline">
                                                        <input type="hidden" name="id" value="<?= $member['id'] ?>">
                                                        <button type="submit" name="activate" class="text-blue-600 hover:text-blue-800" title="Aktifkan">
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