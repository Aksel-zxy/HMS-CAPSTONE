<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

function sendInterviewEmail($toEmail, $toName, $interviewDate) {
    $mail = new PHPMailer(true);

    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Email server ng provider mo
        $mail->SMTPAuth = true;
        $mail->Username = 'capstoneproject744@gmail.com';  // Ilagay email mo
        $mail->Password = 'dqzw kkxo adbo pwry';   // Ilagay password / app password
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        //Recipients
        $mail->setFrom('capstoneproject744@gmail.com', 'Dr. Eduardo V. Roquero Memorial Hospital');
        $mail->addAddress($toEmail, $toName);

        //Content
        $mail->isHTML(true);
        $mail->Subject = 'Interview Schedule';
        $mail->Body = "
            <html>
                <body style='background-color:#f4f4f4; font-family: Arial, sans-serif; margin:0; padding:0;'>
                    <table width='100%' cellpadding='0' cellspacing='0' style='max-width:600px; margin:40px auto; background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 0 10px rgba(0,0,0,0.1);'>
                    <tr>
                        <td style='padding:30px; text-align:center; background-color:#0073e6; color:#ffffff; font-size:24px; font-weight:bold;'>
                        HR Department
                        </td>
                    </tr>
                    <tr>
                        <td style='padding:30px; color:#333333; font-size:16px; line-height:1.5;'>
                        <p>Hi <strong>$toName</strong>,</p>
                        <p>You are scheduled for an interview on:</p>
                        <p style='text-align:center; font-size:18px; font-weight:bold; margin:20px 0; color:#0073e6;'>$interviewDate</p>
                        <p>Please be on time and come prepared.</p>
                        <p>Thank you!</p>
                        </td>
                    </tr>
                    <tr>
                        <td style='padding:20px; text-align:center; background-color:#f4f4f4; color:#999999; font-size:12px;'>
                        This is an automated message. Please do not reply.
                        </td>
                    </tr>
                    </table>
                </body>
            </html>
            ";

        $mail->send();
    } catch (Exception $e) {
        error_log("Mailer Error: {$mail->ErrorInfo}");
    }
}
