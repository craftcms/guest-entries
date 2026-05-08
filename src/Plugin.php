<?php

namespace CraftCms\GuestEntries;

use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Override;

use function CraftCms\Cms\template;

class Plugin extends BasePlugin
{
    public string $schemaVersion = '2.1.0';

    public bool $hasCpSettings = true;

    public bool $config = true;

    public array $styles = [
        __DIR__.'/../resources/css/guest-entries.css' => 'css/guest-entries.css',
    ];

    #[Override]
    public function bootPlugin(): void
    {
        RateLimiter::for('guest-entries', function (Request $request) {
            $limit = $this->getSettings()->rateLimit;

            // `null` has special significance, but isn't handled by other limit methods:
            if ($limit === null) {
                return Limit::none();
            }

            return Limit::perMinute($limit)->by($request->getClientIp());
        });
    }

    #[Override]
    protected function settingsHtml(): string
    {
        return template('guest-entries/_settings', [
            'settings' => $this->getSettings(),
        ]);
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings;
    }
}
