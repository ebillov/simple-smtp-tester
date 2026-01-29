<?php
// Suppress warning messages from being displayed
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'controller/SmtpController.php';

$controller = new SmtpController();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $from = $_POST['from'] ?? '';
    $to = $_POST['to'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $content = $_POST['content'] ?? '';

    if (empty($from) || empty($to) || empty($subject) || empty($content)) {
        $error = 'All fields are required.';
    } else {
        if ($controller->sendEmail($from, $to, $subject, $content)) {
            $message = 'Email sent successfully!';
        } else {
            $error = 'Failed to send email. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Email Form</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            flex-direction: column;
        }
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 100%;
        }
        
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        input[type="email"],
        input[type="text"],
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        input[type="email"]:focus,
        input[type="text"]:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }
        
        textarea {
            resize: vertical;
            min-height: 150px;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: none;
        }
        
        .alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }
        
        .alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }

        .log_container {
            width: 100%;
            max-width: 100%;
            margin-top: 30px;
        }

        .log_output {
            background: #1e1e1e;
            color: #00ff2d;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
            overflow-wrap: break-word; /* modern alternative to word-wrap */
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Send Email via SMTP</h1>
        
        <?php if ($message): ?>
            <div class="alert success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="from">From</label>
                <input 
                    type="email" 
                    id="from" 
                    name="from" 
                    placeholder="your@example.com"
                    value="<?php echo isset($_POST['from']) ? htmlspecialchars($_POST['from']) : 'noreply@c9.com.au'; ?>"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="to">To</label>
                <input 
                    type="email" 
                    id="to" 
                    name="to" 
                    placeholder="recipient@example.com"
                    value="<?php echo isset($_POST['to']) ? htmlspecialchars($_POST['to']) : 'hosting@c9.com.au'; ?>"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="subject">Subject</label>
                <input 
                    type="text" 
                    id="subject" 
                    name="subject" 
                    placeholder="Email subject"
                    value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : 'C9 Test Email'; ?>"
                    required
                >
            </div>
            
            <div class="form-group">
                <label for="content">Content</label>
                <textarea 
                    id="content" 
                    name="content" 
                    placeholder="Your email message"
                    required
                ><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : 'Test email content by C9. Please ignore.'; ?></textarea>
            </div>
            
            <button type="submit">Send Email</button>
        </form>
    </div>
    <div class="container log_container">
        <h2>Log Output</h2>
        <pre class="log_output"><?php echo htmlspecialchars(file_get_contents(Logger::getLogFile())); ?></pre>
    </div>
</body>
</html>
