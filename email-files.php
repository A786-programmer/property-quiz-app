<?php
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

    require 'vendor/autoload.php';

    $mail = new PHPMailer(true);

    try {
        // SMTP Server Configuration
        $mail->isSMTP();                                 // Set mailer to use SMTP
        $mail->Host       = 'smtp.gmail.com'; // SMTP server (use the one from cPanel setup)
        $mail->SMTPAuth   = true;                        // Enable SMTP authentication
        $mail->Username   = 'propixel786@gmail.com'; // Your full email address
        $mail->Password   = 'dpyn hunf rcvg nhsv';              // Your email password
        $mail->SMTPSecure = 'tls';  // Enable SSL encryption
        $mail->Port       = 587;                         // TCP port to connect to (use 465 for SSL)
        
        // Set sender details (you can set this to the same email address)
        $mail->setFrom('propixel786@gmail.com'); // Sender's email and name
    } catch (Exception $e) {
        echo "Mailer Error: {$mail->ErrorInfo}";
    }