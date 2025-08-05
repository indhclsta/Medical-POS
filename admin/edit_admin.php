<?php
session_start();
include '../service/connection.php';

if (!isset($_SESSION['email'])) {
    header("location: ../service/login.php");
    exit();
}

$current_role = $_SESSION['role'];
$current_email = $_SESSION['email'];

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("ID Admin tidak ditemukan.");
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

// Ambil data admin
$stmt = $conn->prepare("SELECT * FROM admin WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute(); 
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    die("Data admin tidak ditemukan.");
}

// Cek izin edit
$is_editing_self = ($row['email'] === $current_email);
$is_super_admin = ($current_role === 'super_admin');

// Kasir hanya bisa edit diri sendiri
if (!$is_super_admin && !$is_editing_self) {
    die("Anda tidak memiliki izin untuk mengedit admin ini.");
}

// Proses update
if (isset($_POST['update_admin'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    
    // Super Admin bisa edit semua field
    if ($is_super_admin) {
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $role = mysqli_real_escape_string($conn, $_POST['role']);
        $password = $_POST['password'];
        
        // Validasi email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Format email tidak valid!";
            header("Location: edit_admin.php?id=" . $id);
            exit();
        }
        
        // Cek email unik
        $check_email = $conn->prepare("SELECT id FROM admin WHERE email = ? AND id != ?");
        $check_email->bind_param("si", $email, $id);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            $_SESSION['error'] = "Email sudah digunakan!";
            header("Location: edit_admin.php?id=" . $id);
            exit();
        }
    }

    // Handle gambar
    $update_image = false;
    if (!empty($_FILES['image']['name'])) {
        $image = $_FILES['image']['name'];
        $target = "uploads/" . basename($image);
        $file_type = $_FILES['image']['type'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "Hanya file gambar (JPEG, PNG, GIF) yang diizinkan!";
            header("Location: edit_admin.php?id=" . $id);
            exit();
        }

        if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
            $update_image = true;
        } else {
            $_SESSION['error'] = "Gagal mengupload gambar!";
            header("Location: edit_admin.php?id=" . $id);
            exit();
        }
    }

    // Bangun query berdasarkan role
    if ($is_super_admin) {
        $sql = "UPDATE admin SET username = ?, email = ?, role = ?";
        $params = [$username, $email, $role];
        $types = "sss";
        
        // Tambahkan password jika diisi
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql .= ", password = ?";
            $params[] = $hashed_password;
            $types .= "s";
        }
    } else {
        $sql = "UPDATE admin SET username = ?";
        $params = [$username];
        $types = "s";
    }
    
    // Tambahkan gambar jika diupload
    if ($update_image) {
        $sql .= ", image = ?";
        $params[] = $image;
        $types .= "s";
    }
    
    $sql .= " WHERE id = ?";
    $params[] = $id;
    $types .= "i";

    // Eksekusi query
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Data admin berhasil diperbarui!";
        header("Location: admin.php");
        exit();
    } else {
        $_SESSION['error'] = "Gagal menyimpan perubahan!";
        header("Location: edit_admin.php?id=" . $id);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&family=Poppins:wght@400;700&display=swap" rel="stylesheet"/>
    <style>
        .font-outfit { font-family: 'Outfit', sans-serif; }
        .font-poppins { font-family: 'Poppins', sans-serif; }
        .bg-custom { background-color: #F1F9E4; }
        .text-primary { color: #779341; }
        .btn-primary { background-color: #779341; color: white; }
        .btn-primary:hover { background-color: #5e762f; }
        .form-label { color: #4A5568; }
        .form-input { border-color: #D1D5DB; }
        .form-readonly {
            background-color: #E5E7EB;
            color: #6B7280;
            cursor: not-allowed;
        }
    </style>
</head>
<body class="bg-custom font-outfit flex items-center justify-center min-h-screen">
    <div class="bg-white rounded-lg shadow-lg p-8 w-full max-w-2xl">
        <h1 class="text-4xl font-bold font-poppins text-center mb-6">Smart<span class="text-primary"> Cash</span></h1>
        <h2 class="text-xl font-bold mb-6 font-poppins text-center">Edit Admin</h2>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block form-label mb-2">Email</label>
                    <input type="email" name="email" value="<?php echo $row['email']; ?>"
                        class="w-full px-3 py-2 border rounded-lg form-input <?php echo !$is_editing_self ? 'form-readonly' : ''; ?>"
                        <?php echo !$is_editing_self ? 'readonly' : ''; ?>>
                </div>
                <div>
                    <label class="block form-label mb-2">Username</label>
                    <input type="text" name="username" value="<?php echo $row['username']; ?>" required
                        class="w-full px-3 py-2 border rounded-lg form-input">
                </div>
                <div>
                    <label class="block form-label mb-2">Password (kosongkan jika tidak diganti)</label>
                    <input type="password" name="password" placeholder="Masukkan password baru"
                        class="w-full px-3 py-2 border rounded-lg form-input <?php echo !$is_editing_self ? 'form-readonly' : ''; ?>"
                        <?php echo !$is_editing_self ? 'readonly' : ''; ?>>
                </div>
                <div>
                    <label class="block form-label mb-2">Foto Admin</label>
                    <?php if ($is_editing_self): ?>
                        <input type="file" name="image" class="w-full px-3 py-2 border rounded-lg form-input">
                        <?php if ($row['image']) : ?>
                            <img src="uploads/<?php echo $row['image']; ?>" class="mt-3 w-20 h-20 rounded-lg border">
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-sm text-gray-500 py-2">Tidak dapat mengubah foto admin lain</p>
                        <?php if ($row['image']) : ?>
                            <img src="uploads/<?php echo $row['image']; ?>" class="mt-3 w-20 h-20 rounded-lg border">
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-6 flex justify-end">
                <a href="admin.php" class="px-4 py-2 mr-2 rounded-lg border text-gray-700 hover:bg-gray-100">Batal</a>
                <button type="submit" name="update_admin" class="btn-primary px-4 py-2 rounded-lg">Simpan</button>
            </div>
        </form>
    </div>
</body>
</html>