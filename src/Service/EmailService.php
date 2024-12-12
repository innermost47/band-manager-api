<?php

namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


class EmailService
{
    private $mailerHost;
    private $mailerPassword;
    private $mailerUsername;
    private $styles = "
    body {
        font-family: Arial, sans-serif;
        line-height: 1.6;
        color: #333333;
    }
    .container {
        max-width: 600px;
        margin: 0 auto;
        padding: 20px;
    }
    .header {
        background-color: #f8f9fa;
        padding: 20px;
        text-align: center;
        border-radius: 5px;
    }
    .content {
        margin: 20px 0;
    }
    .highlight-box {
        background-color: #e9ecef;
        padding: 15px;
        margin: 20px 0;
        text-align: center;
        font-size: 24px;
        font-weight: bold;
        letter-spacing: 5px;
        border-radius: 5px;
    }
    .warning-box {
        color: #721c24;
        background-color: #f8d7da;
        padding: 10px;
        margin: 20px 0;
        border-radius: 5px;
    }
    .success-box {
        color: #155724;
        background-color: #d4edda;
        padding: 10px;
        margin: 20px 0;
        border-radius: 5px;
    }
    .info-box {
        color: #004085;
        background-color: #cce5ff;
        padding: 10px;
        margin: 20px 0;
        border-radius: 5px;
    }
    .footer {
        margin-top: 20px;
        font-size: 12px;
        color: #6c757d;
        border-top: 1px solid #dee2e6;
        padding-top: 20px;
    }
    
    .event-details {
        background-color: #f8f9fa;
        border-left: 4px solid #007bff;
        padding: 15px;
        margin: 20px 0;
        border-radius: 5px;
    }
    
    .event-details table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .event-details table td {
        padding: 8px;
        vertical-align: top;
    }
    
    .event-details table td:first-child {
        font-weight: bold;
        width: 120px;
        color: #495057;
    }

    .project-name {
            background-color: #e9ecef;
            padding: 10px 15px;
            margin: 15px 0;
            border-radius: 5px;
            font-weight: bold;
            color: #495057;
            display: inline-block;
        }
        
    .action-button {
        background-color: #007bff;
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        text-decoration: none;
        display: inline-block;
        margin: 10px 0;
    }
";

    public function __construct(ParameterBagInterface $params)
    {
        $this->mailerHost = $params->get("mailer_host");
        $this->mailerPassword = $params->get("mailer_password");
        $this->mailerUsername = $params->get("mailer_username");
    }

    public function sendEmail($recipientEmail, $subject, $body, $altBody, $fromSubject)
    {
        $mail = new PHPMailer();
        $mail->isSMTP();
        $mail->Host = $this->mailerHost;
        $mail->SMTPAuth = true;
        $mail->Username = $this->mailerUsername;
        $mail->Password = $this->mailerPassword;
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';

        $to = $recipientEmail;
        $mail->setFrom($this->mailerUsername, $fromSubject);
        $mail->addAddress($to);

        $mail->Subject = $subject;
        $mail->Body = str_replace("\n", "\r\n", $body);
        $mail->AltBody = str_replace("\n", "\r\n", $altBody);

        if ($mail->send()) {
            return true;
        } else {
            error_log('Email error: ' . $mail->ErrorInfo);
            return false;
        }
    }

    public function getVerify2faEmail($twoFactorCode)
    {
        $content = "
            <p>Hello,</p>
            <p>We received a login request for your account. To ensure the security of your account, please use the verification code below:</p>
            <div class='highlight-box'>{$twoFactorCode}</div>
            <div class='warning-box'>
                <strong>Important:</strong> This code will expire in 10 minutes.
            </div>
            <p>If you did not initiate this login request, please ignore this email and secure your account immediately by changing your password.</p>
            <p>For your security:</p>
            <ul>
                <li>Never share this code with anyone</li>
                <li>Our team will never ask you for this code</li>
                <li>Always ensure you're on our official website before entering any codes</li>
            </ul>
        ";

        return [
            "fromSubject" => "Verify2fa",
            "subject" => "Your Two-Factor Authentication Code",
            "body" => $this->getBaseTemplate($content, "Two-Factor Authentication"),
            "altBody" => $this->getPlainTextVersion($content, "Two-Factor Authentication")
        ];
    }

