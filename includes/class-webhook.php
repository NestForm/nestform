<?php
/**
 * Outbound webhook on successful submit.
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Webhook {

	public static function init() {
		add_action( 'nestform_submitted', array( __CLASS__, 'dispatch' ), 20, 3 );
	}

	/**
	 * @param int                  $form_id  Form ID.
	 * @param array<string, mixed> $data     Sanitized data.
	 * @param int                  $entry_id Entry ID.
	 */
	public static function dispatch( $form_id, $data, $entry_id ) {
		$form_id  = (int) $form_id;
		$entry_id = (int) $entry_id;
		$settings = Nestform_Form_Config::get_settings( $form_id );

		if ( empty( $settings['webhook_enabled'] ) || '1' !== (string) $settings['webhook_enabled'] ) {
			return;
		}

		if ( ! class_exists( 'Nestform_Features' ) || ! Nestform_Features::can( Nestform_Features::WEBHOOK ) ) {
			return;
		}

		$url = isset( $settings['webhook_url'] ) ? esc_url_raw( (string) $settings['webhook_url'] ) : '';
		if ( $url === '' || ! Nestform_Form_Config::is_safe_outbound_url( $url ) ) {
			return;
		}

		$payload = array(
			'event'      => 'nestform.submitted',
			'form_id'    => $form_id,
			'form_title' => get_the_title( $form_id ),
			'entry_id'   => $entry_id,
			'submitted'  => gmdate( 'c' ),
			'site_url'   => home_url( '/' ),
			'data'       => self::normalize_data( $data ),
		);

		/**
		 * Filter webhook JSON payload before send.
		 *
		 * @param array $payload  Payload.
		 * @param int   $form_id  Form ID.
		 * @param array $data     Entry data.
		 * @param int   $entry_id Entry ID.
		 */
		$payload = (array) apply_filters( 'nestform_webhook_payload', $payload, $form_id, $data, $entry_id );

		$headers = array(
			'Content-Type' => 'application/json; charset=utf-8',
			'User-Agent'   => 'Nestform/' . NESTFORM_VERSION,
		);

		$secret = isset( $settings['webhook_secret'] ) ? (string) $settings['webhook_secret'] : '';
		if ( $secret !== '' ) {
			$headers['X-Nestform-Secret']     = $secret;
			$headers['X-Vite-Forms-Secret'] = $secret;
		}

		$args = array(
			'timeout'     => 8,
			'blocking'    => false,
			'redirection' => 0,
			'headers'     => $headers,
			'body'        => wp_json_encode( $payload ),
		);

		/**
		 * Filter wp_remote_post args for webhook.
		 *
		 * @param array $args     Request args.
		 * @param int   $form_id  Form ID.
		 * @param array $payload  Payload.
		 * @param int   $entry_id Entry ID.
		 */
		$args = (array) apply_filters( 'nestform_webhook_request_args', $args, $form_id, $payload, $entry_id );

		// Force safe transport regardless of filter mutations.
		$args['redirection'] = 0;
		if ( isset( $args['headers'] ) && ! is_array( $args['headers'] ) ) {
			$args['headers'] = $headers;
		}
		$url_send = $url;
		if ( isset( $args['url'] ) && is_string( $args['url'] ) && $args['url'] !== '' ) {
			$candidate = esc_url_raw( $args['url'] );
			$url_send  = ( $candidate !== '' && Nestform_Form_Config::is_safe_outbound_url( $candidate ) ) ? $candidate : $url;
			unset( $args['url'] );
		}
		if ( ! Nestform_Form_Config::is_safe_outbound_url( $url_send ) ) {
			return;
		}

		$response = wp_safe_remote_post( $url_send, $args );

		/**
		 * Fires after webhook dispatch attempt.
		 *
		 * @param array|WP_Error $response Response.
		 * @param int            $form_id  Form ID.
		 * @param int            $entry_id Entry ID.
		 * @param string         $url      Webhook URL.
		 */
		do_action( 'nestform_webhook_sent', $response, $form_id, $entry_id, $url_send );
	}

	/**
	 * @param array<string, mixed> $data Data.
	 * @return array<string, mixed>
	 */
	private static function normalize_data( array $data ) {
		$out = array();
		foreach ( $data as $key => $value ) {
			if ( is_bool( $value ) ) {
				$out[ $key ] = $value;
				continue;
			}
			if ( is_array( $value ) && ! empty( $value['url'] ) ) {
				$out[ $key ] = array(
					'url'  => (string) $value['url'],
					'name' => isset( $value['name'] ) ? (string) $value['name'] : '',
					'id'   => isset( $value['id'] ) ? (int) $value['id'] : 0,
				);
				continue;
			}
			if ( is_array( $value ) ) {
				$flat = array();
				foreach ( $value as $item ) {
					if ( is_scalar( $item ) ) {
						$flat[] = (string) $item;
					}
				}
				$out[ $key ] = $flat;
				continue;
			}
			$out[ $key ] = is_scalar( $value ) ? $value : '';
		}
		return $out;
	}
}
