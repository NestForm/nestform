<?php
/**
 * Front renderer + shortcode.
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Renderer {

	/** @var bool */
	private static $assets_queued = false;

	public static function init() {
		add_shortcode( 'nestform', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'   => '',
				'slug' => '',
			),
			$atts,
			'nestform'
		);

		$form_id = Nestform_Form_Config::resolve_form_id( $atts );
		if ( $form_id <= 0 ) {
			if ( current_user_can( 'edit_posts' ) ) {
				return '<p class="nest-form nest-form--missing">' . esc_html__( 'Nestform: form not found.', 'nestform' ) . '</p>';
			}
			return '';
		}

		return self::render( $form_id );
	}

	/**
	 * @param int                  $form_id Form ID.
	 * @param array<string, mixed> $args    Optional render args. `preview` (bool) marks admin preview submits.
	 * @return string
	 */
	public static function render( $form_id, $args = array() ) {
		$form_id = (int) $form_id;
		$args    = wp_parse_args(
			$args,
			array(
				'preview' => false,
			)
		);
		$is_preview = ! empty( $args['preview'] );
		$config  = Nestform_Form_Config::get( $form_id );

		/**
		 * After form config is loaded for render (Pro may detect field types).
		 *
		 * @param int                  $form_id Form ID.
		 * @param array<string, mixed> $config  Config.
		 */
		do_action( 'nestform_render_form', $form_id, $config );

		self::enqueue_front();

		$uid      = 'nest-form-' . $form_id . '-' . wp_unique_id();
		$settings = $config['settings'];
		$settings = Nestform_Form_Config::apply_feature_gates( $settings );
		$messages = $config['messages'];
		$fields   = $config['fields'];
		$steps_on = ( '1' === (string) ( $settings['enable_steps'] ?? '0' ) );
		$steps    = $steps_on ? Nestform_Form_Config::collect_steps( $fields ) : array( 1 );
		$labels   = Nestform_Form_Config::parse_step_labels( (string) ( $settings['step_labels'] ?? '' ) );
		$steps_count = count( $steps );
		$form_class  = 'nest-form' . ( $steps_on && $steps_count > 1 ? ' nest-form--steps' : '' );
		$style_classes = Nestform_Form_Config::style_form_classes( $settings );
		if ( array() !== $style_classes ) {
			$form_class .= ' ' . implode( ' ', $style_classes );
		}
		$style_inline = Nestform_Form_Config::style_inline_css( $settings );
		$custom_css  = Nestform_Form_Config::scope_custom_css(
			(string) ( $settings['style_custom_css'] ?? '' ),
			'#' . $uid
		);
		$has_file    = Nestform_Form_Config::has_file_field( $fields );

		ob_start();
		if ( $custom_css !== '' ) :
			?>
			<style id="<?php echo esc_attr( $uid ); ?>-custom-css"><?php echo $custom_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized CSS only. ?></style>
			<?php
		endif;
		?>
		<form
			class="<?php echo esc_attr( $form_class ); ?>"
			<?php if ( $style_inline !== '' ) : ?>
				style="<?php echo esc_attr( $style_inline ); ?>"
			<?php endif; ?>
			id="<?php echo esc_attr( $uid ); ?>"
			method="post"
			action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
			<?php echo $has_file ? ' enctype="multipart/form-data"' : ''; ?>
			novalidate
			data-nest-form
			data-form-id="<?php echo esc_attr( (string) $form_id ); ?>"
			<?php if ( $is_preview ) : ?>
				data-nest-form-preview="1"
			<?php endif; ?>
			data-style-skin="<?php echo esc_attr( (string) ( $settings['style_skin'] ?? 'theme' ) ); ?>"
			data-redirect="<?php echo esc_attr( $settings['redirect_url'] ); ?>"
			data-success-display="<?php echo esc_attr( (string) ( $settings['success_display'] ?? 'inline' ) ); ?>"
			data-msg-required="<?php echo esc_attr( (string) ( $messages['required'] ?? '' ) ); ?>"
			data-msg-invalid-email="<?php echo esc_attr( (string) ( $messages['invalid_email'] ?? '' ) ); ?>"
			data-msg-invalid-tel="<?php echo esc_attr( (string) ( $messages['invalid_tel'] ?? '' ) ); ?>"
			data-msg-invalid-url="<?php echo esc_attr( (string) ( $messages['invalid_url'] ?? '' ) ); ?>"
			data-msg-invalid-number="<?php echo esc_attr( (string) ( $messages['invalid_number'] ?? '' ) ); ?>"
			data-msg-invalid-date="<?php echo esc_attr( (string) ( $messages['invalid_date'] ?? '' ) ); ?>"
			data-msg-invalid-time="<?php echo esc_attr( (string) ( $messages['invalid_time'] ?? '' ) ); ?>"
			data-msg-invalid-file="<?php echo esc_attr( (string) ( $messages['invalid_file'] ?? '' ) ); ?>"
			data-msg-file-too-large="<?php echo esc_attr( (string) ( $messages['file_too_large'] ?? '' ) ); ?>"
			data-msg-too-many-files="<?php echo esc_attr( (string) ( $messages['too_many_files'] ?? '' ) ); ?>"
			data-error-generic="<?php echo esc_attr( (string) ( $messages['error_generic'] ?? '' ) ); ?>"
			<?php if ( $steps_on && $steps_count > 1 ) : ?>
				data-nest-form-steps="1"
				data-steps="<?php echo esc_attr( wp_json_encode( array_values( $steps ) ) ); ?>"
				data-step-index="0"
				data-branch-rules="<?php echo esc_attr( wp_json_encode( Nestform_Form_Config::parse_branch_rules( (string) ( $settings['branch_rules'] ?? '' ) ) ) ); ?>"
			<?php endif; ?>
			<?php
			$form_extra_attrs = (array) apply_filters(
				'nestform_form_html_attrs',
				array(),
				$form_id,
				array(
					'fields'   => $fields,
					'messages' => $messages,
					'settings' => $settings,
				)
			);
			foreach ( $form_extra_attrs as $attr_key => $attr_val ) {
				if ( ! is_string( $attr_key ) || $attr_key === '' ) {
					continue;
				}
				echo ' ' . esc_attr( $attr_key ) . '="' . esc_attr( (string) $attr_val ) . '"';
			}
			?>
		>
			<input type="hidden" name="action" value="nestform_submit" />
			<?php if ( ! empty( $settings['quiz_timer_seconds'] ) && (int) $settings['quiz_timer_seconds'] > 0 ) : ?>
				<input type="hidden" name="nestform_quiz_started_at" value="<?php echo esc_attr( (string) time() ); ?>" data-nestform-quiz-started />
			<?php endif; ?>
			<div class="nest-form__result" data-nest-form-result hidden></div>
			<input type="hidden" name="form_id" value="<?php echo esc_attr( (string) $form_id ); ?>" />
			<input type="hidden" name="nestform_loaded_at" value="<?php echo esc_attr( (string) time() ); ?>" />
			<input type="hidden" name="nestform_visited_steps" value="1" data-nest-form-visited-steps />
			<?php wp_nonce_field( 'nestform_submit_' . $form_id, 'nestform_nonce' ); ?>
			<?php if ( $is_preview ) : ?>
				<input type="hidden" name="nestform_preview" value="1" />
				<?php wp_nonce_field( 'nestform_preview_submit_' . $form_id, 'nestform_preview_nonce' ); ?>
			<?php endif; ?>
			<div class="nest-form__honeypot" aria-hidden="true">
				<label>
					<span><?php esc_html_e( 'Leave empty', 'nestform' ); ?></span>
					<input type="text" name="nestform_hp" value="" tabindex="-1" autocomplete="off" />
				</label>
			</div>

			<?php if ( $steps_on && $steps_count > 1 ) : ?>
				<div class="nest-form__progress" data-nest-form-progress role="navigation" aria-label="<?php esc_attr_e( 'Form steps', 'nestform' ); ?>">
					<div class="nest-form__progress-bar" aria-hidden="true">
						<span class="nest-form__progress-fill" data-nest-form-progress-fill style="width: <?php echo esc_attr( (string) ( 100 / $steps_count ) ); ?>%"></span>
					</div>
					<ol class="nest-form__progress-steps">
						<?php foreach ( $steps as $i => $step_num ) : ?>
							<?php
							$step_label = isset( $labels[ $i + 1 ] ) ? $labels[ $i + 1 ] : sprintf(
								/* translators: %d: step number */
								__( 'Step %d', 'nestform' ),
								$i + 1
							);
							?>
							<li class="nest-form__progress-step<?php echo 0 === $i ? ' is-active' : ''; ?>" data-nest-form-progress-step data-step="<?php echo esc_attr( (string) $step_num ); ?>">
								<span class="nest-form__progress-index"><?php echo esc_html( (string) ( $i + 1 ) ); ?></span>
								<span class="nest-form__progress-label"><?php echo esc_html( $step_label ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endif; ?>

			<div class="nest-form__fields">
				<?php if ( $steps_on && $steps_count > 1 ) : ?>
					<?php foreach ( $steps as $i => $step_num ) : ?>
						<?php
						$step_label = isset( $labels[ $i + 1 ] ) ? $labels[ $i + 1 ] : sprintf(
							/* translators: %d: step number */
							__( 'Step %d', 'nestform' ),
							$i + 1
						);
						?>
						<div
							class="nest-form__step<?php echo 0 === $i ? ' is-active' : ''; ?>"
							data-nest-form-step-panel
							data-step="<?php echo esc_attr( (string) $step_num ); ?>"
							<?php echo 0 === $i ? '' : ' hidden'; ?>
						>
							<p class="nest-form__step-title"><?php echo esc_html( $step_label ); ?></p>
							<div class="nest-form__step-fields">
								<?php foreach ( $fields as $field ) : ?>
									<?php
									$field_step = isset( $field['step'] ) ? (int) $field['step'] : 1;
									if ( $field_step !== (int) $step_num ) {
										continue;
									}
									echo self::render_field( $field, $uid, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									?>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				<?php else : ?>
					<?php foreach ( $fields as $field ) : ?>
						<?php echo self::render_field( $field, $uid, false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
			<?php
			$captcha_html = (string) apply_filters( 'nestform_captcha_html', '', $form_id, $config );
			if ( $captcha_html !== '' ) :
				$captcha_provider = '';
				if ( class_exists( 'Nestform_Captcha' ) && Nestform_Captcha::enabled_for_form( $form_id, $config ) ) {
					$captcha_provider = Nestform_Captcha::provider();
				}
				$captcha_class = 'nest-form__captcha';
				if ( 'recaptcha_v3' === $captcha_provider ) {
					$captcha_class .= ' nest-form__captcha--invisible';
				}
				$captcha_hidden = $steps_on && $steps_count > 1;
				?>
				<div class="<?php echo esc_attr( $captcha_class ); ?>"<?php echo $captcha_provider !== '' ? ' data-nest-form-captcha="' . esc_attr( $captcha_provider ) . '"' : ''; ?> data-nest-form-captcha-wrap<?php echo $captcha_hidden ? ' hidden' : ''; ?>>
					<?php echo $captcha_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php endif; ?>
			<div class="nest-form__actions">
				<?php if ( $steps_on && $steps_count > 1 ) : ?>
					<button type="button" class="button button--outline nest-form__prev" data-nest-form-prev hidden>
						<?php echo esc_html( (string) ( $settings['prev_label'] ?: __( 'Back', 'nestform' ) ) ); ?>
					</button>
					<button type="button" class="button button--primary nest-form__next" data-nest-form-next>
						<?php echo esc_html( (string) ( $settings['next_label'] ?: __( 'Continue', 'nestform' ) ) ); ?>
					</button>
					<button type="submit" class="button button--primary nest-form__submit" data-nest-form-submit hidden>
						<?php echo esc_html( $settings['submit_label'] ); ?>
					</button>
				<?php else : ?>
					<button type="submit" class="button button--primary nest-form__submit">
						<?php echo esc_html( $settings['submit_label'] ); ?>
					</button>
				<?php endif; ?>
			</div>
			<div class="nest-form__status" data-nest-form-status role="status" aria-live="polite" aria-atomic="true" hidden></div>
		</form>
		<?php
		$html = ob_get_clean();

		/**
		 * Filter rendered form HTML.
		 *
		 * @param string $html    Markup.
		 * @param int    $form_id Form ID.
		 * @param array  $config  Config.
		 */
		return (string) apply_filters( 'nestform_render_html', $html, $form_id, $config );
	}

	/**
	 * @param array  $field       Field config.
	 * @param string $uid         Form unique id.
	 * @param bool   $start_hidden Hide initially (other steps).
	 * @return string
	 */
	private static function render_field( array $field, $uid, $start_hidden = false ) {
		$type = $field['type'];

		/**
		 * Short-circuit field render (Pro advanced widgets).
		 * Only applied when Advanced Fields capability is active.
		 *
		 * @param string|null          $html         Custom HTML or null to use core.
		 * @param array<string, mixed> $field        Field config.
		 * @param string               $uid          Form uid.
		 * @param bool                 $start_hidden Hidden initially.
		 */
		$custom = null;
		if ( class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::ADVANCED_FIELDS ) ) {
			$custom = apply_filters( 'nestform_render_field', null, $field, $uid, $start_hidden );
		}
		if ( is_string( $custom ) ) {
			return $custom;
		}

		$name  = $field['name'];
		$id    = $uid . '-' . $name;
		$width = $field['width'] === 'half' ? 'half' : 'full';
		$req   = ! empty( $field['required'] );
		$label = (string) $field['label'];
		$ph    = (string) $field['placeholder'];
		$desc  = (string) ( $field['description'] ?? '' );
		$def   = (string) ( $field['default'] ?? '' );
		$extra = sanitize_html_class( (string) ( $field['css_class'] ?? '' ) );
		$step  = isset( $field['step'] ) ? max( 1, (int) $field['step'] ) : 1;

		$classes = array(
			'field',
			'nest-form__field',
			'nest-form__field--' . $type,
			'nest-form__field--' . $width,
		);
		if ( $req ) {
			$classes[] = 'nest-form__field--required';
		}
		if ( $extra !== '' ) {
			$classes[] = $extra;
		}

		$classes = (array) apply_filters( 'nestform_field_classes', $classes, $field, $uid );

		if ( Nestform_Form_Config::is_layout_field( $type ) ) {
			return self::render_layout_field( $field, $uid, $start_hidden );
		}

		ob_start();

		if ( 'hidden' === $type ) {
			printf(
				'<input type="hidden" class="nest-form__input" name="%1$s" id="%2$s" value="%3$s" data-field-step="%4$s" />',
				esc_attr( $name ),
				esc_attr( $id ),
				esc_attr( $def !== '' ? $def : $ph ),
				esc_attr( (string) $step )
			);
			$html = (string) ob_get_clean();
			return (string) apply_filters( 'nestform_field_html', $html, $field, $uid );
		}

		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-field-name="' . esc_attr( $name ) . '" data-field-step="' . esc_attr( (string) $step ) . '"' . self::condition_data_attrs( $field ) . ( $start_hidden ? ' hidden' : '' ) . '>';

		if ( in_array( $type, array( 'checkbox', 'acceptance' ), true ) ) {
			$check_label_class = 'checkbox-field nest-form__check';
			if ( $req ) {
				$check_label_class .= ' label--required';
			}
			echo '<label class="' . esc_attr( $check_label_class ) . '">';
			printf(
				'<input type="checkbox" class="checkbox nest-form__checkbox" name="%1$s" id="%2$s" value="1"%3$s%4$s />',
				esc_attr( $name ),
				esc_attr( $id ),
				$req ? ' required' : '',
				( $def === '1' || $def === 'true' || $def === 'yes' ) ? ' checked' : ''
			);
			$label_html = $label !== '' ? $label : $name;
			if ( 'acceptance' === $type ) {
				$label_html = Nestform_Form_Config::sanitize_acceptance_label( $label_html );
			} else {
				$label_html = esc_html( $label_html );
			}
			echo '<span class="label nest-form__label">' . $label_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kses/esc_html above.
			echo '</span></label>';
		} else {
			$show_label = ( $label !== '' || $req ) && ! in_array( $type, array( 'radio', 'checkboxes' ), true );
			if ( $show_label ) {
				$label_for    = 'select' === $type ? $id . '-trigger' : $id;
				$label_class  = 'label nest-form__label';
				if ( $req ) {
					$label_class .= ' label--required';
				}
				echo '<label class="' . esc_attr( $label_class ) . '" for="' . esc_attr( $label_for ) . '">';
				echo esc_html( $label !== '' ? $label : $name );
				echo '</label>';
			} elseif ( in_array( $type, array( 'radio', 'checkboxes' ), true ) && ( $label !== '' || $req ) ) {
				$legend_class = 'label nest-form__label';
				if ( $req ) {
					$legend_class .= ' label--required';
				}
				echo '<div class="' . esc_attr( $legend_class ) . '" id="' . esc_attr( $id . '-legend' ) . '">';
				echo esc_html( $label !== '' ? $label : $name );
				echo '</div>';
			}

			if ( 'textarea' === $type ) {
				printf(
					'<textarea class="textarea input nest-form__input nest-form__textarea" name="%1$s" id="%2$s" rows="5" placeholder="%3$s"%4$s>%5$s</textarea>',
					esc_attr( $name ),
					esc_attr( $id ),
					esc_attr( $ph ),
					$req ? ' required' : '',
					esc_textarea( $def )
				);
			} elseif ( 'select' === $type ) {
				$choices = Nestform_Form_Config::parse_choice_lines( (string) $field['options'] );
				$allow_other = ! empty( $field['allow_other'] );
				$other_label = Nestform_Form_Config::other_choice_label( $field );
				$ph_text = $ph !== '' ? $ph : __( 'Select…', 'nestform' );
				$list_id = $id . '-list';
				$selected_label = $ph_text;
				$is_placeholder = ( $def === '' );
				foreach ( $choices as $choice ) {
					if ( $def === $choice['value'] ) {
						$selected_label = $choice['label'];
						$is_placeholder = false;
						break;
					}
				}
				echo '<div class="nest-form-select" data-nest-form-select' . ( $allow_other ? ' data-nest-form-allow-other' : '' ) . '>';
				echo '<select class="nest-form-select__native" name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '" data-nest-form-select-native' . ( $req ? ' required' : '' ) . ' tabindex="-1" aria-hidden="true">';
				echo '<option value="">' . esc_html( $ph_text ) . '</option>';
				foreach ( $choices as $choice ) {
					echo '<option value="' . esc_attr( $choice['value'] ) . '"' . selected( $def, $choice['value'], false ) . '>' . esc_html( $choice['label'] ) . '</option>';
				}
				if ( $allow_other ) {
					echo '<option value="' . esc_attr( Nestform_Form_Config::OTHER_VALUE ) . '">' . esc_html( $other_label ) . '</option>';
				}
				echo '</select>';
				printf(
					'<button type="button" class="select input nest-form-select__trigger nest-form__input" id="%1$s-trigger" aria-haspopup="listbox" aria-expanded="false" aria-controls="%2$s" data-nest-form-select-trigger%3$s>',
					esc_attr( $id ),
					esc_attr( $list_id ),
					$req ? ' aria-required="true"' : ''
				);
				echo '<span class="nest-form-select__value' . ( $is_placeholder ? ' is-placeholder' : '' ) . '" data-nest-form-select-value data-placeholder="' . esc_attr( $ph_text ) . '">' . esc_html( $selected_label ) . '</span>';
				echo '<span class="nest-form-select__icon" aria-hidden="true"></span>';
				echo '</button>';
				echo '<ul class="nest-form-select__list" id="' . esc_attr( $list_id ) . '" role="listbox" hidden data-nest-form-select-list>';
				foreach ( $choices as $opt_i => $choice ) {
					$opt_id = $id . '-opt-' . (int) $opt_i;
					$sel    = ( $def === $choice['value'] );
					echo '<li class="nest-form-select__option" role="option" id="' . esc_attr( $opt_id ) . '" tabindex="-1" data-value="' . esc_attr( $choice['value'] ) . '" aria-selected="' . ( $sel ? 'true' : 'false' ) . '">' . esc_html( $choice['label'] ) . '</li>';
				}
				if ( $allow_other ) {
					echo '<li class="nest-form-select__option" role="option" id="' . esc_attr( $id . '-opt-other' ) . '" tabindex="-1" data-value="' . esc_attr( Nestform_Form_Config::OTHER_VALUE ) . '" aria-selected="false">' . esc_html( $other_label ) . '</li>';
				}
				echo '</ul>';
				echo '</div>';
				if ( $allow_other ) {
					printf(
						'<input type="text" class="input nest-form__input nest-form__other" name="%1$s__other" id="%2$s-other" value="" placeholder="%3$s" data-nest-form-other hidden autocomplete="off" />',
						esc_attr( $name ),
						esc_attr( $id ),
						esc_attr__( 'Please specify', 'nestform' )
					);
				}
			} elseif ( 'radio' === $type || 'checkboxes' === $type ) {
				$choices = Nestform_Form_Config::parse_choice_lines( (string) $field['options'] );
				$allow_other = ! empty( $field['allow_other'] );
				$other_label = Nestform_Form_Config::other_choice_label( $field );
				$defaults = array_filter( array_map( 'trim', preg_split( '/\s*,\s*/', $def ) ?: array() ) );
				$group_role = 'radio' === $type ? 'radiogroup' : 'group';
				echo '<div class="nest-form__choices nest-form__choices--' . esc_attr( $type ) . '" role="' . esc_attr( $group_role ) . '" aria-labelledby="' . esc_attr( $id . '-legend' ) . '"' . ( $req ? ' data-required="1"' : '' ) . ' data-nest-form-choices' . ( $allow_other ? ' data-nest-form-allow-other' : '' ) . '>';
				foreach ( $choices as $opt_i => $choice ) {
					$opt_id  = $id . '-' . (int) $opt_i;
					$opt     = $choice['value'];
					$checked = in_array( $opt, $defaults, true ) || ( 'radio' === $type && $def === $opt );
					if ( 'radio' === $type ) {
						echo '<label class="radio-field nest-form__choice" for="' . esc_attr( $opt_id ) . '">';
						printf(
							'<input type="radio" class="radio nest-form__radio" name="%1$s" id="%2$s" value="%3$s"%4$s%5$s />',
							esc_attr( $name ),
							esc_attr( $opt_id ),
							esc_attr( $opt ),
							$checked ? ' checked' : '',
							( $req && 0 === $opt_i ) ? ' required' : ''
						);
						echo '<span class="nest-form__choice-label">' . esc_html( $choice['label'] ) . '</span></label>';
					} else {
						echo '<label class="checkbox-field nest-form__choice" for="' . esc_attr( $opt_id ) . '">';
						printf(
							'<input type="checkbox" class="checkbox nest-form__checkbox" name="%1$s[]" id="%2$s" value="%3$s"%4$s />',
							esc_attr( $name ),
							esc_attr( $opt_id ),
							esc_attr( $opt ),
							$checked ? ' checked' : ''
						);
						echo '<span class="nest-form__choice-label">' . esc_html( $choice['label'] ) . '</span></label>';
					}
				}
				if ( $allow_other ) {
					$other_id = $id . '-other-choice';
					if ( 'radio' === $type ) {
						echo '<label class="radio-field nest-form__choice nest-form__choice--other" for="' . esc_attr( $other_id ) . '">';
						printf(
							'<input type="radio" class="radio nest-form__radio" name="%1$s" id="%2$s" value="%3$s" data-nest-form-other-trigger />',
							esc_attr( $name ),
							esc_attr( $other_id ),
							esc_attr( Nestform_Form_Config::OTHER_VALUE )
						);
						echo '<span class="nest-form__choice-label">' . esc_html( $other_label ) . '</span></label>';
					} else {
						echo '<label class="checkbox-field nest-form__choice nest-form__choice--other" for="' . esc_attr( $other_id ) . '">';
						printf(
							'<input type="checkbox" class="checkbox nest-form__checkbox" name="%1$s[]" id="%2$s" value="%3$s" data-nest-form-other-trigger />',
							esc_attr( $name ),
							esc_attr( $other_id ),
							esc_attr( Nestform_Form_Config::OTHER_VALUE )
						);
						echo '<span class="nest-form__choice-label">' . esc_html( $other_label ) . '</span></label>';
					}
					printf(
						'<input type="text" class="input nest-form__input nest-form__other" name="%1$s__other" id="%2$s-other" value="" placeholder="%3$s" data-nest-form-other hidden autocomplete="off" />',
						esc_attr( $name ),
						esc_attr( $id ),
						esc_attr__( 'Please specify', 'nestform' )
					);
				}
				echo '</div>';
			} elseif ( 'file' === $type ) {
				$extensions = Nestform_Form_Config::parse_file_extensions( (string) ( $field['options'] ?? '' ) );
				$accept     = array();
				foreach ( $extensions as $ext ) {
					$accept[] = '.' . $ext;
				}
				$max_mb    = max( 1, min( 50, (int) ( $ph !== '' ? $ph : Nestform_Form_Config::file_default_max_mb() ) ) );
				$max_files = Nestform_Form_Config::file_max_count( $field );
				$multiple  = $max_files > 1;
				printf(
					'<input type="file" class="input nest-form__input nest-form__file" name="%1$s%6$s" id="%2$s"%3$s accept="%4$s" data-max-mb="%5$s" data-max-files="%7$s"%8$s />',
					esc_attr( $name ),
					esc_attr( $id ),
					$req ? ' required' : '',
					esc_attr( implode( ',', $accept ) ),
					esc_attr( (string) $max_mb ),
					$multiple ? '[]' : '',
					esc_attr( (string) $max_files ),
					$multiple ? ' multiple' : ''
				);
			} elseif ( 'tel' === $type && class_exists( 'Nestform_Phone' ) && Nestform_Phone::is_picker_enabled( $field ) ) {
				Nestform_Phone::render_field( $field, $id, $name, $req, $ph, $def );
			} elseif ( 'range' === $type ) {
				$range = Nestform_Form_Config::parse_range_options( (string) ( $field['options'] ?? '' ) );
				$val   = $def !== '' && is_numeric( $def ) ? $def : (string) $range['min'];
				printf(
					'<input type="range" class="input nest-form__input nest-form__range" name="%1$s" id="%2$s" min="%3$s" max="%4$s" step="%5$s" value="%6$s"%7$s />',
					esc_attr( $name ),
					esc_attr( $id ),
					esc_attr( (string) $range['min'] ),
					esc_attr( (string) $range['max'] ),
					esc_attr( (string) $range['step'] ),
					esc_attr( $val ),
					$req ? ' required' : ''
				);
			} elseif ( 'calculated' === $type ) {
				$formula = (string) ( $field['options'] ?? '' );
				printf(
					'<input type="text" class="input nest-form__input nest-form__input--calculated" name="%1$s" id="%2$s" value="" readonly tabindex="-1" data-nestform-calculated data-nestform-formula="%3$s" />',
					esc_attr( $name ),
					esc_attr( $id ),
					esc_attr( $formula )
				);
			} elseif ( 'repeater' === $type ) {
				self::render_repeater_field( $field, $uid, $id, $name, $req );
			} else {
				$input_type = Nestform_Form_Config::html_input_type( $type );
				$autocomplete = 'on';
				if ( 'email' === $input_type ) {
					$autocomplete = 'email';
				} elseif ( 'url' === $input_type ) {
					$autocomplete = 'url';
				} elseif ( 'password' === $input_type ) {
					$autocomplete = 'new-password';
				} elseif ( 'tel' === $input_type ) {
					$autocomplete = 'tel';
				} elseif ( in_array( $input_type, array( 'date', 'time', 'number', 'range' ), true ) ) {
					$autocomplete = 'off';
				}
				printf(
					'<input type="%1$s" class="input nest-form__input%8$s" name="%2$s" id="%3$s" placeholder="%4$s" value="%5$s"%6$s autocomplete="%7$s" />',
					esc_attr( $input_type ),
					esc_attr( $name ),
					esc_attr( $id ),
					esc_attr( $ph ),
					esc_attr( $def ),
					$req ? ' required' : '',
					esc_attr( $autocomplete ),
					'password' === $input_type ? ' nest-form__input--password' : ''
				);
			}
		}

		if ( $desc !== '' ) {
			echo '<p class="field__hint nest-form__help">' . esc_html( $desc ) . '</p>';
		}

		echo '<p class="field__error nest-form__error" id="' . esc_attr( $id . '-error' ) . '" data-nest-form-error role="alert" hidden></p>';
		echo '</div>';

		$html = (string) ob_get_clean();

		/**
		 * Filter single field HTML.
		 *
		 * @param string $html  Markup.
		 * @param array  $field Field config.
		 * @param string $uid   Form uid.
		 */
		return (string) apply_filters( 'nestform_field_html', $html, $field, $uid );
	}

	/**
	 * Render repeater field (supports nested repeaters).
	 *
	 * @param array  $field Field.
	 * @param string $uid   Form uid.
	 * @param string $id    Input id base.
	 * @param string $name  Field name.
	 * @param bool   $req   Required.
	 */
	private static function render_repeater_field( array $field, $uid, $id, $name, $req ) {
		$subfields = isset( $field['subfields'] ) && is_array( $field['subfields'] ) ? $field['subfields'] : array();
		echo '<div class="nest-form__repeater" data-nestform-repeater data-nestform-repeater-name="' . esc_attr( $name ) . '"' . ( $req ? ' data-nestform-repeater-required="1"' : '' ) . '>';
		echo '<div class="nest-form__repeater-rows" data-nestform-repeater-rows>';
		self::render_repeater_row( $subfields, $name, 0, $uid );
		echo '</div>';
		echo '<template data-nestform-repeater-template>';
		self::render_repeater_row( $subfields, $name, '__INDEX__', $uid );
		echo '</template>';
		echo '<button type="button" class="button button--outline nest-form__repeater-add" data-nestform-repeater-add>' . esc_html__( 'Add row', 'nestform' ) . '</button>';
		echo '</div>';
	}

	/**
	 * @param array        $subfields Subfields.
	 * @param string       $base_name Parent name prefix.
	 * @param int|string   $index     Row index.
	 * @param string       $uid       Form uid.
	 */
	private static function render_repeater_row( array $subfields, $base_name, $index, $uid ) {
		echo '<div class="nest-form__repeater-row" data-nestform-repeater-row>';
		echo '<div class="nest-form__repeater-row-fields">';
		foreach ( $subfields as $sub ) {
			if ( ! is_array( $sub ) ) {
				continue;
			}
			$sub_name = (string) ( $sub['name'] ?? '' );
			$sub_type = (string) ( $sub['type'] ?? 'text' );
			if ( '' === $sub_name ) {
				continue;
			}
			$input_name = $base_name . '[' . $index . '][' . $sub_name . ']';
			$input_id   = $uid . '-' . sanitize_html_class( $base_name . '-' . $index . '-' . $sub_name );
			$label      = (string) ( $sub['label'] ?? $sub_name );
			$ph         = (string) ( $sub['placeholder'] ?? '' );
			$def        = (string) ( $sub['default'] ?? '' );
			$sub_req    = ! empty( $sub['required'] );

			echo '<div class="nest-form__repeater-subfield nest-form__field nest-form__field--' . esc_attr( $sub_type ) . '" data-field-name="' . esc_attr( $sub_name ) . '">';
			if ( 'calculated' === $sub_type ) {
				if ( $label !== '' ) {
					echo '<label class="label nest-form__label" for="' . esc_attr( $input_id ) . '">' . esc_html( $label ) . '</label>';
				}
				printf(
					'<input type="text" class="input nest-form__input nest-form__input--calculated" name="%1$s" id="%2$s" value="" readonly tabindex="-1" data-nestform-calculated data-nestform-formula="%3$s" />',
					esc_attr( $input_name ),
					esc_attr( $input_id ),
					esc_attr( (string) ( $sub['options'] ?? '' ) )
				);
			} elseif ( 'textarea' === $sub_type ) {
				if ( $label !== '' ) {
					echo '<label class="label nest-form__label" for="' . esc_attr( $input_id ) . '">' . esc_html( $label ) . '</label>';
				}
				printf(
					'<textarea class="input nest-form__input" name="%1$s" id="%2$s" rows="3" placeholder="%3$s"%4$s>%5$s</textarea>',
					esc_attr( $input_name ),
					esc_attr( $input_id ),
					esc_attr( $ph ),
					$sub_req ? ' required' : '',
					esc_textarea( $def )
				);
			} elseif ( in_array( $sub_type, array( 'select', 'radio', 'checkboxes' ), true ) ) {
				if ( $label !== '' ) {
					echo '<span class="label nest-form__label" id="' . esc_attr( $input_id . '-legend' ) . '">' . esc_html( $label );
					if ( $sub_req ) {
						echo ' <span class="nest-form__req" aria-hidden="true">*</span>';
					}
					echo '</span>';
				}
				$choices = Nestform_Form_Config::parse_choice_lines( (string) ( $sub['options'] ?? '' ) );
				if ( 'select' === $sub_type ) {
					echo '<select class="input nest-form__input" name="' . esc_attr( $input_name ) . '" id="' . esc_attr( $input_id ) . '"' . ( $sub_req ? ' required' : '' ) . '>';
					echo '<option value="">' . esc_html( $ph !== '' ? $ph : __( 'Select…', 'nestform' ) ) . '</option>';
					foreach ( $choices as $choice ) {
						$val = (string) ( $choice['value'] ?? '' );
						$lab = (string) ( $choice['label'] ?? $val );
						echo '<option value="' . esc_attr( $val ) . '"' . selected( $def, $val, false ) . '>' . esc_html( $lab ) . '</option>';
					}
					echo '</select>';
				} elseif ( 'radio' === $sub_type || 'checkboxes' === $sub_type ) {
					$group_role = 'radio' === $sub_type ? 'radiogroup' : 'group';
					echo '<div class="nest-form__choices nest-form__choices--' . esc_attr( $sub_type ) . '" role="' . esc_attr( $group_role ) . '"' . ( $label !== '' ? ' aria-labelledby="' . esc_attr( $input_id . '-legend' ) . '"' : '' ) . ( $sub_req ? ' data-required="1"' : '' ) . '>';
					foreach ( $choices as $i => $choice ) {
						$val    = (string) ( $choice['value'] ?? '' );
						$lab    = (string) ( $choice['label'] ?? $val );
						$cid    = $input_id . '-' . (int) $i;
						$checked = ( 'radio' === $sub_type && $def === $val );
						if ( 'radio' === $sub_type ) {
							echo '<label class="radio-field nest-form__choice" for="' . esc_attr( $cid ) . '">';
							printf(
								'<input type="radio" class="radio nest-form__radio" name="%1$s" id="%2$s" value="%3$s"%4$s%5$s />',
								esc_attr( $input_name ),
								esc_attr( $cid ),
								esc_attr( $val ),
								$checked ? ' checked' : '',
								( $sub_req && 0 === (int) $i ) ? ' required' : ''
							);
							echo '<span class="nest-form__choice-label">' . esc_html( $lab ) . '</span></label>';
						} else {
							echo '<label class="checkbox-field nest-form__choice" for="' . esc_attr( $cid ) . '">';
							printf(
								'<input type="checkbox" class="checkbox nest-form__checkbox" name="%1$s[]" id="%2$s" value="%3$s" />',
								esc_attr( $input_name ),
								esc_attr( $cid ),
								esc_attr( $val )
							);
							echo '<span class="nest-form__choice-label">' . esc_html( $lab ) . '</span></label>';
						}
					}
					echo '</div>';
				}
			} elseif ( in_array( $sub_type, array( 'checkbox', 'acceptance' ), true ) ) {
				$check_label_class = 'checkbox-field nest-form__check';
				if ( $sub_req ) {
					$check_label_class .= ' label--required';
				}
				echo '<label class="' . esc_attr( $check_label_class ) . '" for="' . esc_attr( $input_id ) . '">';
				printf(
					'<input type="checkbox" class="checkbox nest-form__checkbox" name="%1$s" id="%2$s" value="1"%3$s />',
					esc_attr( $input_name ),
					esc_attr( $input_id ),
					$sub_req ? ' required' : ''
				);
				echo '<span class="nest-form__choice-label">' . esc_html( $label !== '' ? $label : $sub_name ) . '</span></label>';
			} else {
				if ( $label !== '' ) {
					echo '<label class="label nest-form__label" for="' . esc_attr( $input_id ) . '">' . esc_html( $label ) . '</label>';
				}
				$input_type = Nestform_Form_Config::html_input_type( $sub_type );
				printf(
					'<input type="%1$s" class="input nest-form__input" name="%2$s" id="%3$s" placeholder="%4$s" value="%5$s"%6$s />',
					esc_attr( $input_type ),
					esc_attr( $input_name ),
					esc_attr( $input_id ),
					esc_attr( $ph ),
					esc_attr( $def ),
					$sub_req ? ' required' : ''
				);
			}
			echo '</div>';
		}
		echo '</div>';
		printf(
			'<button type="button" class="button button--outline nest-form__repeater-remove" data-nestform-repeater-remove aria-label="%1$s" hidden>%2$s</button>',
			esc_attr__( 'Remove row', 'nestform' ),
			esc_html__( 'Remove', 'nestform' )
		);
		echo '</div>';
	}

	/**
	 * Layout-only blocks (heading, image, HTML) — not submitted.
	 *
	 * @param array  $field        Field config.
	 * @param string $uid          Form uid.
	 * @param bool   $start_hidden Hidden initially.
	 * @return string
	 */
	private static function render_layout_field( array $field, $uid, $start_hidden = false ) {
		$type  = (string) $field['type'];
		$name  = (string) $field['name'];
		$width = ( $field['width'] ?? '' ) === 'half' ? 'half' : 'full';
		$label = (string) ( $field['label'] ?? '' );
		$desc  = (string) ( $field['description'] ?? '' );
		$extra = sanitize_html_class( (string) ( $field['css_class'] ?? '' ) );
		$step  = isset( $field['step'] ) ? max( 1, (int) $field['step'] ) : 1;

		$classes = array(
			'nest-form__layout',
			'nest-form__layout--' . $type,
			'nest-form__field',
			'nest-form__field--' . $type,
			'nest-form__field--' . $width,
		);
		if ( $extra !== '' ) {
			$classes[] = $extra;
		}
		$classes = (array) apply_filters( 'nestform_field_classes', $classes, $field, $uid );

		ob_start();
		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-nest-form-layout data-field-step="' . esc_attr( (string) $step ) . '"' . ( $start_hidden ? ' hidden' : '' ) . '>';

		if ( 'heading' === $type ) {
			$level = (string) ( $field['options'] ?? 'h2' );
			if ( ! in_array( $level, array( 'h2', 'h3', 'h4' ), true ) ) {
				$level = 'h2';
			}
			$text = $label !== '' ? $label : $name;
			printf(
				'<%1$s class="nest-form__heading nest-form__heading--%2$s">%3$s</%1$s>',
				$level,
				esc_attr( str_replace( 'h', '', $level ) ),
				esc_html( $text )
			);
		} elseif ( 'image' === $type ) {
			$attachment_id = max( 0, (int) ( $field['default'] ?? 0 ) );
			if ( $attachment_id > 0 ) {
				$alt = $desc !== '' ? $desc : $label;
				echo '<figure class="nest-form__figure">';
				echo wp_get_attachment_image(
					$attachment_id,
					'large',
					false,
					array(
						'class' => 'nest-form__image',
						'alt'   => $alt,
					)
				);
				if ( $label !== '' ) {
					echo '<figcaption class="nest-form__caption">' . esc_html( $label ) . '</figcaption>';
				}
				echo '</figure>';
			}
		} elseif ( 'paragraph' === $type ) {
			$text = (string) ( $field['options'] ?? '' );
			if ( $text !== '' ) {
				echo '<div class="nest-form__paragraph">' . nl2br( esc_html( $text ) ) . '</div>';
			}
		} elseif ( 'divider' === $type ) {
			echo '<hr class="nest-form__divider" />';
		} elseif ( 'spacer' === $type ) {
			$size = (string) ( $field['options'] ?? 'm' );
			if ( ! in_array( $size, array( 's', 'm', 'l' ), true ) ) {
				$size = 'm';
			}
			echo '<div class="nest-form__spacer nest-form__spacer--' . esc_attr( $size ) . '" aria-hidden="true"></div>';
		} elseif ( 'html' === $type ) {
			$content = (string) ( $field['options'] ?? '' );
			if ( $content !== '' ) {
				echo '<div class="nest-form__html">' . wp_kses( $content, Nestform_Form_Config::html_allowed_tags() ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
		}

		echo '</div>';
		$html = (string) ob_get_clean();

		return (string) apply_filters( 'nestform_field_html', $html, $field, $uid );
	}

	/**
	 * @param array<string, mixed> $field Field.
	 * @return string HTML attributes (leading space when non-empty).
	 */
	private static function condition_data_attrs( array $field ) {
		$watch = isset( $field['condition_field'] ) ? (string) $field['condition_field'] : '';
		if ( $watch === '' ) {
			return '';
		}
		$op  = isset( $field['condition_op'] ) ? (string) $field['condition_op'] : 'equals';
		$val = isset( $field['condition_value'] ) ? (string) $field['condition_value'] : '';
		return sprintf(
			' data-condition-field="%1$s" data-condition-op="%2$s" data-condition-value="%3$s"',
			esc_attr( $watch ),
			esc_attr( $op ),
			esc_attr( $val )
		);
	}

	/**
	 * @param string $raw Options text.
	 * @return array<int, string>
	 */
	private static function parse_options( $raw ) {
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		if ( ! is_array( $lines ) ) {
			return array();
		}
		$out = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line !== '' ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	public static function enqueue_front() {
		if ( self::$assets_queued ) {
			return;
		}
		self::$assets_queued = true;

		$css = NESTFORM_PATH . 'assets/front.css';
		$js  = NESTFORM_PATH . 'assets/front.js';
		wp_enqueue_style(
			'nestform-front',
			NESTFORM_URL . 'assets/front.css',
			array(),
			(string) filemtime( $css ) ?: NESTFORM_VERSION
		);
		wp_enqueue_script(
			'nestform-front',
			NESTFORM_URL . 'assets/front.js',
			array(),
			(string) filemtime( $js ) ?: NESTFORM_VERSION,
			true
		);
		wp_localize_script(
			'nestform-front',
			'nestform',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'close'         => __( 'Close', 'nestform' ),
					'successTitle'  => __( 'Thank you', 'nestform' ),
				),
			)
		);

		/**
		 * After core front assets are queued (Pro may enqueue widgets / trackers).
		 */
		do_action( 'nestform_enqueue_front' );
	}
}
