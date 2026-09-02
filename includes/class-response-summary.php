<?php
/**
 * Aggregates form entries into per-field summaries.
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Response_Summary {

	const CACHE_PREFIX     = 'nestform_summary_';
	const MAX_ENTRIES      = 10000;
	const TEXT_SAMPLE_SIZE = 5;

	public static function init() {
		add_action( 'nestform_submitted', array( __CLASS__, 'on_submitted' ), 10, 3 );
	}

	/**
	 * Invalidate summary cache after a new entry.
	 *
	 * @param int                  $form_id  Form ID.
	 * @param array<string, mixed> $data     Entry payload.
	 * @param int                  $entry_id Entry ID.
	 */
	public static function on_submitted( $form_id, $data, $entry_id ) {
		unset( $data, $entry_id );
		self::invalidate( (int) $form_id );
	}

	/**
	 * @param int $form_id Form ID.
	 */
	public static function invalidate( $form_id ) {
		$form_id = (int) $form_id;
		if ( $form_id <= 0 ) {
			return;
		}
		delete_transient( self::CACHE_PREFIX . $form_id );
	}

	/**
	 * Summary screen URL for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return string
	 */
	public static function url( $form_id ) {
		return Nestform_Submissions::hub_url(
			array(
				'form_id'  => (int) $form_id,
				'summary'  => '1',
			)
		);
	}

	/**
	 * Whether the current user may view the summary for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return bool
	 */
	public static function user_can_view( $form_id ) {
		$form_id = (int) $form_id;
		if ( $form_id <= 0 ) {
			return false;
		}
		if ( class_exists( 'Nestform_Capabilities' ) && Nestform_Capabilities::can_view_entries() ) {
			return Nestform_Submissions::user_can_manage_form_entries( $form_id )
				|| current_user_can( 'edit_post', $form_id );
		}
		return Nestform_Submissions::user_can_manage_form_entries( $form_id );
	}

	/**
	 * Build or return cached summary for a form.
	 *
	 * @param int $form_id Form ID.
	 * @return array{total:int,capped:bool,entry_count:int,fields:array<int,array<string,mixed>>}
	 */
	public static function summarize( $form_id ) {
		$form_id = (int) $form_id;
		$count   = class_exists( 'Nestform_Submissions' )
			? (int) Nestform_Submissions::count_entries(
				array(
					'form_id'           => $form_id,
					'skip_access_check' => true,
				)
			)
			: 0;

		$cache_key = self::CACHE_PREFIX . $form_id;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached )
			&& isset( $cached['entry_count'], $cached['fields'], $cached['total'] )
			&& (int) $cached['entry_count'] === $count
		) {
			return $cached;
		}

		$summary                 = self::build_summary( $form_id );
		$summary['entry_count']  = $count;
		set_transient( $cache_key, $summary, HOUR_IN_SECONDS );

		return $summary;
	}

	/**
	 * @param int $form_id Form ID.
	 * @return array{total:int,capped:bool,fields:array<int,array<string,mixed>>}
	 */
	private static function build_summary( $form_id ) {
		$fields   = Nestform_Form_Config::get_fields( $form_id );
		$payloads = array();
		$paged    = 1;
		$pages    = 1;

		do {
			$result = Nestform_Submissions::query_entries(
				array(
					'form_id'           => $form_id,
					'limit'             => 100,
					'paged'             => $paged,
					'skip_access_check' => true,
				)
			);
			$posts = isset( $result['posts'] ) && is_array( $result['posts'] ) ? $result['posts'] : array();
			$pages = isset( $result['pages'] ) ? max( 1, (int) $result['pages'] ) : 1;

			foreach ( $posts as $post ) {
				$data       = get_post_meta( (int) $post->ID, Nestform_Submissions::META_DATA, true );
				$payloads[] = is_array( $data ) ? $data : array();
				if ( count( $payloads ) >= self::MAX_ENTRIES ) {
					break 2;
				}
			}
			++$paged;
		} while ( $paged <= $pages );

		$total = count( $payloads );

		$out_fields = array();
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$type = (string) ( $field['type'] ?? '' );
			$name = (string) ( $field['name'] ?? '' );
			if ( $name === '' || Nestform_Form_Config::is_layout_field( $type ) ) {
				continue;
			}
			if ( in_array( $type, array( 'hidden', 'password', 'file', 'html', 'submit' ), true ) ) {
				continue;
			}
			$out_fields[] = self::summarize_field( $field, $payloads );
		}

		return array(
			'total'  => $total,
			'capped' => $total >= self::MAX_ENTRIES,
			'fields' => $out_fields,
		);
	}

	/**
	 * @param array<string, mixed>           $field    Field definition.
	 * @param array<int, array<string,mixed>> $payloads Entry payloads, newest first.
	 * @return array<string, mixed>
	 */
	private static function summarize_field( array $field, array $payloads ) {
		$name   = (string) ( $field['name'] ?? '' );
		$type   = (string) ( $field['type'] ?? 'text' );
		$label  = (string) ( $field['label'] ?? '' );
		$values = array();

		foreach ( $payloads as $payload ) {
			if ( ! array_key_exists( $name, $payload ) ) {
				continue;
			}
			$value = $payload[ $name ];
			if ( '' === $value || null === $value || array() === $value ) {
				continue;
			}
			$values[] = $value;
		}

		$base = array(
			'label'    => $label !== '' ? wp_strip_all_tags( $label ) : $name,
			'type'     => $type,
			'answered' => count( $values ),
			'name'     => $name,
		);

		switch ( $type ) {
			case 'select':
			case 'radio':
			case 'checkboxes':
			case 'checkbox':
				$options = isset( $field['options'] ) && is_array( $field['options'] ) ? $field['options'] : array();
				return array_merge(
					$base,
					array(
						'kind'   => 'choice',
						'counts' => self::count_choices( $values, $options ),
					)
				);

			case 'number':
			case 'range':
				return array_merge(
					$base,
					array( 'kind' => 'number' ),
					self::number_stats( $values )
				);

			case 'text':
			case 'email':
			case 'textarea':
			case 'tel':
			case 'url':
			default:
				return array_merge(
					$base,
					array(
						'kind'    => 'text',
						'samples' => self::text_samples( $values ),
					)
				);
		}
	}

	/**
	 * @param array<int, mixed> $values  Submitted values.
	 * @param array<int, mixed> $options Configured options.
	 * @return array<string, int>
	 */
	private static function count_choices( array $values, array $options ) {
		$labels = array();
		foreach ( $options as $opt ) {
			if ( is_array( $opt ) ) {
				$labels[] = (string) ( $opt['label'] ?? $opt['value'] ?? '' );
			} else {
				$labels[] = (string) $opt;
			}
		}
		$labels = array_values( array_filter( array_map( 'strval', $labels ), static function ( $v ) {
			return $v !== '';
		} ) );

		$counts = array();
		foreach ( $labels as $label ) {
			$counts[ $label ] = 0;
		}
		$extra = array();

		foreach ( $values as $value ) {
			foreach ( (array) $value as $choice ) {
				if ( is_array( $choice ) ) {
					$choice = (string) ( $choice['label'] ?? $choice['value'] ?? '' );
				} else {
					$choice = (string) $choice;
				}
				if ( $choice === '' ) {
					continue;
				}
				if ( array_key_exists( $choice, $counts ) ) {
					++$counts[ $choice ];
				} else {
					if ( ! isset( $extra[ $choice ] ) ) {
						$extra[ $choice ] = 0;
					}
					++$extra[ $choice ];
				}
			}
		}

		arsort( $extra );

		return $counts + $extra;
	}

	/**
	 * @param array<int, mixed> $values Submitted values.
	 * @return array{min?:float,max?:float,avg?:float}
	 */
	private static function number_stats( array $values ) {
		$numbers = array();
		foreach ( $values as $value ) {
			if ( is_numeric( $value ) ) {
				$numbers[] = (float) $value;
			}
		}
		if ( array() === $numbers ) {
			return array();
		}
		return array(
			'min' => min( $numbers ),
			'max' => max( $numbers ),
			'avg' => array_sum( $numbers ) / count( $numbers ),
		);
	}

	/**
	 * @param array<int, mixed> $values Submitted values, newest first.
	 * @return array<int, string>
	 */
	private static function text_samples( array $values ) {
		$samples = array();
		foreach ( $values as $value ) {
			$samples[] = is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
			if ( count( $samples ) >= self::TEXT_SAMPLE_SIZE ) {
				break;
			}
		}
		return $samples;
	}

	/**
	 * Render the summary admin screen (called from hub when summary=1).
	 *
	 * @return bool True if rendered.
	 */
	public static function render_summary_screen() {
		$form_id = 0;
		if ( isset( $_GET['form_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$form_id = (int) $_GET['form_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( isset( $_GET['nestform_form_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$form_id = (int) $_GET['nestform_form_id']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( $form_id <= 0 || Nestform_Post_Type::POST_TYPE !== get_post_type( $form_id ) ) {
			return false;
		}

		$can = false;
		if ( class_exists( 'Nestform_Capabilities' ) ) {
			$can = Nestform_Capabilities::can_view_entries() || current_user_can( 'edit_posts' );
		} else {
			$can = current_user_can( 'edit_posts' );
		}
		if ( ! $can || ! self::user_can_view( $form_id ) ) {
			wp_die( esc_html__( 'You do not have permission to view this summary.', 'nestform' ), '', array( 'response' => 403 ) );
		}

		$form    = get_post( $form_id );
		$summary = self::summarize( $form_id );
		$title   = ( $form && $form->post_title !== '' ) ? $form->post_title : __( 'Form', 'nestform' );

		$actions  = '<a class="nestform-btn nestform-btn--outline" href="' . esc_url( Nestform_Submissions::list_url( $form_id ) ) . '">';
		$actions .= nestform_admin_icon_html( 'back' ) . ' ' . esc_html__( 'Form inbox', 'nestform' );
		$actions .= '</a>';
		if ( class_exists( 'Nestform_Export' ) ) {
			$actions .= ' ' . Nestform_Export::dropdown_html(
				$form_id,
				array(
					'variant' => 'outline',
				)
			);
		}

		?>
		<div class="wrap nestform-hub nestform-summary">
			<?php
			nestform_render_page_head(
				array(
					'title'        => sprintf(
						/* translators: %s: form title */
						__( 'Summary — %s', 'nestform' ),
						$title
					),
					'description'  => __( 'Aggregated answers across entries for this form.', 'nestform' ),
					'actions_html' => $actions,
					'icon'         => 'analytics',
				)
			);
			?>

			<p class="nestform-summary__meta">
				<?php
				printf(
					/* translators: %d: number of entries summarized */
					esc_html( _n( 'Based on %d entry.', 'Based on %d entries.', (int) $summary['total'], 'nestform' ) ),
					(int) $summary['total']
				);
				if ( ! empty( $summary['capped'] ) ) {
					echo ' ';
					esc_html_e( 'Showing the newest 10,000 entries only.', 'nestform' );
				}
				?>
			</p>

			<?php if ( array() === $summary['fields'] ) : ?>
				<p class="description"><?php esc_html_e( 'No summarizable fields on this form yet.', 'nestform' ); ?></p>
			<?php else : ?>
				<div class="nestform-summary__grid">
					<?php foreach ( $summary['fields'] as $field_summary ) : ?>
						<?php self::render_field_card( $field_summary ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return true;
	}

	/**
	 * @param array<string, mixed> $field Field summary row.
	 */
	private static function render_field_card( array $field ) {
		$kind     = (string) ( $field['kind'] ?? 'text' );
		$label    = (string) ( $field['label'] ?? '' );
		$answered = (int) ( $field['answered'] ?? 0 );
		$type     = (string) ( $field['type'] ?? '' );
		?>
		<div class="nestform-summary__card nestform-admin__surface">
			<div class="nestform-summary__card-head">
				<h3 class="nestform-summary__card-title"><?php echo esc_html( $label ); ?></h3>
				<p class="nestform-summary__card-meta">
					<?php
					echo esc_html( $type );
					echo ' · ';
					printf(
						/* translators: %d: answered count */
						esc_html( _n( '%d answer', '%d answers', $answered, 'nestform' ) ),
						$answered
					);
					?>
				</p>
			</div>
			<div class="nestform-summary__card-body">
				<?php if ( 0 === $answered ) : ?>
					<p class="description"><?php esc_html_e( 'No answers yet.', 'nestform' ); ?></p>
				<?php elseif ( 'choice' === $kind ) : ?>
					<?php
					$counts = isset( $field['counts'] ) && is_array( $field['counts'] ) ? $field['counts'] : array();
					$max    = $counts ? max( array_map( 'intval', $counts ) ) : 0;
					?>
					<ul class="nestform-summary__bars">
						<?php foreach ( $counts as $option => $count ) : ?>
							<?php
							$count = (int) $count;
							$pct   = $max > 0 ? round( ( $count / $max ) * 100 ) : 0;
							?>
							<li class="nestform-summary__bar">
								<span class="nestform-summary__bar-label"><?php echo esc_html( (string) $option ); ?></span>
								<span class="nestform-summary__bar-track" aria-hidden="true">
									<span class="nestform-summary__bar-fill" style="width:<?php echo esc_attr( (string) $pct ); ?>%"></span>
								</span>
								<span class="nestform-summary__bar-count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php elseif ( 'number' === $kind ) : ?>
					<?php if ( isset( $field['min'], $field['max'], $field['avg'] ) ) : ?>
						<dl class="nestform-summary__stats">
							<div>
								<dt><?php esc_html_e( 'Min', 'nestform' ); ?></dt>
								<dd><?php echo esc_html( self::format_number( (float) $field['min'] ) ); ?></dd>
							</div>
							<div>
								<dt><?php esc_html_e( 'Avg', 'nestform' ); ?></dt>
								<dd><?php echo esc_html( self::format_number( (float) $field['avg'] ) ); ?></dd>
							</div>
							<div>
								<dt><?php esc_html_e( 'Max', 'nestform' ); ?></dt>
								<dd><?php echo esc_html( self::format_number( (float) $field['max'] ) ); ?></dd>
							</div>
						</dl>
					<?php else : ?>
						<p class="description"><?php esc_html_e( 'No numeric answers yet.', 'nestform' ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<?php
					$samples = isset( $field['samples'] ) && is_array( $field['samples'] ) ? $field['samples'] : array();
					?>
					<ul class="nestform-summary__samples">
						<?php foreach ( $samples as $sample ) : ?>
							<li><?php echo esc_html( (string) $sample ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * @param float $n Number.
	 * @return string
	 */
	private static function format_number( $n ) {
		if ( abs( $n - round( $n ) ) < 0.0001 ) {
			return number_format_i18n( (int) round( $n ) );
		}
		return number_format_i18n( $n, 2 );
	}
}
