<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestBrevoMailCommand extends Command
{
    protected $signature = 'mail:test-brevo {email : Adresse de destination}';

    protected $description = 'Envoie un email de test via Brevo API';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (blank(config('services.brevo.key'))) {
            $this->error('BREVO_API_KEY est vide. Ajoutez-la dans API/.env');

            return self::FAILURE;
        }

        Mail::raw(
            "Ceci est un email de test FichoChat envoyé via Brevo API.\n\n".now()->toIso8601String(),
            function ($message) use ($email): void {
                $message
                    ->to($email)
                    ->subject('FichoChat — Test Brevo');
            },
        );

        $this->info("Email de test envoyé à {$email} via Brevo.");

        return self::SUCCESS;
    }
}
