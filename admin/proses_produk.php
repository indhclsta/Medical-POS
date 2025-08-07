<?php
session_start();
require '../service/connection.php';
require_once __DIR__ . '/../vendor/autoload.php'; // For barcode generation

use Picqer\Barcode\BarcodeGeneratorPNG;

// Function to generate barcode image
function generateBarcode($barcodeText) {
    $generator = new BarcodeGeneratorPNG();
    $barcodeData = $generator->getBarcode($barcodeText, $generator::TYPE_CODE_128);
    $filename = 'barcode_' . time() . '.png';
    file_put_contents('../uploads/barcodes/' . $filename, $barcodeData);
    return $filename;
}

// Function to handle file upload
function uploadFile($file, $targetDir) {
    $filename = uniqid() . '_' . basename($file['name']);
    $targetPath = $targetDir . $filename;
    
    // Check file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Hanya file JPG, PNG, atau GIF yang diperbolehkan'];
    }
    
    // Check file size (max 2MB)
    if ($file['size'] > 2097152) {
        return ['success' => false, 'message' => 'Ukuran file maksimal 2MB'];
    }
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'message' => 'Gagal mengupload file'];
    }
}

// CREATE PRODUCT - Handle product addition
if (isset($_POST['tambah_produk'])) {
    $productName = mysqli_real_escape_string($conn, $_POST['namaProduk']);
    $description = mysqli_real_escape_string($conn, $_POST['deskripsiProduk']);
    $categoryId = (int)$_POST['fid_category'];
    $stock = (int)$_POST['stokProduk'];
    $purchasePrice = (int)$_POST['hargaModalProduk'];
    $sellingPrice = (int)$_POST['hargaJualProduk'];
    $expiryDate = !empty($_POST['exp']) ? mysqli_real_escape_string($conn, $_POST['exp']) : null;
    $barcode = !empty($_POST['barcodeInput']) ? mysqli_real_escape_string($conn, $_POST['barcodeInput']) : uniqid();

    // Calculate margin
    $margin = $sellingPrice - $purchasePrice;

    // Upload product image
    $imageResult = uploadFile($_FILES['gambarProduk'], '../uploads/');
    if (!$imageResult['success']) {
        $_SESSION['message'] = $imageResult['message'];
        $_SESSION['message_type'] = 'error';
        header("Location: manage_product.php");
        exit();
    }
    $imageName = $imageResult['filename'];

    // Generate barcode if not provided
    $barcodeImage = null;
    if (!empty($barcode)) {
        $barcodeImage = generateBarcode($barcode);
    }

    // Insert into database
    $query = "INSERT INTO products (product_name, description, fid_category, qty, starting_price, selling_price, margin, exp, image, barcode, barcode_image) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssiiiidssss", $productName, $description, $categoryId, $stock, $purchasePrice, $sellingPrice, $margin, $expiryDate, $imageName, $barcode, $barcodeImage);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Produk berhasil ditambahkan";
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = "Gagal menambahkan produk: " . $conn->error;
        $_SESSION['message_type'] = 'error';
    }

    $stmt->close();
    header("Location: manage_product.php");
    exit();
}

