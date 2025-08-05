<?php
session_start();
if (!isset($_SESSION['email'])) {
    header("location:../service/login.php");
    exit();
}

include '../service/connection.php';

// Set default role if not exists
$_SESSION['role'] = $_SESSION['role'] ?? 'cashier';

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get logged in admin data
$stmt = $conn->prepare("SELECT id, email, username, image, role, status FROM admin WHERE email = ?");
$stmt->bind_param("s", $_SESSION['email']);
$stmt->execute();
$admin_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin_data) {
    die("Data admin tidak ditemukan");
}

// Set safe defaults
$admin_data['status'] = $admin_data['status'] ?? 'active';
$admin_data['image'] = $admin_data['image'] ?? 'default.jpg';

// Query for admin data
if ($_SESSION['role'] === 'super_admin') {
    $query = "SELECT id, email, username, image, role, status FROM admin ORDER BY 
              FIELD(status, 'pending', 'active', 'inactive'), 
              role DESC, 
              username ASC";
} else {
    $query = "SELECT id, email, username, image, role, status FROM admin 
              WHERE role = 'cashier' 
              ORDER BY username ASC";
}

$result = $conn->query($query);
if (!$result) {
    die("Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - SmartCash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <style>
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

        .card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            width: 300px;
            margin: 1rem;
            padding: 20px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        .card img {
            width: 110px;
            height: 110px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #eee;
        }

        .super-admin-badge {
            background-color: #f59e0b;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-block;
            margin-left: 5px;
        }

        .cashier-badge {
            background-color: #3b82f6;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-block;
            margin-left: 5px;
        }

        .status-active {
            color: #10B981;
            font-weight: 600;
        }

        .status-pending {
            color: #F59E0B;
            font-weight: 600;
        }

        .status-inactive {
            color: #EF4444;
            font-weight: 600;
        }

        .btn-add-admin {
            background-color: #10B981;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-add-admin:hover {
            background-color: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .edit-btn {
            background-color: #FFC107;
            color: white;
        }

        .edit-btn:hover {
            background-color: #E0A800;
        }

        .delete-btn {
            background-color: #DC3545;
            color: white;
        }

        .delete-btn:hover {
            background-color: #C82333;
        }

        .verify-btn {
            background-color: #17A2B8;
            color: white;
        }

        .verify-btn:hover {
            background-color: #138496;
        }
    </style>
</head>

<body class="bg-[#F1F9E4] font-sans">
    <!-- Header -->
    <div class="container mx-auto p-4">
        <div class="flex items-center mb-8 relative">
            <i id="menuToggle" class="fas fa-bars text-2xl mr-4 cursor-pointer text-[#61892F] hover:text-[#4a6e24]"></i>
            <h1 class="text-3xl font-bold text-black">Smart <span class="text-[#779341]">Cash</span></h1>
            
            <div class="flex items-center ml-auto space-x-4">
                <a href="profil.php" class="flex items-center hover:opacity-80 transition">
                    <img src="../uploads/<?= htmlspecialchars($admin_data['image']) ?>" 
                         alt="Foto Profil" 
                         class="w-10 h-10 rounded-full object-cover inline-block border-2 border-[#61892F]">
                    <span class="text-[#1F2937] font-medium ml-2"><?= htmlspecialchars($admin_data['username']) ?></span>
                </a>
            </div>
        </div>
    </div>
    
    <div class="max-w-6xl mx-auto px-4">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Manajemen Admin</h2>
            <?php if ($_SESSION['role'] === 'super_admin'): ?>
                <a href="create_admin.php" class="btn-add-admin px-4 py-2 rounded-lg font-medium flex items-center">
                    <i class="fas fa-plus mr-2"></i> Tambah Admin
                </a>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="flex flex-wrap justify-center -mx-2">
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                $foto_profil = !empty($row['image']) ? '../uploads/' . $row['image'] : '../uploads/default.jpg';
                $is_current_user = ($row['email'] === $_SESSION['email']);
                
                $status_class = 'status-' . $row['status'];
                $status_text = match($row['status']) {
                    'active' => $is_current_user ? 'Anda (Aktif)' : 'Aktif',
                    'pending' => 'Menunggu Verifikasi',
                    'inactive' => 'Nonaktif',
                    default => 'Tidak Dikenal'
                };
                ?>
                <div class="w-full sm:w-1/2 lg:w-1/3 xl:w-1/4 px-2 mb-4">
                    <div class="card">
                        <img src="<?= htmlspecialchars($foto_profil) ?>" 
                             alt="Foto Profil <?= htmlspecialchars($row['username']) ?>" 
                             class="mb-4">
                        
                        <h3 class="text-xl font-bold text-gray-800 mb-1">
                            <?= htmlspecialchars($row['username']) ?>
                            <span class="<?= $row['role'] === 'super_admin' ? 'super-admin-badge' : 'cashier-badge' ?>">
                                <?= $row['role'] === 'super_admin' ? 'Super Admin' : 'Kasir' ?>
                            </span>
                        </h3>
                        
                        <p class="text-gray-600 mb-1"><span class="font-semibold">Email:</span> <?= htmlspecialchars($row['email']) ?></p>
                        <p class="text-gray-600 mb-3">
                            <span class="font-semibold">Status:</span> 
                            <span class="<?= $status_class ?>">
                                <i class="fas fa-circle text-xs mr-1"></i> <?= $status_text ?>
                            </span>
                        </p>
                        
                        <div class="actions flex justify-center space-x-2 w-full">
                            <?php if ($_SESSION['role'] === 'super_admin' || $is_current_user): ?>
                                <a href="edit_admin.php?id=<?= $row['id'] ?>" 
                                   class="edit-btn px-3 py-1 rounded text-sm">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($_SESSION['role'] === 'super_admin' && !$is_current_user): ?>
                                <form action="delete_admin.php" method="POST" class="inline">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                    <button type="submit" 
                                            class="delete-btn px-3 py-1 rounded text-sm"
                                            onclick="return confirm('Yakin ingin menghapus admin <?= htmlspecialchars(addslashes($row['username'])) ?>?')">
                                        <i class="fas fa-trash-alt mr-1"></i> Hapus
                                    </button>
                                </form>
                                
                                <?php if ($row['status'] === 'pending'): ?>
                                    <a href="verify_admin.php?id=<?= $row['id'] ?>" 
                                       class="verify-btn px-3 py-1 rounded text-sm">
                                        <i class="fas fa-check mr-1"></i> Verifikasi
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
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
                <form action="../service/logout.php" method="POST" class="w-full">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <button type="submit" class="flex items-center text-red-200 font-semibold w-full">
                        <i class="fas fa-sign-out-alt mr-3 w-5 text-center"></i> Logout
                    </button>
                </form>
            </li>
        </ul>
    </div>

    <script>
        document.getElementById('menuToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('sidebar-hidden');
        });

        // Logout confirmation
        document.querySelector('form[action="../service/logout.php"]').addEventListener('submit', function(e) {
            if (!confirm('Yakin ingin logout?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>