<?php
/**
 * SMTP class for PHPMailer compatibility
 * Minimal implementation for compatibility with user's script
 */

class SMTP {
    // Stub class for SMTP functionality
    // This is a minimal implementation for compatibility
    
    public function __construct() {
        // Constructor
    }
    
    public function connect($host, $port = 25, $timeout = 30) {
        // Stub method for SMTP connection
        return false;
    }
    
    public function authenticate($username, $password) {
        // Stub method for SMTP authentication
        return false;
    }
    
    public function quit() {
        // Stub method for SMTP quit
        return true;
    }
}
?>