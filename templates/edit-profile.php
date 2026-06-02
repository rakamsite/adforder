<?php
/**
 * Edit profile shortcode template.
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$fip_profile_context = 'edit';
$fip_profile_title   = __( 'ویرایش پروفایل', 'filter-inquiry-portal' );
include FILTER_INQUIRY_PORTAL_PLUGIN_DIR . 'templates/profile-form.php';
