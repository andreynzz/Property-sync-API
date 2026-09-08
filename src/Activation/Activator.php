<?php
/**
 * Plugin activation tasks.
 *
 * @package PropertySync
 */

declare(strict_types=1);

namespace PropertySync\Activation;

final class Activator
{
    private const VERSION_OPTION = 'property_sync_version';

    /**
     * Store the installed plugin version without adding it to every request.
     */
    public static function activate(): void
    {
        if (false === get_option(self::VERSION_OPTION, false)) {
            add_option(self::VERSION_OPTION, PROPERTY_SYNC_VERSION, '', false);

            return;
        }

        update_option(self::VERSION_OPTION, PROPERTY_SYNC_VERSION, false);
    }
}