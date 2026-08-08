<?php

namespace App\Filament\Pages;

use App\Models\Gallery;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Models\Redirect;
use App\Services\SettingsService;
use Filament\Pages\Page as FilamentPage;
use Illuminate\Support\Facades\DB;

class LaunchChecklist extends FilamentPage
{
    protected static ?string $navigationGroup = 'نگهداری سیستم';

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'وضعیت سیستم';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.launch-checklist';

    public function systemStatus(): array
    {
        return [
            [
                'label' => 'Application environment',
                'ok' => app()->environment('production'),
                'detail' => app()->environment(),
            ],
            [
                'label' => 'Laravel version',
                'ok' => true,
                'detail' => app()->version(),
            ],
            [
                'label' => 'PHP version',
                'ok' => version_compare(PHP_VERSION, '8.2', '>='),
                'detail' => PHP_VERSION,
            ],
            [
                'label' => 'Database connection',
                'ok' => $this->databaseIsAvailable(),
                'detail' => $this->databaseIsAvailable() ? 'Connection OK' : 'Database connection failed.',
            ],
            [
                'label' => 'Public storage link',
                'ok' => $this->storageLinkExists(),
                'detail' => $this->storageLinkExists() ? 'public/storage is available.' : 'Run php artisan storage:link.',
            ],
            [
                'label' => 'Mail configuration',
                'ok' => filled(config('mail.default')) && filled(config('mail.from.address')),
                'detail' => 'Mailer: '.((string) config('mail.default') ?: 'not configured').'; from address '.(filled(config('mail.from.address')) ? 'configured' : 'missing').'.',
            ],
            [
                'label' => 'Queue connection',
                'ok' => filled(config('queue.default')),
                'detail' => (string) config('queue.default', 'not configured'),
            ],
            [
                'label' => 'Sitemap route',
                'ok' => (string) app(SettingsService::class)->get('sitemap_enabled', '1') !== '0',
                'detail' => route('sitemap', absolute: false),
            ],
            [
                'label' => 'Robots route',
                'ok' => true,
                'detail' => route('robots', absolute: false),
            ],
        ];
    }

    public function checks(): array
    {
        $settings = app(SettingsService::class);
        $paymentGateway = (string) $settings->get('payment_gateway', 'manual');

        return [
            [
                'label' => 'Site name',
                'ok' => filled($settings->get('site_name')),
                'detail' => filled($settings->get('site_name')) ? 'Configured' : 'Set the public site name before launch.',
            ],
            [
                'label' => 'Logo and favicon',
                'ok' => filled($settings->get('site_logo')) && filled($settings->get('site_favicon')),
                'detail' => 'Upload both branding assets in Site Settings > Branding.',
            ],
            [
                'label' => 'Contact details',
                'ok' => filled($settings->get('contact_email')) && filled($settings->get('contact_phone')),
                'detail' => 'Contact email and phone should be configured.',
            ],
            [
                'label' => 'Default SEO',
                'ok' => filled($settings->get('site_title')) && filled($settings->get('default_meta_description')),
                'detail' => 'Set default SEO title and description.',
            ],
            [
                'label' => 'Sitemap',
                'ok' => (string) $settings->get('sitemap_enabled', '1') !== '0',
                'detail' => route('sitemap', absolute: false),
            ],
            [
                'label' => 'Robots',
                'ok' => true,
                'detail' => route('robots', absolute: false),
            ],
            [
                'label' => 'Health check',
                'ok' => (string) $settings->get('health_check_enabled', '1') !== '0',
                'detail' => route('health', absolute: false),
            ],
            [
                'label' => 'Mail configuration',
                'ok' => filled(config('mail.default')) || filled(env('MAIL_MAILER')),
                'detail' => 'Confirm SMTP or transactional mail values in production .env.',
            ],
            [
                'label' => 'Shop payment mode',
                'ok' => in_array($paymentGateway, ['manual', 'zarinpal'], true),
                'detail' => 'Current mode: '.($paymentGateway ?: 'manual'),
            ],
            [
                'label' => 'Zarinpal token',
                'ok' => $paymentGateway !== 'zarinpal' || filled($settings->get('zarinpal_access_token')),
                'detail' => $paymentGateway === 'zarinpal'
                    ? 'Required before live Zarinpal payment work.'
                    : 'Not required while manual payment is selected.',
            ],
            [
                'label' => 'Active redirects',
                'ok' => true,
                'detail' => Redirect::query()->active()->count().' active redirects configured.',
            ],
            [
                'label' => 'Draft content',
                'ok' => $this->draftContentCount() === 0,
                'detail' => $this->draftContentCount().' draft pages/posts/projects/galleries/products remain.',
            ],
            [
                'label' => 'Module decisions',
                'ok' => true,
                'detail' => 'Projects: '.$this->enabledLabel($settings->get('projects_enabled', '1')).'; Galleries: '.$this->enabledLabel($settings->get('galleries_enabled', '1')).'; Shop: '.$this->enabledLabel($settings->get('shop_enabled', '1')).'.',
            ],
            [
                'label' => 'Backup plan',
                'ok' => false,
                'detail' => 'Confirm database, storage/app/public, uploaded media, and secure .env backups outside this application.',
            ],
        ];
    }

    public function backupItems(): array
    {
        return [
            'Database: schedule server-side MySQL/PostgreSQL backups and test restore before launch.',
            'Files: back up storage/app/public, including uploaded Media Library files.',
            'Symlink target: confirm public/storage points to storage/app/public after deployment.',
            '.env: keep a secure manual backup outside git and outside public web directories.',
            'Exports: use CSV exports for orders, contact messages, and redirects when handing data to a client.',
        ];
    }

    private function draftContentCount(): int
    {
        return Page::query()->where('status', 'draft')->count()
            + Post::query()->where('status', 'draft')->count()
            + Project::query()->where('status', 'draft')->count()
            + Gallery::query()->where('status', 'draft')->count()
            + Product::query()->where('status', 'draft')->count();
    }

    private function databaseIsAvailable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function storageLinkExists(): bool
    {
        return is_link(public_path('storage')) || is_dir(public_path('storage'));
    }

    private function enabledLabel(mixed $value): string
    {
        return (string) $value === '0' ? 'disabled' : 'enabled';
    }
}