    public function getEmailVerificationEmail($verificationCode)
    {
        $content = "
            <p>Hello,</p>
            
            <p>Thank you for signing up! To complete your registration and ensure the security of your account, please verify your email address.</p>
            
            <p>Use the following verification code:</p>
            
            <div class='highlight-box'>{$verificationCode}</div>
            
            <div class='info-box'>
                <strong>Next steps:</strong>
                <ul style='margin: 10px 0 0 0; padding-left: 20px;'>
                    <li>Enter this code on the verification page</li>
                    <li>Once verified, you'll have full access to your account</li>
                </ul>
            </div>

            <div class='warning-box'>
                <strong>Important:</strong> This verification code will expire soon. Please verify your email address as soon as possible.
            </div>
            
            <p>If you did not sign up for an account, please ignore this email.</p>
        ";

        return [
            "fromSubject" => "Account Verification",
            "subject" => "Verify Your Email",
            "body" => $this->getBaseTemplate($content, "Email Verification"),
            "altBody" => $this->getPlainTextVersion($content, "Email Verification")
        ];
    }


    private function getBaseTemplate($content, $title)
    {
        return "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='utf-8'>
                    <style>{$this->styles}</style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>{$title}</h2>
                        </div>
                        <div class='content'>
                            {$content}
                        </div>
                        {$this->getDefaultFooter()}
                    </div>
                </body>
                </html>
            ";
    }

    private function getDefaultFooter()
    {
        return "
                <div class='footer'>
                    <p>This is an automated message, please do not reply to this email.</p>
                    <p>If you need assistance, please contact our support team.</p>
                </div>
            ";
    }

    public function getEventReminderEmail($eventData)
    {
        $content = "
            <p>Hello {$eventData['username']},</p>
            
            <p>This is a reminder about an upcoming event you are associated with:</p>
            
            <div class='event-details'>
                <table>
                    <tr>
                        <td>Event:</td>
                        <td>{$eventData['name']}</td>
                    </tr>
                    <tr>
                        <td>Date:</td>
                        <td>{$eventData['date']}</td>
                    </tr>
                    <tr>
                        <td>Location:</td>
                        <td>{$eventData['location']}</td>
                    </tr>
                </table>
            </div>
            
            <div class='info-box'>
                <strong>Reminder:</strong> This event is happening in the next 24 hours.
            </div>
            
            <p>Don't forget to prepare everything you need for this event.</p>
            
            <p>Best regards,<br>BandManager</p>
        ";

        return [
            "fromSubject" => "Event Reminder",
            "subject" => "Event Reminder: {$eventData['name']}",
            "body" => $this->getBaseTemplate($content, "Event Reminder"),
            "altBody" => $this->getPlainTextVersion($content, "Event Reminder")
        ];
    }

    public function getPasswordResetVerificationEmail($verificationCode)
    {
        $content = "
            <p>Hello,</p>
            
            <p>We received a request to reset your password. To continue with your password reset, please use the verification code below:</p>
            
            <div class='highlight-box'>{$verificationCode}</div>
            
            <div class='warning-box'>
                <strong>Important:</strong> This code will expire in 15 minutes.
            </div>
            
            <p>To reset your password:</p>
            <ul>
                <li>Return to the password reset page</li>
                <li>Enter this verification code</li>
                <li>Create your new password</li>
            </ul>

            <div class='info-box'>
                <strong>Security Notice:</strong> If you did not request this code, please ignore this email and ensure your account is secure.
            </div>
        ";

        return [
            "fromSubject" => "Password Reset Verification Code",
            "subject" => "Password Reset Verification Code",
            "body" => $this->getBaseTemplate($content, "Password Reset Verification"),
            "altBody" => $this->getPlainTextVersion($content, "Password Reset Verification")
        ];
    }

    public function getProjectInvitationEmail(string $projectName): array
    {
        $content = "
            <p>Hello,</p>
            
            <p>You have been invited to join the project:</p>
            
            <div class='project-name'>{$projectName}</div>
            
            <div class='info-box'>
                <strong>Next Steps:</strong>
                <p>To respond to this invitation, please visit your profile page and review the invitation.</p>
            </div>
            
            <p>If you have any questions, feel free to contact us.</p>
            
            <p>Best regards,<br>Band Manager</p>
        ";

        return [
            "fromSubject" => "Project Invitation",
            "subject" => "You've been invited to join a project",
            "body" => $this->getBaseTemplate($content, "Project Invitation"),
            "altBody" => $this->getPlainTextVersion($content, "Project Invitation")
        ];
    }

