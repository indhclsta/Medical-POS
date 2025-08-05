<?php
session_start();
include './service/connection.php';

// Handle member creation/editing
if (isset($_POST['simpan'])) {
    $id = $_POST['id'] ?? null;
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $point = isset($_POST['point']) ? (int)$_POST['point'] : 0;
    $status = isset($_POST['status']) ? mysqli_real_escape_string($conn, $_POST['status']) : 'active';
    $inactive_duration = isset($_POST['inactive_duration']) ? (int)$_POST['inactive_duration'] : 30;
    $duration_unit = isset($_POST['duration_unit']) ? mysqli_real_escape_string($conn, $_POST['duration_unit']) : 'DAY';

    if ($id) {
        // Update existing member
        $query = "UPDATE member SET
                 name='$name',
                 phone='$phone',
                 point='$point', 
                 status='$status',
                 inactive_duration='$inactive_duration',
                 duration_unit='$duration_unit'
                 WHERE id='$id'";
    } else {
        // Create new member with default active status and 0 points
        $query = "INSERT INTO member 
                 (name, phone, point, status, last_active, inactive_duration, duration_unit) 
                 VALUES 
                 ('$name', '$phone', 0, 'active', NOW(), 30, 'DAY')";
    }

    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Data member berhasil disimpan";
    } else {
        $_SESSION['error'] = "Gagal menyimpan data: " . mysqli_error($conn);
    }
    header("Location: member.php");
    exit();
}

// Handle member deletion
if (isset($_POST['hapus'])) {
    $id = (int)$_POST['id'];
    $query = "DELETE FROM member WHERE id='$id'";
    
    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Member berhasil dihapus";
    } else {
        $_SESSION['error'] = "Gagal menghapus member: " . mysqli_error($conn);
    }
    header("Location: member.php");
    exit();
}

// Handle member reactivation
if (isset($_POST['activate'])) {
    $id = (int)$_POST['id'];
    $query = "UPDATE member SET status='active', last_active=NOW() WHERE id='$id'";
    
    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Member berhasil diaktifkan kembali";
    } else {
        $_SESSION['error'] = "Gagal mengaktifkan member: " . mysqli_error($conn);
    }
    header("Location: member.php");
    exit();
}

// Handle member status check (called periodically)
if (isset($_GET['check_status'])) {
    // Set members as inactive if last_active > 5 seconds ago
    $query = "UPDATE member 
             SET status = 'non-active' 
             WHERE status = 'active' 
             AND last_active < DATE_SUB(NOW(), INTERVAL 5 MINUTE)";
    mysqli_query($conn, $query);

    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'updated' => mysqli_affected_rows($conn)]);
    exit();
}

// Handle last_active update during transactions
if (isset($_POST['update_activity'])) {
    $id = (int)$_POST['id'];
    $query = "UPDATE member SET last_active=NOW() WHERE id='$id'";
    mysqli_query($conn, $query);
    exit();
}

header("Location: member.php");
exit();