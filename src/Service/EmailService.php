<?php

namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


class EmailService
{
    private $mailerHost;
    private $mailerPassword;
    private $mailerUsername;

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

        $to = $recipientEmail;
        $mail->setFrom($this->mailerUsername, $fromSubject);
        $mail->addAddress($to);

        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $altBody;

        if ($mail->send()) {
            return true;
        } else {
            return false;
        }
    }
}
