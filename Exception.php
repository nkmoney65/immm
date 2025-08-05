<?php
/**
 * Exception class stub for PHPMailer compatibility
 */

class Exception extends \Exception {
    // Stub class extending PHP's built-in Exception
    // This provides compatibility for PHPMailer exception handling
    
    public function __construct($message = "", $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
?>