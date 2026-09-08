<?php
/**
 * Plugin deactivation tasks.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Activation;

final class Deactivator
{
    private const CRON_HOOK = 'property_sync_run_scheduled';

    /**
     * Remove scheduled work while preserving user data.
     */
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }
}