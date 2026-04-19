<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

/**
 * Role-based email sender.
 * Mirrors the original Node.js emailService.js.
 *
 * Roles: 'seller' | 'remission' | 'security'
 *
 * Each role maps to its own SMTP credentials via .env.
 * Laravel's Mail facade is used with on-the-fly transport swapping.
 */
class EmailService
{
    /**
     * Returns the mailer config array for the given role.
     */
    private function getSmtpConfig(string $role): array
    {
        $host = env('SMTP_HOST', 'mail.lacasitadelsabor.com');
        $port = (int) env('SMTP_PORT', 465);

        [$user, $pass] = match ($role) {
            'seller'    => [env('SMTP_SELLER_USER'),    env('SMTP_SELLER_PASS')],
            'remission' => [env('SMTP_REMISSION_USER'), env('SMTP_REMISSION_PASS')],
            'security'  => [env('SMTP_SECURITY_USER'),  env('SMTP_SECURITY_PASS')],
            default     => throw new Exception("Invalid email role: {$role}"),
        };

        return [
            'transport'  => 'smtp',
            'host'       => $host,
            'port'       => $port,
            'encryption' => 'ssl',
            'username'   => $user,
            'password'   => $pass,
            'from'       => ['address' => $user, 'name' => 'Casita del Sabor'],
        ];
    }

    /**
     * Send an email using the given role's SMTP account.
     *
     * @param array $opts  {
     *   to:          string|string[],
     *   subject:     string,
     *   html:        string,
     *   attachments: array<{filename: string, content: string|resource, contentType: string}>
     * }
     * @param string $role  'seller' | 'remission' | 'security'
     */
    public function sendEmailWithAttachment(array $opts, string $role): void
    {
        $config = $this->getSmtpConfig($role);
        $to     = (array) ($opts['to'] ?? []);

        // Dynamically create a transport so we can use per-role credentials
        $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
            $config['host'],
            $config['port'],
            true // SSL
        );
        $transport->setUsername($config['username']);
        $transport->setPassword($config['password']);

        $mailer = new \Symfony\Component\Mailer\Mailer($transport);

        $email = (new \Symfony\Component\Mime\Email())
            ->from(new \Symfony\Component\Mime\Address(
                $config['from']['address'],
                $config['from']['name']
            ))
            ->subject($opts['subject'])
            ->html($opts['html']);

        foreach ($to as $address) {
            $email->addTo($address);
        }

        foreach ($opts['attachments'] ?? [] as $attachment) {
            $email->attach(
                $attachment['content'],
                $attachment['filename'],
                $attachment['contentType'] ?? 'application/octet-stream'
            );
        }

        $mailer->send($email);
    }
}
