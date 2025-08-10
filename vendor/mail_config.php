<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Pastikan ini ada di proyek

function sendMail($to, $subject, $message) {
    $mail = new PHPMailer(true);

    try {
        // Konfigurasi SMTP
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Gunakan SMTP provider lain jika perlu
        $mail->SMTPAuth = true;
        $mail->Username = 'youremail@gmail.com'; // Ganti dengan email pengirim
        $mail->Password = 'yourpassword'; // Ganti dengan password atau App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Pengaturan email
        $mail->setFrom('youremail@gmail.com', 'MediPOS Support');
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $message;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
