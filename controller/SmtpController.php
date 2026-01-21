<?php

require_once dirname(__DIR__) . '/config/EnvLoader.php';
require_once dirname(__DIR__) . '/config/Logger.php';

class SmtpController
{
    private $smtpHost;
    private $smtpPort;
    private $smtpUsername;
    private $smtpPassword;
    private $smtpSecurity;
    private $requireAuth;

    /**
     * Constructor to initialize SMTP configuration
     */
    public function __construct()
    {
        // Initialize logger
        Logger::init();

        // Load environment variables from .env file
        EnvLoader::load();

        // Set SMTP configuration from environment variables
        $this->smtpHost = EnvLoader::get('SMTP_HOST', 'smtp.gmail.com');
        $this->smtpPort = EnvLoader::get('SMTP_PORT', 587);
        $this->smtpUsername = EnvLoader::get('SMTP_USERNAME', '');
        $this->smtpPassword = EnvLoader::get('SMTP_PASSWORD', '');
        
        // Get security setting from env, but auto-detect based on port if needed
        $configuredSecurity = strtolower(EnvLoader::get('SMTP_SECURITY', ''));
        
        // Get authentication requirement setting
        $requireAuthEnv = EnvLoader::get('SMTP_REQUIRE_AUTH', 'true');
        $this->requireAuth = strtolower($requireAuthEnv) === 'true' || $requireAuthEnv === '1';
        
        // Auto-detect security based on port if not explicitly set
        if (empty($configuredSecurity)) {
            if ($this->smtpPort == 465) {
                $this->smtpSecurity = 'ssl';
            } else if ($this->smtpPort == 587) {
                $this->smtpSecurity = 'starttls';
            } else if ($this->smtpPort == 25) {
                $this->smtpSecurity = 'starttls'; // Port 25 typically uses STARTTLS
            } else {
                $this->smtpSecurity = 'starttls'; // Default to STARTTLS
            }
            Logger::info("Auto-detected security mode: {$this->smtpSecurity} for port {$this->smtpPort}");
        } else {
            $this->smtpSecurity = $configuredSecurity;
        }

        // Validate security option
        if (!in_array($this->smtpSecurity, ['starttls', 'ssl', 'none'])) {
            Logger::error('Invalid SMTP_SECURITY value. Must be: starttls, ssl, or none');
            throw new Exception('Invalid SMTP_SECURITY value in .env file');
        }
        
        // Validate port/security combination
        if ($this->smtpPort == 25 && $this->smtpSecurity === 'ssl') {
            Logger::warning("Port 25 typically doesn't use implicit SSL. Switching to STARTTLS.");
            $this->smtpSecurity = 'starttls';
        }
        if ($this->smtpPort == 465 && $this->smtpSecurity !== 'ssl') {
            Logger::warning("Port 465 typically uses implicit SSL. Switching to SSL.");
            $this->smtpSecurity = 'ssl';
        }

        // Validate that credentials are set
        if (empty($this->smtpHost) || empty($this->smtpPort)) {
            Logger::error('SMTP credentials not configured in .env file');
            throw new Exception('SMTP credentials not configured in .env file');
        }

        Logger::info('SmtpController initialized successfully');
    }

    /**
     * Read complete SMTP response (handles multi-line responses)
     */
    private function readSmtpResponse($socket)
    {
        $response = '';
        $continue = true;
        
        while ($continue) {
            $line = fgets($socket, 1024);
            if (!$line) {
                break;
            }
            $response .= $line;
            
            // Check if this is the last line (format: "code text" instead of "code-text")
            if (preg_match('/^\d{3} /', $line)) {
                $continue = false;
            }
        }
        
        return $response;
    }
    
    /**
     * Send command and get response
     */
    private function sendSmtpCommand($socket, $command)
    {
        fputs($socket, $command . "\r\n");
        return $this->readSmtpResponse($socket);
    }
    