// UPDATE PRODUCT - Handle product update
if (isset($_POST['update_produk'])) {
    $productId = (int)$_POST['produk_id'];
    $productName = mysqli_real_escape_string($conn, $_POST['namaProdukEdit']);
    $description = mysqli_real_escape_string($conn, $_POST['deskripsiProdukEdit']);
    $categoryId = (int)$_POST['fid_category_edit'];
    $stock = (int)$_POST['stokProdukEdit'];
    $purchasePrice = (int)$_POST['hargaModalProdukEdit'];
    $sellingPrice = (int)$_POST['hargaJualProdukEdit'];
    $expiryDate = !empty($_POST['expEdit']) ? mysqli_real_escape_string($conn, $_POST['expEdit']) : null;
    $barcode = !empty($_POST['barcodeEdit']) ? mysqli_real_escape_string($conn, $_POST['barcodeEdit']) : null;
    $existingImage = $_POST['existing_image'];
    $existingBarcodeImage = $_POST['existing_barcode_image'];

    // Calculate margin
    $margin = $sellingPrice - $purchasePrice;

    // Handle image upload
    $imageName = $existingImage;
    if (!empty($_FILES['gambarProdukEdit']['name'])) {
        // Delete old image if exists
        if (!empty($existingImage) && file_exists("../uploads/$existingImage")) {
            unlink("../uploads/$existingImage");
        }
        
        $imageResult = uploadFile($_FILES['gambarProdukEdit'], '../uploads/');
        if (!$imageResult['success']) {
            $_SESSION['message'] = $imageResult['message'];
            $_SESSION['message_type'] = 'error';
            header("Location: manage_product.php");
            exit();
        }
        $imageName = $imageResult['filename'];
    }

    // Handle barcode update
    $barcodeImage = $existingBarcodeImage;
    if (!empty($barcode) && ($barcode != $_POST['existing_barcode'])) {
        // Delete old barcode image if exists
        if (!empty($existingBarcodeImage) && file_exists("../uploads/barcodes/$existingBarcodeImage")) {
            unlink("../uploads/barcodes/$existingBarcodeImage");
        }
        $barcodeImage = generateBarcode($barcode);
    }

    // Update database
    $query = "UPDATE products SET 
              product_name = ?, 
              description = ?, 
              fid_category = ?, 
              qty = ?, 
              starting_price = ?, 
              selling_price = ?, 
              margin = ?, 
              exp = ?, 
              image = ?, 
              barcode = ?, 
              barcode_image = ?
              WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssiiiidssssi", $productName, $description, $categoryId, $stock, $purchasePrice, $sellingPrice, $margin, $expiryDate, $imageName, $barcode, $barcodeImage, $productId);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Produk berhasil diperbarui";
        $_SESSION['message_type'] = 'success';
    } else {
        $_SESSION['message'] = "Gagal memperbarui produk: " . $conn->error;
        $_SESSION['message_type'] = 'error';
    }

    $stmt->close();
    header("Location: manage_product.php");
    exit();
}

// DELETE PRODUCT - Improved deletion function
if (isset($_GET['hapus'])) {
    $productId = (int)$_GET['hapus'];
    
    // Check if request is AJAX
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    // Response function
    function respond($success, $message, $redirect = null) {
        global $isAjax;
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message,
                'redirect' => $redirect
            ]);
            exit();
        } else {
            $_SESSION['message'] = $message;
            $_SESSION['message_type'] = $success ? 'success' : 'error';
            header("Location: " . ($redirect ?? 'manage_product.php'));
            exit();
        }
    }
    
    try {
        // Begin transaction
        mysqli_begin_transaction($conn);
        
        // 1. Get product data
        $query = "SELECT image, barcode_image FROM products WHERE id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("i", $productId);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Produk tidak ditemukan");
        }
        
        $product = $result->fetch_assoc();
        $stmt->close();
        
        // 2. Delete the product from database
        $deleteQuery = "DELETE FROM products WHERE id = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        
        if (!$deleteStmt) {
            throw new Exception("Prepare delete failed: " . $conn->error);
        }
        
        $deleteStmt->bind_param("i", $productId);
        
        if (!$deleteStmt->execute()) {
            throw new Exception("Delete failed: " . $deleteStmt->error);
        }
        
        if ($deleteStmt->affected_rows === 0) {
            throw new Exception("Tidak ada produk yang terhapus");
        }
        
        $deleteStmt->close();
        
        // 3. Delete associated files
        $deletedFiles = [];
        
        // Delete product image
        if (!empty($product['image'])) {
            $imagePath = '../uploads/' . $product['image'];
            if (file_exists($imagePath)) {
                if (unlink($imagePath)) {
                    $deletedFiles[] = "Gambar produk";
                } else {
                    error_log("Failed to delete product image: $imagePath");
                }
            }
        }
        
        // Delete barcode image
        if (!empty($product['barcode_image'])) {
            $barcodePath = '../uploads/barcodes/' . $product['barcode_image'];
            if (file_exists($barcodePath)) {
                if (unlink($barcodePath)) {
                    $deletedFiles[] = "Barcode";
                } else {
                    error_log("Failed to delete barcode image: $barcodePath");
                }
            }
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Prepare success message
        $message = "Produk berhasil dihapus";
        if (!empty($deletedFiles)) {
            $message .= " (termasuk file: " . implode(", ", $deletedFiles) . ")";
        }
        
        respond(true, $message, 'manage_product.php');
        
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        error_log("Delete product error: " . $e->getMessage());
        respond(false, $e->getMessage());
    }
}


// If no action matched, redirect back
header("Location: manage_product.php");
exit();
?>