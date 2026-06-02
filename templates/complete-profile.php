<?php
/**
 * Complete profile shortcode template.
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fip_profile_context = 'complete';
$fip_profile_title   = __( 'تکمیل پروفایل', 'filter-inquiry-portal' );
include FILTER_INQUIRY_PORTAL_PLUGIN_DIR . 'templates/profile-form.php';
