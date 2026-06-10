<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Mail;

use App\User\Domain\Email as UserEmail;
use App\User\Domain\PasswordRecoveryEmailSender;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class SymfonyPasswordRecoveryEmailSender implements PasswordRecoveryEmailSender
{
    public function __construct(
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $frontendUrl,
        private string $fromAddress,
    ) {
    }

    public function send(UserEmail $to, string $plainToken): void
    {
        $resetUrl = rtrim($this->frontendUrl, '/').'/reset-password/'.$plainToken;

        $email = new Email()
            ->from($this->fromAddress)
            ->to($to->value)
            ->subject('Reset your password')
            ->text(\sprintf("Click the link below to reset your password (valid for 1 hour):\n\n%s", $resetUrl))
            ->html(\sprintf(
                '<p>Click the link below to reset your password (valid for 1 hour):</p>'
                .'<p><a href="%1$s">Reset password</a></p>'
                .'<p>If the link does not work, copy this URL into your browser:</p>'
                .'<p>%1$s</p>',
                htmlspecialchars($resetUrl, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'),
            ));

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // Log only the error message string — defensive against future Symfony surface changes
            // that might add the Mime body (and therefore the token) to the exception object.
            $this->logger->error('password_recovery_email_send_failed', ['error' => $e->getMessage()]);
        }
    }
}
