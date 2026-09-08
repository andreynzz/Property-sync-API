<?php
/**
 * Uninstall handler for Property Sync API.
 *
 * Synced properties and settings are intentionally preserved. A future opt-in
 * setting will allow destructive cleanup without surprising site owners.
 *
 * @package PropertySync
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}
