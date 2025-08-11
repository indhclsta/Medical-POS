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

// Query untuk data kasir saja (tidak termasuk super admin)
$query = "SELECT id, email, username, image, status FROM admin WHERE role = 'cashier' ORDER BY username ASC";

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
    <title>Kelola Kasir - SmartCash</title>
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

        .admin-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .admin-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .cashier-badge {
            background-color: #6b46c1;
            color: white;
        }

        .status-active {
            color: #10b981;
        }

        .status-pending {
            color: #f59e0b;
        }

        .status-inactive {
            color: #ef4444;
        }

        .btn-primary {
            background-color: #6b46c1;
            color: white;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background-color: #805ad5;
            transform: translateY(-2px);
        }

        .btn-edit {
            background-color: #f59e0b;
            color: white;
        }

        .btn-edit:hover {
            background-color: #e0920a;
        }

        .btn-delete {
            background-color: #ef4444;
            color: white;
        }

        .btn-delete:hover {
            background-color: #dc2626;
        }

        .btn-verify {
            background-color: #10b981;
            color: white;
        }

        .btn-verify:hover {
            background-color: #0d9c6e;
        }

        /* .btn-verify dan hover dihapus karena fitur verify tidak digunakan */
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 1.25rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 1.25rem;
        }

        .modal-footer {
            padding: 1.25rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .close-modal {
            cursor: pointer;
            font-size: 1.5rem;
            color: #6b7280;
        }

        .close-modal:hover {
            color: #4b5563;
        }

        .form-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-input:focus {
            border-color: #9f7aea;
            box-shadow: 0 0 0 3px rgba(159, 122, 234, 0.2);
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
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
                    <p class="font-medium text-white"><?= htmlspecialchars($admin_data['username']) ?></p>
                    <p class="text-xs text-purple-200">Super Admin</p>
                </div>
            </div>

            <nav class="mt-8">
                <a href="dashboard.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-tachometer-alt mr-3"></i>
                    Dashboard
                </a>
                <a href="manage_admin.php" class="flex items-center px-4 py-3 rounded-lg nav-active">
                    <i class="fas fa-user-cog mr-3"></i>
                    Kelola Kasir
                </a>
                <a href="manage_member.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
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
                <a href="settings.php" class="flex items-center px-4 py-3 rounded-lg hover:bg-purple-800">
                    <i class="fas fa-cog mr-3"></i>
                    Pengaturan
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
                    <h2 class="text-xl font-semibold text-gray-800">Kelola Kasir</h2>
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-500" id="currentDateTime"></span>
                        <div class="relative">
                            <img src="../uploads/<?= htmlspecialchars($admin_data['image']) ?>"
                                alt="Profile"
                                class="w-8 h-8 rounded-full border-2 border-purple-500">
                            <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full"></span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6">
                <!-- Action Bar -->
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-800">Daftar Kasir</h3>
                    <button onclick="openCreateModal()"
                        class="btn-primary px-4 py-2 rounded-lg font-medium flex items-center">
                        <i class="fas fa-plus mr-2"></i> Tambah Kasir
                    </button>
                </div>

                <!-- Notifications -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="mb-6 p-4 bg-green-100 border border-green-400 text-green-700 rounded-lg">
                        <?= $_SESSION['success'];
                        unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-lg">
                        <?= $_SESSION['error'];
                        unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Admin Cards Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                        $profile_img = !empty($row['image']) ? '../uploads/' . $row['image'] : '../uploads/default.jpg';

                        $status_class = 'status-' . $row['status'];
                        $status_text = ($row['status'] === 'active') ? 'Aktif' : (($row['status'] === 'inactive') ? 'Nonaktif' : 'Tidak Dikenal');
                        ?>
                        <div class="admin-card p-6">
                            <div class="flex flex-col items-center text-center">
                                <!-- Profile Image -->
                                <img src="<?= htmlspecialchars($profile_img) ?>"
                                    alt="Profile <?= htmlspecialchars($row['username']) ?>"
                                    class="w-24 h-24 rounded-full object-cover border-4 border-purple-100 mb-4">

                                <!-- Admin Info -->
                                <h4 class="text-lg font-bold text-gray-800 mb-1">
                                    <?= htmlspecialchars($row['username']) ?>
                                </h4>

                                <span class="cashier-badge text-xs px-2 py-1 rounded-full mb-2">
                                    Kasir
                                </span>

                                <p class="text-gray-600 text-sm mb-1">
                                    <i class="fas fa-envelope mr-1"></i> <?= htmlspecialchars($row['email']) ?>
                                </p>

                                <p class="text-sm mb-4">
                                    Status:
                                    <span class="<?= $status_class ?> font-medium">
                                        <i class="fas fa-circle text-xs mr-1"></i> <?= $status_text ?>
                                    </span>
                                </p>

                                <!-- Action Buttons -->
                                <div class="flex flex-wrap justify-center gap-2 w-full">
                                    <button onclick="openEditModal(
                                        '<?= $row['id'] ?>',
                                        '<?= htmlspecialchars($row['username'], ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($row['email'], ENT_QUOTES) ?>',
                                        '<?= $row['status'] ?>',
                                        '<?= htmlspecialchars($row['image']) ?>'
                                    )" class="btn-edit px-3 py-1 rounded text-sm flex items-center">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </button>

                                    <form action="process_delete_admin.php" method="POST" class="inline">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit"
                                            class="btn-delete px-3 py-1 rounded text-sm flex items-center"
                                            onclick="return confirm('Yakin ingin menghapus kasir <?= htmlspecialchars(addslashes($row['username'])) ?>?')">
                                            <i class="fas fa-trash-alt mr-1"></i> Hapus
                                        </button>
                                    </form>

                                    <!-- Tidak ada tombol verify, hanya aksi edit dan hapus -->
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Create Admin Modal -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-lg font-semibold text-gray-800">Tambah Kasir Baru</h3>
                <span class="close-modal" onclick="closeModal('createModal')">&times;</span>
            </div>
            <form id="createForm" action="process_create_admin.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="role" value="cashier">
                <div class="modal-body space-y-4">
                    <div>
                        <label for="create_username" class="form-label">Username</label>
                        <input type="text" id="create_username" name="username" required
                            class="form-input" placeholder="Masukkan username">
                    </div>
                    <div>
                        <label for="create_email" class="form-label">Email</label>
                        <input type="email" id="create_email" name="email" required
                            class="form-input" placeholder="Masukkan email">
                    </div>
                    <div>
                        <label for="create_password" class="form-label">Password</label>
                        <input type="password" id="create_password" name="password" required
                            class="form-input" placeholder="Masukkan password">
                    </div>
                    <div>
                        <label for="create_image" class="form-label">Foto Profil</label>
                        <input type="file" id="create_image" name="image" accept="image/*"
                            class="form-input">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('createModal')"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Admin Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-lg font-semibold text-gray-800">Edit Kasir</h3>
                <span class="close-modal" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form id="editForm" action="process_edit_admin.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" id="edit_id" name="id">
                <input type="hidden" name="role" value="cashier">
                <div class="modal-body space-y-4">
                    <div>
                        <label for="edit_username" class="form-label">Username</label>
                        <input type="text" id="edit_username" name="username" required
                            class="form-input" placeholder="Masukkan username">
                    </div>
                    <div>
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" id="edit_email" name="email" required
                            class="form-input" placeholder="Masukkan email">
                    </div>
                    <div>
                        <label for="edit_password" class="form-label">Password (Kosongkan jika tidak diubah)</label>
                        <input type="password" id="edit_password" name="password"
                            class="form-input" placeholder="Masukkan password baru">
                    </div>
                    <div>
                        <label for="edit_status" class="form-label">Status</label>
                        <select id="edit_status" name="status" class="form-input">
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>
                    <div>
                        <label for="edit_image" class="form-label">Foto Profil (Kosongkan jika tidak diubah)</label>
                        <input type="file" id="edit_image" name="image" accept="image/*"
                            class="form-input">
                        <div id="current_image" class="mt-2 text-sm text-gray-500"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('editModal')"
                        class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Batal
                    </button>
                    <button type="submit"
                        class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal Functions
        function openCreateModal() {
            document.getElementById('createModal').style.display = 'flex';
            document.getElementById('create_username').focus();
        }

        function openEditModal(id, username, email, status, image) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_status').value = status;

            // Display current image info
            const currentImageDiv = document.getElementById('current_image');
            if (image && image !== 'default.jpg') {
                currentImageDiv.innerHTML = `
                    <span class="font-medium">Foto saat ini:</span>
                    <img src="../uploads/${image}" alt="Current profile" class="w-16 h-16 rounded-full object-cover mt-1">
                    <div class="text-xs mt-1">${image}</div>
                `;
            } else {
                currentImageDiv.innerHTML = '<span class="font-medium">Menggunakan foto default</span>';
            }

            document.getElementById('editModal').style.display = 'flex';
            document.getElementById('edit_username').focus();
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        // Close modal with ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal('createModal');
                closeModal('editModal');
            }
        });

        // Form validation
        document.getElementById('createForm').addEventListener('submit', function(e) {
            const password = document.getElementById('create_password').value;
            if (password.length < 6) {
                alert('Password harus minimal 6 karakter');
                e.preventDefault();
            }
        });

        document.getElementById('editForm').addEventListener('submit', function(e) {
            const password = document.getElementById('edit_password').value;
            if (password && password.length < 6) {
                alert('Password harus minimal 6 karakter');
                e.preventDefault();
            }
        });

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
            if (confirm('Yakin ingin logout?')) {
                window.location.href = this.getAttribute('href');
            }
        });
    </script>
</body>

</html>