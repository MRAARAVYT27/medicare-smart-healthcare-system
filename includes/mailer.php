<?php
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../config/email_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

function sendOTPEmail($toEmail, $toName, $otp, $purpose = 'verification') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'MediCare — Your Verification Code';

        $purposeText = match($purpose) {
            'registration'    => 'complete your registration',
            'login'           => 'sign in to your account',
            'password_reset'  => 'reset your password',
            default           => 'verify your identity'
        };

        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset='UTF-8'>
          <style>
            body { margin:0; padding:0; font-family: 'Segoe UI', Arial, sans-serif; background:#f0f4f8; }
            .wrap { max-width:520px; margin:40px auto; background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.10); }
            .header { background: linear-gradient(135deg, #0f4c81, #1a8fe3); padding:36px 32px 28px; text-align:center; }
            .header h1 { color:#fff; margin:0; font-size:26px; letter-spacing:-.5px; }
            .header p  { color:rgba(255,255,255,.75); margin:6px 0 0; font-size:13px; }
            .body { padding:36px 32px; }
            .body p { color:#374151; font-size:15px; line-height:1.6; margin:0 0 16px; }
            .otp-box { background:#f0f9ff; border:2px dashed #1a8fe3; border-radius:12px; padding:20px; text-align:center; margin:24px 0; }
            .otp { font-size:42px; font-weight:800; letter-spacing:12px; color:#0f4c81; font-family:monospace; }
            .otp-note { font-size:12px; color:#6b7280; margin-top:8px; }
            .footer { background:#f9fafb; padding:20px 32px; text-align:center; border-top:1px solid #e5e7eb; }
            .footer p { color:#9ca3af; font-size:12px; margin:0; }
          </style>
        </head>
        <body>
          <div class='wrap'>
            <div class='header'>
              <h1>🏥 MediCare</h1>
              <p>Smart Healthcare Management System</p>
            </div>
            <div class='body'>
              <p>Hello <strong>{$toName}</strong>,</p>
              <p>Use the verification code below to {$purposeText}. This code expires in <strong>10 minutes</strong>.</p>
              <div class='otp-box'>
                <div class='otp'>{$otp}</div>
                <div class='otp-note'>Do not share this code with anyone</div>
              </div>
              <p>If you did not request this, please ignore this email or contact our support team immediately.</p>
            </div>
            <div class='footer'>
              <p>© " . date('Y') . " MediCare Health System. All rights reserved.</p>
            </div>
          </div>
        </body>
        </html>";

        $mail->AltBody = "Your MediCare OTP is: {$otp}\nExpires in 10 minutes.";
        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}

function sendAppointmentEmail($toEmail, $toName, $details, $type = 'booked') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);

        $statusMap = [
            'booked'    => ['subject' => 'Appointment Confirmed', 'color' => '#16a34a', 'icon' => '✅'],
            'cancelled' => ['subject' => 'Appointment Cancelled', 'color' => '#dc2626', 'icon' => '❌'],
            'reminder'  => ['subject' => 'Appointment Reminder',  'color' => '#d97706', 'icon' => '🔔'],
        ];
        $info = $statusMap[$type] ?? $statusMap['booked'];
        $mail->Subject = "MediCare — {$info['subject']}";

        $mail->Body = "
        <!DOCTYPE html><html><head><meta charset='UTF-8'>
        <style>
          body{margin:0;padding:0;font-family:'Segoe UI',Arial,sans-serif;background:#f0f4f8;}
          .wrap{max-width:520px;margin:40px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.10);}
          .header{background:linear-gradient(135deg,#0f4c81,#1a8fe3);padding:36px 32px 28px;text-align:center;}
          .header h1{color:#fff;margin:0;font-size:26px;}
          .body{padding:36px 32px;}
          .info-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f3f4f6;font-size:14px;}
          .info-label{color:#6b7280;font-weight:500;}
          .info-value{color:#111827;font-weight:600;}
          .badge{display:inline-block;padding:4px 14px;border-radius:99px;color:#fff;background:{$info['color']};font-size:13px;}
          .footer{background:#f9fafb;padding:20px 32px;text-align:center;border-top:1px solid #e5e7eb;}
          .footer p{color:#9ca3af;font-size:12px;margin:0;}
        </style></head><body>
        <div class='wrap'>
          <div class='header'><h1>🏥 MediCare</h1></div>
          <div class='body'>
            <p style='font-size:22px;margin:0 0 8px'>{$info['icon']} {$info['subject']}</p>
            <p style='color:#6b7280;font-size:14px;margin:0 0 24px'>Hello <strong>{$toName}</strong>, here are your appointment details:</p>
            <div class='info-row'><span class='info-label'>Doctor</span><span class='info-value'>{$details['doctor_name']}</span></div>
            <div class='info-row'><span class='info-label'>Specialization</span><span class='info-value'>{$details['specialization']}</span></div>
            <div class='info-row'><span class='info-label'>Date</span><span class='info-value'>{$details['date']}</span></div>
            <div class='info-row'><span class='info-label'>Time</span><span class='info-value'>{$details['time']}</span></div>
            <div class='info-row'><span class='info-label'>Status</span><span class='info-value'><span class='badge'>{$type}</span></span></div>
            " . (!empty($details['notes']) ? "<div class='info-row'><span class='info-label'>Notes</span><span class='info-value'>{$details['notes']}</span></div>" : "") . "
          </div>
          <div class='footer'><p>© " . date('Y') . " MediCare Health System</p></div>
        </div></body></html>";

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}
?>
