<?php
/**
 * reCAPTCHA for Nestform (plugin settings, not theme Auth).
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Captcha {

	const ACTION = 'nestform';

	/** @var bool */
	private static $needs_assets = false;

	/** @var string */
	private static $provider = '';

	/** @var string */
	private static $site_key = '';

	public static function init() {
		add_filter( 'nestform_captcha_html', array( __CLASS__, 'filter_html' ), 10, 3 );
		// Verification is enforced imperatively in Nestform_Submit (cannot be bypassed by filter).
		add_action( 'wp_footer', array( __CLASS__, 'maybe_enqueue' ), 5 );
	}

	/**
	 * Captcha provider from plugin settings.
	 *
	 * @return string recaptcha_v2|recaptcha_v3|empty
	 */
	public static function provider() {
		if ( ! class_exists( 'Nestform_Settings' ) || ! Nestform_Settings::captcha_ready() ) {
			return '';
		}
		$s = Nestform_Settings::get();
		return (string) ( $s['captcha_provider'] ?? 'recaptcha_v2' );
	}

	/**
	 * Whether this form should show/verify captcha.
	 *
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $config  Optional preloaded config.
	 * @return bool
	 */
	public static function enabled_for_form( $form_id, array $config = array() ) {
		$form_id = (int) $form_id;
		if ( $form_id <= 0 ) {
			return false;
		}

		if ( array() === $config ) {
			$config = Nestform_Form_Config::get( $form_id );
		}

		$settings = isset( $config['settings'] ) && is_array( $config['settings'] ) ? $config['settings'] : array();
		if ( empty( $settings['enable_captcha'] ) || '0' === (string) $settings['enable_captcha'] ) {
			return false;
		}

		return class_exists( 'Nestform_Settings' ) && Nestform_Settings::captcha_ready();
	}

	/**
	 * @param string               $html    Existing HTML.
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $config  Config.
	 * @return string
	 */
	public static function filter_html( $html, $form_id, $config ) {
		if ( ! self::enabled_for_form( $form_id, is_array( $config ) ? $config : array() ) ) {
			return (string) $html;
		}

		$settings = Nestform_Settings::get();
		$site_key = (string) ( $settings['captcha_site_key'] ?? '' );
		$provider = (string) ( $settings['captcha_provider'] ?? 'recaptcha_v2' );

		if ( $site_key === '' ) {
			return (string) $html;
		}

		self::$needs_assets = true;
		self::$provider     = $provider;
		self::$site_key     = $site_key;

		ob_start();
		if ( 'recaptcha_v3' === $provider ) {
			echo '<input type="hidden" name="g-recaptcha-response" value="" data-nest-form-captcha-token />';
		} else {
			printf(
				'<div class="g-recaptcha" data-sitekey="%s"></div>',
				esc_attr( $site_key )
			);
		}

		return (string) $html . (string) ob_get_clean();
	}

	/**
	 * @param true|WP_Error|bool    $result   Current result.
	 * @param int                   $form_id  Form ID.
	 * @param array<string, string> $messages Messages.
	 * @return true|WP_Error|bool
	 */
	public static function filter_verify( $result, $form_id, $messages ) {
		if ( is_wp_error( $result ) || false === $result ) {
			return $result;
		}

		if ( ! self::enabled_for_form( (int) $form_id ) ) {
			return true;
		}

		$verified = self::verify();
		if ( is_wp_error( $verified ) ) {
			$msg = $verified->get_error_message();
			if ( $msg === '' && isset( $messages['invalid_captcha'] ) ) {
				$msg = $messages['invalid_captcha'];
			}
			return new WP_Error( 'nestform_captcha', $msg );
		}

		return true;
	}

	/**
	 * Verify Google reCAPTCHA token from POST.
	 *
	 * @return true|WP_Error
	 */
	public static function verify() {
		if ( ! class_exists( 'Nestform_Settings' ) || ! Nestform_Settings::captcha_ready() ) {
			return new WP_Error( 'nestform_captcha_unavailable', __( 'Captcha is not available.', 'nestform' ) );
		}

		$token = '';
		if ( isset( $_POST['g-recaptcha-response'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- form nonce checked in submit handler
			$token = trim( (string) wp_unslash( $_POST['g-recaptcha-response'] ) );
		}

		if ( $token === '' ) {
			return new WP_Error( 'nestform_captcha_missing', __( 'Please complete the captcha and try again.', 'nestform' ) );
		}

		$settings = Nestform_Settings::get();
		$secret   = (string) $settings['captcha_secret_key'];
		$provider = (string) $settings['captcha_provider'];

		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => $ip,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'nestform_captcha_http', __( 'Captcha verification failed. Please try again.', 'nestform' ) );
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['success'] ) ) {
			return new WP_Error( 'nestform_captcha_invalid', __( 'Captcha verification failed. Please try again.', 'nestform' ) );
		}

		if ( 'recaptcha_v3' === $provider ) {
			$score     = isset( $body['score'] ) ? (float) $body['score'] : 0.0;
			$min_score = (float) ( $settings['captcha_v3_score'] ?? 0.5 );
			$action    = isset( $body['action'] ) ? (string) $body['action'] : '';

			if ( $action !== '' && $action !== self::ACTION ) {
				return new WP_Error( 'nestform_captcha_action', __( 'Captcha verification failed. Please try again.', 'nestform' ) );
			}

			if ( $score < $min_score ) {
				return new WP_Error( 'nestform_captcha_score', __( 'Captcha verification failed. Please try again.', 'nestform' ) );
			}
		}

		return true;
	}

	/**
	 * Enqueue Google script when a captcha-enabled form was rendered.
	 */
	public static function maybe_enqueue() {
		if ( ! self::$needs_assets || self::$site_key === '' ) {
			return;
		}

		$handle = 'nestform-recaptcha';

		if ( 'recaptcha_v3' === self::$provider ) {
			wp_enqueue_script(
				$handle,
				'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( self::$site_key ),
				array(),
				null,
				true
			);
			wp_localize_script(
				'nestform-front',
				'nestformCaptcha',
				array(
					'provider' => 'recaptcha_v3',
					'siteKey'  => self::$site_key,
					'action'   => self::ACTION,
				)
			);
			return;
		}

		wp_enqueue_script(
			$handle,
			'https://www.google.com/recaptcha/api.js',
			array(),
			null,
			true
		);
		wp_localize_script(
			'nestform-front',
			'nestformCaptcha',
			array(
				'provider' => 'recaptcha_v2',
				'siteKey'  => self::$site_key,
				'action'   => self::ACTION,
			)
		);
	}

	/**
	 * Admin hint status for the form builder.
	 *
	 * @return array{available:bool,global_on:bool,message:string}
	 */
	public static function admin_status() {
		if ( ! class_exists( 'Nestform_Settings' ) ) {
			return array(
				'available' => false,
				'global_on' => false,
				'url'       => '',
				'message'   => __( 'Nestform settings are not loaded.', 'nestform' ),
			);
		}

		$s   = Nestform_Settings::get();
		$ready = Nestform_Settings::captcha_ready();
		$url = class_exists( 'Nestform_Integrations' ) ? Nestform_Integrations::url() : Nestform_Settings::url();

		if ( $ready ) {
			$provider = (string) ( $s['captcha_provider'] ?? 'recaptcha_v2' );
			$label    = 'recaptcha_v3' === $provider ? 'reCAPTCHA v3' : 'reCAPTCHA v2';
			return array(
				'available' => true,
				'global_on' => true,
				'url'       => $url,
				'message'   => sprintf(
					/* translators: %s: provider name */
					__( 'Using Nestform captcha (%s). Keys are managed under Forms → Integrations.', 'nestform' ),
					$label
				),
			);
		}

		$master   = (string) ( $s['captcha_enabled'] ?? '0' ) === '1';
		$has_keys = (string) ( $s['captcha_site_key'] ?? '' ) !== '' && (string) ( $s['captcha_secret_key'] ?? '' ) !== '';

		if ( ! $master ) {
			$msg = __( 'Captcha is off in Integrations. Enable it under Forms → Integrations.', 'nestform' );
		} elseif ( ! $has_keys ) {
			$msg = __( 'Add reCAPTCHA site/secret keys under Forms → Integrations.', 'nestform' );
		} else {
			$msg = __( 'Captcha is not ready yet. Check Forms → Integrations.', 'nestform' );
		}

		return array(
			'available' => true,
			'global_on' => false,
			'url'       => $url,
			'message'   => $msg,
		);
	}
}
