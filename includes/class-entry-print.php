<?php
/**
 * Printable single entry view.
 *
 * Renders one entry as a standalone page (no WP admin chrome) for Print / Save as PDF.
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Entry_Print {

	const ACTION = 'nestform_print_entry';

	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'render' ) );
	}

	/**
	 * @param int $entry_id Entry ID.
	 * @return string
	 */
	public static function url( $entry_id ) {
		$entry_id = (int) $entry_id;
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION . '&entry_id=' . $entry_id ),
			self::ACTION . '_' . $entry_id
		);
	}

	/**
	 * Streams the printable page and exits.
	 */
	public static function render() {
		$entry_id = isset( $_GET['entry_id'] ) ? (int) $_GET['entry_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		check_admin_referer( self::ACTION . '_' . $entry_id );

		if ( $entry_id <= 0 || Nestform_Submissions::POST_TYPE !== get_post_type( $entry_id ) ) {
			wp_die( esc_html__( 'Entry not found.', 'nestform' ), 404 );
		}

		$form_id = (int) get_post_meta( $entry_id, Nestform_Submissions::META_FORM, true );
		if ( $form_id <= 0 || Nestform_Post_Type::POST_TYPE !== get_post_type( $form_id ) ) {
			wp_die( esc_html__( 'Form not found.', 'nestform' ), 404 );
		}

		if ( ! current_user_can( 'edit_post', $form_id ) ) {
			wp_die( esc_html__( 'You do not have permission to print this entry.', 'nestform' ), 403 );
		}

		$form  = get_post( $form_id );
		$entry = get_post( $entry_id );
		if ( ! $form || ! $entry ) {
			wp_die( esc_html__( 'Entry not found.', 'nestform' ), 404 );
		}

		$data = get_post_meta( $entry_id, Nestform_Submissions::META_DATA, true );
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		header( 'Content-Type: text/html; charset=utf-8' );
		nocache_headers();

		self::render_page( $form, $entry, $data );
		exit;
	}

	/**
	 * @param WP_Post              $form  Form post.
	 * @param WP_Post              $entry Entry post.
	 * @param array<string, mixed> $data  Payload.
	 */
	private static function render_page( $form, $entry, array $data ) {
		$form_title = $form->post_title !== '' ? $form->post_title : __( '(no title)', 'nestform' );
		$title      = sprintf(
			/* translators: 1: form title, 2: entry ID. */
			__( '%1$s — entry #%2$d', 'nestform' ),
			$form_title,
			(int) $entry->ID
		);

		$status     = Nestform_Submissions::get_status( $entry->ID );
		$labels     = Nestform_Submissions::status_labels();
		$status_lbl = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
		$submitted  = class_exists( 'Nestform_Settings' )
			? Nestform_Settings::format_entry_datetime( $entry )
			: get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry );
		$ip         = (string) get_post_meta( $entry->ID, Nestform_Submissions::META_IP, true );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $title ); ?></title>
	<style><?php echo self::styles(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed stylesheet, no user data. ?></style>
</head>
<body class="nestform-print-body">
	<div class="nestform-print-actions">
		<button type="button" class="nestform-print-actions__button" onclick="window.print()"><?php esc_html_e( 'Print', 'nestform' ); ?></button>
	</div>

	<article class="nestform-print">
		<header class="nestform-print__header">
			<p class="nestform-print__site"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
			<h1 class="nestform-print__title"><?php echo esc_html( $form_title ); ?></h1>
			<dl class="nestform-print__meta">
				<div class="nestform-print__meta-item">
					<dt class="nestform-print__meta-label"><?php esc_html_e( 'Entry', 'nestform' ); ?></dt>
					<dd class="nestform-print__meta-value">#<?php echo esc_html( (string) (int) $entry->ID ); ?></dd>
				</div>
				<div class="nestform-print__meta-item">
					<dt class="nestform-print__meta-label"><?php esc_html_e( 'Submitted', 'nestform' ); ?></dt>
					<dd class="nestform-print__meta-value"><?php echo esc_html( $submitted ); ?></dd>
				</div>
				<div class="nestform-print__meta-item">
					<dt class="nestform-print__meta-label"><?php esc_html_e( 'Status', 'nestform' ); ?></dt>
					<dd class="nestform-print__meta-value"><?php echo esc_html( $status_lbl ); ?></dd>
				</div>
				<?php if ( $ip !== '' ) : ?>
					<div class="nestform-print__meta-item">
						<dt class="nestform-print__meta-label"><?php esc_html_e( 'IP', 'nestform' ); ?></dt>
						<dd class="nestform-print__meta-value"><?php echo esc_html( $ip ); ?></dd>
					</div>
				<?php endif; ?>
			</dl>
		</header>

		<?php self::render_fields( $form->ID, $data ); ?>

		<footer class="nestform-print__footer">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: site name, 2: date the page was printed. */
					__( '%1$s — printed %2$s', 'nestform' ),
					get_bloginfo( 'name' ),
					date_i18n( get_option( 'date_format' ) )
				)
			);
			?>
		</footer>
	</article>
