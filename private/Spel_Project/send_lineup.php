<?php
// Gebruik de PHPMailer-classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// 1. LAAD DE BENODIGDE BESTANDEN
// We laden de PHPMailer-bestanden handmatig en daarna onze veilige configuratie.
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'config.php';

// Standaard antwoord is JSON
header('Content-Type: application/json');

// Helper-functie voor een net antwoord
function sendJsonResponse($status, $message) {
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// 2. ONTVANG EN VALIDEER DE DATA
// We controleren of de data van het formulier correct binnenkomt.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse('error', 'Ongeldige verzoekmethode.');
}
$data = json_decode(file_get_contents('php://input'));
if (!$data || !filter_var($data->email, FILTER_VALIDATE_EMAIL) || empty($data->screenshot)) {
    sendJsonResponse('error', 'Verplichte velden ontbreken of zijn onjuist.');
}

// Maak de data veilig voor gebruik
$firstName = htmlspecialchars($data->firstName ?? 'N.v.t.');
$lastName = htmlspecialchars($data->lastName ?? 'N.v.t.');
$userEmail = $data->email;
$screenshotData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data->screenshot));

// 3. VERSTUUR DE E-MAILS
$mail = new PHPMailer(true);
try {
    // Stel de server-instellingen in met de data uit config.php
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    // ---- E-MAIL 1: Notificatie naar jou (de admin) ----
    $mail->setFrom(SMTP_USERNAME, FROM_NAME);
    $mail->addAddress(SMTP_USERNAME, 'Admin');
    $mail->addReplyTo($userEmail, "{$firstName} {$lastName}");
    $mail->isHTML(true);
    $mail->Subject = "Nieuwe opstelling van: {$firstName}";
    $mail->Body    = "Een nieuwe opstelling is ingediend.<br><br><b>Naam:</b> {$firstName} {$lastName}<br><b>E-mail:</b> {$userEmail}<br><br>De opstelling staat in de bijlage.";
    $mail->addStringAttachment($screenshotData, 'opstelling.jpg');
    $mail->send();

    // ---- E-MAIL 2: Bevestiging naar de gebruiker ----
    $mail->clearAddresses();
    $mail->clearReplyTos();
    $mail->clearAttachments();
    $mail->addAddress($userEmail, "{$firstName} {$lastName}");
    $mail->Subject = 'Jouw opstelling van Roni.Website';
    $mail->Body    = "Hoi {$firstName},<br><br>Bedankt voor het gebruiken van de opstelling tool! In de bijlage vind je de afbeelding die je hebt gemaakt.<br><br>Met vriendelijke groet,<br>Het Roni.Website Team";
    $mail->addStringAttachment($screenshotData, 'jouw-opstelling.jpg');
    $mail->send();

    // Alles is goed gegaan, stuur een succesbericht terug
    sendJsonResponse('success', 'Opstelling succesvol verstuurd!');

} catch (Exception $e) {
    // Er ging iets mis. Log de fout voor jezelf en geef een algemene melding aan de gebruiker.
    error_log("PHPMailer Fout: " . $mail->ErrorInfo);
    sendJsonResponse('error', 'Het versturen is mislukt door een technisch probleem.');
}