    /**
     * Authenticate with SMTP server (supports LOGIN and PLAIN, or skip if not required)
     */
    private function authenticateSmtp($socket)
    {
        if (!$this->requireAuth) {
            Logger::info("Authentication not required, skipping AUTH");
            return true;
        }
        
        // Try AUTH PLAIN first (preferred by Outlook/Office365)
        Logger::info("Attempting AUTH PLAIN...");
        $response = $this->sendSmtpCommand($socket, "AUTH PLAIN");
        
        if (strpos($response, '334') !== false) {
            // AUTH PLAIN expects: base64(username\0username\0password)
            $authString = base64_encode("\0" . $this->smtpUsername . "\0" . $this->smtpPassword);
            Logger::info("Sending PLAIN credentials...");
            $response = $this->sendSmtpCommand($socket, $authString);
            
            if (strpos($response, '235') !== false) {
                Logger::info("AUTH PLAIN authentication successful");
                return true;
            } else {
                Logger::error("AUTH PLAIN failed. Response: " . trim($response));
                return false;
            }
        }
        
        // Try AUTH LOGIN as fallback (supported by Gmail, etc.)
        Logger::info("AUTH PLAIN not available, trying AUTH LOGIN...");
        $response = $this->sendSmtpCommand($socket, "AUTH LOGIN");
        
        if (strpos($response, '334') !== false) {
            Logger::info("Sending username: " . $this->smtpUsername);
            $encodedUsername = base64_encode($this->smtpUsername);
            Logger::info("Base64 encoded username: " . $encodedUsername);
            $response = $this->sendSmtpCommand($socket, $encodedUsername);
            Logger::info("Username response: " . trim($response));
            
            if (strpos($response, '535') !== false) {
                Logger::error("Authentication failed at username stage - check credentials");
                return false;
            }
            
            if (strpos($response, '334') === false) {
                Logger::error("Unexpected response after username. Response: " . trim($response));
                return false;
            }
            
            Logger::info("Sending password (masked)...");
            $encodedPassword = base64_encode($this->smtpPassword);
            Logger::info("Base64 encoded password length: " . strlen($encodedPassword) . " chars");
            Logger::info("Password length before encoding: " . strlen($this->smtpPassword) . " chars");
            $response = $this->sendSmtpCommand($socket, $encodedPassword);
            Logger::info("Password response: " . trim($response));
            
            if (strpos($response, '235') !== false) {
                Logger::info("AUTH LOGIN authentication successful");
                return true;
            } else {
                Logger::error("AUTH LOGIN failed. Response: " . trim($response));
                return false;
            }
        }
        
        Logger::error("No supported authentication methods available. AUTH response: " . trim($response));
        return false;
    }
    
    /**
     * Send email via SMTP
     */
    public function sendEmail($from, $to, $subject, $content)
    {
        $socket = null;
        
        try {
            Logger::info("Attempting to send email from: {$from} to: {$to}");

            // Validate email addresses
            if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid sender email address.');
            }
            
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid recipient email address.');
            }

            Logger::info("Email addresses validated successfully");
            Logger::info("Connecting to SMTP server: {$this->smtpHost}:{$this->smtpPort}");
            Logger::info("Security mode: {$this->smtpSecurity}");
            
            // Use SSL context for implicit SSL (port 465)
            $context = stream_context_create();
            if ($this->smtpSecurity === 'ssl') {
                stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
                stream_context_set_option($context, 'ssl', 'verify_peer', false);
                stream_context_set_option($context, 'ssl', 'verify_peer_name', false);
            }
            
            // For implicit SSL (port 465), use ssl:// scheme; for others use tcp://
            $scheme = ($this->smtpSecurity === 'ssl') ? 'ssl' : 'tcp';
            
            $socket = stream_socket_client(
                "{$scheme}://{$this->smtpHost}:{$this->smtpPort}",
                $errno,
                $errstr,
                30,
                STREAM_CLIENT_CONNECT,
                $context
            );
            
