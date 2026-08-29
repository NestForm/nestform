<?php
/**
 * HTML email template helpers (Pro email designer).
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Mail_Html {

	/**
	 * Layout presets for the Mail tab.
	 *
	 * @return array<string, array{label:string,html:string}>
	 */
	public static function presets() {
		$site = esc_html( get_bloginfo( 'name' ) );
		return array(
			'simple'  => array(
				'label' => __( 'Simple', 'nestform' ),
				'html'  => '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:#1f2937">'
					. '<p><strong>New submission from {form_title}</strong></p>'
					. '<p>{all_fields}</p>'
					. '</div>',
			),
			'branded' => array(
				'label' => __( 'Branded', 'nestform' ),
				'html'  => '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 0">'
					. '<tr><td align="center">'
					. '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden">'
					. '<tr><td style="background:#111827;color:#ffffff;padding:20px 28px;font-family:Arial,Helvetica,sans-serif;font-size:18px;font-weight:700">' . $site . '</td></tr>'
					. '<tr><td style="padding:28px;font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1f2937">'
					. '<p style="margin:0 0 12px">New submission from <strong>{form_title}</strong></p>'
					. '<div style="border-top:1px solid #e5e7eb;padding-top:16px">{all_fields}</div>'
					. '</td></tr>'
					. '<tr><td style="padding:16px 28px;background:#f9fafb;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#6b7280">Form ID: {form_id}</td></tr>'
					. '</table></td></tr></table>',
			),
			'confirm' => array(
				'label' => __( 'Visitor thank-you', 'nestform' ),
				'html'  => '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#1f2937;max-width:560px">'
					. '<p>Hi {name},</p>'
					. '<p>Thanks for contacting us. We received your message and will get back to you soon.</p>'
					. '<p style="color:#6b7280;font-size:13px">— {form_title}</p>'
					. '</div>',
			),
		);
	}

	/**
	 * Allowed HTML for email body templates.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_tags() {
		$base = class_exists( 'Nestform_Form_Config' ) ? Nestform_Form_Config::html_allowed_tags() : array();
		$extra = array(
			'table' => array(
				'class'       => true,
				'style'       => true,
				'width'       => true,
				'cellpadding' => true,
				'cellspacing' => true,
				'role'        => true,
				'border'      => true,
				'align'       => true,
			),
			'tbody' => array( 'class' => true, 'style' => true ),
			'thead' => array( 'class' => true, 'style' => true ),
			'tr'    => array( 'class' => true, 'style' => true ),
			'td'    => array(
				'class'   => true,
				'style'   => true,
				'width'   => true,
				'align'   => true,
				'valign'  => true,
				'colspan' => true,
				'rowspan' => true,
			),
			'th'    => array(
				'class'   => true,
				'style'   => true,
				'width'   => true,
				'align'   => true,
				'colspan' => true,
			),
			'p'     => array( 'class' => true, 'style' => true ),
			'div'   => array( 'class' => true, 'style' => true ),
			'span'  => array( 'class' => true, 'style' => true ),
			'h1'    => array( 'class' => true, 'style' => true ),
			'h2'    => array( 'class' => true, 'style' => true ),
			'h3'    => array( 'class' => true, 'style' => true ),
			'h4'    => array( 'class' => true, 'style' => true ),
			'img'   => array(
				'src'    => true,
				'alt'    => true,
				'class'  => true,
				'style'  => true,
				'width'  => true,
				'height' => true,
			),
			'a'     => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
				'class'  => true,
				'style'  => true,
			),
			'ul'    => array( 'class' => true, 'style' => true ),
			'ol'    => array( 'class' => true, 'style' => true ),
			'li'    => array( 'class' => true, 'style' => true ),
			'br'    => array(),
			'hr'    => array( 'class' => true, 'style' => true ),
			'strong'=> array( 'style' => true ),
			'em'    => array( 'style' => true ),
			'b'     => array(),
			'i'     => array(),
		);
		return array_merge( $base, $extra );
	}

	/**
	 * Sanitize mail body (HTML when designer enabled).
	 *
	 * @param string $body     Raw body.
	 * @param bool   $as_html  Allow HTML.
	 * @return string
	 */
	public static function sanitize_body( $body, $as_html ) {
		$body = (string) $body;
		if ( ! $as_html ) {
			return sanitize_textarea_field( $body );
		}
		return wp_kses( $body, self::allowed_tags() );
	}

	/**
	 * Whether a body should be sent as HTML.
	 *
	 * @param string               $body Body after placeholders.
	 * @param array<string, string> $mail Mail config.
	 * @return bool
	 */
	public static function is_html_mail( $body, array $mail ) {
		if ( ! empty( $mail['html_enabled'] ) && '1' === (string) $mail['html_enabled'] ) {
			return true;
		}
		return (bool) preg_match( '/<\/?(?:p|div|table|br|h[1-6]|span|a|img|ul|ol|li)\b/i', (string) $body );
	}

	/**
	 * Wrap plain-text all_fields for HTML emails.
	 *
	 * @param array<string, mixed> $data Data.
	 * @param bool                 $html HTML mode.
	 * @return string
	 */
	public static function format_all_fields( array $data, $html ) {
		$lines = array();
		foreach ( $data as $key => $value ) {
			$display = self::format_value( $value, $html );
			if ( $html ) {
				$lines[] = '<tr><td style="padding:6px 12px 6px 0;vertical-align:top;font-weight:600;color:#374151">'
					. esc_html( (string) $key )
					. '</td><td style="padding:6px 0;vertical-align:top;color:#1f2937">'
					. $display
					. '</td></tr>';
			} else {
				$lines[] = $key . ': ' . wp_strip_all_tags( $display );
			}
		}
		if ( $html ) {
			return '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse">'
				. implode( '', $lines )
				. '</table>';
		}
		return implode( "\n", $lines );
	}

	/**
	 * @param mixed $value Value.
	 * @param bool  $html  HTML.
	 * @return string
	 */
	public static function format_value( $value, $html = false ) {
		if ( is_bool( $value ) ) {
			$text = $value ? 'yes' : 'no';
			return $html ? esc_html( $text ) : $text;
		}
		if ( is_array( $value ) && ! empty( $value['url'] ) ) {
			$name = ! empty( $value['name'] ) ? (string) $value['name'] : 'file';
			$url  = (string) $value['url'];
			if ( $html ) {
				return '<a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>';
			}
			return $name . ' (' . $url . ')';
		}
		// Repeater rows (list of associative arrays).
		if ( is_array( $value ) && self::is_list_of_maps( $value ) ) {
			$parts = array();
			foreach ( $value as $i => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$inner = array();
				foreach ( $row as $k => $v ) {
					$inner[] = $k . ': ' . wp_strip_all_tags( self::format_value( $v, false ) );
				}
				$parts[] = '#' . ( (int) $i + 1 ) . ' — ' . implode( '; ', $inner );
			}
			$text = implode( $html ? "\n" : "\n", $parts );
			return $html ? nl2br( esc_html( $text ), false ) : $text;
		}
		if ( is_array( $value ) ) {
			$flat = array();
			foreach ( $value as $item ) {
				$flat[] = wp_strip_all_tags( self::format_value( $item, false ) );
			}
			$text = implode( ', ', $flat );
			return $html ? esc_html( $text ) : $text;
		}
		$text = (string) $value;
		return $html ? nl2br( esc_html( $text ), false ) : $text;
	}

	/**
	 * @param array $value Value.
	 * @return bool
	 */
	private static function is_list_of_maps( array $value ) {
		if ( array() === $value ) {
			return false;
		}
		foreach ( $value as $item ) {
			if ( ! is_array( $item ) || isset( $item['url'] ) ) {
				return false;
			}
			// Associative row.
			$keys = array_keys( $item );
			if ( $keys !== range( 0, count( $item ) - 1 ) && array() !== $item ) {
				return true;
			}
			if ( array() === $item ) {
				continue;
			}
			return true;
		}
		return false;
	}

	/**
	 * Expand {#repeater}...{/repeater} loops (supports nesting).
	 *
	 * @param string               $template Template.
	 * @param array<string, mixed> $data     Data context.
	 * @param bool                 $html     HTML mode.
	 * @return string
	 */
	public static function expand_loops( $template, array $data, $html = false ) {
		$template = (string) $template;
		$max      = 20;
		while ( $max-- > 0 && preg_match( '/\{#([a-zA-Z0-9_]+)\}/', $template ) ) {
			$template = preg_replace_callback(
				'/\{#([a-zA-Z0-9_]+)\}([\s\S]*?)\{\/\1\}/',
				static function ( $m ) use ( $data, $html ) {
					$key  = $m[1];
					$inner = $m[2];
					$rows = isset( $data[ $key ] ) && is_array( $data[ $key ] ) ? $data[ $key ] : array();
					if ( ! self::is_list_of_maps( $rows ) && ! ( is_array( $rows ) && array() !== $rows && isset( $rows[0] ) && is_array( $rows[0] ) ) ) {
						return '';
					}
					$out = '';
					foreach ( $rows as $i => $row ) {
						if ( ! is_array( $row ) ) {
							continue;
						}
						$ctx = array_merge( $data, $row );
						$ctx['_index'] = (string) ( (int) $i + 1 );
						$chunk = self::expand_loops( $inner, $ctx, $html );
						$chunk = self::replace_simple_tokens( $chunk, $ctx, $html );
						$out  .= $chunk;
					}
					return $out;
				},
				$template,
				1
			);
			if ( ! is_string( $template ) ) {
				break;
			}
		}
		return is_string( $template ) ? $template : '';
	}

	/**
	 * Replace {field} tokens (non-loop).
	 *
	 * @param string               $template Template.
	 * @param array<string, mixed> $data     Data.
	 * @param bool                 $html     HTML.
	 * @return string
	 */
	public static function replace_simple_tokens( $template, array $data, $html = false ) {
		foreach ( $data as $key => $value ) {
			if ( ! is_string( $key ) && ! is_int( $key ) ) {
				continue;
			}
			$key_s = (string) $key;
			if ( '{' === $key_s || '' === $key_s ) {
				continue;
			}
			$display = self::format_value( $value, $html );
			$template = str_replace( '{' . $key_s . '}', $display, $template );
		}
		return $template;
	}
}
