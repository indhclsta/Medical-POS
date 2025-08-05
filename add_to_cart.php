<?php 
session_start();
include './service/connection.php'; // pastikan file ini ada dan nama file benar

// Cek jika data yang dikirimkan lengkap
if (!isset($_POST['product_id'], $_POST['product_name'], $_POST['price'], $_POST['qty'], $_POST['image'], $_POST['category'])) {
    echo "<script>alert('Data tidak lengkap!'); window.history.back();</script>";
    exit;
}

$product_id = $_POST['product_id'];
$nama = $_POST['product_name'];
$harga = $_POST['price'];
$qty = $_POST['qty'];
$gambar = $_POST['image'];
$kategori = $_POST['category'];

// Cek stok dari database
$query = "SELECT qty FROM products WHERE id = $product_id";
$result = mysqli_query($conn, $query);
$product = mysqli_fetch_assoc($result);

if ($product['qty'] < $qty) {
    // Jika stok tidak cukup
    header("Location: transaksi.php?error=stok_tidak_cukup");
    exit;
}

// Inisialisasi keranjang jika belum ada
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Hitung total item di keranjang
$totalItem = 0;
foreach ($_SESSION['cart'] as $item) {
    $totalItem += $item['jumlah'];
}

// Cek jika jumlah item di keranjang melebihi batas maksimal
if ($totalItem + $qty > 10) {
    echo "<script>alert('Maksimal hanya 10 item di keranjang!'); window.history.back();</script>";
    exit;
}

// Tambah produk ke keranjang atau update jika sudah ada
$found = false;
foreach ($_SESSION['cart'] as $index => $item) {
    if ($item['id'] == $product_id) {
        $_SESSION['cart'][$index]['jumlah'] += $qty;
        $found = true;
        break;
    }
}

// Jika produk belum ada, tambahkan ke keranjang
if (!$found) {
    $_SESSION['cart'][] = [
        'id' => $product_id,
        'nama' => $nama,
        'harga' => $harga,
        'gambar' => $gambar,
        'kategori' => $kategori,
        'jumlah' => $qty
    ];
}

// Redirect ke halaman transaksi
header("Location: transaksi.php");
exit;

?>
