<?php
// unauthorized.php
http_response_code(403); // Set HTTP status code ke 403 Forbidden
?>
<!DOCTYPE html>
<html>
<head>
    <title>Akses Ditolak</title>
</head>
<body>
    <h1>403 - Tidak Diizinkan</h1>
    <p>Maaf, Anda tidak memiliki hak akses ke halaman ini.</p>
    <a href="index.php">Kembali ke Beranda</a>
</body>
</html>