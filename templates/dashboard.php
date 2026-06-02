<?php
/**
 * Customer dashboard template.
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = fip_plugin()->get_module( 'settings' );
$profile  = fip_plugin()->get_module( 'profile' );

$get_page_url = static function ( $key ) use ( $settings ) {
	if ( $settings && method_exists( $settings, 'get_page_url' ) ) {
		return $settings->get_page_url( $key );
	}

	return '';
};

$login_url            = $get_page_url( 'login_page_id' );
$complete_profile_url = $get_page_url( 'complete_profile_page_id' );
$dashboard_url        = $get_page_url( 'dashboard_page_id' );
$new_request_url      = $get_page_url( 'new_request_page_id' );
$my_requests_url      = $get_page_url( 'my_requests_page_id' );
$edit_profile_url     = $get_page_url( 'edit_profile_page_id' );
$request_detail_url   = $get_page_url( 'request_detail_page_id' );

if ( ! $login_url ) {
	$login_url = wp_login_url( get_permalink() );
}

if ( ! is_user_logged_in() ) :
	?>
	<div class="fip-dashboard fip-dashboard--gated" dir="rtl">
		<div class="fip-card">
			<p class="fip_notice fip_notice--info"><?php echo esc_html__( 'برای مشاهده داشبورد ابتدا وارد حساب کاربری شوید.', 'filter-inquiry-portal' ); ?></p>
			<a class="fip-button fip-button--primary fip_button fip_button--primary" href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html__( 'ورود به حساب کاربری', 'filter-inquiry-portal' ); ?></a>
		</div>
	</div>
	<?php
	return;
endif;

$user_id = get_current_user_id();

if ( ! $profile || ! method_exists( $profile, 'is_profile_completed' ) || ! $profile->is_profile_completed( $user_id ) ) :
	?>
	<div class="fip-dashboard fip-dashboard--gated" dir="rtl">
		<div class="fip-card">
			<p class="fip_notice fip_notice--info"><?php echo esc_html__( 'لطفاً ابتدا حساب کاربری خود را تکمیل کنید.', 'filter-inquiry-portal' ); ?></p>
			<?php if ( $complete_profile_url ) : ?>
				<a class="fip-button fip-button--primary fip_button fip_button--primary" href="<?php echo esc_url( $complete_profile_url ); ?>"><?php echo esc_html__( 'تکمیل حساب کاربری', 'filter-inquiry-portal' ); ?></a>
			<?php else : ?>
				<p class="fip_notice fip_notice--error"><?php echo esc_html__( 'صفحه تکمیل پروفایل هنوز از تنظیمات افزونه انتخاب نشده است.', 'filter-inquiry-portal' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return;
endif;

$user_profile = method_exists( $profile, 'get_profile' ) ? $profile->get_profile( $user_id ) : array();
$statuses     = class_exists( 'FIP_Requests', false ) && method_exists( 'FIP_Requests', 'get_statuses' ) ? FIP_Requests::get_statuses() : array();
$requests     = class_exists( 'FIP_Requests', false ) && method_exists( 'FIP_Requests', 'get_user_requests' ) ? FIP_Requests::get_user_requests( $user_id, 1, 5 ) : null;

$dashboard_notice_enabled = $settings && method_exists( $settings, 'get_option' ) ? absint( $settings->get_option( 'dashboard_notice_enabled', 0 ) ) : 0;
$dashboard_notice_title   = $settings && method_exists( $settings, 'get_option' ) ? (string) $settings->get_option( 'dashboard_notice_title', '' ) : '';
$dashboard_notice_content = $settings && method_exists( $settings, 'get_option' ) ? (string) $settings->get_option( 'dashboard_notice_content', '' ) : '';

$menu_items = array(
	array(
		'label' => __( 'داشبورد', 'filter-inquiry-portal' ),
		'url'   => $dashboard_url ? $dashboard_url : '#',
	),
	array(
		'label' => __( 'ثبت درخواست', 'filter-inquiry-portal' ),
		'url'   => $new_request_url ? $new_request_url : '#',
	),
	array(
		'label' => __( 'درخواست‌های من', 'filter-inquiry-portal' ),
		'url'   => $my_requests_url ? $my_requests_url : '#',
	),
	array(
		'label' => __( 'ویرایش حساب کاربری', 'filter-inquiry-portal' ),
		'url'   => $edit_profile_url ? $edit_profile_url : '#',
	),
	array(
		'label' => __( 'خروج', 'filter-inquiry-portal' ),
		'url'   => wp_logout_url( $login_url ? $login_url : home_url( '/' ) ),
	),
);

$summary_rows = array(
	__( 'نام و نام خانوادگی', 'filter-inquiry-portal' ) => trim( ( isset( $user_profile['first_name'] ) ? $user_profile['first_name'] : '' ) . ' ' . ( isset( $user_profile['last_name'] ) ? $user_profile['last_name'] : '' ) ),
	__( 'شرکت / فروشگاه', 'filter-inquiry-portal' )   => isset( $user_profile['company'] ) ? $user_profile['company'] : '',
	__( 'حوزه فعالیت', 'filter-inquiry-portal' )       => isset( $user_profile['activity_field'] ) ? $user_profile['activity_field'] : '',
	__( 'استان / شهر', 'filter-inquiry-portal' )        => trim( ( isset( $user_profile['province'] ) ? $user_profile['province'] : '' ) . ' / ' . ( isset( $user_profile['city'] ) ? $user_profile['city'] : '' ), ' /' ),
	__( 'موبایل', 'filter-inquiry-portal' )             => isset( $user_profile['mobile'] ) ? $user_profile['mobile'] : '',
);
?>
<div class="fip-dashboard" dir="rtl">
	<aside class="fip-dashboard__sidebar" aria-label="<?php echo esc_attr__( 'منوی پنل استعلام', 'filter-inquiry-portal' ); ?>">
		<div class="fip-card">
			<h2 class="fip-dashboard__menu-title"><?php echo esc_html__( 'منوی پنل استعلام', 'filter-inquiry-portal' ); ?></h2>
			<nav class="fip-dashboard-menu" aria-label="<?php echo esc_attr__( 'منوی پنل استعلام', 'filter-inquiry-portal' ); ?>">
				<?php if ( has_nav_menu( 'filter_portal_menu' ) ) : ?>
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'filter_portal_menu',
							'container'      => false,
							'menu_class'     => 'fip-dashboard-menu__list',
							'fallback_cb'    => false,
							'depth'          => 1,
						)
					);
					?>
				<?php else : ?>
					<ul class="fip-dashboard-menu__list">
						<?php foreach ( $menu_items as $menu_item ) : ?>
							<li class="fip-dashboard-menu__item"><a href="<?php echo esc_url( $menu_item['url'] ); ?>"><?php echo esc_html( $menu_item['label'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</nav>
		</div>
	</aside>

	<main class="fip-dashboard__content">
		<?php if ( $dashboard_notice_enabled ) : ?>
			<div class="fip-card fip-dashboard__notice">
				<?php if ( '' !== $dashboard_notice_title ) : ?>
					<h2><?php echo esc_html( $dashboard_notice_title ); ?></h2>
				<?php endif; ?>
				<?php if ( '' !== $dashboard_notice_content ) : ?>
					<div class="fip-dashboard__notice-content"><?php echo wp_kses_post( wpautop( $dashboard_notice_content ) ); ?></div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="fip-card fip-dashboard__hero">
			<div>
				<h1><?php echo esc_html__( 'داشبورد استعلام', 'filter-inquiry-portal' ); ?></h1>
				<p><?php echo esc_html__( 'از این بخش می‌توانید وضعیت حساب کاربری و آخرین درخواست‌های خود را مشاهده کنید.', 'filter-inquiry-portal' ); ?></p>
			</div>
			<?php if ( $new_request_url ) : ?>
				<a class="fip-button fip-button--primary fip-button--large fip_button fip_button--primary" href="<?php echo esc_url( $new_request_url ); ?>"><?php echo esc_html__( 'ثبت درخواست', 'filter-inquiry-portal' ); ?></a>
			<?php else : ?>
				<div class="fip-dashboard__disabled-action">
					<span class="fip-button fip-button--large fip-button--disabled fip_button" aria-disabled="true"><?php echo esc_html__( 'ثبت درخواست', 'filter-inquiry-portal' ); ?></span>
					<p class="fip_notice fip_notice--info"><?php echo esc_html__( 'صفحه ثبت درخواست هنوز از تنظیمات افزونه انتخاب نشده است.', 'filter-inquiry-portal' ); ?></p>
				</div>
			<?php endif; ?>
		</div>

		<section class="fip-card fip-dashboard__summary" aria-labelledby="fip-account-summary-title">
			<div class="fip-dashboard__section-header">
				<h2 id="fip-account-summary-title"><?php echo esc_html__( 'خلاصه حساب کاربری', 'filter-inquiry-portal' ); ?></h2>
				<?php if ( $edit_profile_url ) : ?>
					<a class="fip-button fip_button" href="<?php echo esc_url( $edit_profile_url ); ?>"><?php echo esc_html__( 'ویرایش حساب کاربری', 'filter-inquiry-portal' ); ?></a>
				<?php endif; ?>
			</div>
			<dl class="fip-dashboard__summary-list">
				<?php foreach ( $summary_rows as $label => $value ) : ?>
					<?php if ( '' === trim( (string) $value ) ) { continue; } ?>
					<div>
						<dt><?php echo esc_html( $label ); ?></dt>
						<dd><?php echo esc_html( $value ); ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
		</section>

		<section class="fip-card fip-dashboard__requests" aria-labelledby="fip-latest-requests-title">
			<div class="fip-dashboard__section-header">
				<h2 id="fip-latest-requests-title"><?php echo esc_html__( 'آخرین درخواست‌ها', 'filter-inquiry-portal' ); ?></h2>
				<?php if ( $my_requests_url ) : ?>
					<a class="fip-button fip_button" href="<?php echo esc_url( $my_requests_url ); ?>"><?php echo esc_html__( 'درخواست‌های من', 'filter-inquiry-portal' ); ?></a>
				<?php endif; ?>
			</div>

			<?php if ( $requests instanceof WP_Query && $requests->have_posts() ) : ?>
				<div class="fip-request-table-wrap">
					<table class="fip-request-table">
						<thead>
							<tr>
								<th scope="col"><?php echo esc_html__( 'شماره درخواست', 'filter-inquiry-portal' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'تاریخ ثبت', 'filter-inquiry-portal' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'تعداد آیتم‌ها', 'filter-inquiry-portal' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'وضعیت', 'filter-inquiry-portal' ); ?></th>
								<th scope="col"><?php echo esc_html__( 'مشاهده', 'filter-inquiry-portal' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							while ( $requests->have_posts() ) :
								$requests->the_post();
								$request_id     = get_the_ID();
								$request        = FIP_Requests::get_request( $request_id );
								$request_number = $request && ! empty( $request['request_number'] ) ? $request['request_number'] : FIP_Requests::generate_request_number( $request_id );
								$status         = $request && ! empty( $request['status'] ) ? $request['status'] : 'pending';
								$status_label   = isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
								$items_count    = $request && isset( $request['items_count'] ) ? absint( $request['items_count'] ) : 0;
								$detail_link    = $request_detail_url ? add_query_arg( 'request_id', $request_id, $request_detail_url ) : '';
								?>
								<tr>
									<td><?php echo esc_html( $request_number ); ?></td>
									<td><?php echo esc_html( wp_date( get_option( 'date_format' ), get_post_time( 'U', true, $request_id ) ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $items_count ) ); ?></td>
									<td><span class="fip-status-badge fip-status-badge--<?php echo esc_attr( sanitize_html_class( $status ) ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
									<td>
										<?php if ( $detail_link ) : ?>
											<a href="<?php echo esc_url( $detail_link ); ?>"><?php echo esc_html__( 'مشاهده', 'filter-inquiry-portal' ); ?></a>
										<?php else : ?>
											<span aria-hidden="true">—</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endwhile; ?>
						</tbody>
					</table>
				</div>
				<?php wp_reset_postdata(); ?>
			<?php else : ?>
				<p class="fip-dashboard__empty"><?php echo esc_html__( 'هنوز درخواستی ثبت نکرده‌اید.', 'filter-inquiry-portal' ); ?></p>
				<?php if ( $new_request_url ) : ?>
					<a class="fip-button fip-button--primary fip_button fip_button--primary" href="<?php echo esc_url( $new_request_url ); ?>"><?php echo esc_html__( 'ثبت اولین درخواست', 'filter-inquiry-portal' ); ?></a>
				<?php endif; ?>
			<?php endif; ?>
		</section>
	</main>
</div>
