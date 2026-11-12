<?php

declare(strict_types=1);

namespace CraftCms\GuestEntries;

use CraftCms\Cms\Plugin\Plugin as BasePlugin;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\GuestEntries\Http\Requests\SettingsRequest;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * @method Settings getSettings()
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '2.1.0';

    public bool $hasCpSettings = true;

    public bool $config = true;

    public function register(): void
    {
        parent::register();

        // Let controllers, form requests and routes type-hint the plugin’s settings:
        $this->app->singleton(Settings::class, fn () => $this->getSettings());
    }

    public function boot(): void
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

    public function settingsUi(UiContext $context = new UiContext): ?Ui
    {
        return $this->app->make(SettingsUi::class)->make($context);
    }

    protected static function createSettings(): Settings
    {
        return new Settings;
    }

    public function getSettingsRequestClass(): ?string
    {
        return SettingsRequest::class;
    }
}