            if (!$socket) {
                throw new Exception("Failed to connect to SMTP server: $errstr ($errno)");
            }

            Logger::info("Connected to SMTP server successfully");
            
            // Read greeting
            $response = fgets($socket, 1024);
            if (strpos($response, '220') === false) {
                throw new Exception('SMTP connection failed.');
            }

            Logger::info("SMTP server greeting received");
            
            // Send EHLO command (read all response lines)
            $response = $this->sendSmtpCommand($socket, "EHLO " . $_SERVER['SERVER_NAME']);
            Logger::info("EHLO response received");

            if (extension_loaded('openssl')) {
                Logger::info("OpenSSL is enabled with version: " . OPENSSL_VERSION_TEXT);
            } else {
                Logger::info("OpenSSL is NOT enabled");
            }

            // For STARTTLS (port 587), upgrade connection to TLS after greeting
            if ($this->smtpSecurity === 'starttls') {
                Logger::info("Starting STARTTLS encryption...");
                $response = $this->sendSmtpCommand($socket, "STARTTLS");
                
                if (strpos($response, '220') === false) {
                    Logger::error("STARTTLS response: " . trim($response));
                    throw new Exception('Failed to start STARTTLS encryption.');
                }
                
                // Enable crypto for secure connection
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                Logger::info("STARTTLS encryption enabled successfully");
                
                // Send EHLO again after TLS (read all response lines)
                $response = $this->sendSmtpCommand($socket, "EHLO " . $_SERVER['SERVER_NAME']);
                Logger::info("Post-TLS EHLO completed");
            } else if ($this->smtpSecurity === 'ssl') {
                Logger::info("Implicit SSL connection already established");
            } else if ($this->smtpSecurity === 'none') {
                Logger::info("No encryption enabled, proceeding with plaintext");
            }
            
            // Authenticate
            if (!$this->authenticateSmtp($socket)) {
                throw new Exception('SMTP authentication failed.');
            }
            
            // Set sender
            Logger::info("Sending MAIL FROM command for: {$from}");
            $response = $this->sendSmtpCommand($socket, "MAIL FROM: <{$from}>");
            Logger::info("MAIL FROM response: " . trim($response));
            
            if (strpos($response, '250') === false) {
                throw new Exception('Failed to set sender. Response: ' . trim($response));
            }
            
            // Set recipient
            Logger::info("Sending RCPT TO command for: {$to}");
            $response = $this->sendSmtpCommand($socket, "RCPT TO: <{$to}>");
            Logger::info("RCPT TO response: " . trim($response));
            
            if (strpos($response, '250') === false) {
                throw new Exception('Failed to set recipient. Response: ' . trim($response));
            }
            
            // Send message data
            Logger::info("Sending DATA command");
            $response = $this->sendSmtpCommand($socket, "DATA");
            Logger::info("DATA response: " . trim($response));
            
            if (strpos($response, '354') === false) {
                throw new Exception('Failed to send email data. Response: ' . trim($response));
            }
            
            // Compose email headers and body
            $headers = "From: {$from}\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: 8bit\r\n";
            
            $message = $headers . "\r\n" . $content;
            
            Logger::info("Sending message body...");
            fputs($socket, $message . "\r\n.\r\n");
            $response = $this->readSmtpResponse($socket);
            Logger::info("Message response: " . trim($response));
            
            if (strpos($response, '250') === false) {
                throw new Exception('Failed to send email message. Response: ' . trim($response));
            }
            
            // Close connection
            $this->sendSmtpCommand($socket, "QUIT");

            Logger::success("Email sent successfully from {$from} to {$to} with subject: {$subject}");
            return true;
            
        } catch (Exception $e) {
            Logger::error("SMTP Error: " . $e->getMessage());
            if (isset($socket) && is_resource($socket)) {
                fclose($socket);
            }
            return false;
        }
    }
}
