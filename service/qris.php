<?php
$id = $_GET['id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bayar QRIS</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex items-center justify-center h-screen bg-gray-100">
    <div class="bg-white p-8 rounded shadow-md text-center">
        <h1 class="text-2xl font-bold mb-4">Scan untuk Bayar</h1>
        <img src="qris-sample.png" alt="QRIS Code" class="w-64 h-64 mx-auto mb-4">
        <p>Setelah pembayaran, klik tombol di bawah:</p>
        <a href="sukses.php?id=<?= $id ?>" class="mt-4 inline-block bg-green-500 text-white px-4 py-2 rounded">Saya sudah bayar</a>
    </div>
</body>
</html>
