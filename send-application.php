<?php
require_once 'vendor/autoload.php'; // Make sure this path is correct

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Set proper headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Vercel serverless function handler
function handler($request) {
    // Set proper headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        return json_encode(['success' => true]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        return json_encode(['success' => false, 'error' => 'Method not allowed']);
    }

// Get form data with proper sanitization
$fullName = trim($_POST['fullName'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$studyType = trim($_POST['studyType'] ?? '');
$experience = trim($_POST['experience'] ?? '');
$message = trim($_POST['message'] ?? '');

// Validate required fields
$errors = [];
if (empty($fullName)) $errors[] = 'Full name is required';
if (empty($email)) $errors[] = 'Email is required';
if (empty($phone)) $errors[] = 'Phone is required';
if (empty($studyType)) $errors[] = 'Study type is required';
if (empty($experience)) $errors[] = 'Experience level is required';

// Validate email format
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
}

// Check for required file uploads
$requiredFiles = ['transcripts', 'resume', 'passport', 'waec'];
foreach ($requiredFiles as $field) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $errors[] = ucfirst($field) . ' file is required';
    }
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => implode(', ', $errors)]);
    exit;
}

    try {
        $mail = new PHPMailer(true);
        
        // Use environment variables for sensitive data
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'] ?? 'drbrillo.edu@gmail.com';
        $mail->Password = $_ENV['SMTP_PASSWORD'] ?? 'vjqi emad cimj jjqb';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

    // Recipients
    $mail->setFrom('drbrillo.edu@gmail.com', 'Dr. Brillo Edu Services');
    $mail->addAddress('drbrillo.edu@gmail.com', 'Dr. Brillo Edu Services');
    $mail->addReplyTo($email, $fullName);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'New Application Submission - ' . $fullName;
    
    $mail->Body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; }
            .header { background: #4a90e2; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 30px; background: #f9f9f9; }
            .field { margin-bottom: 15px; padding: 15px; background: white; border-radius: 5px; border-left: 4px solid #4a90e2; }
            .label { font-weight: bold; color: #4a90e2; display: block; margin-bottom: 5px; }
            .value { color: #333; }
            .attachments { background: #e3f0ff; padding: 20px; border-radius: 8px; margin-top: 20px; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; background: #f0f0f0; border-radius: 0 0 8px 8px; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>📋 New Application Submission</h2>
            <p>Received: " . date('Y-m-d H:i:s') . "</p>
        </div>
        <div class='content'>
            <div class='field'>
                <span class='label'>👤 Full Name:</span>
                <span class='value'>" . htmlspecialchars($fullName) . "</span>
            </div>
            <div class='field'>
                <span class='label'>📧 Email:</span>
                <span class='value'>" . htmlspecialchars($email) . "</span>
            </div>
            <div class='field'>
                <span class='label'>📱 Phone:</span>
                <span class='value'>" . htmlspecialchars($phone) . "</span>
            </div>
            <div class='field'>
                <span class='label'>🎓 Study Type:</span>
                <span class='value'>" . htmlspecialchars(ucfirst($studyType)) . "</span>
            </div>
            <div class='field'>
                <span class='label'>💼 Experience:</span>
                <span class='value'>" . htmlspecialchars($experience) . "</span>
            </div>";
    
    if (!empty($message)) {
        $mail->Body .= "
            <div class='field'>
                <span class='label'>💬 Message:</span>
                <div style='margin-top: 10px; padding: 15px; background: white; border-left: 4px solid #4a90e2;'>
                    " . nl2br(htmlspecialchars($message)) . "
                </div>
            </div>";
    }
    
    $mail->Body .= "
            <div class='attachments'>
                <h3>📎 Attached Documents:</h3>
                <ul style='margin: 10px 0; padding-left: 20px;'>
                    <li>📄 Academic Transcripts</li>
                    <li>📋 Resume/CV</li>
                    <li>🛂 Passport Copy</li>
                    <li>📜 WAEC Results</li>
                </ul>
            </div>
        </div>
        <div class='footer'>
            <p>This application was submitted through the Dr. Brillo Edu Services website.</p>
            <p><strong>Reply to:</strong> " . htmlspecialchars($email) . "</p>
        </div>
    </body>
    </html>";

    // Handle file attachments with validation
    $fileFields = ['transcripts', 'resume', 'passport', 'waec'];
    $fileLabels = [
        'transcripts' => 'Academic_Transcripts',
        'resume' => 'Resume_CV',
        'passport' => 'Passport_Copy',
        'waec' => 'WAEC_Results'
    ];

    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $allowedTypes = [
        'transcripts' => ['pdf', 'jpg', 'jpeg', 'png'],
        'resume' => ['pdf', 'doc', 'docx'],
        'passport' => ['pdf', 'jpg', 'jpeg', 'png'],
        'waec' => ['pdf', 'jpg', 'jpeg', 'png']
    ];

    foreach ($fileFields as $field) {
        if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$field];
            
            // Validate file size
            if ($file['size'] > $maxFileSize) {
                throw new Exception("File {$field} is too large. Maximum size is 5MB.");
            }
            
            // Validate file type
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, $allowedTypes[$field])) {
                throw new Exception("Invalid file type for {$field}. Allowed: " . implode(', ', $allowedTypes[$field]));
            }
            
            // Create safe filename
            $safeFileName = $fileLabels[$field] . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $fullName) . '_' . date('Y-m-d') . '.' . $fileExtension;
            
            $mail->addAttachment($file['tmp_name'], $safeFileName);
        }
    }

        if ($mail->send()) {
            return json_encode([
                'success' => true, 
                'message' => 'Application submitted successfully!'
            ]);
        } else {
            throw new Exception('Failed to send email');
        }
        
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
        http_response_code(500);
        return json_encode([
            'success' => false, 
            'error' => 'Failed to send application. Please try again.'
        ]);
    }
}

// Call the handler
echo handler($_REQUEST);
?>