</body>
</html>
		<?php
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $data    Payload.
	 */
	private static function render_fields( $form_id, array $data ) {
		$fields = Nestform_Form_Config::get_fields( $form_id );
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}

		foreach ( $fields as $field ) {
			$type = (string) ( isset( $field['type'] ) ? $field['type'] : '' );

			if ( Nestform_Form_Config::is_layout_field( $type ) ) {
				self::render_layout_block( $field, $type );
				continue;
			}

			if ( in_array( $type, array( 'hidden', 'password' ), true ) ) {
				continue;
			}

			$name  = (string) ( isset( $field['name'] ) ? $field['name'] : '' );
			if ( $name === '' ) {
				continue;
			}

			$label    = (string) ( isset( $field['label'] ) && $field['label'] !== '' ? $field['label'] : $name );
			$value    = array_key_exists( $name, $data ) ? $data[ $name ] : '';
			$display  = Nestform_Export::cell_value( $value );
			$answered = '' !== $display;
			?>
			<div class="nestform-print__field">
				<p class="nestform-print__label"><?php echo esc_html( $label ); ?></p>
				<div class="nestform-print__value<?php echo $answered ? '' : ' nestform-print__value--empty'; ?>">
					<?php
					if ( $answered ) {
						echo esc_html( $display );
					} else {
						esc_html_e( 'Not answered', 'nestform' );
					}
					?>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * @param array  $field Field definition.
	 * @param string $type  Field type.
	 */
	private static function render_layout_block( array $field, $type ) {
		if ( in_array( $type, array( 'image', 'divider', 'spacer' ), true ) ) {
			return;
		}

		$label   = (string) ( isset( $field['label'] ) ? $field['label'] : '' );
		$content = (string) ( isset( $field['content'] ) ? $field['content'] : ( isset( $field['default'] ) ? $field['default'] : '' ) );

		if ( 'heading' === $type ) {
			if ( '' === $label && '' === $content ) {
				return;
			}
			?>
			<div class="nestform-print__section">
				<h2 class="nestform-print__section-title"><?php echo esc_html( $label !== '' ? $label : $content ); ?></h2>
			</div>
			<?php
			return;
		}

		if ( 'paragraph' === $type || 'html' === $type ) {
			$text = $content !== '' ? $content : $label;
			if ( '' === $text ) {
				return;
			}
			?>
			<div class="nestform-print__note"><?php echo wp_kses_post( wpautop( $text ) ); ?></div>
			<?php
		}
	}

	/**
	 * Inline print stylesheet (BEM: nestform-print*).
	 *
	 * @return string
	 */
	private static function styles() {
		return '
		:root { color-scheme: light; }
		* { box-sizing: border-box; }
		.nestform-print-body {
			margin: 0;
			padding: 32px 16px 64px;
			background: #f1f1f1;
			color: #1d2327;
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
			font-size: 15px;
			line-height: 1.6;
		}
		.nestform-print-actions { max-width: 720px; margin: 0 auto 16px; text-align: right; }
		.nestform-print-actions__button {
			padding: 8px 16px;
			border: 1px solid #2271b1;
			border-radius: 4px;
			background: #2271b1;
			color: #fff;
			font: inherit;
			font-size: 14px;
			cursor: pointer;
		}
		.nestform-print {
			max-width: 720px;
			margin: 0 auto;
			padding: 48px;
			background: #fff;
			box-shadow: 0 1px 3px rgba(0,0,0,.13);
		}
		.nestform-print__header { margin-bottom: 32px; padding-bottom: 24px; border-bottom: 2px solid #1d2327; }
		.nestform-print__site { margin: 0 0 4px; color: #646970; font-size: 13px; text-transform: uppercase; letter-spacing: .06em; }
		.nestform-print__title { margin: 0 0 16px; font-size: 26px; line-height: 1.25; }
		.nestform-print__meta { display: flex; flex-wrap: wrap; gap: 32px; margin: 0; }
		.nestform-print__meta-item { margin: 0; }
		.nestform-print__meta-label { color: #646970; font-size: 12px; text-transform: uppercase; letter-spacing: .06em; }
		.nestform-print__meta-value { margin: 2px 0 0; font-weight: 600; }
		.nestform-print__section { margin: 32px 0 16px; }
		.nestform-print__section-title { margin: 0 0 4px; padding-bottom: 6px; border-bottom: 1px solid #dcdcde; font-size: 18px; }
		.nestform-print__note { color: #50575e; }
		.nestform-print__field { margin: 0 0 20px; page-break-inside: avoid; break-inside: avoid; }
		.nestform-print__label { margin: 0 0 2px; color: #646970; font-size: 13px; font-weight: 600; }
		.nestform-print__value { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; }
		.nestform-print__value--empty { color: #8c8f94; font-style: italic; }
		.nestform-print__footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #dcdcde; color: #646970; font-size: 12px; }

		@media print {
			.nestform-print-body { padding: 0; background: #fff; font-size: 12pt; }
			.nestform-print-actions { display: none; }
			.nestform-print { max-width: none; padding: 0; box-shadow: none; }
			a { color: inherit; text-decoration: none; }
		}
		@page { margin: 18mm; }
		';
	}
}
