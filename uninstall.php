<?php
/**
 * Plugin uninstall handler.
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Phase 0 intentionally does not delete options, user data, logs, or future request records.
// Final cleanup behavior will be decided in a later phase after data retention requirements are defined.