    public function getCollaborationRequestEmail(string $username, string $projectName): array
    {
        $content = "
            <p>Hello,</p>
            
            <p><strong>{$username}</strong> has requested to join your project:</p>
            
            <div class='project-name'>{$projectName}</div>
            
            <div class='info-box'>
                <strong>Action Required:</strong>
                <p>To respond to this request, please visit your project management page.</p>
            </div>
            
            <p>If you have any questions, feel free to contact us.</p>
            
            <p>Best regards,<br>Band Manager</p>
        ";

        return [
            "fromSubject" => "Collaboration Request",
            "subject" => "New Collaboration Request",
            "body" => $this->getBaseTemplate($content, "Collaboration Request"),
            "altBody" => $this->getPlainTextVersion($content, "Collaboration Request")
        ];
    }

    public function getAcceptanceEmail(string $projectName, bool $isRequest = false): array
    {
        $title = $isRequest ? "Request Accepted" : "Invitation Accepted";
        $content = "
            <p>Hello,</p>
            
            " . ($isRequest ? "
                <p>We are happy to inform you that your request to join the project:</p>
            " : "
                <p>We are happy to inform you that the invitation to join the project:</p>
            ") . "
            
            <div class='project-name'>{$projectName}</div>
            
            <div class='success-box'>
                " . ($isRequest ? "
                    <strong>Congratulations!</strong> You are now part of this project.
                " : "
                    <strong>Great News!</strong> The invited member is now part of your project.
                ") . "
            </div>
            
            <p>If you have any further questions, feel free to contact us.</p>
            
            <p>Best regards,<br>Band Manager</p>
        ";

        return [
            "fromSubject" => $title,
            "subject" => $title,
            "body" => $this->getBaseTemplate($content, $title),
            "altBody" => $this->getPlainTextVersion($content, $title)
        ];
    }

    public function getDeclineEmail(string $projectName, bool $isRequest = false): array
    {
        $title = $isRequest ? "Collaboration Request Declined" : "Invitation Declined";
        $content = "
            <p>Hello,</p>
            
            " . ($isRequest ? "
                <p>We regret to inform you that your request to join the project:</p>
            " : "
                <p>We regret to inform you that the invitation to join the project:</p>
            ") . "
            
            <div class='project-name'>{$projectName}</div>
            
            " . ($isRequest ? "
                <div class='info-box'>
                    <strong>Note:</strong> If you have any questions about this decision, feel free to contact us.
                </div>
            " : "
                <div class='info-box'>
                    <strong>Note:</strong> If you wish to send another invitation or have any questions, feel free to contact us.
                </div>
            ") . "
            
            <p>Best regards,<br>Band Manager</p>
        ";

        return [
            "fromSubject" => $title,
            "subject" => $title,
            "body" => $this->getBaseTemplate($content, $title),
            "altBody" => $this->getPlainTextVersion($content, $title)
        ];
    }

    public function getCancellationEmail(string $projectName, bool $isRequest = false): array
    {
        $title = $isRequest ? "Collaboration Request Cancelled" : "Invitation Cancelled";
        $content = "
            <p>Hello,</p>
            
            " . ($isRequest ? "
                <p>The collaboration request for the project:</p>
            " : "
                <p>The invitation to join the project:</p>
            ") . "
            
            <div class='project-name'>{$projectName}</div>
            
            <p>has been " . ($isRequest ? "cancelled." : "cancelled by the sender.") . "</p>
            
            <div class='info-box'>
                <strong>Note:</strong> If this was not intentional or you need assistance, feel free to contact us.
            </div>
            
            <p>Best regards,<br>Band Manager</p>
        ";

        return [
            "fromSubject" => $title,
            "subject" => $title,
            "body" => $this->getBaseTemplate($content, $title),
            "altBody" => $this->getPlainTextVersion($content, $title)
        ];
    }

    private function getPlainTextVersion($htmlContent, $title)
    {
        $text = $title . "\n\n";
        $text .= strip_tags(str_replace(
            ['<div>', '</div>', '<p>', '</p>', '<br>', '<br/>', '<hr>', '<hr/>', '<li>'],
            ["\n", "\n", "\n", "\n", "\n", "\n", "\n---\n", "\n---\n", "\n- "],
            $htmlContent
        ));
        return trim(preg_replace('/[\r\n]+/', "\n\n", $text));
    }
}
