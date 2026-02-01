<?php
/**
 * Email Functions
 */

require_once __DIR__ . '/../../config.php';

/**
 * Send email using SMTP
 */
function sendEmail($to, $subject, $body, $isHtml = true) {
    // Create boundary for mixed content
    $boundary = md5(time());
    
    // Headers
    $headers = [
        'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM . '>',
        'Reply-To: ' . SMTP_FROM,
        'MIME-Version: 1.0',
    ];
    
    if ($isHtml) {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    }
    
    // Try to send via SMTP using fsockopen
    $result = sendViaSMTP($to, $subject, $body, $isHtml);
    
    if ($result['success']) {
        return ['success' => true, 'message' => 'Email sent successfully'];
    }
    
    // Fallback to PHP mail() function
    $mailResult = @mail($to, $subject, $body, implode("\r\n", $headers));
    
    if ($mailResult) {
        return ['success' => true, 'message' => 'Email sent successfully (fallback)'];
    }
    
    return ['success' => false, 'message' => 'Failed to send email: ' . $result['message']];
}

/**
 * Send email via SMTP with SSL
 */
function sendViaSMTP($to, $subject, $body, $isHtml = true) {
    $smtpHost = SMTP_HOST;
    $smtpPort = SMTP_PORT;
    $smtpUser = SMTP_USER;
    $smtpPass = SMTP_PASS;
    $from = SMTP_FROM;
    $fromName = SMTP_FROM_NAME;
    
    try {
        // Connect with SSL
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        
        $socket = @stream_socket_client(
            "ssl://{$smtpHost}:{$smtpPort}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$socket) {
            return ['success' => false, 'message' => "Connection failed: {$errstr} ({$errno})"];
        }
        
        // Read server greeting
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '220') {
            fclose($socket);
            return ['success' => false, 'message' => "Invalid greeting: {$response}"];
        }
        
        // EHLO
        fwrite($socket, "EHLO {$smtpHost}\r\n");
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
        
        // AUTH LOGIN
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            return ['success' => false, 'message' => "AUTH failed: {$response}"];
        }
        
        // Username
        fwrite($socket, base64_encode($smtpUser) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '334') {
            fclose($socket);
            return ['success' => false, 'message' => "Username rejected: {$response}"];
        }
        
        // Password
        fwrite($socket, base64_encode($smtpPass) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '235') {
            fclose($socket);
            return ['success' => false, 'message' => "Authentication failed: {$response}"];
        }
        
        // MAIL FROM
        fwrite($socket, "MAIL FROM:<{$from}>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return ['success' => false, 'message' => "MAIL FROM failed: {$response}"];
        }
        
        // RCPT TO
        fwrite($socket, "RCPT TO:<{$to}>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return ['success' => false, 'message' => "RCPT TO failed: {$response}"];
        }
        
        // DATA
        fwrite($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '354') {
            fclose($socket);
            return ['success' => false, 'message' => "DATA failed: {$response}"];
        }
        
        // Headers
        $contentType = $isHtml ? 'text/html' : 'text/plain';
        $message = "From: {$fromName} <{$from}>\r\n";
        $message .= "To: {$to}\r\n";
        $message .= "Subject: {$subject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: {$contentType}; charset=UTF-8\r\n";
        $message .= "\r\n";
        $message .= $body;
        $message .= "\r\n.\r\n";
        
        fwrite($socket, $message);
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != '250') {
            fclose($socket);
            return ['success' => false, 'message' => "Message rejected: {$response}"];
        }
        
        // QUIT
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
        
        return ['success' => true, 'message' => 'Email sent'];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Send registration confirmation email
 */
