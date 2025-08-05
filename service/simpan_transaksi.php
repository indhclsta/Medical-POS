<?php
session_start();
include './connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $discount = isset($_POST['discount']) ? (float)$_POST['discount'] : 0;
    $nominal = isset($_POST['nominal']) ? (int)$_POST['nominal'] : 0;
    $metode = isset($_POST['metode']) ? trim($_POST['metode']) : '';
    $id_admin = isset($_SESSION['id']) ? $_SESSION['id'] : null;
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $total_after_discount = isset($_POST['total']) ? (float)$_POST['total'] : 0;
    $subtotal_before_discount = isset($_POST['subtotal_before_discount']) ? (float)$_POST['subtotal_before_discount'] : 0;

    // Validasi login admin
    if (!$id_admin) {
        $_SESSION['error'] = "Anda belum login.";
        header("Location: ../login.php");
        exit();
    }

    // Validasi keranjang
    if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
        $_SESSION['error'] = "Keranjang belanja kosong.";
        header("Location: ../keranjang.php");
        exit();
    }

    // Hitung total dari keranjang dan margin total
    $calculated_subtotal = 0;
    $margin_total = 0;
    foreach ($_SESSION['cart'] as $item) {
        $calculated_subtotal += $item['harga'] * $item['jumlah'];
        
        // Ambil margin dari database untuk setiap produk
        $product_stmt = $conn->prepare("SELECT margin, selling_price, starting_price FROM products WHERE id = ?");
        $product_stmt->bind_param("i", $item['id']);
        $product_stmt->execute();
        $product_result = $product_stmt->get_result();
        
        if ($product_result->num_rows > 0) {
            $product_data = $product_result->fetch_assoc();
            // Hitung margin jika tidak ada di database
            $margin = $product_data['margin'] ?? ($product_data['selling_price'] - $product_data['starting_price']);
            $margin_total += $margin * $item['jumlah'];
        }
    }

    // Validasi konsistensi subtotal
    if (abs($calculated_subtotal - $subtotal_before_discount) > 0.01) {
        $_SESSION['error'] = "Perhitungan subtotal tidak valid";
        header("Location: ../keranjang.php");
        exit();
    }

    // Validasi konsistensi diskon
    $calculated_discount = $subtotal_before_discount - $total_after_discount;
    if (abs($calculated_discount - ($subtotal_before_discount * $discount)) > 0.01) {
        $_SESSION['error'] = "Perhitungan diskon tidak valid";
        header("Location: ../keranjang.php");
        exit();
    }

    // Validasi pembayaran (gunakan total setelah diskon)
    if ($nominal < $total_after_discount) {
        $_SESSION['error'] = "Nominal pembayaran kurang dari total setelah diskon.";
        header("Location: ../keranjang.php");
        exit();
    }

    // Inisialisasi ID member jika ada
    $id_member = null;
    $member_points = 0;
    if (!empty($phone)) {
        $stmt = $conn->prepare("SELECT id, point FROM member WHERE phone = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $member = $result->fetch_assoc();
            $id_member = $member['id'];
            $member_points = $member['point'];

            // Update last_active dan status jadi active
            $update_stmt = $conn->prepare("UPDATE member SET last_active = NOW(), status = 'active' WHERE id = ?");
            $update_stmt->bind_param("i", $id_member);
            $update_stmt->execute();
        } else {
            $_SESSION['error'] = "Member tidak ditemukan.";
            header("Location: ../keranjang.php");
            exit();
        }
    }

    // Hitung poin yang didapat (berdasarkan subtotal sebelum diskon)
    $poin_didapat = floor($subtotal_before_discount / 10000); // 1 poin per Rp10.000
    $kembalian = $nominal - $total_after_discount;

    // Mulai transaksi database
    $conn->begin_transaction();

    try {
        // Simpan transaksi utama
        $stmt = $conn->prepare("INSERT INTO transactions 
            (fid_admin, fid_member, total_price, margin_total, paid_amount, kembalian, payment_method, points, discount) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("iiddddsid", 
            $id_admin, 
            $id_member, 
            $total_after_discount,
            $margin_total,
            $nominal, 
            $kembalian, 
            $metode,
            $poin_didapat,
            $discount
        );

        if (!$stmt->execute()) {
            throw new Exception("Gagal simpan transaksi: " . $stmt->error);
        }

        $transaction_id = $conn->insert_id;

        // Simpan detail item transaksi
        foreach ($_SESSION['cart'] as $item) {
            $product_id = $item['id'];
            $quantity = $item['jumlah'];
            $price = $item['harga'];
            $subtotal = $price * $quantity;

            $detail_stmt = $conn->prepare("INSERT INTO transaction_details 
                (transaction_id, product_id, quantity, subtotal, harga) 
                VALUES (?, ?, ?, ?, ?)");

            $detail_stmt->bind_param("iiidi", 
                $transaction_id, 
                $product_id, 
                $quantity, 
                $subtotal,
                $price
            );

            if (!$detail_stmt->execute()) {
                throw new Exception("Gagal simpan detail transaksi: " . $detail_stmt->error);
            }

            // Update stok produk dengan pengecekan stok cukup
            $update_stmt = $conn->prepare("UPDATE products SET qty = qty - ? WHERE id = ? AND qty >= ?");
            $update_stmt->bind_param("iii", $quantity, $product_id, $quantity);
            if (!$update_stmt->execute() || $update_stmt->affected_rows === 0) {
                throw new Exception("Stok produk tidak mencukupi atau produk tidak ditemukan");
            }
        }

        // Tambahkan poin ke member (jika ada)
        if ($id_member && $poin_didapat > 0) {
            $stmt = $conn->prepare("UPDATE member SET point = point + ? WHERE id = ?");
            $stmt->bind_param("ii", $poin_didapat, $id_member);
            if (!$stmt->execute()) {
                throw new Exception("Gagal update poin member: " . $stmt->error);
            }
        }

        // Commit transaksi jika semua berhasil
        $conn->commit();
        unset($_SESSION['cart']);
        unset($_SESSION['cart_last_activity']);

        // Redirect ke halaman sukses
        $_SESSION['success'] = "Transaksi berhasil dengan ID: $transaction_id";
        header("Location: sukses.php?id=$transaction_id");
        exit();

    } catch (Exception $e) {
        // Rollback transaksi jika gagal
        $conn->rollback();
        $_SESSION['error'] = "Transaksi gagal: " . $e->getMessage();
        header("Location: ../keranjang.php");
        exit();
    }
} else {
    $_SESSION['error'] = "Metode request tidak valid";
    header("Location: ../keranjang.php");
    exit();
}
?>