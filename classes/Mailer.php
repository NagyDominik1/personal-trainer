<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/mail_config.php';

class Mailer
{
    // Send account activation email with token link
    public static function sendActivation(string $toEmail, string $toName, string $token): bool
    {
        $activationLink = APP_URL . '/pages/activate.php?token=' . $token;

        $subject = 'Activate your Personal Trainer account';
        $body    = "
            <h2>Welcome to Personal Trainer, {$toName}!</h2>
            <p>Thank you for registering. Please click the link below to activate your account:</p>
            <p>
                <a href='{$activationLink}' style='
                    background-color: #198754;
                    color: white;
                    padding: 12px 24px;
                    text-decoration: none;
                    border-radius: 6px;
                    display: inline-block;
                '>Activate Account</a>
            </p>
            <p>Or copy this link into your browser:</p>
            <p><a href='{$activationLink}'>{$activationLink}</a></p>
            <p>If you did not register, please ignore this email.</p>
        ";

        return self::send($toEmail, $toName, $subject, $body);
    }

    // Send password reset email with token link
    public static function sendPasswordReset(string $toEmail, string $toName, string $token): bool
    {
        $resetLink = APP_URL . '/pages/reset_password.php?token=' . $token;

        $subject = 'Reset your Personal Trainer password';
        $body    = "
            <h2>Password Reset Request</h2>
            <p>Hello {$toName},</p>
            <p>We received a request to reset your password. Click the button below:</p>
            <p>
                <a href='{$resetLink}' style='
                    background-color: #198754;
                    color: white;
                    padding: 12px 24px;
                    text-decoration: none;
                    border-radius: 6px;
                    display: inline-block;
                '>Reset Password</a>
            </p>
            <p>This link expires in 1 hour. If you did not request a reset, ignore this email.</p>
        ";

        return self::send($toEmail, $toName, $subject, $body);
    }

    // Core send method — used by all public methods above
    private static function send(string $toEmail, string $toName, string $subject, string $body): bool
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;

            $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            return true;
        } catch (Exception $e) {
            // Log error silently — never expose mail errors to the user
            error_log('Mailer error: ' . $mail->ErrorInfo);
            return false;
        }
    }
}