function sendRegistrationEmail($email, $name) {
    $subject = 'Welcome to ' . APP_NAME . ' - Registration Successful';
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #4a90d9; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 10px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>" . APP_NAME . "</h1>
            </div>
            <div class='content'>
                <h2>Welcome, {$name}!</h2>
                <p>Your registration has been successful. You can now log in to access your courses.</p>
                <p>Your account is pending approval from your course faculty. You will receive another email once approved.</p>
                <p><a href='" . APP_URL . "/public/login.html'>Click here to login</a></p>
            </div>
            <div class='footer'>
                <p>This is an automated message from " . APP_NAME . ". Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Send enrollment approval email
 */
function sendApprovalEmail($email, $name, $courseName) {
    $subject = APP_NAME . ' - Enrollment Approved';
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #28a745; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 10px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Enrollment Approved!</h1>
            </div>
            <div class='content'>
                <h2>Congratulations, {$name}!</h2>
                <p>Your enrollment in <strong>{$courseName}</strong> has been approved.</p>
                <p>You can now access the course materials and attempt quizzes.</p>
                <p><a href='" . APP_URL . "/public/student/dashboard.html'>Go to Dashboard</a></p>
            </div>
            <div class='footer'>
                <p>This is an automated message from " . APP_NAME . ". Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Send quiz available notification
 */
function sendQuizNotificationEmail($email, $name, $quizTitle, $courseName, $conceptName, $endTime) {
    $subject = APP_NAME . ' - New Quiz Available: ' . $quizTitle;
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #ffc107; color: #333; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 10px; text-align: center; font-size: 12px; color: #666; }
            .highlight { background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>New Quiz Available!</h1>
            </div>
            <div class='content'>
                <h2>Hello, {$name}!</h2>
                <p>A new quiz is available for you:</p>
                <div class='highlight'>
                    <p><strong>Quiz:</strong> {$quizTitle}</p>
                    <p><strong>Course:</strong> {$courseName}</p>
                    <p><strong>Concept:</strong> {$conceptName}</p>
                    <p><strong>Deadline:</strong> {$endTime}</p>
                </div>
                <p><a href='" . APP_URL . "/public/student/dashboard.html'>Attempt Quiz Now</a></p>
            </div>
            <div class='footer'>
                <p>This is an automated message from " . APP_NAME . ". Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Send quiz results email
 */
function sendQuizResultsEmail($email, $name, $quizTitle, $score, $maxScore) {
    $percentage = $maxScore > 0 ? round(($score / $maxScore) * 100, 1) : 0;
    $subject = APP_NAME . ' - Quiz Results: ' . $quizTitle;
    
    $resultColor = $percentage >= 60 ? '#28a745' : '#dc3545';
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: {$resultColor}; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 10px; text-align: center; font-size: 12px; color: #666; }
            .score-box { background: white; padding: 20px; border-radius: 10px; text-align: center; margin: 20px 0; }
            .score { font-size: 48px; font-weight: bold; color: {$resultColor}; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Quiz Results</h1>
            </div>
            <div class='content'>
                <h2>Hello, {$name}!</h2>
                <p>Your results for <strong>{$quizTitle}</strong> are ready:</p>
                <div class='score-box'>
                    <div class='score'>{$score}/{$maxScore}</div>
                    <p><strong>{$percentage}%</strong></p>
                </div>
                <p><a href='" . APP_URL . "/public/student/dashboard.html'>View Detailed Results</a></p>
            </div>
            <div class='footer'>
                <p>This is an automated message from " . APP_NAME . ". Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Send password reset email
 */
function sendPasswordResetEmail($email, $name, $token) {
    $resetLink = APP_URL . '/public/reset-password.html?token=' . $token;
    $subject = APP_NAME . ' - Password Reset Request';
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #6c757d; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 10px; text-align: center; font-size: 12px; color: #666; }
            .btn { display: inline-block; background: #4a90d9; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .warning { background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Password Reset</h1>
            </div>
            <div class='content'>
                <h2>Hello, {$name}!</h2>
                <p>We received a request to reset your password. Click the button below to set a new password:</p>
                <p style='text-align: center;'>
                    <a href='{$resetLink}' class='btn'>Reset Password</a>
                </p>
                <div class='warning'>
                    <p><strong>Note:</strong> This link will expire in 1 hour.</p>
                    <p>If you didn't request this reset, please ignore this email.</p>
                </div>
            </div>
            <div class='footer'>
                <p>This is an automated message from " . APP_NAME . ". Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body);
}

/**
 * Send feedback received notification
 */
function sendFeedbackNotificationEmail($email, $name, $feedbackType, $submitterName) {
    $subject = APP_NAME . ' - New Feedback Received';
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #17a2b8; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 10px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>New Feedback</h1>
            </div>
            <div class='content'>
                <h2>Hello, {$name}!</h2>
                <p>You have received new feedback:</p>
                <p><strong>Type:</strong> {$feedbackType}</p>
                <p><strong>From:</strong> {$submitterName}</p>
                <p><a href='" . APP_URL . "/public/admin/feedback-view.html'>View Feedback</a></p>
            </div>
            <div class='footer'>
                <p>This is an automated message from " . APP_NAME . ". Please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body);
}
