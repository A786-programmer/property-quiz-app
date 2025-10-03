<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

// Receive JSON from frontend
$input = json_decode(file_get_contents("php://input"), true);

if (!$input || empty($input['image'])) {
    die("No image received");
}

// Extract and decode image data
$imageData = $input['image'];
$imageData = str_replace('data:image/png;base64,', '', $imageData);
$imageData = str_replace(' ', '+', $imageData);
$imageBinary = base64_decode($imageData);

// Save image in results folder
if (!is_dir('results')) {
    mkdir('results', 0777, true);
}

$imageFile = 'results/chart_' . time() . '.png';
file_put_contents($imageFile, $imageBinary);

// Check if image was saved successfully
if (!file_exists($imageFile) || filesize($imageFile) === 0) {
    // fallback: use latest image in results folder
    $files = glob('results/chart_*.png');
    if (!empty($files)) {
        rsort($files); // latest file first
        $imageFile = $files[0];
    } else {
        die("No valid image available to send.");
    }
}

try {
    $mail = new PHPMailer(true);

    // SMTP Setup
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'propixel786@gmail.com';
    $mail->Password   = 'dpyn hunf rcvg nhsv'; // Gmail App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;

    // Email setup
    $mail->setFrom('propixel786@gmail.com', 'Quiz App');
    $mail->addAddress($_SESSION['email']); 
    $mail->addAddress("shahrukhqtech@gmail.com"); // jisko email bhejni hai

    $mail->isHTML(true);
    $mail->Subject = "Quiz Result Graph";
    $mail->isHTML(true);
    $mail->Subject = "Quiz Result Graph";
    $mail->Body    = "
        <h3>Quiz Result Graph</h3>
        <p>Attached is the quiz result graph.</p>
        <p><strong>Name:</strong> " . ($_SESSION['name'] ?? 'N/A') . "</p>
        <p><strong>Phone:</strong> " . ($_SESSION['phone'] ?? 'N/A') . "</p>
    ";


    // Forcefully attach image
    $mail->addAttachment($imageFile, 'quiz_chart.png');

    $mail->send();
    echo "Email sent successfully with image!";
} catch (Exception $e) {
    echo "Mailer Error: {$mail->ErrorInfo}";
}
