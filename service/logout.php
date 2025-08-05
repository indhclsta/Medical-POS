<?php
session_start();
$_SESSION = array();
session_destroy();

// Hapus cookie sesi jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// SweetAlert untuk logout
echo "<script>
    setTimeout(function() {
        Swal.fire({
            title: 'Logout Berhasil!',
            text: 'Anda akan diarahkan ke halaman login.',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false
        }).then(() => {
            window.location.href = 'login.php';
        });
    }, 500);
</script>";
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
