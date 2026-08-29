<?php
/**
 * CSV export for form entries.
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Export {

	const ACTION = 'nestform_export_csv';

	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
	}

	/**
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function url( $form_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'             => self::ACTION,
					'nestform_form_id' => (int) $form_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . (int) $form_id
		);
	}

	public static function handle() {
		$form_id = isset( $_GET['nestform_form_id'] ) ? (int) $_GET['nestform_form_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $form_id <= 0 || Nestform_Post_Type::POST_TYPE !== get_post_type( $form_id ) ) {
			wp_die( esc_html__( 'Invalid form.', 'nestform' ), 400 );
		}

		if ( ! current_user_can( 'edit_post', $form_id ) ) {
			wp_die( esc_html__( 'You do not have permission to export entries.', 'nestform' ), 403 );
		}

		check_admin_referer( self::ACTION . '_' . $form_id );

		$fields = Nestform_Form_Config::get_fields( $form_id );
		$keys   = array();
		$labels = array();
		foreach ( $fields as $field ) {
			if ( empty( $field['name'] ) || Nestform_Form_Config::is_layout_field( $field['type'] ) ) {
				continue;
			}
			if ( in_array( (string) $field['type'], array( 'hidden', 'password' ), true ) ) {
				continue;
			}
			$key = (string) $field['name'];
			if ( isset( $keys[ $key ] ) ) {
				continue;
			}
			$keys[ $key ]   = true;
			$labels[ $key ] = (string) ( $field['label'] !== '' ? $field['label'] : $key );
		}

		$query = new WP_Query(
			array(
				'post_type'      => Nestform_Submissions::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					array(
						'key'   => Nestform_Submissions::META_FORM,
						'value' => $form_id,
					),
				),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$filename = 'nestform-' . $form_id . '-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' );
		if ( false === $out ) {
			wp_die( esc_html__( 'Could not open export stream.', 'nestform' ), 500 );
		}

		// UTF-8 BOM for Excel.
		fwrite( $out, "\xEF\xBB\xBF" );

		$header = array_merge(
			array( 'entry_id', 'submitted_at', 'status', 'ip' ),
			array_keys( $labels )
		);
		fputcsv( $out, $header );

		foreach ( $query->posts as $entry_id ) {
			$entry_id = (int) $entry_id;
			$data     = get_post_meta( $entry_id, Nestform_Submissions::META_DATA, true );
			if ( ! is_array( $data ) ) {
				$data = array();
			}
			$status = Nestform_Submissions::get_status( $entry_id );
			$ip     = (string) get_post_meta( $entry_id, Nestform_Submissions::META_IP, true );
			$row    = array(
				$entry_id,
				get_post_time( 'c', true, $entry_id ),
				$status,
				$ip,
			);
			foreach ( array_keys( $labels ) as $key ) {
				$row[] = self::cell_value( isset( $data[ $key ] ) ? $data[ $key ] : '' );
			}
			fputcsv( $out, $row );
		}

		fclose( $out );
		exit;
	}

	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function cell_value( $value ) {
		/**
		 * Filter CSV cell value (Pro advanced field types).
		 *
		 * @param string|null $custom Custom string or null for default.
		 * @param mixed       $value  Stored value.
		 */
		$custom = apply_filters( 'nestform_export_cell_value', null, $value );
		if ( is_string( $custom ) ) {
			return $custom;
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}
		if ( is_array( $value ) && ! empty( $value['url'] ) ) {
			return (string) $value['url'];
		}
		if ( is_array( $value ) ) {
			$flat = array();
			foreach ( $value as $item ) {
				if ( is_array( $item ) && ! empty( $item['url'] ) ) {
					$flat[] = (string) $item['url'];
				} elseif ( is_scalar( $item ) ) {
					$flat[] = (string) $item;
				}
			}
			return implode( ', ', $flat );
		}
		return trim( (string) $value );
	}
}
