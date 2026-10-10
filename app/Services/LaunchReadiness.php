<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class LaunchReadiness
{
    public function databaseReady(): bool
    {
        try {
            DB::select('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function checks(): array
    {
        $database = $this->databaseReady();
        $migrations = false;
        $admin = false;
        $noDemo = false;
        if ($database) {
            try {
                $migrator = app('migrator');
                $files = $migrator->getMigrationFiles(database_path('migrations'));
                $migrations = count(array_diff(array_keys($files), $migrator->getRepository()->getRan())) === 0;
                $admin = User::where('role', 'admin')->where('is_active', true)->whereNotNull('email_verified_at')->exists();
                $noDemo = ! User::where('email', 'like', '%@clinic.example')->exists();
            } catch (Throwable) {
                // Missing tables are failed checks, never an exception containing connection details.
            }
        }
        $url = parse_url((string) config('app.url'));
        $sender = (string) config('mail.from.address');
        $domain = strtolower(substr(strrchr($sender, '@') ?: '', 1));

        return [
            'Production environment' => app()->isProduction(),
            'Debug disabled' => ! config('app.debug'),
            'HTTPS application URL' => ($url['scheme'] ?? null) === 'https' && filled($url['host'] ?? null),
            'Secure session cookies' => (bool) config('session.secure'),
            'HTTP-only session cookies' => (bool) config('session.http_only'),
            'Session same-site protection' => in_array(config('session.same_site'), ['lax', 'strict'], true),
            'Encrypted sessions' => (bool) config('session.encrypt'),
            'Delivery-capable mail transports configured' => $this->deliveryMailer((string) config('mail.default')),
            'Non-placeholder sender address' => filter_var($sender, FILTER_VALIDATE_EMAIL) !== false && ! in_array($domain, ['example.com', 'example.org', 'example.net', 'localhost'], true) && ! preg_match('/\.(example|test|invalid)$/', $domain),
            'Application key present' => filled(config('app.key')),
            'Clinic phone configured' => filled(config('clinic.phone')),
            'Clinic address configured' => filled(config('clinic.address')),
            'Clinic and application timezones agree' => config('clinic.timezone') === config('app.timezone') && in_array(config('clinic.timezone'), timezone_identifiers_list(), true),
            'Database reachable' => $database,
            'All migrations applied' => $migrations,
            'Verified active administrator exists' => $admin,
            'No demo accounts' => $noDemo,
            'Production asset manifest present' => is_file(public_path('build/manifest.json')),
            'Development asset server disabled' => ! is_file(public_path('hot')),
        ];
    }

    private function deliveryMailer(string $name, array $visited = []): bool
    {
        if (in_array($name, $visited, true)) {
            return false;
        }
        $mailer = config('mail.mailers.'.$name, []);
        $transport = $mailer['transport'] ?? null;
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $mailer['mailers'] ?? [];
            if (! is_array($children) || $children === []) {
                return false;
            }
            foreach ($children as $child) {
                if (! is_string($child) || ! $this->deliveryMailer($child, [...$visited, $name])) {
                    return false;
                }
            }

            return true;
        }

        return in_array($transport, ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'resend', 'mailgun'], true);
    }
}
