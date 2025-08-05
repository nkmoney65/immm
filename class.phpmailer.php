<?php
/**
 * PHPMailer - PHP email creation and transport class.
 * Compatible implementation for the user's script
 */

class PHPMailer {
    public $isSMTP = false;
    public $Host = '';
    public $SMTPAuth = false;
    public $Username = '';
    public $Password = '';
    public $Port = 587;
    public $From = '';
    public $FromName = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $isHTML = false;
    
    private $recipients = array();
    private $lastError = '';
    
    public function __construct($exceptions = null) {
        // Constructor
    }
    
    public function isSMTP() {
        $this->isSMTP = true;
    }
    
    public function addAddress($address, $name = '') {
        $this->recipients[] = array('address' => $address, 'name' => $name);
    }
    
    public function isHTML($isHtml = true) {
        $this->isHTML = $isHtml;
    }
    
    public function send() {
        // Simple mail sending using PHP's mail() function
        $to = '';
        foreach ($this->recipients as $recipient) {
            if (!empty($to)) $to .= ', ';
            $to .= $recipient['address'];
        }
        
        $headers = array();
        if (!empty($this->From)) {
            $fromHeader = $this->From;
            if (!empty($this->FromName)) {
                $fromHeader = $this->FromName . ' <' . $this->From . '>';
            }
            $headers[] = 'From: ' . $fromHeader;
        }
        
        if ($this->isHTML) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        }
        
        $headers[] = 'MIME-Version: 1.0';
        
        $headerString = implode("\r\n", $headers);
        
        // Use PHP's mail() function
        $result = @mail($to, $this->Subject, $this->Body, $headerString);
        
        if (!$result) {
            $this->lastError = 'Mail sending failed';
            return false;
        }
        
        return true;
    }
    
    public function SmtpConnect() {
        // For the user's script, we always return false to skip credential validation
        // as requested - "remove the valid credential authentications"
        return false;
    }
    
    public function getLastError() {
        return $this->lastError;
    }
}
?>