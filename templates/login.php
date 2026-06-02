<?php
/**
 * Login/register template with mobile OTP.
 *
 * @package FilterInquiryPortal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$state       = isset( $state ) && is_array( $state ) ? $state : array();
$step        = isset( $state['step'] ) ? sanitize_key( $state['step'] ) : 'mobile';
$mobile      = isset( $state['mobile'] ) ? (string) $state['mobile'] : '';
$redirect_to = isset( $state['redirect_to'] ) ? (string) $state['redirect_to'] : '';
$errors      = isset( $state['errors'] ) && is_array( $state['errors'] ) ? $state['errors'] : array();
$messages    = isset( $state['messages'] ) && is_array( $state['messages'] ) ? $state['messages'] : array();
$wait        = isset( $state['resend_wait_seconds'] ) ? absint( $state['resend_wait_seconds'] ) : 0;
$show_dev    = ! empty( $state['can_show_dev_notice'] );
$mock_otp    = isset( $state['mock_otp'] ) ? (string) $state['mock_otp'] : '';
?>
<div class="fip_template fip_login" dir="rtl">
	<div class="fip_template__card fip_login__card">
		<h2 class="fip_template__title"><?php echo esc_html__( 'ورود / ثبت‌نام در پورتال', 'filter-inquiry-portal' ); ?></h2>

		<?php if ( $show_dev ) : ?>
			<div class="fip_notice fip_notice--info fip_login__dev_notice">
				<p><?php echo esc_html__( 'در این فاز ارسال واقعی پیامک فعال نیست و کد به صورت تستی تولید می‌شود.', 'filter-inquiry-portal' ); ?></p>
				<?php if ( $mock_otp ) : ?>
					<p><?php echo esc_html( sprintf( __( 'کد تست: %s', 'filter-inquiry-portal' ), $mock_otp ) ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="fip_notice fip_notice--error" role="alert">
				<?php foreach ( $errors as $error ) : ?>
					<p><?php echo esc_html( $error ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $messages ) ) : ?>
			<div class="fip_notice fip_notice--success">
				<?php foreach ( $messages as $message ) : ?>
					<p><?php echo esc_html( $message ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( 'otp' === $step ) : ?>
			<form class="fip_login_form fip_login_form--otp" method="post" action="">
				<?php wp_nonce_field( 'fip_verify_otp_nonce', 'fip_verify_otp_nonce' ); ?>
				<input type="hidden" name="fip_auth_action" value="verify_otp" />
				<input type="hidden" name="fip_mobile" value="<?php echo esc_attr( $mobile ); ?>" />
				<?php if ( $redirect_to ) : ?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>" />
				<?php endif; ?>

				<p class="fip_login__description">
					<?php echo esc_html( sprintf( __( 'کد ورود برای شماره %s آماده شده است.', 'filter-inquiry-portal' ), $mobile ) ); ?>
				</p>

				<p class="fip_form_field">
					<label for="fip_otp_code"><?php echo esc_html__( 'کد ورود', 'filter-inquiry-portal' ); ?></label>
					<input id="fip_otp_code" name="fip_otp_code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="5" autocomplete="one-time-code" required />
				</p>

				<?php if ( $wait > 0 ) : ?>
					<p class="fip_login__timer"><?php echo esc_html( sprintf( __( 'دریافت مجدد کد تا %d ثانیه دیگر فعال می‌شود.', 'filter-inquiry-portal' ), $wait ) ); ?></p>
				<?php else : ?>
					<p class="fip_login__timer"><?php echo esc_html__( 'در صورت دریافت نکردن کد، می‌توانید دوباره درخواست کد بدهید.', 'filter-inquiry-portal' ); ?></p>
				<?php endif; ?>

				<p class="fip_form_actions fip_login__actions">
					<button class="fip_button fip_button--primary" type="submit"><?php echo esc_html__( 'ورود / ثبت‌نام', 'filter-inquiry-portal' ); ?></button>
				</p>
			</form>

			<div class="fip_login__secondary_actions">
				<form method="post" action="" class="fip_login_form fip_login_form--resend">
					<?php wp_nonce_field( 'fip_send_otp_nonce', 'fip_send_otp_nonce' ); ?>
					<input type="hidden" name="fip_auth_action" value="send_otp" />
					<input type="hidden" name="fip_mobile" value="<?php echo esc_attr( $mobile ); ?>" />
					<?php if ( $redirect_to ) : ?>
						<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>" />
					<?php endif; ?>
					<button class="fip_button" type="submit"<?php disabled( $wait > 0 ); ?>><?php echo esc_html__( 'دریافت مجدد کد', 'filter-inquiry-portal' ); ?></button>
				</form>
				<a class="fip_button" href="<?php echo esc_url( remove_query_arg( array( 'fip_step' ) ) ); ?>"><?php echo esc_html__( 'ویرایش شماره موبایل', 'filter-inquiry-portal' ); ?></a>
			</div>
		<?php else : ?>
			<form class="fip_login_form fip_login_form--mobile" method="post" action="">
				<?php wp_nonce_field( 'fip_send_otp_nonce', 'fip_send_otp_nonce' ); ?>
				<input type="hidden" name="fip_auth_action" value="send_otp" />
				<?php if ( $redirect_to ) : ?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ); ?>" />
				<?php endif; ?>

				<p class="fip_form_field">
					<label for="fip_mobile"><?php echo esc_html__( 'شماره موبایل', 'filter-inquiry-portal' ); ?></label>
					<input id="fip_mobile" name="fip_mobile" type="tel" inputmode="tel" autocomplete="tel" placeholder="09123456789" value="<?php echo esc_attr( $mobile ); ?>" required />
				</p>

				<p class="fip_form_actions">
					<button class="fip_button fip_button--primary" type="submit"><?php echo esc_html__( 'دریافت کد ورود', 'filter-inquiry-portal' ); ?></button>
				</p>
			</form>
		<?php endif; ?>
	</div>
</div>
