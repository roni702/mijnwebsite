<?php
// Gebruik de PHPMailer klassen vanuit de 'phpmailer' map
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Laad de PHPMailer bestanden handmatig (non-composer setup)
require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

/**
 * Stuurt een schoon JSON-antwoord en stopt het script.
 * @param bool $success Geeft aan of de operatie succesvol was.
 * @param string $message Het bericht om terug te sturen.
 */
function send_json_response($success, $message) {
    // Wis alle eventuele voorgaande (onbedoelde) output om een schone JSON te garanderen
    if (ob_get_level() > 0) {
        ob_clean();
    }
    // Stel de correcte content type header in
    header('Content-Type: application/json');
    // Stuur het JSON antwoord en stop de uitvoering van het script
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// Haal de JSON-data op die door het JavaScript is gestuurd
$input = file_get_contents('php://input');
$params = json_decode($input, true);

// Valideer de ontvangen data
if (!$params || !isset($params['email'], $params['firstName'], $params['lastName'], $params['screenshot'])) {
    send_json_response(false, 'Incomplete data ontvangen. Vul alle velden in.');
}

// Splits de base64-string van de screenshot
list(, $screenshot_data) = explode(',', $params['screenshot']);

// Maak een nieuwe PHPMailer instantie
$mail = new PHPMailer(true);

try {
    // --- SERVER INSTELLINGEN (VUL JE EIGEN GEGEVENS IN!) ---
    $mail->isSMTP();
    $mail->Host       = 'smtpout.secureserver.net';       // SMTP server van GoDaddy
    $mail->SMTPAuth   = true;
    $mail->Username   = 'info@roni.website';              // Je volledige e-mailadres
    $mail->Password   = 'R2001ony!';      // BELANGRIJK: Vervang dit door je nieuwe, werkende wachtwoord!
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;   // Gebruik SSL
    $mail->Port       = 465;                          // Poort voor SSL

    // --- AFZENDER ---
    $mail->setFrom('info@roni.website', 'Roni Opstelling Tool');

    // --- BIJLAGE & INGESLOTEN AFBEELDING ---
    $imageData = base64_decode($screenshot_data);
    $mail->addStringAttachment($imageData, 'opstelling.png');
    $mail->addStringEmbeddedImage($imageData, 'opstelling-cid', 'opstelling.png');

    // --- E-MAIL 1: NOTIFICATIE NAAR JOU (DE ADMIN) ---
    $mail->addAddress('info@roni.website', 'Admin'); 
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = 'Nieuwe opstelling ingediend door ' . htmlspecialchars($params['firstName']);
    $mail->Body    = "<h3>Nieuwe inzending!</h3>
                    <p>Er is een nieuwe opstelling ingediend door:</p>
                    <ul>
                        <li><b>Naam:</b> " . htmlspecialchars($params['firstName']) . " " . htmlspecialchars($params['lastName']) . "</li>
                        <li><b>E-mail:</b> " . htmlspecialchars($params['email']) . "</li>
                    </ul>
                    <h4>Opstelling:</h4>
                    <img src='cid:opstelling-cid' alt='Opstelling' style='max-width: 100%; border: 1px solid #ccc;'/>";
    $mail->send();

    // --- E-MAIL 2: BEVESTIGING NAAR DE KLANT ---
    $mail->clearAddresses();
    $mail->addAddress($params['email'], htmlspecialchars($params['firstName']) . ' ' . htmlspecialchars($params['lastName']));
    $mail->Subject = 'Je opstelling is succesvol ontvangen!';
    $mail->Body    = "<h3>Hallo " . htmlspecialchars($params['firstName']) . ",</h3>
                    <p>Bedankt voor het gebruiken van de tool! Hieronder vind je de door jou gemaakte opstelling.</p>
                    <img src='cid:opstelling-cid' alt='Jouw opstelling' style='max-width: 100%; border: 1px solid #ccc;'/>
                    <p>Met sportieve groet,<br>Het Team van Roni Website</p>";
    $mail->send();

    // Stuur een succesbericht terug naar de JavaScript frontend
    send_json_response(true, 'E-mails succesvol verstuurd!');

} catch (Exception $e) {
    // Stuur een foutbericht terug bij een mislukte poging
    send_json_response(false, "E-mail kon niet worden verstuurd. Fout: {$mail->ErrorInfo}");
}
?>