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

    public static function sendTrainerApproved(string $toEmail, string $toName): bool
    {
        $link = APP_URL . '/pages/trainer/workouts.php';
        return self::send($toEmail, $toName, 'Your trainer account has been approved!',
            self::wrap('You\'re Approved!', "
                <p style='margin:0 0 18px'>Hi <strong>{$toName}</strong>,</p>
                <p style='margin:0 0 18px'>Great news — an admin has approved your trainer account on <strong>Personal Trainer App</strong>. You can now create and publish workout programs for your clients.</p>
                " . self::btn($link, 'Go to My Workouts', '#2d6a4f') . "
                <p style='margin:18px 0 0;color:#999;font-size:13px'>If you have any questions, contact the admin team.</p>
            ")
        );
    }

    public static function sendTrainerRejected(string $toEmail, string $toName): bool
    {
        return self::send($toEmail, $toName, 'Update on your trainer application',
            self::wrap('Application Not Approved', "
                <p style='margin:0 0 18px'>Hi <strong>{$toName}</strong>,</p>
                <p style='margin:0 0 18px'>Unfortunately your trainer account application on <strong>Personal Trainer App</strong> was not approved at this time. Your account has been removed.</p>
                <p style='margin:0;color:#999;font-size:13px'>If you believe this was a mistake, please re-register or contact the admin team.</p>
            ")
        );
    }

    public static function sendNewWorkout(string $toEmail, string $toName, string $trainerName, string $workoutTitle, int $workoutId): bool
    {
        $link = APP_URL . '/pages/user/workouts.php?id=' . $workoutId;
        return self::send($toEmail, $toName, "{$trainerName} just published a new workout",
            self::wrap('New Program Available', "
                <p style='margin:0 0 18px'>Hi <strong>{$toName}</strong>,</p>
                <p style='margin:0 0 18px'><strong>{$trainerName}</strong>, a trainer you follow, just published a new workout program:</p>
                <div style='background:#f8f4f0;border-left:4px solid #8b5a2b;border-radius:6px;padding:14px 18px;margin:0 0 22px'>
                    <div style='font-size:18px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#3a1f0a'>{$workoutTitle}</div>
                </div>
                " . self::btn($link, 'View Program') . "
            ")
        );
    }

    public static function sendReviewReceived(string $toEmail, string $toName, string $reviewerName, string $workoutTitle, int $rating, string $comment): bool
    {
        $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
        $commentHtml = $comment
            ? "<p style='margin:14px 0 0;font-style:italic;color:#555;font-size:14px'>\"{$comment}\"</p>"
            : '';
        return self::send($toEmail, $toName, "New review on \"{$workoutTitle}\"",
            self::wrap('You Got a Review', "
                <p style='margin:0 0 18px'>Hi <strong>{$toName}</strong>,</p>
                <p style='margin:0 0 18px'><strong>{$reviewerName}</strong> left a review on your workout:</p>
                <div style='background:#f8f4f0;border-left:4px solid #8b5a2b;border-radius:6px;padding:14px 18px;margin:0 0 22px'>
                    <div style='font-size:14px;font-weight:600;color:#555;margin-bottom:6px;text-transform:uppercase;letter-spacing:1px'>{$workoutTitle}</div>
                    <div style='font-size:22px;color:#c9952a;letter-spacing:2px'>{$stars}</div>
                    {$commentHtml}
                </div>
            ")
        );
    }

    public static function sendCoachRequest(string $toEmail, string $toName, string $clientName, string $message): bool
    {
        $link = APP_URL . '/pages/trainer/clients.php';
        $msgHtml = $message
            ? "<div style='background:#f8f4f0;border-left:4px solid #8b5a2b;border-radius:6px;padding:14px 18px;margin:0 0 22px;font-style:italic;color:#555'>\"" . htmlspecialchars($message) . "\"</div>"
            : '';
        return self::send($toEmail, $toName, "{$clientName} wants you as their coach",
            self::wrap('New Coaching Request', "
                <p style='margin:0 0 18px'>Hi <strong>{$toName}</strong>,</p>
                <p style='margin:0 0 18px'><strong>" . htmlspecialchars($clientName) . "</strong> has requested you as their personal coach.</p>
                {$msgHtml}
                " . self::btn($link, 'Review Request') . "
            ")
        );
    }

    public static function sendCoachAccepted(string $toEmail, string $toName, string $trainerName): bool
    {
        $link = APP_URL . '/pages/user/my_plan.php';
        return self::send($toEmail, $toName, "{$trainerName} accepted you as a client",
            self::wrap('You Have a Coach!', "
                <p style='margin:0 0 18px'>Hi <strong>{$toName}</strong>,</p>
                <p style='margin:0 0 18px'><strong>" . htmlspecialchars($trainerName) . "</strong> accepted your coaching request. They can now help build and refine your personal plans.</p>
                " . self::btn($link, 'View My Plans', '#2d6a4f') . "
            ")
        );
    }

    private static function wrap(string $title, string $content): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'></head>
        <body style='margin:0;padding:0;background:#f5f0eb;font-family:Arial,sans-serif'>
        <div style='max-width:560px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.1)'>
            <div style='background:linear-gradient(135deg,#8b5a2b,#5c3210);padding:28px 36px'>
                <div style='color:#d4a96a;font-size:10px;letter-spacing:3px;text-transform:uppercase;margin-bottom:6px'>FitTrainer</div>
                <div style='color:#fff;font-size:22px;font-weight:700;letter-spacing:.5px'>{$title}</div>
            </div>
            <div style='padding:32px 36px;font-size:15px;color:#333;line-height:1.6'>{$content}</div>
            <div style='padding:16px 36px;background:#f8f4f0;border-top:1px solid #ede8e2;font-size:12px;color:#aaa'>
                Personal Trainer App &mdash; You received this because you have an account with us.
            </div>
        </div></body></html>";
    }

    private static function btn(string $url, string $label, string $color = '#8b5a2b'): string
    {
        return "<p style='margin:22px 0 0'><a href='{$url}' style='background:{$color};color:#fff;padding:12px 26px;text-decoration:none;border-radius:7px;display:inline-block;font-weight:700;font-size:14px;letter-spacing:.5px'>{$label}</a></p>";
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