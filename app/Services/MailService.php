<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    public function __construct(private readonly array $config)
    {
    }

    public function sendMagicLink(string $toEmail, string $link): void
    {
        $smtp = $this->config['smtp'] ?? [];
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = (string) ($smtp['host'] ?? '');
            $mail->Port = (int) ($smtp['port'] ?? 587);
            $mail->SMTPAuth = true;
            $mail->Username = (string) ($smtp['username'] ?? '');
            $mail->Password = (string) ($smtp['password'] ?? '');
            $secure = strtolower((string) ($smtp['secure'] ?? 'tls'));
            if ($secure === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($secure === 'none') {
                $mail->SMTPSecure = '';
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->setFrom((string) ($smtp['from_email'] ?? 'noreply@example.com'), (string) ($smtp['from_name'] ?? 'Turo-dekal'));
            $mail->addAddress($toEmail);
            $mail->Subject = 'Din magiske innloggingslenke';
            $mail->Body = "Klikk for å logge inn:\n\n" . $link . "\n\nLenken utløper snart.";
            $mail->send();
        } catch (Exception $e) {
            throw new \RuntimeException('Kunne ikke sende e-post: ' . $e->getMessage());
        }
    }
}

