<?php
// Gebruik strikte typering voor betere codekwaliteit
declare(strict_types=1);

// Importeer de PHPMailer klassen in de globale namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Laad de PHPMailer-bestanden handmatig
require '../phpmailer/Exception.php';
require '../phpmailer/PHPMailer.php';
require '../phpmailer/SMTP.php';

// Stel de header in om een JSON-response terug te sturen
header('Content-Type: application/json');

// Functie om een JSON-response te sturen en het script te stoppen
function send_json_response(bool $success, string $message, int $http_code = 200): void
{
    http_response_code($http_code);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

// Accepteer alleen POST requests voor de veiligheid
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response(false, 'Ongeldige request methode.', 405);
}

// Haal de JSON-data uit de body van het request
$json_data = file_get_contents('php://input');
if (!$json_data) {
    send_json_response(false, 'Geen data ontvangen.', 400);
}

$data = json_decode($json_data, true);

// Valideer de ontvangen data
$firstName = filter_var($data['firstName'] ?? '', FILTER_SANITIZE_STRING);
$lastName = filter_var($data['lastName'] ?? '', FILTER_SANITIZE_STRING);
$userEmail = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
$screenshot_base64 = $data['screenshot'] ?? '';

if (!$firstName || !$lastName || !$userEmail || !$screenshot_base64) {
    send_json_response(false, 'Niet alle velden zijn correct ingevuld.', 400);
}

// Verwerk de base64 screenshot data
if (preg_match('/^data:image\/(\w+);base64,/', $screenshot_base64, $type)) {
    $screenshot_data = substr($screenshot_base64, strpos($screenshot_base64, ',') + 1);
    $type = strtolower($type[1]);
    if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
        send_json_response(false, 'Ongeldig afbeeldingstype.', 400);
    }
    $screenshot_data = base64_decode($screenshot_data);
    if ($screenshot_data === false) {
        send_json_response(false, 'Base64 decodering mislukt.', 400);
    }
} else {
    send_json_response(false, 'Ongeldige Data URI string voor afbeelding.', 400);
}

// Maak een nieuwe PHPMailer instantie aan
$mail = new PHPMailer(true);

try {
    // --- SERVER INSTELLINGEN (VUL DIT IN MET JE EIGEN GEGEVENS!) ---
    $mail->isSMTP();
    $mail->Host       = 'smtp.office365.com';      // <-- VUL HIER JE SMTP HOST IN
    $mail->SMTPAuth   = true;
    $mail->Username   = 'info@roni.website';         // <-- VUL HIER JE SMTP GEBRUIKERSNAAM IN
    $mail->Password   = 'R2001ony!';     // <-- VUL HIER JE SMTP WACHTWOORD IN
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;  // Gebruik PHPMailer::ENCRYPTION_STARTTLS voor TLS
    $mail->Port       = 465;                         // Poort voor SSL is 465, voor TLS is 587
    $mail->CharSet    = 'UTF-8';

    // --- ONTVANGERS ---
    $mail->setFrom('info@roni.website', 'Sport Opstelling Tool'); // Afzender
    $mail->addAddress('info@roni.website', 'Roni Website Admin');   // Ontvanger (jijzelf)
    $mail->addReplyTo($userEmail, $firstName . ' ' . $lastName);   // 'Antwoord aan' adres

    // --- BIJLAGEN ---
    $filename = 'opstelling-' . date('Y-m-d') . '.jpg';
    $mail->addStringAttachment($screenshot_data, $filename, 'base64', 'image/jpeg');

    // --- INHOUD ---
    $mail->isHTML(true);
    $mail->Subject = 'Nieuwe opstelling ingediend door ' . $firstName . ' ' . $lastName;
    $mail->Body    = "
        <html>
        <body style='font-family: Arial, sans-serif; font-size: 16px; color: #333;'>
            <h2 style='color: #0056b3;'>Nieuwe Opstelling Ontvangen</h2>
            <p>Er is een nieuwe opstelling gemaakt en verstuurd via de Sport Opstelling Tool.</p>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr style='background-color: #f2f2f2;'><td style='padding: 12px; border: 1px solid #ddd;'><strong>Voornaam:</strong></td><td style='padding: 12px; border: 1px solid #ddd;'>" . htmlspecialchars($firstName) . "</td></tr>
                <tr><td style='padding: 12px; border: 1px solid #ddd;'><strong>Achternaam:</strong></td><td style='padding: 12px; border: 1px solid #ddd;'>" . htmlspecialchars($lastName) . "</td></tr>
                <tr style='background-color: #f2f2f2;'><td style='padding: 12px; border: 1px solid #ddd;'><strong>E-mailadres:</strong></td><td style='padding: 12px; border: 1px solid #ddd;'>" . htmlspecialchars($userEmail) . "</td></tr>
            </table>
            <p>De opstelling is als bijlage toegevoegd aan deze e-mail (<strong>" . $filename . "</strong>).</p>
            <hr>
            <p style='font-size: 12px; color: #888;'>Dit is een automatisch gegenereerde e-mail.</p>
        </body>
        </html>";
    $mail->AltBody = "Nieuwe opstelling ontvangen van: {$firstName} {$lastName} ({$userEmail}). De opstelling is bijgevoegd.";

    $mail->send();
    send_json_response(true, 'E-mail succesvol verstuurd!');

} catch (Exception $e) {
    // Stuur een foutmelding terug als het versturen mislukt
    send_json_response(false, "E-mail kon niet worden verstuurd. Foutmelding: {$mail->ErrorInfo}", 500);
}
?>