<?php

require_once dirname(__DIR__) . '/config/EnvLoader.php';

class SmtpController
{
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;

    /**
     * Constructor - Load SMTP configuration from .env file
     */
    public function __construct()
    {
        // Load environment variables from .env file
        EnvLoader::load();

        // Set SMTP configuration from environment variables
        $this->smtpHost = EnvLoader::get('SMTP_HOST', 'smtp.gmail.com');
        $this->smtpPort = EnvLoader::get('SMTP_PORT', 587);
        $this->smtpUsername = EnvLoader::get('SMTP_USERNAME', '');
        $this->smtpPassword = EnvLoader::get('SMTP_PASSWORD', '');

        // Validate that credentials are set
        if (empty($this->smtpUsername) || empty($this->smtpPassword)) {
            throw new Exception('SMTP credentials not configured in .env file');
        }
    }
    
    /**
     * Send email via SMTP
     */
    public function sendEmail($from, $to, $subject, $content)
    {
        try {
            // Validate email addresses
            if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid sender email address.');
            }
            
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid recipient email address.');
            }
            
            // Create SMTP connection
            $socket = fsockopen($this->smtpHost, $this->smtpPort, $errno, $errstr, 30);
            
            if (!$socket) {
                throw new Exception("Failed to connect to SMTP server: $errstr ($errno)");
            }
            
            // Read greeting
            $response = fgets($socket, 1024);
            if (strpos($response, '220') === false) {
                throw new Exception('SMTP connection failed.');
            }
            
            // Send HELO command
            fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
            $response = fgets($socket, 1024);
            
            // Start TLS encryption
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 1024);
            
            if (strpos($response, '220') === false) {
                throw new Exception('Failed to start TLS encryption.');
            }
            
            // Enable crypto for secure connection
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            // Send HELO again after TLS
            fputs($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
            $response = fgets($socket, 1024);
            
            // Authenticate
            fputs($socket, "AUTH LOGIN\r\n");
            $response = fgets($socket, 1024);
            
            if (strpos($response, '334') === false) {
                throw new Exception('SMTP authentication failed.');
            }
            
            fputs($socket, base64_encode($this->smtpUsername) . "\r\n");
            $response = fgets($socket, 1024);
            
            fputs($socket, base64_encode($this->smtpPassword) . "\r\n");
            $response = fgets($socket, 1024);
            
            if (strpos($response, '235') === false) {
                throw new Exception('Invalid SMTP credentials.');
            }
            
            // Set sender
            fputs($socket, "MAIL FROM: <{$from}>\r\n");
            $response = fgets($socket, 1024);
            
            if (strpos($response, '250') === false) {
                throw new Exception('Failed to set sender.');
            }
            
            // Set recipient
            fputs($socket, "RCPT TO: <{$to}>\r\n");
            $response = fgets($socket, 1024);
            
            if (strpos($response, '250') === false) {
                throw new Exception('Failed to set recipient.');
            }
            
            // Send message data
            fputs($socket, "DATA\r\n");
            $response = fgets($socket, 1024);
            
            if (strpos($response, '354') === false) {
                throw new Exception('Failed to send email data.');
            }
            
            // Compose email headers and body
            $headers = "From: {$from}\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: 8bit\r\n";
            
            $message = $headers . "\r\n" . $content;
            
            fputs($socket, $message . "\r\n.\r\n");
            $response = fgets($socket, 1024);
            
            if (strpos($response, '250') === false) {
                throw new Exception('Failed to send email message.');
            }
            
            // Close connection
            fputs($socket, "QUIT\r\n");
            fclose($socket);
            
            return true;
            
        } catch (Exception $e) {
            error_log('SMTP Error: ' . $e->getMessage());
            return false;
        }
    }
}
?>
