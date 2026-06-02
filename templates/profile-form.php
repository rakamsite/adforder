<?php
/**
 * Shared profile form template.
 *
 * Expected variables:
 * - $fip_profile_context string complete|edit
 * - $fip_profile_title   string
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$profile_module = fip_plugin()->get_module( 'profile' );
if ( ! $profile_module instanceof FIP_Profile ) {
	return;
}

$context = isset( $fip_profile_context ) ? sanitize_key( $fip_profile_context ) : 'edit';
$title   = isset( $fip_profile_title ) ? $fip_profile_title : __( 'پروفایل کاربری', 'filter-inquiry-portal' );
?>
<div class="fip_template fip_profile fip_profile--<?php echo esc_attr( $context ); ?>" dir="rtl">
	<div class="fip_template__card">
		<h2 class="fip_template__title"><?php echo esc_html( $title ); ?></h2>

		<?php if ( ! is_user_logged_in() ) : ?>
			<p class="fip_notice fip_notice--info"><?php echo esc_html__( 'برای تکمیل حساب کاربری ابتدا وارد شوید.', 'filter-inquiry-portal' ); ?></p>
		<?php else : ?>
			<?php
			$submission = $profile_module->handle_profile_submission( $context );
			$user_id     = get_current_user_id();
			$profile     = $profile_module->get_profile( $user_id );

			if ( ! empty( $submission['submitted'] ) && empty( $submission['success'] ) ) {
				foreach ( array( 'first_name', 'last_name', 'company', 'activity_field', 'position', 'province', 'city', 'birth_date' ) as $field ) {
					if ( isset( $_POST[ $field ] ) && is_scalar( $_POST[ $field ] ) ) {
						$profile[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
					}
				}
			}

			$activity_fields = $profile_module->get_activity_fields();
			$provinces       = $profile_module->get_provinces();
			$cities          = $profile_module->get_cities_by_province( $profile['province'] );
			$settings        = fip_plugin()->get_module( 'settings' );
			$dashboard_url   = ( $settings && method_exists( $settings, 'get_page_url' ) ) ? $settings->get_page_url( 'dashboard_page_id' ) : '';
			?>

			<?php if ( ! empty( $submission['success'] ) ) : ?>
				<div class="fip_notice fip_notice--success">
					<p><?php echo esc_html__( 'اطلاعات پروفایل با موفقیت ذخیره شد.', 'filter-inquiry-portal' ); ?></p>
					<?php if ( $dashboard_url ) : ?>
						<a class="fip_button" href="<?php echo esc_url( $dashboard_url ); ?>"><?php echo esc_html__( 'رفتن به داشبورد', 'filter-inquiry-portal' ); ?></a>
					<?php endif; ?>
				</div>
			<?php elseif ( ! empty( $submission['errors'] ) ) : ?>
				<div class="fip_notice fip_notice--error" role="alert">
					<ul>
						<?php foreach ( $submission['errors'] as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<form class="fip_profile_form" method="post" novalidate>
				<?php wp_nonce_field( 'fip_profile_nonce', 'fip_profile_nonce' ); ?>
				<input type="hidden" name="fip_profile_action" value="save_profile" />
				<input type="hidden" name="fip_profile_context" value="<?php echo esc_attr( $context ); ?>" />

				<div class="fip_form_grid">
					<p class="fip_form_field">
						<label for="fip_first_name"><?php echo esc_html__( 'نام', 'filter-inquiry-portal' ); ?> <span class="fip_required">*</span></label>
						<input id="fip_first_name" name="first_name" type="text" value="<?php echo esc_attr( $profile['first_name'] ); ?>" required />
					</p>

					<p class="fip_form_field">
						<label for="fip_last_name"><?php echo esc_html__( 'نام خانوادگی', 'filter-inquiry-portal' ); ?> <span class="fip_required">*</span></label>
						<input id="fip_last_name" name="last_name" type="text" value="<?php echo esc_attr( $profile['last_name'] ); ?>" required />
					</p>

					<p class="fip_form_field">
						<label for="fip_company"><?php echo esc_html__( 'نام شرکت / فروشگاه', 'filter-inquiry-portal' ); ?></label>
						<input id="fip_company" name="company" type="text" value="<?php echo esc_attr( $profile['company'] ); ?>" />
					</p>

					<p class="fip_form_field">
						<label for="fip_activity_field"><?php echo esc_html__( 'حوزه فعالیت', 'filter-inquiry-portal' ); ?> <span class="fip_required">*</span></label>
						<select id="fip_activity_field" name="activity_field" required>
							<option value=""><?php echo esc_html__( 'انتخاب کنید', 'filter-inquiry-portal' ); ?></option>
							<?php foreach ( $activity_fields as $activity_field ) : ?>
								<option value="<?php echo esc_attr( $activity_field ); ?>" <?php selected( $profile['activity_field'], $activity_field ); ?>><?php echo esc_html( $activity_field ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<p class="fip_form_field">
						<label for="fip_position"><?php echo esc_html__( 'سمت شما', 'filter-inquiry-portal' ); ?></label>
						<input id="fip_position" name="position" type="text" value="<?php echo esc_attr( $profile['position'] ); ?>" />
					</p>

					<p class="fip_form_field">
						<label for="fip_mobile"><?php echo esc_html__( 'شماره موبایل', 'filter-inquiry-portal' ); ?></label>
						<input id="fip_mobile" type="text" value="<?php echo esc_attr( $profile['mobile'] ); ?>" readonly aria-readonly="true" />
					</p>

					<p class="fip_form_field">
						<label for="fip_province"><?php echo esc_html__( 'استان', 'filter-inquiry-portal' ); ?> <span class="fip_required">*</span></label>
						<select id="fip_province" name="province" class="fip_province_select" required>
							<option value=""><?php echo esc_html__( 'انتخاب استان', 'filter-inquiry-portal' ); ?></option>
							<?php foreach ( $provinces as $province ) : ?>
								<option value="<?php echo esc_attr( $province ); ?>" <?php selected( $profile['province'], $province ); ?>><?php echo esc_html( $province ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<p class="fip_form_field">
						<label for="fip_city"><?php echo esc_html__( 'شهر', 'filter-inquiry-portal' ); ?> <span class="fip_required">*</span></label>
						<select id="fip_city" name="city" class="fip_city_select" data-selected-city="<?php echo esc_attr( $profile['city'] ); ?>" required>
							<option value=""><?php echo esc_html__( 'ابتدا استان را انتخاب کنید', 'filter-inquiry-portal' ); ?></option>
							<?php foreach ( $cities as $city ) : ?>
								<option value="<?php echo esc_attr( $city ); ?>" <?php selected( $profile['city'], $city ); ?>><?php echo esc_html( $city ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>

					<p class="fip_form_field">
						<label for="fip_birth_date"><?php echo esc_html__( 'تاریخ تولد', 'filter-inquiry-portal' ); ?></label>
						<input id="fip_birth_date" name="birth_date" type="text" value="<?php echo esc_attr( $profile['birth_date'] ); ?>" placeholder="<?php echo esc_attr__( 'مثلاً ۱۳۷۰/۰۱/۰۱', 'filter-inquiry-portal' ); ?>" />
					</p>
				</div>

				<p class="fip_form_actions">
					<button class="fip_button fip_button--primary" type="submit"><?php echo esc_html__( 'ذخیره اطلاعات', 'filter-inquiry-portal' ); ?></button>
				</p>
			</form>
		<?php endif; ?>
	</div>
</div>
