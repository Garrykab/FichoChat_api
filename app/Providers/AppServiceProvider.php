<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureSecureUrls();

        Mail::extend('brevo', function () {
            return (new BrevoTransportFactory)->create(
                new Dsn(
                    'brevo+api',
                    'default',
                    config('services.brevo.key'),
                )
            );
        });
    }

    /**
     * Force HTTPS for generated URLs (Swagger assets, mails, redirects)
     * when behind Railway / reverse-proxy or APP_URL is https.
     */
    private function configureSecureUrls(): void
    {
        $appUrl = (string) config('app.url', '');

        if (config('security.force_https') || str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
        }

        if ($appUrl !== '') {
            URL::forceRootUrl(rtrim($appUrl, '/'));
        }
    }
}
