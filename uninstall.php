<?php
/**
 * COGS Studio uninstall handler.
 *
 * Data is intentionally preserved by default. The plugin stores historical cost changes
 * that may be operationally important, so uninstalling the plugin is not destructive.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Intentionally no destructive cleanup.
