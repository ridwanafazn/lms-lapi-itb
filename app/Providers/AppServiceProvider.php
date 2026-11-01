<?php

namespace App\Providers;

use App\Models\Course;
use App\Policies\AssignmentPolicy;
use App\Policies\CoursePolicy;
use App\Policies\LessonPolicy;
use App\Policies\QuizPolicy;
use Google\Client;
use Google\Service\Drive;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDrive\GoogleDriveAdapter;
// IMPORT MIDTRANS
use Midtrans\Config;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (app()->environment('local')) {
            URL::forceScheme('https');
        }
        // 0. Konfigurasi Global Midtrans
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = config('services.midtrans.is_production');
        Config::$isSanitized = config('services.midtrans.is_sanitized');
        Config::$is3ds = config('services.midtrans.is_3ds');

        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(\App\Models\Lesson::class, LessonPolicy::class);
        Gate::policy(\App\Models\Quiz::class, QuizPolicy::class);
        Gate::policy(\App\Models\Assignment::class, AssignmentPolicy::class);

        Gate::before(function ($user, $ability) {
            return $user->hasRole('admin') ? true : null;
        });

        // --- Phase 1.3: Rate Limiting ---
        \Illuminate\Support\Facades\RateLimiter::for('certificate', function () {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by(request()->ip());
        });

        \Illuminate\Support\Facades\RateLimiter::for('webhook', function () {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(100)->by(request()->ip());
        });

        \Illuminate\Support\Facades\RateLimiter::for('quiz_submit', function () {
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(30)->by(
                auth()->check() ? auth()->id() : request()->ip()
            );
        });

        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Verifikasi Email - LMS')
                ->line('Klik tombol di bawah untuk verifikasi.')
                ->action('Verifikasi Email', $url);
        });

        try {
            Storage::extend('google', function ($app, $config) {
                $jsonPath = $config['serviceAccount'];
                if (! file_exists($jsonPath)) {
                    throw new \Exception('File JSON Service Account TIDAK DITEMUKAN di: '.$jsonPath);
                }
                $client = new Client;
                $client->setAuthConfig($jsonPath);
                $client->addScope(Drive::DRIVE);
                $service = new Drive($client);
                $adapter = new GoogleDriveAdapter($service, $config['folder'] ?? '/');
                $driver = new Filesystem($adapter);

                return new FilesystemAdapter($driver, $adapter);
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Google Drive Adapter gagal dimuat: '.$e->getMessage());
        }
    }
}
