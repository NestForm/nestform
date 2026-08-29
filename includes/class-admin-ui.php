<?php
/**
 * Admin meta boxes for form builder.
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Admin_UI {

	const NONCE = 'nestform_save_config';

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_boxes' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'remove_default_boxes' ), 40 );
		add_action( 'save_post_' . Nestform_Post_Type::POST_TYPE, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'edit_form_after_title', array( __CLASS__, 'editor_title_actions' ) );
		add_action( 'wp_ajax_nestform_preview', array( __CLASS__, 'ajax_preview' ) );
		add_filter( 'enter_title_here', array( __CLASS__, 'enter_title_here' ), 10, 2 );
	}

	/**
	 * @param string  $text Placeholder.
	 * @param WP_Post $post Post.
	 * @return string
	 */
	public static function enter_title_here( $text, $post ) {
		if ( $post && Nestform_Post_Type::POST_TYPE === $post->post_type ) {
			return __( 'Form name', 'nestform' );
		}
		return $text;
	}

	/**
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && Nestform_Post_Type::POST_TYPE === $screen->post_type ) {
			$classes .= ' nestform-admin-screen';
		}
		return $classes;
	}

	/**
	 * Save / publish next to the form title (Make editor chrome).
	 *
	 * @param WP_Post $post Post.
	 */
	public static function editor_title_actions( $post ) {
		if ( ! $post || Nestform_Post_Type::POST_TYPE !== $post->post_type ) {
			return;
		}
		$status = get_post_status( $post );
		?>
		<div class="nestform-editor__title-actions" data-nestform-title-actions>
			<span class="nestform-editor__dirty" data-nestform-dirty hidden><?php esc_html_e( 'Unsaved', 'nestform' ); ?></span>
			<button type="button" class="nestform-btn nestform-btn--outline nestform-editor__title-action" data-nestform-preview>
				<?php nestform_admin_icon( 'preview' ); ?>
				<?php esc_html_e( 'Preview', 'nestform' ); ?>
			</button>
			<?php if ( 'publish' === $status ) : ?>
				<button type="submit" class="nestform-btn nestform-btn--primary nestform-editor__title-action" name="save" value="Save" data-nestform-save>
					<?php nestform_admin_icon( 'save' ); ?>
					<?php esc_html_e( 'Save', 'nestform' ); ?>
				</button>
			<?php else : ?>
				<button type="submit" class="nestform-btn nestform-btn--warn nestform-editor__title-action" name="saveasdraft" value="1" data-nestform-save>
					<?php nestform_admin_icon( 'save' ); ?>
					<?php esc_html_e( 'Save draft', 'nestform' ); ?>
				</button>
				<button type="submit" class="nestform-btn nestform-btn--primary nestform-editor__title-action" name="publish" value="Publish" data-nestform-save>
					<?php nestform_admin_icon( 'save' ); ?>
					<?php esc_html_e( 'Publish', 'nestform' ); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function remove_default_boxes() {
		$pt = Nestform_Post_Type::POST_TYPE;
		$boxes = array(
			'slugdiv',
			'submitdiv',
			'authordiv',
			'revisionsdiv',
			'commentstatusdiv',
			'commentsdiv',
			'trackbacksdiv',
			'postcustom',
			'postexcerpt',
			'pageparentdiv',
		);
		foreach ( $boxes as $box ) {
			remove_meta_box( $box, $pt, 'normal' );
			remove_meta_box( $box, $pt, 'side' );
			remove_meta_box( $box, $pt, 'advanced' );
		}
	}

	/**
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Nestform_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$ver_css = (string) filemtime( NESTFORM_PATH . 'assets/admin.css' );
		$ver_js  = (string) filemtime( NESTFORM_PATH . 'assets/admin.js' );

		wp_enqueue_media();
		if ( class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::EMAIL_DESIGNER ) ) {
			wp_enqueue_editor();
		}
		wp_enqueue_style(
			'nestform-admin',
			NESTFORM_URL . 'assets/admin.css',
			nestform_admin_style_deps(),
			$ver_css ? $ver_css : NESTFORM_VERSION
		);
		wp_enqueue_script(
			'nestform-admin',
			NESTFORM_URL . 'assets/admin.js',
			array( 'jquery', 'media-editor' ),
			$ver_js ? $ver_js : NESTFORM_VERSION,
			true
		);
		$mail_preview = NESTFORM_PATH . 'assets/mail-preview.js';
		if ( is_readable( $mail_preview ) ) {
			wp_enqueue_script(
				'nestform-mail-preview',
				NESTFORM_URL . 'assets/mail-preview.js',
				array( 'nestform-admin', 'jquery' ),
				(string) filemtime( $mail_preview ) ?: NESTFORM_VERSION,
				true
			);
		}
		wp_localize_script(
			'nestform-admin',
			'nestformAdmin',
			array(
				'i18n' => array(
					'untitled'       => __( 'Untitled field', 'nestform' ),
					'required'       => __( 'Required', 'nestform' ),
					'optional'       => __( 'Optional', 'nestform' ),
					'toggleRequired' => __( 'Toggle required', 'nestform' ),
					'confirmDel'           => __( 'Remove this field?', 'nestform' ),
					'confirmDelStep'       => __( 'Remove this step?', 'nestform' ),
					'confirmDelStepFields' => __( 'Remove this step and its %d field(s)?', 'nestform' ),
					'copied'         => __( 'Copied', 'nestform' ),
					'duplicate'      => __( 'copy', 'nestform' ),
					'step'           => __( 'Step', 'nestform' ),
					'stepTitle'      => __( 'Step %d title', 'nestform' ),
					'layout'         => __( 'Layout', 'nestform' ),
					'pickImage'      => __( 'Select image', 'nestform' ),
					'changeImage'    => __( 'Change image', 'nestform' ),
					'removeImage'    => __( 'Remove', 'nestform' ),
					'noImage'        => __( 'No image selected', 'nestform' ),
					'mediaUnavailable' => __( 'WordPress media library is not available.', 'nestform' ),
					'selectPlaceholder' => __( 'Select…', 'nestform' ),
					'fieldPlaceholder'  => __( 'Optional hint', 'nestform' ),
					'previewNeedSave'   => __( 'Save the form to refresh preview.', 'nestform' ),
					'templateSaveFirst' => __( 'Save the form as a draft first, then you can apply a template.', 'nestform' ),
					'alwaysShow'        => __( '— Always show —', 'nestform' ),
					'ifPrefix'          => __( 'if', 'nestform' ),
					'removeRule'        => __( 'Remove', 'nestform' ),
					'fromStep'          => __( 'From', 'nestform' ),
					'toStep'            => __( 'To', 'nestform' ),
					'pickField'         => __( '— Field —', 'nestform' ),
					'pickFieldHint'     => __( 'Choose a field for this branch rule.', 'nestform' ),
					'noRules'           => __( 'No branch rules yet.', 'nestform' ),
					'fieldPreviewEmpty' => __( 'Configure this field to see a preview.', 'nestform' ),
					'fieldPreviewHtml'  => __( 'HTML block', 'nestform' ),
					'fieldPreviewHidden'=> __( 'Hidden field — not shown on the form', 'nestform' ),
					'fieldPreviewSpacer'=> __( 'Spacer', 'nestform' ),
					'fieldPreviewDivider'=> __( 'Divider', 'nestform' ),
					'fieldPreviewImage' => __( 'Image', 'nestform' ),
					'fieldPreviewFile'  => __( 'Choose files…', 'nestform' ),
					'fieldPreviewCalc'  => __( 'Calculated value', 'nestform' ),
					'fieldPreviewRepeater'=> __( 'Repeater row', 'nestform' ),
					'fieldPreviewRequired'=> __( 'required', 'nestform' ),
					'fieldPreviewSignature'=> __( 'Sign here', 'nestform' ),
					'subOptChoices'     => __( 'Choices (one per line)', 'nestform' ),
					'subOptRange'       => __( 'Min / max / step', 'nestform' ),
					'subOptFormula'     => __( 'Formula', 'nestform' ),
					'subHintChoices'    => __( 'One choice per line — the text visitors see. Example: Yes', 'nestform' ),
					'subHintRange'      => __( 'Three lines: lowest value, highest value, step. Example: 0, then 100, then 1.', 'nestform' ),
					'subHintFormula'    => __( 'Use other subfield names in braces, e.g. {qty} * {price}.', 'nestform' ),
					'subPhChoices'      => __( "Yes\nNo", 'nestform' ),
					'subPhRange'        => "0\n100\n1",
					'subPhFormula'      => '{price} * {qty}',
					'optionsLabelChoices' => __( 'Choices', 'nestform' ),
					'optionsLabelRange'   => __( 'Min / max / step', 'nestform' ),
					'optionsLabelRating'  => __( 'Number of stars', 'nestform' ),
					'optionsLabelScale'   => __( 'Scale setup', 'nestform' ),
					'optionsLabelMatrix'  => __( 'Rows and columns', 'nestform' ),
					'optionsHintChoices'  => __( 'One choice per line — the text visitors see. For quizzes, add points after | : Correct answer|10', 'nestform' ),
					'optionsHintRange'    => __( 'Three lines: lowest value, highest value, and step size.', 'nestform' ),
					'optionsHintRating'   => __( 'Enter one number for how many stars to show (1–10), e.g. 5.', 'nestform' ),
					'optionsHintScale'    => __( 'Four lines: lowest number, highest number, left label, right label.', 'nestform' ),
					'optionsHintMatrix'   => __( 'List row labels, then a line with only ---, then column labels.', 'nestform' ),
					'optionsPhChoices'    => __( "Yes\nNo\nMaybe", 'nestform' ),
					'optionsPhRange'      => "0\n100\n1",
					'optionsPhRating'     => '5',
					'optionsPhScale'      => __( "1\n5\nVery dissatisfied\nVery satisfied", 'nestform' ),
					'optionsPhMatrix'     => __( "Support\nProduct\n---\nPoor\nFair\nGood", 'nestform' ),
					'optionsTipChoices'   => __( 'One choice per line. Quizzes: Correct answer|10. Optional advanced: Label|saved_value|points', 'nestform' ),
					'optionsTipRange'     => __( 'Line 1 = min, line 2 = max, line 3 = step. Example: 0 / 100 / 1', 'nestform' ),
					'optionsTipRating'    => __( 'A single number sets max stars (1–10). Or list one label per star.', 'nestform' ),
					'optionsTipScale'     => __( 'Line 1–2 = number range, line 3–4 = labels under the ends of the scale.', 'nestform' ),
					'optionsTipMatrix'    => __( 'Rows above ---, columns below. Each line is one label.', 'nestform' ),
					'subUntitled'       => __( 'Untitled', 'nestform' ),
					'subColFallback'    => __( 'Column %d', 'nestform' ),
					'helpText'          => __( 'Help text', 'nestform' ),
					'altText'           => __( 'Alt text', 'nestform' ),
					'describeImage'     => __( 'Describe the image', 'nestform' ),
					'shownUnderField'   => __( 'Shown under the field', 'nestform' ),
					'typeSectionTitles' => array(
						'heading'    => __( 'Heading', 'nestform' ),
						'image'      => __( 'Image', 'nestform' ),
						'html'       => __( 'HTML', 'nestform' ),
						'paragraph'  => __( 'Paragraph', 'nestform' ),
						'spacer'     => __( 'Spacer', 'nestform' ),
						'tel'        => __( 'Phone', 'nestform' ),
						'file'       => __( 'Upload limits', 'nestform' ),
						'select'     => __( 'Choices', 'nestform' ),
						'radio'      => __( 'Choices', 'nestform' ),
						'checkboxes' => __( 'Choices', 'nestform' ),
						'range'      => __( 'Range', 'nestform' ),
						'rating'     => __( 'Choices', 'nestform' ),
						'scale'      => __( 'Choices', 'nestform' ),
						'ranking'    => __( 'Choices', 'nestform' ),
						'matrix'     => __( 'Matrix', 'nestform' ),
						'calculated' => __( 'Formula', 'nestform' ),
						'repeater'   => __( 'Row fields', 'nestform' ),
					),
				),
				'typeLabels'  => Nestform_Form_Config::field_type_labels(),
				'layoutTypes' => array_keys( Nestform_Form_Config::layout_field_type_labels() ),
				'operators'   => Nestform_Form_Config::condition_operators(),
				'previewUrl'  => admin_url( 'admin-ajax.php?action=nestform_preview' ),
				'previewNonce'=> wp_create_nonce( 'nestform_preview' ),
				'formId'      => isset( $_GET['post'] ) ? (int) $_GET['post'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			)
		);
	}

	public static function ajax_preview() {
		$form_id = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		check_ajax_referer( 'nestform_preview', 'nonce' );
		if ( $form_id <= 0 || Nestform_Post_Type::POST_TYPE !== get_post_type( $form_id ) ) {
			wp_die( esc_html__( 'Invalid form.', 'nestform' ), 400 );
		}
		if ( ! current_user_can( 'edit_post', $form_id ) ) {
			wp_die( esc_html__( 'Forbidden', 'nestform' ), 403 );
		}
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!DOCTYPE html><html><head><meta charset="utf-8" />';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
		wp_head();
		echo '<style>body{margin:24px;background:#f5f5f7;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;font-size:13px;color:#01123e}.nestform-preview-shell{max-width:720px;margin:0 auto;padding:24px;background:#fff;border:1px solid #e8e8ec;border-radius:10px}</style>';
		echo '</head><body class="nestform-preview-body"><div class="nestform-preview-shell">';
		echo Nestform_Renderer::render( $form_id, array( 'preview' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		wp_footer();
		echo '</body></html>';
		exit;
	}

	public static function meta_boxes() {
		add_meta_box(
			'nestform_builder',
			__( 'Form builder', 'nestform' ),
			array( __CLASS__, 'render_builder_box' ),
			Nestform_Post_Type::POST_TYPE,
			'normal',
			'high'
		);
		add_meta_box(
			'nestform_shortcode',
			__( 'Publish & embed', 'nestform' ),
			array( __CLASS__, 'render_shortcode_box' ),
			Nestform_Post_Type::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public static function render_builder_box( $post ) {
		wp_nonce_field( self::NONCE, 'nestform_nonce' );
		$form_id  = (int) $post->ID;
		$fields   = Nestform_Form_Config::get_fields( $form_id );
		$messages = Nestform_Form_Config::get_messages( $form_id );
		$mail     = Nestform_Form_Config::get_mail( $form_id );
		$settings = Nestform_Form_Config::get_settings( $form_id );
		$types        = Nestform_Form_Config::field_type_labels();
		$layout_types = Nestform_Form_Config::layout_field_type_labels();
		$input_types  = Nestform_Form_Config::input_field_type_labels();
		$steps_enabled = ( '1' === (string) ( $settings['enable_steps'] ?? '0' ) );
		$can_multi     = class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::MULTI_STEP );
		$can_webhook   = class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::WEBHOOK );
		$can_quiz      = class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::QUIZ_SURVEY );
		$can_advanced  = class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::ADVANCED_FIELDS );
		$can_auto      = class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::AUTOMATIONS );
		// Runtime / editor: steps UI only when capability is active.
		if ( ! $can_multi ) {
			$steps_enabled = false;
		}
		$step_labels   = Nestform_Form_Config::parse_step_labels( (string) ( $settings['step_labels'] ?? '' ) );
		$used_steps    = Nestform_Form_Config::collect_steps( $fields );
		if ( $steps_enabled ) {
			$max_step = ! empty( $used_steps ) ? max( $used_steps ) : 1;
			if ( ! empty( $step_labels ) ) {
				$max_step = max( $max_step, max( array_keys( $step_labels ) ) );
			}
			$max_step = max( $max_step, 2 );
		} else {
			$max_step = 1;
		}
		$fields_by_step = array();
		foreach ( $fields as $i => $field ) {
			$s = isset( $field['step'] ) ? max( 1, (int) $field['step'] ) : 1;
			if ( ! isset( $fields_by_step[ $s ] ) ) {
				$fields_by_step[ $s ] = array();
			}
			$fields_by_step[ $s ][] = array( $i, $field );
		}

		$editor_tabs = array( 'fields', 'messages', 'mail', 'settings', 'appearance' );
		$active_tab  = 'fields';
		$tab_cookie  = 'nestform_editor_tab_' . $form_id;
		if ( isset( $_COOKIE[ $tab_cookie ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$cookie_tab = sanitize_key( wp_unslash( $_COOKIE[ $tab_cookie ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( in_array( $cookie_tab, $editor_tabs, true ) ) {
				$active_tab = $cookie_tab;
			}
		}
		$tab_labels = array(
			'fields'     => __( 'Fields', 'nestform' ),
			'messages'   => __( 'Messages', 'nestform' ),
			'mail'       => __( 'Mail', 'nestform' ),
			'settings'   => __( 'Settings', 'nestform' ),
			'appearance' => __( 'Appearance', 'nestform' ),
		);
		?>
		<div class="nestform-admin" data-nestform-admin data-form-id="<?php echo esc_attr( (string) (int) $form_id ); ?>" data-steps-enabled="<?php echo $steps_enabled ? '1' : '0'; ?>">
			<nav class="nestform-admin__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Form sections', 'nestform' ); ?>">
				<?php foreach ( $editor_tabs as $tab_id ) : ?>
					<?php $is_tab = ( $active_tab === $tab_id ); ?>
					<button
						type="button"
						class="nestform-admin__tab<?php echo $is_tab ? ' is-active' : ''; ?>"
						role="tab"
						aria-selected="<?php echo $is_tab ? 'true' : 'false'; ?>"
						tabindex="<?php echo $is_tab ? '0' : '-1'; ?>"
						data-nestform-tab="<?php echo esc_attr( $tab_id ); ?>"
						id="nestform-tab-<?php echo esc_attr( $tab_id ); ?>"
						aria-controls="nestform-panel-<?php echo esc_attr( $tab_id ); ?>"
					><?php echo esc_html( $tab_labels[ $tab_id ] ); ?></button>
				<?php endforeach; ?>
			</nav>

			<div class="nestform-admin__panel<?php echo 'fields' === $active_tab ? ' is-active' : ''; ?>" data-nestform-panel="fields" id="nestform-panel-fields" role="tabpanel" aria-labelledby="nestform-tab-fields"<?php echo 'fields' === $active_tab ? '' : ' hidden'; ?>>
				<div class="nestform-admin__panel-head">
					<div>
						<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Fields', 'nestform' ); ?></h3>
						<p class="nestform-admin__panel-desc"><?php
						echo wp_kses(
							sprintf(
								/* translators: %s: {name} token */
								__( 'Drag to reorder. Field name becomes %s in mail templates.', 'nestform' ),
								'<code class="nestform-admin__token">{name}</code>'
							),
							array(
								'code' => array(
									'class' => true,
								),
							)
						);
						?></p>
					</div>
					<div class="nestform-admin__panel-tools">
						<button type="button" class="nestform-btn nestform-btn--ghost" data-nestform-collapse-all>
							<?php esc_html_e( 'Collapse all', 'nestform' ); ?>
						</button>
						<button type="button" class="nestform-btn nestform-btn--ghost" data-nestform-expand-all>
							<?php esc_html_e( 'Expand all', 'nestform' ); ?>
						</button>
					</div>
				</div>
				<?php
				$show_tpl_empty = array() === $fields;
				if ( $show_tpl_empty ) :
					?>
				<div class="nestform-templates-empty" data-nestform-templates-empty>
					<div class="nestform-templates-empty__copy">
						<strong><?php esc_html_e( 'Start from a template', 'nestform' ); ?></strong>
						<p><?php esc_html_e( 'Pick a ready-made form, then tweak fields to match your brand.', 'nestform' ); ?></p>
					</div>
					<button type="button" class="nestform-btn nestform-btn--primary" data-nestform-templates-open>
						<?php esc_html_e( 'Browse templates', 'nestform' ); ?>
					</button>
				</div>
				<?php endif; ?>

				<div class="nestform-admin__surface nestform-steps-setup<?php echo $steps_enabled ? ' is-on' : ''; ?><?php echo ! $can_multi ? ' nestform-steps-setup--locked' : ''; ?>" data-nestform-steps-setup data-nestform-can-multi-step="<?php echo $can_multi ? '1' : '0'; ?>">
					<div class="nestform-steps-setup__bar">
						<label class="nestform-steps-setup__toggle<?php echo $steps_enabled ? ' is-on' : ''; ?>">
							<input type="hidden" name="nestform[settings][enable_steps]" value="0" />
							<input type="checkbox" name="nestform[settings][enable_steps]" value="1" <?php checked( $steps_enabled ); ?> data-nestform-enable-steps />
							<span class="nestform-switch" aria-hidden="true"></span>
							<span class="nestform-steps-setup__toggle-copy">
								<span class="nestform-steps-setup__toggle-title"><?php esc_html_e( 'Multi-step form', 'nestform' ); ?><?php echo ! $can_multi ? ' ' . Nestform_Upgrade::pill_html() : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<span class="nestform-steps-setup__toggle-desc"><?php echo $can_multi ? esc_html__( 'Split into wizard steps.', 'nestform' ) : esc_html__( 'Available in Nestform Pro.', 'nestform' ); ?></span>
							</span>
						</label>

						<div class="nestform-steps-setup__tools" data-nestform-steps-extra <?php echo $steps_enabled ? '' : 'hidden'; ?>>
							<label class="nestform-steps-setup__tool">
								<span class="nestform-admin__label"><?php esc_html_e( 'Next', 'nestform' ); ?></span>
								<input type="text" class="nestform-admin__input nestform-steps-setup__input" name="nestform[settings][next_label]" value="<?php echo esc_attr( (string) ( $settings['next_label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Next', 'nestform' ); ?>" />
							</label>
							<label class="nestform-steps-setup__tool">
								<span class="nestform-admin__label"><?php esc_html_e( 'Back', 'nestform' ); ?></span>
								<input type="text" class="nestform-admin__input nestform-steps-setup__input" name="nestform[settings][prev_label]" value="<?php echo esc_attr( (string) ( $settings['prev_label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Back', 'nestform' ); ?>" />
							</label>
							<button type="button" class="nestform-admin__chip nestform-steps-setup__add-step" data-nestform-add-step>
								<?php nestform_admin_icon( 'plus' ); ?>
								<?php esc_html_e( 'Add step', 'nestform' ); ?>
							</button>
						</div>
					</div>

					<details class="nestform-steps-setup__branch" data-nestform-steps-branch <?php echo $steps_enabled ? '' : 'hidden'; ?>>
						<summary class="nestform-steps-setup__branch-summary">
							<span class="nestform-steps-setup__branch-title"><?php esc_html_e( 'Branch rules', 'nestform' ); ?></span>
							<span class="nestform-steps-setup__branch-hint"><?php esc_html_e( 'Jump to another step when a field matches.', 'nestform' ); ?></span>
						</summary>
						<textarea class="nestform-admin__input nestform-admin__textarea" rows="3" name="nestform[settings][branch_rules]" data-nestform-branch-rules hidden><?php echo esc_textarea( (string) ( $settings['branch_rules'] ?? '' ) ); ?></textarea>
						<div class="nestform-branch" data-nestform-branch-ui>
							<ul class="nestform-branch__list" data-nestform-branch-list></ul>
							<div class="nestform-branch__add">
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'From step', 'nestform' ); ?></span>
									<select class="nestform-admin__input" data-nestform-branch-from></select>
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'Field', 'nestform' ); ?></span>
									<select class="nestform-admin__input" data-nestform-branch-field></select>
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'Operator', 'nestform' ); ?></span>
									<select class="nestform-admin__input" data-nestform-branch-op>
										<?php foreach ( Nestform_Form_Config::condition_operators() as $op_key => $op_label ) : ?>
											<option value="<?php echo esc_attr( $op_key ); ?>"><?php echo esc_html( $op_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'Value', 'nestform' ); ?></span>
									<input type="text" class="nestform-admin__input" data-nestform-branch-value placeholder="<?php esc_attr_e( 'Value', 'nestform' ); ?>" />
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'To step', 'nestform' ); ?></span>
									<select class="nestform-admin__input" data-nestform-branch-to></select>
								</label>
								<div class="nestform-branch__add-action">
									<span class="nestform-admin__label" aria-hidden="true">&nbsp;</span>
									<button type="button" class="nestform-btn nestform-btn--outline" data-nestform-branch-add>
										<?php nestform_admin_icon( 'plus' ); ?>
										<?php esc_html_e( 'Add rule', 'nestform' ); ?>
									</button>
								</div>
							</div>
							<p class="nestform-branch__hint" data-nestform-branch-hint hidden></p>
						</div>
					</details>
					<textarea class="nestform-admin__input" hidden name="nestform[settings][step_labels]" data-nestform-step-labels><?php echo esc_textarea( (string) ( $settings['step_labels'] ?? '' ) ); ?></textarea>
				</div>

				<div class="nestform-admin__surface nestform-admin__quick-add" data-nestform-quick-add>
					<?php
					$favorite_keys = array( 'text', 'email', 'tel', 'textarea', 'select' );
					$more_groups   = array(
						__( 'Text & data', 'nestform' ) => array( 'url', 'password', 'number', 'range', 'date', 'time' ),
						__( 'Choices', 'nestform' )     => array( 'radio', 'checkboxes', 'checkbox', 'acceptance' ),
						__( 'Other', 'nestform' )       => array( 'file', 'hidden' ),
					);
					$pro_teasers = class_exists( 'Nestform_Features' )
						? Nestform_Features::advanced_field_teasers()
						: array(
							'rating'    => __( 'Rating', 'nestform' ),
							'signature' => __( 'Signature', 'nestform' ),
						);
					$specialty_teasers = class_exists( 'Nestform_Features' )
						? Nestform_Features::specialty_field_teasers()
						: array(
							'calculated' => __( 'Calculated', 'nestform' ),
							'repeater'   => __( 'Repeater', 'nestform' ),
						);
					$pro_menu_types = array_merge( $pro_teasers, $specialty_teasers );
					?>
					<div class="nestform-add">
						<div class="nestform-add__main">
							<button
								type="button"
								class="nestform-add__label"
								data-nestform-add-browse
								aria-haspopup="true"
								aria-expanded="false"
								aria-controls="nestform-add-more-panel"
								title="<?php esc_attr_e( 'Browse all field types', 'nestform' ); ?>"
							>
								<span class="nestform-add__icon" aria-hidden="true">
									<svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
										<rect width="22" height="22" rx="6" fill="#2563eb"/>
										<path d="M11 6.5v9M6.5 11h9" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
									</svg>
								</span>
								<?php esc_html_e( 'Add field', 'nestform' ); ?>
							</button>
							<div class="nestform-add__favorites" role="group" aria-label="<?php esc_attr_e( 'Common fields', 'nestform' ); ?>">
								<?php foreach ( $favorite_keys as $type_key ) : ?>
									<?php if ( isset( $input_types[ $type_key ] ) ) : ?>
										<button type="button" class="nestform-admin__chip nestform-add__chip" data-nestform-add-type="<?php echo esc_attr( $type_key ); ?>">
											<?php echo esc_html( $input_types[ $type_key ] ); ?>
										</button>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
							<div class="nestform-add__menus">
								<div class="nestform-add-menu" data-nestform-add-menu="more">
									<button
										type="button"
										class="nestform-add-menu__toggle"
										aria-expanded="false"
										aria-haspopup="true"
										data-nestform-add-menu-toggle
									>
										<?php esc_html_e( 'More fields', 'nestform' ); ?>
									</button>
									<div
										id="nestform-add-more-panel"
										class="nestform-add-menu__panel"
										data-nestform-add-menu-panel
										hidden
									>
										<label class="nestform-add-menu__search">
											<span class="screen-reader-text"><?php esc_html_e( 'Search fields', 'nestform' ); ?></span>
											<input type="search" class="nestform-admin__input" placeholder="<?php esc_attr_e( 'Search fields…', 'nestform' ); ?>" data-nestform-add-search autocomplete="off" />
										</label>
										<?php foreach ( $more_groups as $group_label => $type_keys ) : ?>
											<div class="nestform-add-menu__group">
												<span class="nestform-add-menu__group-label"><?php echo esc_html( $group_label ); ?></span>
												<div class="nestform-add-menu__list">
													<?php foreach ( $type_keys as $type_key ) : ?>
														<?php if ( isset( $input_types[ $type_key ] ) ) : ?>
															<button type="button" class="nestform-add-menu__item" data-nestform-add-type="<?php echo esc_attr( $type_key ); ?>">
																<?php echo esc_html( $input_types[ $type_key ] ); ?>
															</button>
														<?php endif; ?>
													<?php endforeach; ?>
												</div>
											</div>
										<?php endforeach; ?>
										<?php if ( array() !== $pro_menu_types ) : ?>
											<div class="nestform-add-menu__group">
												<span class="nestform-add-menu__group-label"><?php esc_html_e( 'Pro fields', 'nestform' ); ?></span>
												<div class="nestform-add-menu__list">
													<?php foreach ( $pro_menu_types as $pro_key => $pro_label ) : ?>
														<?php if ( isset( $input_types[ $pro_key ] ) ) : ?>
															<button type="button" class="nestform-add-menu__item" data-nestform-add-type="<?php echo esc_attr( $pro_key ); ?>">
																<?php echo esc_html( $input_types[ $pro_key ] ); ?>
															</button>
														<?php else : ?>
															<button type="button" class="nestform-add-menu__item" data-nestform-pro-field="<?php echo esc_attr( $pro_key ); ?>">
																<?php echo esc_html( $pro_label ); ?>
																<?php echo Nestform_Upgrade::pill_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
															</button>
														<?php endif; ?>
													<?php endforeach; ?>
												</div>
											</div>
										<?php endif; ?>
									</div>
								</div>
								<div class="nestform-add-menu" data-nestform-add-menu="layout">
									<button
										type="button"
										class="nestform-add-menu__toggle"
										aria-expanded="false"
										aria-haspopup="true"
										data-nestform-add-menu-toggle
									>
										<?php esc_html_e( 'Layout', 'nestform' ); ?>
									</button>
									<div class="nestform-add-menu__panel" data-nestform-add-menu-panel hidden>
										<div class="nestform-add-menu__group">
											<span class="nestform-add-menu__group-label"><?php esc_html_e( 'Display only', 'nestform' ); ?></span>
											<div class="nestform-add-menu__list">
												<?php foreach ( $layout_types as $type_key => $type_label ) : ?>
													<button type="button" class="nestform-add-menu__item" data-nestform-add-type="<?php echo esc_attr( $type_key ); ?>">
														<?php echo esc_html( $type_label ); ?>
													</button>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						<span class="nestform-add__hint" data-nestform-add-hint <?php echo $steps_enabled ? '' : 'hidden'; ?>>
							<?php esc_html_e( 'Adds to active step', 'nestform' ); ?>
						</span>
					</div>
				</div>

				<div class="nestform-workspace" data-nestform-workspace data-mode="<?php echo $steps_enabled ? 'steps' : 'flat'; ?>">
					<nav class="nestform-step-nav" data-nestform-step-nav <?php echo $steps_enabled ? '' : 'hidden'; ?> role="tablist" aria-label="<?php esc_attr_e( 'Steps', 'nestform' ); ?>">
						<?php for ( $s = 1; $s <= $max_step; $s++ ) : ?>
							<?php
							$nav_title = isset( $step_labels[ $s ] ) ? $step_labels[ $s ] : sprintf(
								/* translators: %d: step number */
								__( 'Step %d', 'nestform' ),
								$s
							);
							$count_in = isset( $fields_by_step[ $s ] ) ? count( $fields_by_step[ $s ] ) : 0;
							?>
							<button type="button" class="nestform-step-nav__btn<?php echo 1 === $s ? ' is-active' : ''; ?>" data-nestform-step-tab="<?php echo esc_attr( (string) $s ); ?>" role="tab" aria-selected="<?php echo 1 === $s ? 'true' : 'false'; ?>">
								<span class="nestform-step-nav__index"><?php echo esc_html( (string) $s ); ?></span>
								<span class="nestform-step-nav__title" data-nestform-step-nav-title><?php echo esc_html( $nav_title ); ?></span>
								<span class="nestform-step-nav__count" data-nestform-step-nav-count><?php echo esc_html( (string) $count_in ); ?></span>
							</button>
						<?php endfor; ?>
					</nav>

					<div class="nestform-admin__surface nestform-step-groups" data-nestform-step-groups>
						<?php for ( $s = 1; $s <= $max_step; $s++ ) : ?>
							<?php
							$group_title = isset( $step_labels[ $s ] ) ? $step_labels[ $s ] : '';
							$group_fields = $fields_by_step[ $s ] ?? array();
							$show_group = ! $steps_enabled ? ( 1 === $s ) : ( 1 === $s );
							?>
							<section class="nestform-step-group<?php echo $show_group ? ' is-active' : ''; ?>" data-nestform-step-group data-step="<?php echo esc_attr( (string) $s ); ?>" <?php echo ( $steps_enabled && 1 !== $s ) ? 'hidden' : ''; ?>>
								<header class="nestform-step-group__head" data-nestform-step-head <?php echo $steps_enabled ? '' : 'hidden'; ?>>
									<span class="nestform-step-group__badge"><?php echo esc_html( sprintf( /* translators: %d step */ __( 'Step %d', 'nestform' ), $s ) ); ?></span>
									<label class="nestform-step-group__title-wrap">
										<span class="screen-reader-text"><?php esc_html_e( 'Step title', 'nestform' ); ?></span>
										<input type="text" class="nestform-admin__input nestform-step-group__title" value="<?php echo esc_attr( $group_title ); ?>" placeholder="<?php echo esc_attr( sprintf( /* translators: %d */ __( 'Step %d title', 'nestform' ), $s ) ); ?>" data-nestform-step-title />
									</label>
									<button type="button" class="nestform-btn nestform-btn--ghost nestform-btn--danger-text nestform-step-group__remove" data-nestform-remove-step title="<?php esc_attr_e( 'Remove step', 'nestform' ); ?>" <?php echo $max_step <= 1 ? 'hidden' : ''; ?>>
										<?php esc_html_e( 'Remove step', 'nestform' ); ?>
									</button>
								</header>
								<div class="nestform-admin__fields" data-nestform-fields data-step="<?php echo esc_attr( (string) $s ); ?>">
									<?php if ( $steps_enabled ) : ?>
										<?php foreach ( $group_fields as $pair ) : ?>
											<?php self::render_field_row( (int) $pair[0], $pair[1], $types, true ); ?>
										<?php endforeach; ?>
									<?php elseif ( 1 === $s ) : ?>
										<?php foreach ( $fields as $i => $field ) : ?>
											<?php self::render_field_row( (int) $i, $field, $types, true ); ?>
										<?php endforeach; ?>
									<?php endif; ?>
								</div>
								<p class="nestform-admin__empty" data-nestform-empty <?php echo ( $steps_enabled ? empty( $group_fields ) : ( 1 === $s && array() === $fields ) ) ? '' : 'hidden'; ?>>
									<span class="nestform-admin__empty-copy"><?php echo $steps_enabled ? esc_html__( 'No fields on this step yet.', 'nestform' ) : esc_html__( 'No fields yet.', 'nestform' ); ?></span>
									<button type="button" class="nestform-btn nestform-btn--primary nestform-admin__empty-cta" data-nestform-add-browse>
										<?php esc_html_e( 'Add first field', 'nestform' ); ?>
									</button>
								</p>
							</section>
						<?php endfor; ?>
					</div>
				</div>

				<template data-nestform-field-template>
					<?php
					self::render_field_row(
						'__INDEX__',
						array(
							'type'            => 'text',
							'name'            => '',
							'label'           => '',
							'placeholder'     => '',
							'description'     => '',
							'default'         => '',
							'css_class'       => '',
							'required'        => false,
							'width'           => 'full',
							'options'         => '',
							'step'            => 1,
							'condition_field' => '',
							'condition_op'    => 'equals',
							'condition_value' => '',
						),
						$types,
						false
					);
					?>
				</template>
				<template data-nestform-step-group-template>
					<section class="nestform-step-group" data-nestform-step-group data-step="__STEP__" hidden>
						<header class="nestform-step-group__head" data-nestform-step-head>
							<span class="nestform-step-group__badge">Step __STEP__</span>
							<label class="nestform-step-group__title-wrap">
								<span class="screen-reader-text"><?php esc_html_e( 'Step title', 'nestform' ); ?></span>
								<input type="text" class="nestform-admin__input nestform-step-group__title" value="" placeholder="<?php esc_attr_e( 'Step title', 'nestform' ); ?>" data-nestform-step-title />
							</label>
							<button type="button" class="nestform-btn nestform-btn--ghost nestform-btn--danger-text nestform-step-group__remove" data-nestform-remove-step><?php esc_html_e( 'Remove step', 'nestform' ); ?></button>
						</header>
						<div class="nestform-admin__fields" data-nestform-fields data-step="__STEP__"></div>
						<p class="nestform-admin__empty" data-nestform-empty>
							<span class="nestform-admin__empty-copy"><?php esc_html_e( 'No fields on this step yet.', 'nestform' ); ?></span>
							<button type="button" class="nestform-btn nestform-btn--primary nestform-admin__empty-cta" data-nestform-add-browse>
								<?php esc_html_e( 'Add first field', 'nestform' ); ?>
							</button>
						</p>
					</section>
				</template>
			</div>

			<div class="nestform-admin__panel<?php echo 'messages' === $active_tab ? ' is-active' : ''; ?>" data-nestform-panel="messages" id="nestform-panel-messages" role="tabpanel" aria-labelledby="nestform-tab-messages"<?php echo 'messages' === $active_tab ? '' : ' hidden'; ?>>
				<div class="nestform-admin__panel-head">
					<div>
						<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Messages', 'nestform' ); ?></h3>
						<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Shown after submit and on validation errors (front + AJAX).', 'nestform' ); ?></p>
					</div>
				</div>
				<div class="nestform-admin__surface">
					<?php
					$message_primary = array(
						'success'       => array( __( 'Success', 'nestform' ), __( 'After a valid submission. Supports {field_name} merge tags. Display mode is under Settings.', 'nestform' ) ),
						'required'      => array( __( 'Required field', 'nestform' ), __( 'Empty required field.', 'nestform' ) ),
						'invalid_email' => array( __( 'Invalid email', 'nestform' ), __( 'Email format check failed.', 'nestform' ) ),
						'error_generic' => array( __( 'Generic error', 'nestform' ), __( 'Fallback when something fails.', 'nestform' ) ),
					);
					$message_more    = array(
						'invalid_tel'     => array( __( 'Invalid phone', 'nestform' ), __( 'Phone format check failed.', 'nestform' ) ),
						'invalid_url'     => array( __( 'Invalid URL', 'nestform' ), __( 'URL format check failed.', 'nestform' ) ),
						'invalid_number'  => array( __( 'Invalid number', 'nestform' ), __( 'Number format check failed.', 'nestform' ) ),
						'invalid_date'    => array( __( 'Invalid date', 'nestform' ), __( 'Date format check failed (YYYY-MM-DD).', 'nestform' ) ),
						'invalid_time'    => array( __( 'Invalid time', 'nestform' ), __( 'Time format check failed (HH:MM).', 'nestform' ) ),
						'rate_limited'    => array( __( 'Rate limited', 'nestform' ), __( 'Too many submits from one IP.', 'nestform' ) ),
						'invalid_captcha' => array( __( 'Captcha failed', 'nestform' ), __( 'When a captcha hook rejects the submit.', 'nestform' ) ),
						'invalid_file'    => array( __( 'Invalid file', 'nestform' ), __( 'Wrong type or upload failed.', 'nestform' ) ),
						'file_too_large'  => array( __( 'File too large', 'nestform' ), __( 'Exceeds the max size for this field.', 'nestform' ) ),
						'too_many_files'  => array( __( 'Too many files', 'nestform' ), __( 'Exceeds max files for this field.', 'nestform' ) ),
					);
					?>
					<div class="nestform-admin__grid">
						<?php foreach ( $message_primary as $key => $meta ) : ?>
							<label class="nestform-admin__field-control<?php echo 'success' === $key ? ' nestform-admin__field-control--full' : ''; ?>">
								<span class="nestform-admin__label">
									<?php echo esc_html( $meta[0] ); ?>
									<?php self::render_field_tip( $meta[1] ); ?>
								</span>
								<?php if ( 'success' === $key ) : ?>
									<textarea class="nestform-admin__input nestform-admin__textarea" name="nestform[messages][<?php echo esc_attr( $key ); ?>]" rows="3" placeholder="<?php esc_attr_e( 'Thank you. Your message has been sent.', 'nestform' ); ?>"><?php echo esc_textarea( $messages[ $key ] ?? '' ); ?></textarea>
								<?php else : ?>
									<input type="text" class="nestform-admin__input" name="nestform[messages][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $messages[ $key ] ?? '' ); ?>" />
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
					</div>
					<details class="nestform-admin__more">
						<summary><?php esc_html_e( 'More validation messages', 'nestform' ); ?></summary>
						<div class="nestform-admin__more-body nestform-admin__grid">
							<?php foreach ( $message_more as $key => $meta ) : ?>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label">
										<?php echo esc_html( $meta[0] ); ?>
										<?php self::render_field_tip( $meta[1] ); ?>
									</span>
									<input type="text" class="nestform-admin__input" name="nestform[messages][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $messages[ $key ] ?? '' ); ?>" />
								</label>
							<?php endforeach; ?>
						</div>
					</details>
				</div>
			</div>

			<?php
			$can_email          = class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::EMAIL_DESIGNER );
			$html_on            = $can_email && '1' === (string) ( $mail['html_enabled'] ?? '0' );
			$can_pdf            = class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::PDF_EXPORT );
			$user_mail_open     = '1' === (string) ( $mail['user_mail_enabled'] ?? '0' );
			$mail_advanced_open = ( '1' === (string) ( $mail['extra_enabled'] ?? '0' )
				|| (string) ( $mail['cc'] ?? '' ) !== ''
				|| (string) ( $mail['bcc'] ?? '' ) !== '' );
			?>
			<div class="nestform-admin__panel<?php echo 'mail' === $active_tab ? ' is-active' : ''; ?>" data-nestform-panel="mail" id="nestform-panel-mail" role="tabpanel" aria-labelledby="nestform-tab-mail"<?php echo 'mail' === $active_tab ? '' : ' hidden'; ?>>
				<div class="nestform-admin__panel-head">
					<div>
						<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Mail', 'nestform' ); ?></h3>
						<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Sent via wp_mail. Use WP Mail SMTP (or similar) for delivery — no SMTP settings here.', 'nestform' ); ?></p>
					</div>
				</div>

				<div class="nestform-admin__stack">
					<section class="nestform-admin__block nestform-admin__block--primary">
						<h4 class="nestform-admin__section-title"><?php esc_html_e( 'Notification', 'nestform' ); ?></h4>
						<p class="nestform-admin__section-desc"><?php esc_html_e( 'Email you receive when someone submits the form.', 'nestform' ); ?></p>
						<div class="nestform-admin__grid nestform-admin__grid--2">
							<label class="nestform-admin__field-control">
								<span class="nestform-admin__label">
									<?php esc_html_e( 'To', 'nestform' ); ?>
									<?php self::render_field_tip( __( 'Comma-separated addresses.', 'nestform' ) ); ?>
								</span>
								<input type="text" class="nestform-admin__input" name="nestform[mail][to]" value="<?php echo esc_attr( $mail['to'] ); ?>" placeholder="you@example.com" />
							</label>
							<label class="nestform-admin__field-control">
								<span class="nestform-admin__label"><?php esc_html_e( 'Subject', 'nestform' ); ?></span>
								<input type="text" class="nestform-admin__input" name="nestform[mail][subject]" value="<?php echo esc_attr( $mail['subject'] ); ?>" />
							</label>
							<label class="nestform-admin__field-control">
								<span class="nestform-admin__label"><?php esc_html_e( 'From name', 'nestform' ); ?></span>
								<input type="text" class="nestform-admin__input" name="nestform[mail][from_name]" value="<?php echo esc_attr( $mail['from_name'] ); ?>" />
							</label>
							<label class="nestform-admin__field-control">
								<span class="nestform-admin__label">
									<?php esc_html_e( 'Reply-To field', 'nestform' ); ?>
									<?php self::render_field_tip( __( 'Field name that holds the visitor email.', 'nestform' ) ); ?>
								</span>
								<input type="text" class="nestform-admin__input" name="nestform[mail][reply_to_field]" value="<?php echo esc_attr( $mail['reply_to_field'] ); ?>" placeholder="email" />
							</label>
						</div>
					</section>

					<section class="nestform-admin__block">
						<h4 class="nestform-admin__section-title"><?php esc_html_e( 'Message body', 'nestform' ); ?></h4>
						<p class="nestform-admin__section-desc"><?php esc_html_e( 'Tokens like {email} or {all_fields}. Repeater: {#items}…{field}…{/items}.', 'nestform' ); ?></p>
						<div class="nestform-admin__field-control nestform-admin__field-control--full">
							<span class="nestform-admin__label">
								<?php esc_html_e( 'Body template', 'nestform' ); ?>
								<?php self::render_field_tip( __( 'Placeholders: {field_name}, {all_fields}, {form_title}, {form_id}. Repeater loops: {#items}…{price}…{/items}.', 'nestform' ) ); ?>
							</span>
							<?php if ( $can_email ) : ?>
								<div class="nestform-mail-designer" data-nestform-mail-designer>
									<?php
									$logo_id  = (int) ( $mail['logo_id'] ?? 0 );
									$logo_url = $logo_id > 0 ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
									?>
									<div class="nestform-mail-designer__toolbar">
										<div class="nestform-mail-designer__group nestform-mail-designer__group--mode">
											<label class="nestform-admin__check">
												<input type="hidden" name="nestform[mail][html_enabled]" value="0" />
												<input type="checkbox" name="nestform[mail][html_enabled]" value="1" <?php checked( $html_on ); ?> data-nestform-mail-html />
												<span><?php esc_html_e( 'HTML email', 'nestform' ); ?></span>
											</label>
										</div>
										<div class="nestform-mail-designer__group nestform-mail-designer__group--brand">
											<input type="hidden" name="nestform[mail][logo_id]" value="<?php echo esc_attr( (string) $logo_id ); ?>" data-nestform-mail-logo-id />
											<button type="button" class="nestform-btn nestform-btn--outline" data-nestform-mail-logo><?php esc_html_e( 'Set logo', 'nestform' ); ?></button>
											<button type="button" class="nestform-btn nestform-btn--ghost nestform-btn--danger-text" data-nestform-mail-logo-clear<?php echo $logo_id ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'nestform' ); ?></button>
											<span class="nestform-mail-designer__logo-preview" data-nestform-mail-logo-preview>
												<?php if ( $logo_url ) : ?>
													<img src="<?php echo esc_url( $logo_url ); ?>" alt="" />
												<?php endif; ?>
											</span>
										</div>
										<div class="nestform-mail-designer__group nestform-mail-designer__group--layout">
											<label class="nestform-mail-designer__preset">
												<span class="screen-reader-text"><?php esc_html_e( 'Layout preset', 'nestform' ); ?></span>
												<select class="nestform-admin__input" data-nestform-mail-preset>
													<option value=""><?php esc_html_e( 'Layout presets…', 'nestform' ); ?></option>
													<?php foreach ( Nestform_Mail_Html::presets() as $pkey => $preset ) : ?>
														<option value="<?php echo esc_attr( $pkey ); ?>"><?php echo esc_html( $preset['label'] ); ?></option>
													<?php endforeach; ?>
												</select>
											</label>
										</div>
										<div class="nestform-mail-designer__group nestform-mail-designer__group--actions">
											<button type="button" class="nestform-btn nestform-btn--outline" data-nestform-mail-token="{all_fields}"><?php esc_html_e( 'Insert {all_fields}', 'nestform' ); ?></button>
											<button type="button" class="nestform-btn nestform-btn--primary" data-nestform-mail-preview><?php esc_html_e( 'Preview', 'nestform' ); ?></button>
										</div>
									</div>
									<?php
									wp_editor(
										(string) $mail['body_template'],
										'nestform_mail_body_template',
										array(
											'textarea_name' => 'nestform[mail][body_template]',
											'textarea_rows' => 12,
											'media_buttons' => true,
											'teeny'         => false,
											'quicktags'     => true,
											'tinymce'       => array(
												'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,forecolor,backcolor,removeformat',
												'toolbar2' => '',
											),
										)
									);
									?>
									<div class="nestform-mail-preview" data-nestform-mail-preview-panel hidden>
										<button type="button" class="nestform-mail-preview__backdrop" data-nestform-mail-preview-close aria-label="<?php esc_attr_e( 'Close preview', 'nestform' ); ?>"></button>
										<div class="nestform-mail-preview__dialog" role="dialog" aria-modal="true" aria-labelledby="nestform-mail-preview-title">
											<header class="nestform-mail-preview__head">
												<div class="nestform-mail-preview__copy">
													<strong id="nestform-mail-preview-title"><?php esc_html_e( 'Email preview', 'nestform' ); ?></strong>
													<span class="description"><?php esc_html_e( 'Sample merge tags filled in — not a real send.', 'nestform' ); ?></span>
												</div>
												<button type="button" class="button" data-nestform-mail-preview-close><?php esc_html_e( 'Close', 'nestform' ); ?></button>
											</header>
											<iframe class="nestform-mail-preview__frame" title="<?php esc_attr_e( 'Email preview', 'nestform' ); ?>" data-nestform-mail-preview-frame></iframe>
										</div>
									</div>
								</div>
								<script type="application/json" id="nestform-mail-presets"><?php echo wp_json_encode( Nestform_Mail_Html::presets() ); ?></script>
							<?php else : ?>
								<input type="hidden" name="nestform[mail][html_enabled]" value="0" />
								<input type="hidden" name="nestform[mail][logo_id]" value="0" />
								<textarea class="nestform-admin__input nestform-admin__textarea" rows="8" name="nestform[mail][body_template]"><?php echo esc_textarea( $mail['body_template'] ); ?></textarea>
								<p class="description">
									<button type="button" class="button-link" data-nestform-pro-upsell="email"><?php esc_html_e( 'Upgrade to Pro for the HTML email designer', 'nestform' ); ?></button>
								</p>
							<?php endif; ?>
						</div>
						<label class="nestform-admin__check nestform-admin__check--full" style="margin-top:12px">
							<input type="hidden" name="nestform[mail][pdf_attach]" value="0" />
							<input type="checkbox" name="nestform[mail][pdf_attach]" value="1" <?php checked( $can_pdf && '1' === (string) ( $mail['pdf_attach'] ?? '0' ) ); ?> <?php disabled( ! $can_pdf ); ?> />
							<span>
								<?php esc_html_e( 'Attach PDF of the submission to this notification', 'nestform' ); ?>
								<?php self::render_field_tip( __( 'With HTML email on, the PDF uses your message body template. Otherwise it is a field report.', 'nestform' ) ); ?>
								<?php if ( ! $can_pdf ) : ?>
									<span class="nestform-admin__pro-pill"><?php esc_html_e( 'Pro', 'nestform' ); ?></span>
								<?php endif; ?>
							</span>
						</label>
					</section>

					<details class="nestform-admin__fold" <?php echo $user_mail_open ? 'open' : ''; ?>>
						<summary>
							<span class="nestform-admin__fold-chevron" aria-hidden="true"></span>
							<span class="nestform-admin__fold-copy">
								<span class="nestform-admin__fold-title"><?php esc_html_e( 'Visitor confirmation', 'nestform' ); ?></span>
								<span class="nestform-admin__fold-hint"><?php esc_html_e( 'Optional autoreply to the visitor\'s Reply-To email.', 'nestform' ); ?></span>
							</span>
						</summary>
						<div class="nestform-admin__fold-body">
							<label class="nestform-admin__check">
								<input type="hidden" name="nestform[mail][user_mail_enabled]" value="0" />
								<input type="checkbox" name="nestform[mail][user_mail_enabled]" value="1" <?php checked( $user_mail_open ); ?> />
								<span><?php esc_html_e( 'Send a confirmation email to the Reply-To field', 'nestform' ); ?></span>
							</label>
							<div class="nestform-admin__grid nestform-admin__grid--2" style="margin-top:12px">
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'Autoreply subject', 'nestform' ); ?></span>
									<input type="text" class="nestform-admin__input" name="nestform[mail][user_mail_subject]" value="<?php echo esc_attr( (string) ( $mail['user_mail_subject'] ?? '' ) ); ?>" />
								</label>
								<label class="nestform-admin__field-control nestform-admin__field-control--full">
									<span class="nestform-admin__label"><?php esc_html_e( 'Autoreply body', 'nestform' ); ?></span>
									<textarea class="nestform-admin__input nestform-admin__textarea" rows="5" name="nestform[mail][user_mail_body]"><?php echo esc_textarea( (string) ( $mail['user_mail_body'] ?? '' ) ); ?></textarea>
								</label>
							</div>
						</div>
					</details>

					<details class="nestform-admin__fold" <?php echo $mail_advanced_open ? 'open' : ''; ?>>
						<summary>
							<span class="nestform-admin__fold-chevron" aria-hidden="true"></span>
							<span class="nestform-admin__fold-copy">
								<span class="nestform-admin__fold-title"><?php esc_html_e( 'Advanced', 'nestform' ); ?></span>
								<span class="nestform-admin__fold-hint"><?php esc_html_e( 'CC/BCC and a second notification when a field matches.', 'nestform' ); ?></span>
							</span>
						</summary>
						<div class="nestform-admin__fold-body">
							<h5 class="nestform-admin__subsection-title"><?php esc_html_e( 'Copies', 'nestform' ); ?></h5>
							<div class="nestform-admin__grid nestform-admin__grid--2">
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'CC', 'nestform' ); ?></span>
									<input type="text" class="nestform-admin__input" name="nestform[mail][cc]" value="<?php echo esc_attr( (string) ( $mail['cc'] ?? '' ) ); ?>" placeholder="cc@example.com" />
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'BCC', 'nestform' ); ?></span>
									<input type="text" class="nestform-admin__input" name="nestform[mail][bcc]" value="<?php echo esc_attr( (string) ( $mail['bcc'] ?? '' ) ); ?>" placeholder="bcc@example.com" />
								</label>
							</div>
							<h5 class="nestform-admin__subsection-title"><?php esc_html_e( 'Extra notification', 'nestform' ); ?></h5>
							<label class="nestform-admin__check">
								<input type="hidden" name="nestform[mail][extra_enabled]" value="0" />
								<input type="checkbox" name="nestform[mail][extra_enabled]" value="1" <?php checked( (string) ( $mail['extra_enabled'] ?? '0' ), '1' ); ?> />
								<span><?php esc_html_e( 'Enable extra notification', 'nestform' ); ?></span>
							</label>
							<div class="nestform-admin__grid nestform-admin__grid--2" style="margin-top:12px">
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'Extra To', 'nestform' ); ?></span>
									<input type="text" class="nestform-admin__input" name="nestform[mail][extra_to]" value="<?php echo esc_attr( (string) ( $mail['extra_to'] ?? '' ) ); ?>" />
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'Extra subject', 'nestform' ); ?></span>
									<input type="text" class="nestform-admin__input" name="nestform[mail][extra_subject]" value="<?php echo esc_attr( (string) ( $mail['extra_subject'] ?? '' ) ); ?>" />
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'If field', 'nestform' ); ?></span>
									<select class="nestform-admin__input" name="nestform[mail][extra_condition_field]" data-nestform-extra-condition-field>
										<option value=""><?php esc_html_e( '— Always (when enabled) —', 'nestform' ); ?></option>
										<?php
										$extra_field = (string) ( $mail['extra_condition_field'] ?? '' );
										if ( $extra_field !== '' ) :
											?>
											<option value="<?php echo esc_attr( $extra_field ); ?>" selected><?php echo esc_html( $extra_field ); ?></option>
										<?php endif; ?>
									</select>
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'Operator', 'nestform' ); ?></span>
									<select class="nestform-admin__input" name="nestform[mail][extra_condition_op]">
										<?php
										$extra_op = (string) ( $mail['extra_condition_op'] ?? 'equals' );
										foreach ( Nestform_Form_Config::condition_operators() as $op_key => $op_label ) :
											?>
											<option value="<?php echo esc_attr( $op_key ); ?>" <?php selected( $extra_op, $op_key ); ?>><?php echo esc_html( $op_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'Value', 'nestform' ); ?></span>
									<input type="text" class="nestform-admin__input" name="nestform[mail][extra_condition_value]" value="<?php echo esc_attr( (string) ( $mail['extra_condition_value'] ?? '' ) ); ?>" />
								</label>
								<label class="nestform-admin__field-control nestform-admin__field-control--full">
									<span class="nestform-admin__label"><?php esc_html_e( 'Extra body', 'nestform' ); ?></span>
									<textarea class="nestform-admin__input nestform-admin__textarea" rows="5" name="nestform[mail][extra_body]"><?php echo esc_textarea( (string) ( $mail['extra_body'] ?? '' ) ); ?></textarea>
								</label>
							</div>
						</div>
					</details>
				</div>
			</div>

			<?php
			$form_mode_val      = (string) ( $settings['form_mode'] ?? 'form' );
			$settings_spam_open = ( '1' === (string) ( $settings['enable_captcha'] ?? '0' )
				|| '1' === (string) ( $settings['enable_akismet'] ?? '0' )
				|| '0' === (string) ( $settings['store_ip'] ?? '1' ) );
			$settings_quiz_open = in_array( $form_mode_val, array( 'quiz', 'survey' ), true );
			$settings_hook_open = '1' === (string) ( $settings['webhook_enabled'] ?? '0' );
			$settings_auto_open = '1' === (string) ( $settings['automation_enabled'] ?? '0' );
			$captcha_status     = class_exists( 'Nestform_Captcha' ) ? Nestform_Captcha::admin_status() : array(
				'global_on' => false,
				'message'   => '',
				'url'       => '',
			);
			?>
			<div class="nestform-admin__panel<?php echo 'settings' === $active_tab ? ' is-active' : ''; ?>" data-nestform-panel="settings" id="nestform-panel-settings" role="tabpanel" aria-labelledby="nestform-tab-settings"<?php echo 'settings' === $active_tab ? '' : ' hidden'; ?>>
				<div class="nestform-admin__panel-head">
					<div>
						<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Settings', 'nestform' ); ?></h3>
						<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Submit behavior, spam protection, and optional Pro integrations.', 'nestform' ); ?></p>
					</div>
				</div>

				<div class="nestform-admin__stack">
					<section class="nestform-admin__block nestform-admin__block--primary">
						<h4 class="nestform-admin__section-title"><?php esc_html_e( 'Submit & thank-you', 'nestform' ); ?></h4>
						<p class="nestform-admin__section-desc"><?php esc_html_e( 'Button label, where the success message appears, and optional redirect.', 'nestform' ); ?></p>
						<div class="nestform-admin__grid nestform-admin__grid--2">
							<label class="nestform-admin__field-control">
								<span class="nestform-admin__label"><?php esc_html_e( 'Submit button label', 'nestform' ); ?></span>
								<input type="text" class="nestform-admin__input" name="nestform[settings][submit_label]" value="<?php echo esc_attr( $settings['submit_label'] ); ?>" />
							</label>
							<label class="nestform-admin__field-control">
								<span class="nestform-admin__label">
									<?php esc_html_e( 'Thank-you display', 'nestform' ); ?>
									<?php self::render_field_tip( __( 'Where to show the success message from the Messages tab. Redirect (if set) still runs after.', 'nestform' ) ); ?>
								</span>
								<?php $success_display = (string) ( $settings['success_display'] ?? 'inline' ); ?>
								<select class="nestform-admin__input" name="nestform[settings][success_display]">
									<option value="inline" <?php selected( $success_display, 'inline' ); ?>><?php esc_html_e( 'Below the form', 'nestform' ); ?></option>
									<option value="replace" <?php selected( $success_display, 'replace' ); ?>><?php esc_html_e( 'Replace the form', 'nestform' ); ?></option>
									<option value="popup" <?php selected( $success_display, 'popup' ); ?>><?php esc_html_e( 'Popup', 'nestform' ); ?></option>
								</select>
							</label>
							<label class="nestform-admin__field-control nestform-admin__field-control--full">
								<span class="nestform-admin__label">
									<?php esc_html_e( 'Redirect URL', 'nestform' ); ?>
									<?php self::render_field_tip( __( 'Optional. Leave empty to stay on the page and show the success message.', 'nestform' ) ); ?>
								</span>
								<input type="url" class="nestform-admin__input" name="nestform[settings][redirect_url]" value="<?php echo esc_attr( $settings['redirect_url'] ); ?>" placeholder="https://" />
							</label>
						</div>
					</section>

					<details class="nestform-admin__fold" <?php echo $settings_spam_open ? 'open' : ''; ?>>
						<summary>
							<span class="nestform-admin__fold-chevron" aria-hidden="true"></span>
							<span class="nestform-admin__fold-copy">
								<span class="nestform-admin__fold-title"><?php esc_html_e( 'Spam & privacy', 'nestform' ); ?></span>
								<span class="nestform-admin__fold-hint"><?php esc_html_e( 'Captcha, time trap, Akismet, and IP storage.', 'nestform' ); ?></span>
							</span>
						</summary>
						<div class="nestform-admin__fold-body">
							<div class="nestform-admin__grid nestform-admin__grid--2">
								<label class="nestform-admin__check nestform-admin__field-control--full" style="margin-top:0">
									<input type="hidden" name="nestform[settings][enable_captcha]" value="0" />
									<input type="checkbox" name="nestform[settings][enable_captcha]" value="1" <?php checked( (string) ( $settings['enable_captcha'] ?? '0' ), '1' ); ?> />
									<span><?php esc_html_e( 'Enable reCAPTCHA on this form', 'nestform' ); ?></span>
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label">
										<?php esc_html_e( 'Time trap (seconds)', 'nestform' ); ?>
										<?php self::render_field_tip( __( 'Reject (silently) submits faster than this. 0 = off.', 'nestform' ) ); ?>
									</span>
									<input type="number" min="0" max="60" class="nestform-admin__input" name="nestform[settings][time_trap_seconds]" value="<?php echo esc_attr( (string) ( $settings['time_trap_seconds'] ?? '3' ) ); ?>" />
								</label>
								<label class="nestform-admin__check nestform-admin__field-control--full">
									<input type="hidden" name="nestform[settings][enable_akismet]" value="0" />
									<input type="checkbox" name="nestform[settings][enable_akismet]" value="1" <?php checked( (string) ( $settings['enable_akismet'] ?? '0' ), '1' ); ?> />
									<span><?php esc_html_e( 'Check with Akismet (if plugin active)', 'nestform' ); ?></span>
								</label>
								<label class="nestform-admin__check nestform-admin__field-control--full">
									<input type="hidden" name="nestform[settings][store_ip]" value="0" />
									<input type="checkbox" name="nestform[settings][store_ip]" value="1" <?php checked( (string) ( $settings['store_ip'] ?? '1' ), '1' ); ?> />
									<span><?php esc_html_e( 'Store visitor IP with entries', 'nestform' ); ?></span>
								</label>
							</div>
							<div class="nestform-admin__note<?php echo empty( $captcha_status['global_on'] ) ? ' nestform-admin__note--warn' : ''; ?>">
								<strong><?php esc_html_e( 'Captcha keys', 'nestform' ); ?></strong>
								<p><?php echo esc_html( (string) ( $captcha_status['message'] ?? '' ) ); ?></p>
								<?php if ( ! empty( $captcha_status['url'] ) ) : ?>
									<p><a href="<?php echo esc_url( (string) $captcha_status['url'] ); ?>"><?php esc_html_e( 'Open Integrations', 'nestform' ); ?></a></p>
								<?php endif; ?>
								<p><?php esc_html_e( 'Also built-in: nonce, honeypot, IP rate limit.', 'nestform' ); ?></p>
							</div>
						</div>
					</details>

					<details class="nestform-admin__fold" <?php echo $settings_quiz_open ? 'open' : ''; ?>>
						<summary>
							<span class="nestform-admin__fold-chevron" aria-hidden="true"></span>
							<span class="nestform-admin__fold-copy">
								<span class="nestform-admin__fold-title">
									<?php esc_html_e( 'Quiz & survey', 'nestform' ); ?>
									<?php
									if ( ! $can_quiz ) {
										echo ' ' . Nestform_Upgrade::pill_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									}
									?>
								</span>
								<span class="nestform-admin__fold-hint"><?php echo $can_quiz ? esc_html__( 'Scoring, result messages, timer, attempts, resume, and shareable results.', 'nestform' ) : esc_html__( 'Available in Nestform Pro.', 'nestform' ); ?></span>
							</span>
						</summary>
						<div class="nestform-admin__fold-body">
							<?php if ( $can_quiz ) : ?>
							<div class="nestform-admin__grid nestform-admin__grid--2">
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'Form mode', 'nestform' ); ?></span>
									<select class="nestform-admin__input" name="nestform[settings][form_mode]">
										<option value="form" <?php selected( $form_mode_val, 'form' ); ?>><?php esc_html_e( 'Standard form', 'nestform' ); ?></option>
										<option value="quiz" <?php selected( $form_mode_val, 'quiz' ); ?>><?php esc_html_e( 'Quiz (scored)', 'nestform' ); ?></option>
										<option value="survey" <?php selected( $form_mode_val, 'survey' ); ?>><?php esc_html_e( 'Survey', 'nestform' ); ?></option>
									</select>
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'Timer (seconds)', 'nestform' ); ?></span>
									<input type="number" min="0" max="7200" class="nestform-admin__input" name="nestform[settings][quiz_timer_seconds]" value="<?php echo esc_attr( (string) ( $settings['quiz_timer_seconds'] ?? '0' ) ); ?>" />
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'Max attempts', 'nestform' ); ?></span>
									<input type="number" min="0" max="50" class="nestform-admin__input" name="nestform[settings][quiz_max_attempts]" value="<?php echo esc_attr( (string) ( $settings['quiz_max_attempts'] ?? '0' ) ); ?>" />
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'Limit attempts by', 'nestform' ); ?></span>
									<select class="nestform-admin__input" name="nestform[settings][quiz_attempt_by]">
										<option value="ip" <?php selected( (string) ( $settings['quiz_attempt_by'] ?? 'ip' ), 'ip' ); ?>><?php esc_html_e( 'IP address', 'nestform' ); ?></option>
										<option value="email" <?php selected( (string) ( $settings['quiz_attempt_by'] ?? '' ), 'email' ); ?>><?php esc_html_e( 'Email field', 'nestform' ); ?></option>
									</select>
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'Attempt email field', 'nestform' ); ?></span>
									<input type="text" class="nestform-admin__input" name="nestform[settings][quiz_attempt_field]" value="<?php echo esc_attr( (string) ( $settings['quiz_attempt_field'] ?? 'email' ) ); ?>" placeholder="email" />
								</label>
								<div class="nestform-admin__field-control nestform-admin__field-control--full">
									<span class="nestform-admin__label">
										<?php esc_html_e( 'Result messages', 'nestform' ); ?>
										<?php self::render_field_tip( __( 'After the quiz, show a message based on the score percent (0–100). First matching range wins.', 'nestform' ) ); ?>
									</span>
									<?php
									$quiz_bands = Nestform_Form_Config::parse_quiz_bands( (string) ( $settings['quiz_results'] ?? '' ) );
									if ( array() === $quiz_bands ) {
										$quiz_bands = array(
											array(
												'min'      => '0',
												'max'      => '49',
												'title'    => '',
												'message'  => '',
												'redirect' => '',
											),
											array(
												'min'      => '50',
												'max'      => '79',
												'title'    => '',
												'message'  => '',
												'redirect' => '',
											),
											array(
												'min'      => '80',
												'max'      => '100',
												'title'    => '',
												'message'  => '',
												'redirect' => '',
											),
										);
									}
									?>
									<div class="nestform-bands" data-nestform-bands>
										<p class="nestform-bands__lead">
											<?php esc_html_e( 'Example: 0–49 = Keep practicing, 50–79 = Good job, 80–100 = Excellent. Ranges use score %.', 'nestform' ); ?>
										</p>
										<div class="nestform-bands__list" data-nestform-bands-list>
											<?php foreach ( $quiz_bands as $bi => $band ) : ?>
												<div class="nestform-bands__row" data-nestform-bands-row>
													<label class="nestform-admin__field-control nestform-bands__from">
														<span class="nestform-admin__label"><?php esc_html_e( 'From %', 'nestform' ); ?></span>
														<input type="number" min="0" max="100" step="1" class="nestform-admin__input" name="nestform[settings][quiz_bands][<?php echo (int) $bi; ?>][min]" value="<?php echo esc_attr( (string) ( $band['min'] ?? '0' ) ); ?>" placeholder="0" data-nestform-bands-min />
													</label>
													<label class="nestform-admin__field-control nestform-bands__to">
														<span class="nestform-admin__label"><?php esc_html_e( 'To %', 'nestform' ); ?></span>
														<input type="number" min="0" max="100" step="1" class="nestform-admin__input" name="nestform[settings][quiz_bands][<?php echo (int) $bi; ?>][max]" value="<?php echo esc_attr( (string) ( $band['max'] ?? '100' ) ); ?>" placeholder="100" data-nestform-bands-max />
													</label>
													<label class="nestform-admin__field-control nestform-bands__title">
														<span class="nestform-admin__label"><?php esc_html_e( 'Title', 'nestform' ); ?></span>
														<input type="text" class="nestform-admin__input" name="nestform[settings][quiz_bands][<?php echo (int) $bi; ?>][title]" value="<?php echo esc_attr( (string) ( $band['title'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Good job', 'nestform' ); ?>" data-nestform-bands-title />
													</label>
													<label class="nestform-admin__field-control nestform-bands__message">
														<span class="nestform-admin__label"><?php esc_html_e( 'Message', 'nestform' ); ?></span>
														<input type="text" class="nestform-admin__input" name="nestform[settings][quiz_bands][<?php echo (int) $bi; ?>][message]" value="<?php echo esc_attr( (string) ( $band['message'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Solid score — keep going.', 'nestform' ); ?>" data-nestform-bands-message />
													</label>
													<label class="nestform-admin__field-control nestform-bands__redirect">
														<span class="nestform-admin__label"><?php esc_html_e( 'Redirect URL', 'nestform' ); ?></span>
														<input type="url" class="nestform-admin__input" name="nestform[settings][quiz_bands][<?php echo (int) $bi; ?>][redirect]" value="<?php echo esc_attr( (string) ( $band['redirect'] ?? '' ) ); ?>" placeholder="https://" data-nestform-bands-redirect />
													</label>
													<button type="button" class="nestform-btn nestform-btn--ghost nestform-btn--danger-text nestform-bands__remove" data-nestform-bands-remove aria-label="<?php esc_attr_e( 'Remove result', 'nestform' ); ?>">
														<?php esc_html_e( 'Remove', 'nestform' ); ?>
													</button>
												</div>
											<?php endforeach; ?>
										</div>
										<p class="nestform-bands__actions">
											<button type="button" class="button" data-nestform-bands-add <?php echo count( $quiz_bands ) >= 8 ? 'hidden' : ''; ?>>
												<?php esc_html_e( 'Add result', 'nestform' ); ?>
											</button>
											<span class="nestform-bands__limit"><?php esc_html_e( 'Up to 8 results', 'nestform' ); ?></span>
										</p>
										<template data-nestform-bands-tpl>
											<div class="nestform-bands__row" data-nestform-bands-row>
												<label class="nestform-admin__field-control nestform-bands__from">
													<span class="nestform-admin__label"><?php esc_html_e( 'From %', 'nestform' ); ?></span>
													<input type="number" min="0" max="100" step="1" class="nestform-admin__input" name="nestform[settings][quiz_bands][__i__][min]" value="0" placeholder="0" data-nestform-bands-min />
												</label>
												<label class="nestform-admin__field-control nestform-bands__to">
													<span class="nestform-admin__label"><?php esc_html_e( 'To %', 'nestform' ); ?></span>
													<input type="number" min="0" max="100" step="1" class="nestform-admin__input" name="nestform[settings][quiz_bands][__i__][max]" value="100" placeholder="100" data-nestform-bands-max />
												</label>
												<label class="nestform-admin__field-control nestform-bands__title">
													<span class="nestform-admin__label"><?php esc_html_e( 'Title', 'nestform' ); ?></span>
													<input type="text" class="nestform-admin__input" name="nestform[settings][quiz_bands][__i__][title]" value="" placeholder="<?php esc_attr_e( 'Good job', 'nestform' ); ?>" data-nestform-bands-title />
												</label>
												<label class="nestform-admin__field-control nestform-bands__message">
													<span class="nestform-admin__label"><?php esc_html_e( 'Message', 'nestform' ); ?></span>
													<input type="text" class="nestform-admin__input" name="nestform[settings][quiz_bands][__i__][message]" value="" placeholder="<?php esc_attr_e( 'Solid score — keep going.', 'nestform' ); ?>" data-nestform-bands-message />
												</label>
												<label class="nestform-admin__field-control nestform-bands__redirect">
													<span class="nestform-admin__label"><?php esc_html_e( 'Redirect URL', 'nestform' ); ?></span>
													<input type="url" class="nestform-admin__input" name="nestform[settings][quiz_bands][__i__][redirect]" value="" placeholder="https://" data-nestform-bands-redirect />
												</label>
												<button type="button" class="nestform-btn nestform-btn--ghost nestform-btn--danger-text nestform-bands__remove" data-nestform-bands-remove aria-label="<?php esc_attr_e( 'Remove result', 'nestform' ); ?>">
													<?php esc_html_e( 'Remove', 'nestform' ); ?>
												</button>
											</div>
										</template>
									</div>
								</div>
								<label class="nestform-admin__check">
									<input type="hidden" name="nestform[settings][quiz_show_score]" value="0" />
									<input type="checkbox" name="nestform[settings][quiz_show_score]" value="1" <?php checked( (string) ( $settings['quiz_show_score'] ?? '1' ), '1' ); ?> />
									<span><?php esc_html_e( 'Show score on result', 'nestform' ); ?></span>
								</label>
								<label class="nestform-admin__check">
									<input type="hidden" name="nestform[settings][quiz_show_answers]" value="0" />
									<input type="checkbox" name="nestform[settings][quiz_show_answers]" value="1" <?php checked( (string) ( $settings['quiz_show_answers'] ?? '0' ), '1' ); ?> />
									<span><?php esc_html_e( 'Show submitted answers', 'nestform' ); ?></span>
								</label>
								<label class="nestform-admin__check">
									<input type="hidden" name="nestform[settings][partial_save]" value="0" />
									<input type="checkbox" name="nestform[settings][partial_save]" value="1" <?php checked( (string) ( $settings['partial_save'] ?? '0' ), '1' ); ?> />
									<span><?php esc_html_e( 'Allow resume (partial save)', 'nestform' ); ?></span>
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'Resume TTL (days)', 'nestform' ); ?></span>
									<input type="number" min="1" max="30" class="nestform-admin__input" name="nestform[settings][partial_ttl_days]" value="<?php echo esc_attr( (string) ( $settings['partial_ttl_days'] ?? '7' ) ); ?>" />
								</label>
								<label class="nestform-admin__check nestform-admin__field-control--full">
									<input type="hidden" name="nestform[settings][share_results]" value="0" />
									<input type="checkbox" name="nestform[settings][share_results]" value="1" <?php checked( (string) ( $settings['share_results'] ?? '0' ), '1' ); ?> />
									<span><?php esc_html_e( 'Generate shareable result link', 'nestform' ); ?></span>
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label">
										<?php esc_html_e( 'Result CTA label', 'nestform' ); ?>
										<?php self::render_field_tip( __( 'Button on the result screen. Uses the band redirect URL, or the form Redirect URL if the band has none.', 'nestform' ) ); ?>
									</span>
									<input type="text" class="nestform-admin__input" name="nestform[settings][quiz_cta_label]" value="<?php echo esc_attr( (string) ( $settings['quiz_cta_label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Continue', 'nestform' ); ?>" />
								</label>
							</div>
							<?php else : ?>
							<p>
								<button type="button" class="nestform-btn nestform-btn--outline" data-nestform-pro-upsell="quiz_survey">
									<?php esc_html_e( 'Unlock quiz & survey with Pro', 'nestform' ); ?>
								</button>
							</p>
							<?php endif; ?>
						</div>
					</details>

					<details class="nestform-admin__fold" <?php echo $settings_hook_open ? 'open' : ''; ?>>
						<summary>
							<span class="nestform-admin__fold-chevron" aria-hidden="true"></span>
							<span class="nestform-admin__fold-copy">
								<span class="nestform-admin__fold-title">
									<?php esc_html_e( 'Webhook', 'nestform' ); ?>
									<?php
									if ( ! $can_webhook ) {
										echo ' ' . Nestform_Upgrade::pill_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									}
									?>
								</span>
								<span class="nestform-admin__fold-hint"><?php echo $can_webhook ? esc_html__( 'POST JSON after a successful submission (non-blocking).', 'nestform' ) : esc_html__( 'Available in Nestform Pro.', 'nestform' ); ?></span>
							</span>
						</summary>
						<div class="nestform-admin__fold-body">
							<?php if ( $can_webhook ) : ?>
							<div class="nestform-admin__grid">
								<label class="nestform-admin__check nestform-admin__field-control--full">
									<input type="hidden" name="nestform[settings][webhook_enabled]" value="0" />
									<input type="checkbox" name="nestform[settings][webhook_enabled]" value="1" <?php checked( $settings_hook_open ); ?> />
									<span><?php esc_html_e( 'Enable webhook', 'nestform' ); ?></span>
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label">
										<?php esc_html_e( 'Webhook URL', 'nestform' ); ?>
										<?php self::render_field_tip( __( 'HTTPS endpoint that accepts application/json POST.', 'nestform' ) ); ?>
									</span>
									<input type="url" class="nestform-admin__input" name="nestform[settings][webhook_url]" value="<?php echo esc_attr( (string) ( $settings['webhook_url'] ?? '' ) ); ?>" placeholder="https://" />
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label">
										<?php esc_html_e( 'Shared secret', 'nestform' ); ?>
										<?php self::render_field_tip( __( 'Optional. Sent as X-Nestform-Secret header.', 'nestform' ) ); ?>
									</span>
									<input type="text" class="nestform-admin__input" name="nestform[settings][webhook_secret]" value="<?php echo esc_attr( (string) ( $settings['webhook_secret'] ?? '' ) ); ?>" autocomplete="off" />
								</label>
							</div>
							<?php else : ?>
							<p>
								<button type="button" class="nestform-btn nestform-btn--outline" data-nestform-pro-webhook>
									<?php esc_html_e( 'Unlock webhooks with Pro', 'nestform' ); ?>
								</button>
							</p>
							<?php endif; ?>
						</div>
					</details>

					<details class="nestform-admin__fold" <?php echo $settings_auto_open ? 'open' : ''; ?>>
						<summary>
							<span class="nestform-admin__fold-chevron" aria-hidden="true"></span>
							<span class="nestform-admin__fold-copy">
								<span class="nestform-admin__fold-title">
									<?php esc_html_e( 'Automations', 'nestform' ); ?>
									<?php
									if ( ! $can_auto ) {
										echo ' ' . Nestform_Upgrade::pill_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									}
									?>
								</span>
								<span class="nestform-admin__fold-hint"><?php echo $can_auto ? esc_html__( 'When submitted → if conditions → then actions.', 'nestform' ) : esc_html__( 'Available in Nestform Pro.', 'nestform' ); ?></span>
							</span>
						</summary>
						<div class="nestform-admin__fold-body">
							<?php if ( $can_auto ) : ?>
								<?php
								$auto_rules = Nestform_Form_Config::get_automation_rules( $settings );
								if ( array() === $auto_rules ) {
									$auto_rules = array(
										array(
											'field' => '',
											'op'    => 'equals',
											'value' => '',
										),
									);
								}
								$auto_match      = (string) ( $settings['automation_match'] ?? 'all' );
								$auto_skip_spam  = '0' !== (string) ( $settings['automation_skip_spam'] ?? '1' );
								$auto_status     = (string) ( $settings['automation_then_status'] ?? '' );
								$auto_email      = (string) ( $settings['automation_then_email'] ?? '' );
								$auto_webhook    = (string) ( $settings['automation_then_webhook'] ?? '' );
								$auto_has_then   = ( $auto_status !== '' || $auto_email !== '' || $auto_webhook !== '' );
								$field_options   = array();
								foreach ( $fields as $f ) {
									$fn = (string) ( $f['name'] ?? '' );
									if ( $fn === '' ) {
										continue;
									}
									$field_options[ $fn ] = (string) ( $f['label'] ?? $fn ) . ' (' . $fn . ')';
								}
								$ops = Nestform_Form_Config::condition_operators();
								?>
							<div class="nestform-auto" data-nestform-auto>
								<section class="nestform-auto__step nestform-auto__step--when">
									<span class="nestform-auto__badge"><?php esc_html_e( 'When', 'nestform' ); ?></span>
									<p class="nestform-auto__lead"><?php esc_html_e( 'On form submit', 'nestform' ); ?></p>
									<label class="nestform-admin__check">
										<input type="hidden" name="nestform[settings][automation_enabled]" value="0" />
										<input type="checkbox" name="nestform[settings][automation_enabled]" value="1" <?php checked( $settings_auto_open ); ?> data-nestform-auto-enabled />
										<span><?php esc_html_e( 'Enable automation', 'nestform' ); ?></span>
									</label>
									<label class="nestform-admin__check">
										<input type="hidden" name="nestform[settings][automation_skip_spam]" value="0" />
										<input type="checkbox" name="nestform[settings][automation_skip_spam]" value="1" <?php checked( $auto_skip_spam ); ?> />
										<span><?php esc_html_e( 'Skip spam entries', 'nestform' ); ?></span>
									</label>
								</section>

								<section class="nestform-auto__step nestform-auto__step--if">
									<span class="nestform-auto__badge"><?php esc_html_e( 'If', 'nestform' ); ?></span>
									<p class="nestform-auto__lead"><?php esc_html_e( 'Optional conditions. Leave field empty (or remove all) to always run.', 'nestform' ); ?></p>
									<label class="nestform-admin__field-control nestform-auto__match">
										<span class="nestform-admin__label"><?php esc_html_e( 'Match', 'nestform' ); ?></span>
										<select class="nestform-admin__input" name="nestform[settings][automation_match]" data-nestform-auto-match>
											<option value="all" <?php selected( $auto_match, 'all' ); ?>><?php esc_html_e( 'All conditions (AND)', 'nestform' ); ?></option>
											<option value="any" <?php selected( $auto_match, 'any' ); ?>><?php esc_html_e( 'Any condition (OR)', 'nestform' ); ?></option>
										</select>
									</label>
									<div class="nestform-auto__rules" data-nestform-auto-rules>
										<?php foreach ( $auto_rules as $ri => $rule ) : ?>
											<?php
											$r_field = (string) ( $rule['field'] ?? '' );
											$r_op    = (string) ( $rule['op'] ?? 'equals' );
											$r_val   = (string) ( $rule['value'] ?? '' );
											$hide_val = in_array( $r_op, array( 'empty', 'not_empty' ), true );
											?>
											<div class="nestform-auto__rule" data-nestform-auto-rule>
												<label class="nestform-admin__field-control">
													<span class="nestform-admin__label"><?php esc_html_e( 'Field', 'nestform' ); ?></span>
													<select class="nestform-admin__input" name="nestform[settings][automation_rules][<?php echo (int) $ri; ?>][field]" data-nestform-auto-field>
														<option value=""><?php esc_html_e( '— Select —', 'nestform' ); ?></option>
														<?php foreach ( $field_options as $fn => $fl ) : ?>
															<option value="<?php echo esc_attr( $fn ); ?>" <?php selected( $r_field, $fn ); ?>><?php echo esc_html( $fl ); ?></option>
														<?php endforeach; ?>
													</select>
												</label>
												<label class="nestform-admin__field-control">
													<span class="nestform-admin__label"><?php esc_html_e( 'Operator', 'nestform' ); ?></span>
													<select class="nestform-admin__input" name="nestform[settings][automation_rules][<?php echo (int) $ri; ?>][op]" data-nestform-auto-op>
														<?php foreach ( $ops as $op_key => $op_label ) : ?>
															<option value="<?php echo esc_attr( $op_key ); ?>" <?php selected( $r_op, $op_key ); ?>><?php echo esc_html( $op_label ); ?></option>
														<?php endforeach; ?>
													</select>
												</label>
												<label class="nestform-admin__field-control nestform-auto__value" data-nestform-auto-value-wrap <?php echo $hide_val ? 'hidden' : ''; ?>>
													<span class="nestform-admin__label"><?php esc_html_e( 'Value', 'nestform' ); ?></span>
													<input type="text" class="nestform-admin__input" name="nestform[settings][automation_rules][<?php echo (int) $ri; ?>][value]" value="<?php echo esc_attr( $r_val ); ?>" data-nestform-auto-value />
												</label>
												<button type="button" class="nestform-btn nestform-btn--ghost nestform-btn--danger-text nestform-auto__remove" data-nestform-auto-remove aria-label="<?php esc_attr_e( 'Remove condition', 'nestform' ); ?>">
													<?php esc_html_e( 'Remove', 'nestform' ); ?>
												</button>
											</div>
										<?php endforeach; ?>
									</div>
									<p class="nestform-auto__actions">
										<button type="button" class="button" data-nestform-auto-add <?php echo count( $auto_rules ) >= 3 ? 'hidden' : ''; ?>>
											<?php esc_html_e( 'Add condition', 'nestform' ); ?>
										</button>
										<span class="nestform-auto__limit"><?php esc_html_e( 'Up to 3 conditions', 'nestform' ); ?></span>
									</p>
									<template data-nestform-auto-rule-tpl>
										<div class="nestform-auto__rule" data-nestform-auto-rule>
											<label class="nestform-admin__field-control">
												<span class="nestform-admin__label"><?php esc_html_e( 'Field', 'nestform' ); ?></span>
												<select class="nestform-admin__input" name="nestform[settings][automation_rules][__i__][field]" data-nestform-auto-field>
													<option value=""><?php esc_html_e( '— Select —', 'nestform' ); ?></option>
													<?php foreach ( $field_options as $fn => $fl ) : ?>
														<option value="<?php echo esc_attr( $fn ); ?>"><?php echo esc_html( $fl ); ?></option>
													<?php endforeach; ?>
												</select>
											</label>
											<label class="nestform-admin__field-control">
												<span class="nestform-admin__label"><?php esc_html_e( 'Operator', 'nestform' ); ?></span>
												<select class="nestform-admin__input" name="nestform[settings][automation_rules][__i__][op]" data-nestform-auto-op>
													<?php foreach ( $ops as $op_key => $op_label ) : ?>
														<option value="<?php echo esc_attr( $op_key ); ?>"><?php echo esc_html( $op_label ); ?></option>
													<?php endforeach; ?>
												</select>
											</label>
											<label class="nestform-admin__field-control nestform-auto__value" data-nestform-auto-value-wrap>
												<span class="nestform-admin__label"><?php esc_html_e( 'Value', 'nestform' ); ?></span>
												<input type="text" class="nestform-admin__input" name="nestform[settings][automation_rules][__i__][value]" value="" data-nestform-auto-value />
											</label>
											<button type="button" class="nestform-btn nestform-btn--ghost nestform-btn--danger-text nestform-auto__remove" data-nestform-auto-remove aria-label="<?php esc_attr_e( 'Remove condition', 'nestform' ); ?>">
												<?php esc_html_e( 'Remove', 'nestform' ); ?>
											</button>
										</div>
									</template>
								</section>

								<section class="nestform-auto__step nestform-auto__step--then">
									<span class="nestform-auto__badge"><?php esc_html_e( 'Then', 'nestform' ); ?></span>
									<p class="nestform-auto__lead"><?php esc_html_e( 'Run one or more actions when conditions match.', 'nestform' ); ?></p>
									<p class="nestform-admin__note nestform-auto__warn" data-nestform-auto-warn <?php echo ( $settings_auto_open && ! $auto_has_then ) ? '' : 'hidden'; ?>>
										<?php esc_html_e( 'Automation is enabled but no THEN action is set — nothing will run.', 'nestform' ); ?>
									</p>
									<label class="nestform-admin__field-control">
										<span class="nestform-admin__label"><?php esc_html_e( 'Set entry status', 'nestform' ); ?></span>
										<select class="nestform-admin__input" name="nestform[settings][automation_then_status]" data-nestform-auto-then>
											<option value="" <?php selected( $auto_status, '' ); ?>><?php esc_html_e( 'No change', 'nestform' ); ?></option>
											<option value="read" <?php selected( $auto_status, 'read' ); ?>><?php esc_html_e( 'Mark as read', 'nestform' ); ?></option>
											<option value="new" <?php selected( $auto_status, 'new' ); ?>><?php esc_html_e( 'Mark as new', 'nestform' ); ?></option>
											<option value="spam" <?php selected( $auto_status, 'spam' ); ?>><?php esc_html_e( 'Mark as spam', 'nestform' ); ?></option>
										</select>
									</label>
									<label class="nestform-admin__field-control">
										<span class="nestform-admin__label">
											<?php esc_html_e( 'Notify email', 'nestform' ); ?>
											<?php self::render_field_tip( __( 'Optional. Short alert when the IF conditions match.', 'nestform' ) ); ?>
										</span>
										<input type="email" class="nestform-admin__input" name="nestform[settings][automation_then_email]" value="<?php echo esc_attr( $auto_email ); ?>" placeholder="ops@example.com" data-nestform-auto-then />
									</label>
									<label class="nestform-admin__field-control nestform-admin__field-control--full">
										<span class="nestform-admin__label">
											<?php esc_html_e( 'Conditional webhook URL', 'nestform' ); ?>
											<?php self::render_field_tip( __( 'Optional. Separate from the always-on Webhook above — fires only when IF matches.', 'nestform' ) ); ?>
										</span>
										<input type="url" class="nestform-admin__input" name="nestform[settings][automation_then_webhook]" value="<?php echo esc_attr( $auto_webhook ); ?>" placeholder="https://" data-nestform-auto-then />
									</label>
								</section>
							</div>
							<?php else : ?>
							<p>
								<button type="button" class="nestform-btn nestform-btn--outline" data-nestform-pro-upsell="automations">
									<?php esc_html_e( 'Unlock automations with Pro', 'nestform' ); ?>
								</button>
							</p>
							<?php endif; ?>
						</div>
					</details>

					<div class="nestform-admin__note">
						<strong><?php esc_html_e( 'Multi-step', 'nestform' ); ?></strong>
						<p><?php esc_html_e( 'Configure steps on the Fields tab: enable wizard, name each step, add fields into the active step.', 'nestform' ); ?></p>
					</div>
				</div>
			</div>

			<div class="nestform-admin__panel nestform-appearance<?php echo 'appearance' === $active_tab ? ' is-active' : ''; ?>" data-nestform-panel="appearance" id="nestform-panel-appearance" role="tabpanel" aria-labelledby="nestform-tab-appearance"<?php echo 'appearance' === $active_tab ? '' : ' hidden'; ?>>
				<div class="nestform-admin__panel-head">
					<div>
						<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Appearance', 'nestform' ); ?></h3>
						<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Pick a skin and colors per form. Live preview updates as you edit — no save needed.', 'nestform' ); ?></p>
					</div>
				</div>
				<div class="nestform-appearance__layout">
					<div class="nestform-appearance__controls">
				<div class="nestform-admin__surface">
					<span class="nestform-admin__label nestform-appearance__section"><?php esc_html_e( 'Skin', 'nestform' ); ?></span>
					<div class="nestform-style-skins" role="radiogroup" aria-label="<?php esc_attr_e( 'Form skin', 'nestform' ); ?>">
						<?php
						$skin_hints = array(
							'theme'   => __( 'Use theme field & button styles', 'nestform' ),
							'classic' => __( 'Outlined inputs, clear borders', 'nestform' ),
							'minimal' => __( 'Underline fields, light chrome', 'nestform' ),
							'soft'    => __( 'Filled soft backgrounds', 'nestform' ),
							'card'    => __( 'Form in a bordered card', 'nestform' ),
						);
						$current_skin = (string) ( $settings['style_skin'] ?? 'theme' );
						foreach ( Nestform_Form_Config::style_skins() as $skin_key => $skin_label ) :
							?>
							<label class="nestform-style-skins__item<?php echo $current_skin === $skin_key ? ' is-active' : ''; ?>">
								<input
									type="radio"
									name="nestform[settings][style_skin]"
									value="<?php echo esc_attr( $skin_key ); ?>"
									<?php checked( $current_skin, $skin_key ); ?>
								/>
								<span class="nestform-style-skins__preview nestform-style-skins__preview--<?php echo esc_attr( $skin_key ); ?>" aria-hidden="true">
									<span></span><span></span>
								</span>
								<span class="nestform-style-skins__copy">
									<span class="nestform-style-skins__title"><?php echo esc_html( $skin_label ); ?></span>
									<span class="nestform-style-skins__hint"><?php echo esc_html( $skin_hints[ $skin_key ] ?? '' ); ?></span>
								</span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="nestform-admin__surface">
					<div class="nestform-admin__panel-head" style="margin-bottom:14px">
						<div>
							<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Colors', 'nestform' ); ?></h3>
							<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Empty = skin default. Accent also tints progress and selects.', 'nestform' ); ?></p>
						</div>
					</div>
					<?php
					$color_groups = array(
						'brand'    => array(
							'label'  => __( 'Brand', 'nestform' ),
							'fields' => array(
								'style_accent'      => array( __( 'Accent', 'nestform' ), '#2563eb' ),
								'style_accent_text' => array( __( 'On accent', 'nestform' ), '#ffffff' ),
							),
						),
						'text'     => array(
							'label'  => __( 'Text', 'nestform' ),
							'fields' => array(
								'style_text'  => array( __( 'Text', 'nestform' ), '#01123e' ),
								'style_muted' => array( __( 'Muted', 'nestform' ), '#6b6b80' ),
							),
						),
						'surfaces' => array(
							'label'  => __( 'Surfaces', 'nestform' ),
							'fields' => array(
								'style_surface'  => array( __( 'Surface', 'nestform' ), '#ffffff' ),
								'style_input_bg' => array( __( 'Input fill', 'nestform' ), '#ffffff' ),
								'style_border'   => array( __( 'Border', 'nestform' ), '#e8e8ec' ),
							),
						),
					);
					?>
					<div class="nestform-style-colors" data-nestform-style-colors>
						<div class="nestform-style-colors__preview" aria-hidden="true">
							<span class="nestform-style-colors__chip nestform-style-colors__chip--accent" data-nestform-color-preview="style_accent"></span>
							<span class="nestform-style-colors__chip nestform-style-colors__chip--surface" data-nestform-color-preview="style_surface"></span>
							<span class="nestform-style-colors__chip nestform-style-colors__chip--border" data-nestform-color-preview="style_border"></span>
							<span class="nestform-style-colors__chip nestform-style-colors__chip--text" data-nestform-color-preview="style_text"></span>
						</div>
						<?php foreach ( $color_groups as $group ) : ?>
							<div class="nestform-style-colors__group">
								<span class="nestform-style-colors__group-label"><?php echo esc_html( $group['label'] ); ?></span>
								<div class="nestform-style-colors__grid">
									<?php foreach ( $group['fields'] as $color_key => $meta ) : ?>
										<?php
										$color_label = $meta[0];
										$color_fallback = $meta[1];
										$color_val = (string) ( $settings[ $color_key ] ?? '' );
										$swatch_val = $color_val !== '' ? $color_val : $color_fallback;
										?>
										<label class="nestform-style-color<?php echo $color_val !== '' ? ' is-set' : ''; ?>" data-nestform-style-color-wrap>
											<span class="nestform-style-color__swatch-wrap">
												<input
													type="color"
													class="nestform-style-color__swatch"
													value="<?php echo esc_attr( $swatch_val ); ?>"
													data-nestform-style-color
													data-fallback="<?php echo esc_attr( $color_fallback ); ?>"
													aria-label="<?php echo esc_attr( $color_label ); ?>"
												/>
											</span>
											<span class="nestform-style-color__meta">
												<span class="nestform-style-color__name"><?php echo esc_html( $color_label ); ?></span>
												<span class="nestform-style-color__row">
													<input
														type="text"
														class="nestform-admin__input nestform-style-color__hex"
														name="nestform[settings][<?php echo esc_attr( $color_key ); ?>]"
														value="<?php echo esc_attr( $color_val ); ?>"
														placeholder="<?php echo esc_attr( $color_fallback ); ?>"
														pattern="^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$"
														data-nestform-style-hex
														data-color-key="<?php echo esc_attr( $color_key ); ?>"
													/>
													<button type="button" class="button-link nestform-style-color__clear" data-nestform-style-clear <?php echo $color_val === '' ? ' hidden' : ''; ?>>
														<?php esc_html_e( 'Reset', 'nestform' ); ?>
													</button>
												</span>
											</span>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="nestform-admin__surface">
					<div class="nestform-admin__panel-head" style="margin-bottom:12px">
						<div>
							<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Typography & spacing', 'nestform' ); ?></h3>
							<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Font size and field spacing apply to every skin, including Theme.', 'nestform' ); ?></p>
						</div>
					</div>
					<div class="nestform-admin__grid nestform-admin__grid--2">
						<label class="nestform-admin__field-control">
							<span class="nestform-admin__label"><?php esc_html_e( 'Font size', 'nestform' ); ?></span>
							<select class="nestform-admin__input" name="nestform[settings][style_font_size]">
								<?php foreach ( Nestform_Form_Config::style_font_size_options() as $f_key => $f_label ) : ?>
									<option value="<?php echo esc_attr( $f_key ); ?>" <?php selected( (string) ( $settings['style_font_size'] ?? 'md' ), $f_key ); ?>><?php echo esc_html( $f_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="nestform-admin__field-control">
							<span class="nestform-admin__label"><?php esc_html_e( 'Field spacing', 'nestform' ); ?></span>
							<select class="nestform-admin__input" name="nestform[settings][style_gap]">
								<?php foreach ( Nestform_Form_Config::style_gap_options() as $g_key => $g_label ) : ?>
									<option value="<?php echo esc_attr( $g_key ); ?>" <?php selected( (string) ( $settings['style_gap'] ?? 'md' ), $g_key ); ?>><?php echo esc_html( $g_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
				</div>
				<div class="nestform-admin__surface" data-nestform-style-chrome>
					<div class="nestform-admin__panel-head" style="margin-bottom:12px">
						<div>
							<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Shape & controls', 'nestform' ); ?></h3>
							<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Applies to Classic / Minimal / Soft / Card. Theme skin keeps site chrome.', 'nestform' ); ?></p>
							<p class="nestform-style-chrome-note" data-nestform-style-chrome-note hidden><?php esc_html_e( 'Theme skin is active — these options are stored but not applied on the front.', 'nestform' ); ?></p>
						</div>
					</div>
					<div class="nestform-admin__grid nestform-admin__grid--2">
						<label class="nestform-admin__field-control">
							<span class="nestform-admin__label"><?php esc_html_e( 'Input size', 'nestform' ); ?></span>
							<select class="nestform-admin__input" name="nestform[settings][style_density]" data-nestform-style-chrome-field>
								<?php foreach ( Nestform_Form_Config::style_density_options() as $d_key => $d_label ) : ?>
									<option value="<?php echo esc_attr( $d_key ); ?>" <?php selected( (string) ( $settings['style_density'] ?? 'md' ), $d_key ); ?>><?php echo esc_html( $d_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="nestform-admin__field-control">
							<span class="nestform-admin__label"><?php esc_html_e( 'Corner radius', 'nestform' ); ?></span>
							<select class="nestform-admin__input" name="nestform[settings][style_radius]" data-nestform-style-chrome-field>
								<?php foreach ( Nestform_Form_Config::style_radius_options() as $r_key => $r_label ) : ?>
									<option value="<?php echo esc_attr( $r_key ); ?>" <?php selected( (string) ( $settings['style_radius'] ?? 'md' ), $r_key ); ?>><?php echo esc_html( $r_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="nestform-admin__field-control">
							<span class="nestform-admin__label"><?php esc_html_e( 'Primary button', 'nestform' ); ?></span>
							<select class="nestform-admin__input" name="nestform[settings][style_button]" data-nestform-style-chrome-field>
								<?php foreach ( Nestform_Form_Config::style_button_options() as $b_key => $b_label ) : ?>
									<option value="<?php echo esc_attr( $b_key ); ?>" <?php selected( (string) ( $settings['style_button'] ?? 'solid' ), $b_key ); ?>><?php echo esc_html( $b_label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					</div>
				</div>
				<div class="nestform-admin__surface">
					<div class="nestform-admin__panel-head" style="margin-bottom:12px">
						<div>
							<h3 class="nestform-admin__panel-title"><?php esc_html_e( 'Custom CSS', 'nestform' ); ?></h3>
							<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Scoped to this form only. Use the hooks below — no need to write the form id.', 'nestform' ); ?></p>
						</div>
					</div>
					<label class="nestform-admin__field-control nestform-admin__field-control--full">
						<span class="screen-reader-text"><?php esc_html_e( 'Custom CSS', 'nestform' ); ?></span>
						<textarea
							class="nestform-admin__input nestform-admin__textarea nestform-style-css"
							rows="10"
							name="nestform[settings][style_custom_css]"
							spellcheck="false"
							placeholder="<?php echo esc_attr( Nestform_Form_Config::style_custom_css_example() ); ?>"
						><?php echo esc_textarea( (string) ( $settings['style_custom_css'] ?? '' ) ); ?></textarea>
					</label>
					<details class="nestform-style-hooks">
						<summary><?php esc_html_e( 'Class hooks', 'nestform' ); ?></summary>
						<ul class="nestform-style-hooks__list">
							<li><code>.nest-form</code> — <?php esc_html_e( 'root form', 'nestform' ); ?></li>
							<li><code>.nest-form__label</code> / <code>.label</code></li>
							<li><code>.nest-form__input</code> / <code>.input</code> / <code>.textarea</code></li>
							<li><code>.nest-form__submit</code> / <code>.nest-form__next</code> / <code>.nest-form__prev</code></li>
							<li><code>.nest-form__field--{type}</code> — <?php esc_html_e( 'e.g. email, tel, select', 'nestform' ); ?></li>
							<li><code>.nest-form__field--half</code></li>
							<li><code>.nest-form__progress</code> / <code>.nest-form__progress-fill</code></li>
							<li><code>.nest-form-select__trigger</code> / <code>.nest-form-select__list</code></li>
							<li><code>.nest-form__status--success</code> / <code>.nest-form__status--error</code></li>
							<li><code>--nest-form-font-size</code> / <code>--nest-form-gap</code> / <code>--nest-form-control-pad</code></li>
							<li><code>.nest-form--skin-classic</code> — <?php esc_html_e( 'current skin modifier', 'nestform' ); ?></li>
						</ul>
					</details>
				</div>
					</div>
					<?php
					$live_style   = Nestform_Form_Config::style_inline_css( $settings );
					$submit_label = (string) ( $settings['submit_label'] ?? '' );
					if ( $submit_label === '' ) {
						$submit_label = __( 'Send', 'nestform' );
					}
					$live_preview_mods = array( 'nestform-live-preview', 'nestform-live-preview--skin-' . $current_skin );
					if ( 'theme' !== $current_skin ) {
						$live_btn = isset( $settings['style_button'] ) ? sanitize_key( (string) $settings['style_button'] ) : 'solid';
						if ( ! isset( Nestform_Form_Config::style_button_options()[ $live_btn ] ) ) {
							$live_btn = 'solid';
						}
						$live_preview_mods[] = 'nestform-live-preview--btn-' . $live_btn;
					}
					?>
					<aside class="nestform-appearance__live" data-nestform-live-preview>
						<div class="nestform-appearance__live-head">
							<strong><?php esc_html_e( 'Live preview', 'nestform' ); ?></strong>
							<span><?php esc_html_e( 'Updates instantly', 'nestform' ); ?></span>
						</div>
						<div class="nestform-appearance__live-stage">
							<div
								class="<?php echo esc_attr( implode( ' ', $live_preview_mods ) ); ?>"
								data-nestform-live-form
								style="<?php echo esc_attr( $live_style ); ?>"
							>
								<div class="nestform-live-preview__progress" aria-hidden="true">
									<span class="nestform-live-preview__bar">
										<span class="nestform-live-preview__bar-fill"></span>
									</span>
								</div>
								<div class="nestform-live-preview__fields">
									<div class="nestform-live-preview__field nestform-live-preview__field--half">
										<span class="nestform-live-preview__label"><?php esc_html_e( 'Name', 'nestform' ); ?></span>
										<span class="nestform-live-preview__control"><?php esc_html_e( 'Jane Doe', 'nestform' ); ?></span>
									</div>
									<div class="nestform-live-preview__field nestform-live-preview__field--half">
										<span class="nestform-live-preview__label"><?php esc_html_e( 'Email', 'nestform' ); ?></span>
										<span class="nestform-live-preview__control">jane@example.com</span>
									</div>
									<div class="nestform-live-preview__field">
										<span class="nestform-live-preview__label"><?php esc_html_e( 'Message', 'nestform' ); ?></span>
										<span class="nestform-live-preview__control nestform-live-preview__control--area"><?php esc_html_e( 'How can we help?', 'nestform' ); ?></span>
										<span class="nestform-live-preview__hint"><?php esc_html_e( 'Helper text sample', 'nestform' ); ?></span>
									</div>
								</div>
								<div class="nestform-live-preview__actions">
									<span class="nestform-live-preview__btn" data-nestform-live-submit><?php echo esc_html( $submit_label ); ?></span>
								</div>
							</div>
							<p class="nestform-appearance__live-note" data-nestform-live-theme-note <?php echo 'theme' === $current_skin ? '' : 'hidden'; ?>>
								<?php esc_html_e( 'Theme skin keeps your site chrome on the front. Preview still shows Nestform colors, size, and spacing.', 'nestform' ); ?>
							</p>
						</div>
					</aside>
				</div>
			</div>
			<?php self::render_templates_panel( $form_id ); ?>
		</div>
		<script>
		(function () {
			var root = document.querySelector('[data-nestform-admin][data-form-id="<?php echo esc_js( (string) (int) $form_id ); ?>"]');
			if (!root) {
				return;
			}
			var tabs = ['fields', 'messages', 'mail', 'settings', 'appearance'];
			var formId = root.getAttribute('data-form-id') || '0';
			var key = 'nestform_editor_tab_' + formId;
			var id = '';
			var hash = String(window.location.hash || '').replace(/^#/, '');
			if (hash.indexOf('nf-tab=') === 0) {
				id = hash.slice(7).split('&')[0];
			} else if (tabs.indexOf(hash) !== -1) {
				id = hash;
			}
			if (!id || tabs.indexOf(id) === -1) {
				try {
					id = window.sessionStorage.getItem(key) || '';
				} catch (err) {
					id = '';
				}
			}
			if (!id || tabs.indexOf(id) === -1) {
				return;
			}
			var current = root.querySelector('[data-nestform-tab].is-active');
			if (current && current.getAttribute('data-nestform-tab') === id) {
				return;
			}
			root.querySelectorAll('[data-nestform-tab]').forEach(function (t) {
				var active = t.getAttribute('data-nestform-tab') === id;
				t.classList.toggle('is-active', active);
				t.setAttribute('aria-selected', active ? 'true' : 'false');
				t.setAttribute('tabindex', active ? '0' : '-1');
			});
			root.querySelectorAll('[data-nestform-panel]').forEach(function (panel) {
				var match = panel.getAttribute('data-nestform-panel') === id;
				panel.classList.toggle('is-active', match);
				panel.hidden = !match;
			});
		})();
		</script>
		<?php
	}

	/**
	 * Help icon with native title tooltip next to a field label.
	 *
	 * @param string               $text Tip text.
	 * @param array<string, string> $atts Extra HTML attributes.
	 */
	private static function render_field_tip( $text, array $atts = array() ) {
		$text = trim( (string) $text );
		if ( $text === '' ) {
			return;
		}
		$attr = '';
		foreach ( $atts as $key => $value ) {
			if ( $value === '' || $value === null ) {
				continue;
			}
			$attr .= ' ' . esc_attr( (string) $key ) . '="' . esc_attr( (string) $value ) . '"';
		}
		printf(
			'<span class="nestform-admin__tip"%1$s title="%2$s" aria-label="%2$s"><span class="nestform-admin__tip-dot" aria-hidden="true">?</span></span>',
			$attr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr above.
			esc_attr( $text )
		);
	}

	/**
	 * @param int|string            $index     Index.
	 * @param array<string, mixed>  $field     Field.
	 * @param array<string, string> $types     Types.
	 * @param bool                  $collapsed Start collapsed.
	 */
	private static function render_field_row( $index, array $field, array $types, $collapsed = true ) {
		$prefix = 'nestform[fields][' . $index . ']';
		$type   = (string) ( $field['type'] ?? 'text' );
		$label  = (string) ( $field['label'] ?? '' );
		$name   = (string) ( $field['name'] ?? '' );
		$is_layout = Nestform_Form_Config::is_layout_field( $type );
		$title  = $label !== '' ? wp_strip_all_tags( $label ) : ( $name !== '' ? $name : __( 'Untitled field', 'nestform' ) );
		$req    = ! empty( $field['required'] );
		$step   = isset( $field['step'] ) ? max( 1, (int) $field['step'] ) : 1;
		$image_id = (int) ( $field['default'] ?? 0 );
		$heading_level = (string) ( $field['options'] ?? 'h2' );
		if ( ! in_array( $heading_level, array( 'h2', 'h3', 'h4' ), true ) ) {
			$heading_level = 'h2';
		}
		$phone_picker = class_exists( 'Nestform_Phone' ) && Nestform_Phone::is_picker_enabled( $field );
		$phone_iso    = class_exists( 'Nestform_Phone' )
			? Nestform_Phone::sanitize_iso( (string) ( $field['options'] ?? '' ) )
			: 'US';
		$cond_field = (string) ( $field['condition_field'] ?? '' );
		$cond_op    = (string) ( $field['condition_op'] ?? 'equals' );
		$cond_value = (string) ( $field['condition_value'] ?? '' );
		$css_class  = (string) ( $field['css_class'] ?? '' );
		$field_width = (string) ( $field['width'] ?? 'full' );
		$desc_val    = (string) ( $field['description'] ?? '' );
		$ph_val      = (string) ( $field['placeholder'] ?? '' );
		$def_val     = (string) ( $field['default'] ?? '' );
		$more_open   = ( 'half' === $field_width )
			|| ( $desc_val !== '' )
			|| ( 'file' !== $type && $ph_val !== '' )
			|| ( 'file' !== $type && 'image' !== $type && $def_val !== '' && '0' !== $def_val );
		$cond_open   = $cond_field !== '';
		$adv_open    = $css_class !== '' || $step > 1;
		// Keep at most one secondary panel open by default to avoid an overloaded card.
		if ( $cond_open ) {
			$more_open = false;
			$adv_open  = false;
		} elseif ( $adv_open ) {
			$more_open = false;
		}
		$card_class = 'nestform-card' . ( $collapsed ? ' is-collapsed' : '' );
		$layout_types = Nestform_Form_Config::layout_field_type_labels();
		$input_types  = Nestform_Form_Config::input_field_type_labels();
		?>
		<article class="<?php echo esc_attr( $card_class ); ?>" data-nestform-field data-field-type="<?php echo esc_attr( $type ); ?>" data-field-step="<?php echo esc_attr( (string) $step ); ?>" draggable="false">
			<header class="nestform-card__header" data-nestform-card-head>
				<span class="nestform-card__handle" data-nestform-drag-handle title="<?php esc_attr_e( 'Drag to reorder', 'nestform' ); ?>" aria-hidden="true">
					<svg width="12" height="16" viewBox="0 0 8 16" fill="currentColor"><circle cx="2" cy="3" r="1.5"/><circle cx="6" cy="3" r="1.5"/><circle cx="2" cy="8" r="1.5"/><circle cx="6" cy="8" r="1.5"/><circle cx="2" cy="13" r="1.5"/><circle cx="6" cy="13" r="1.5"/></svg>
				</span>
				<button type="button" class="nestform-card__toggle" data-nestform-toggle aria-expanded="<?php echo $collapsed ? 'false' : 'true'; ?>" title="<?php esc_attr_e( 'Expand / collapse', 'nestform' ); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
				</button>
				<span class="nestform-card__badge" data-nestform-type-badge><?php echo esc_html( $types[ $type ] ?? $type ); ?></span>
				<span class="nestform-card__step" data-nestform-step-badge><?php echo esc_html( sprintf( /* translators: %d step */ __( 'Step %d', 'nestform' ), $step ) ); ?></span>
				<div class="nestform-card__identity">
					<button type="button" class="nestform-card__title-btn" data-nestform-toggle>
						<span class="nestform-card__title" data-nestform-card-title><?php echo esc_html( $title ); ?></span>
						<span class="nestform-card__meta" data-nestform-card-meta><?php echo esc_html( $name !== '' ? '{' . $name . '}' : '' ); ?></span>
						<span class="nestform-card__summary" data-nestform-card-summary></span>
					</button>
				</div>
				<label
					class="nestform-card__required<?php echo $req ? ' is-on' : ''; ?>"
					data-nestform-required-wrap
					title="<?php esc_attr_e( 'Toggle required', 'nestform' ); ?>"
					<?php echo $is_layout ? ' hidden' : ''; ?>
				>
					<input type="hidden" name="<?php echo esc_attr( $prefix . '[required]' ); ?>" value="0" />
					<input type="checkbox" name="<?php echo esc_attr( $prefix . '[required]' ); ?>" value="1" <?php checked( $req ); ?> data-nestform-required />
					<span class="nestform-card__required-text"><?php esc_html_e( 'Required', 'nestform' ); ?></span>
					<span class="nestform-switch" aria-hidden="true"></span>
				</label>
				<span class="nestform-card__divider" aria-hidden="true"></span>
				<div class="nestform-card__actions">
					<button type="button" class="nestform-card__btn" data-nestform-duplicate title="<?php esc_attr_e( 'Duplicate', 'nestform' ); ?>">
						<?php nestform_admin_icon( 'copy' ); ?>
						<span class="screen-reader-text"><?php esc_html_e( 'Duplicate', 'nestform' ); ?></span>
					</button>
					<button type="button" class="nestform-card__btn nestform-card__btn--danger" data-nestform-remove-field title="<?php esc_attr_e( 'Remove', 'nestform' ); ?>">
						<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
						<span class="screen-reader-text"><?php esc_html_e( 'Remove', 'nestform' ); ?></span>
					</button>
				</div>
			</header>
			<div class="nestform-card__body" data-nestform-card-body <?php echo $collapsed ? 'hidden' : ''; ?>>
				<?php
				$type_section_title = self::type_section_title( $type );
				?>
				<div class="nestform-card__sections">
					<section class="nestform-card__section nestform-card__section--primary" data-nestform-section="field">
						<h4 class="nestform-card__section-title"><?php esc_html_e( 'Field', 'nestform' ); ?></h4>
						<div class="nestform-card__section-grid">
							<label class="nestform-admin__field-control">
								<span class="nestform-admin__label"><?php esc_html_e( 'Type', 'nestform' ); ?></span>
								<select class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[type]' ); ?>" data-nestform-type>
									<optgroup label="<?php esc_attr_e( 'Layout', 'nestform' ); ?>">
										<?php foreach ( $layout_types as $value => $type_label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $type_label ); ?></option>
										<?php endforeach; ?>
									</optgroup>
									<optgroup label="<?php esc_attr_e( 'Fields', 'nestform' ); ?>">
										<?php foreach ( $input_types as $value => $type_label ) : ?>
											<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $type, $value ); ?>><?php echo esc_html( $type_label ); ?></option>
										<?php endforeach; ?>
									</optgroup>
								</select>
							</label>
							<label class="nestform-admin__field-control">
								<span class="nestform-admin__label">
									<?php echo 'html' === $type ? esc_html__( 'Block title (admin)', 'nestform' ) : esc_html__( 'Label', 'nestform' ); ?>
									<?php
									self::render_field_tip(
										__( 'Links allowed, e.g. I agree to the <a href="/privacy-policy" target="_blank">Privacy Policy</a>', 'nestform' ),
										array( 'data-nestform-show' => 'acceptance-html' )
									);
									?>
								</span>
								<input type="text" class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[label]' ); ?>" value="<?php echo esc_attr( $label ); ?>" data-nestform-label placeholder="<?php echo esc_attr( 'heading' === $type ? __( 'Section title', 'nestform' ) : __( 'Visible label', 'nestform' ) ); ?>" />
							</label>
						</div>

						<div class="nestform-card__primary-type" data-nestform-section="type" <?php echo '' === $type_section_title ? 'hidden' : ''; ?>>
							<h5 class="nestform-card__primary-type-title" data-nestform-type-section-title><?php echo esc_html( $type_section_title ); ?></h5>
							<div class="nestform-card__section-grid">
								<p class="nestform-admin__hint nestform-card__type-intro" data-nestform-show="phone-country">
									<?php esc_html_e( 'Optional country picker with dial code. Leave off for a plain phone input.', 'nestform' ); ?>
								</p>
							<p class="nestform-admin__hint nestform-card__type-intro" data-nestform-show="file-limits">
								<?php esc_html_e( 'Limit which files visitors can upload and how large each file (or set) may be.', 'nestform' ); ?>
							</p>
							<p class="nestform-admin__hint nestform-card__type-intro" data-nestform-show="options" data-nestform-options-intro>
								<?php esc_html_e( 'What visitors can pick — keep each line simple and readable.', 'nestform' ); ?>
							</p>
							<label class="nestform-admin__field-control" data-nestform-show="heading-level">
								<span class="nestform-admin__label"><?php esc_html_e( 'Heading level', 'nestform' ); ?></span>
								<select class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[options]' ); ?>"<?php echo self::disabled_for_show( $type, 'heading-level' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
									<option value="h2" <?php selected( $heading_level, 'h2' ); ?>>H2</option>
									<option value="h3" <?php selected( $heading_level, 'h3' ); ?>>H3</option>
									<option value="h4" <?php selected( $heading_level, 'h4' ); ?>>H4</option>
								</select>
							</label>
							<div class="nestform-admin__field-control nestform-admin__field-control--full" data-nestform-show="image-picker">
								<span class="nestform-admin__label"><?php esc_html_e( 'Image', 'nestform' ); ?></span>
								<input type="hidden" name="<?php echo esc_attr( $prefix . '[default]' ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>" data-nestform-image-id<?php echo self::disabled_for_show( $type, 'image-picker' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
								<div class="nestform-image-picker" data-nestform-image-picker>
									<div class="nestform-image-picker__preview<?php echo $image_id > 0 ? ' has-image' : ''; ?>" data-nestform-image-preview>
										<?php if ( $image_id > 0 ) : ?>
											<?php echo wp_get_attachment_image( $image_id, 'medium', false, array( 'class' => 'nestform-image-picker__img' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<?php else : ?>
											<span class="nestform-image-picker__empty"><?php esc_html_e( 'No image selected', 'nestform' ); ?></span>
										<?php endif; ?>
									</div>
									<div class="nestform-image-picker__actions">
										<button type="button" class="nestform-btn" data-nestform-image-pick><?php esc_html_e( 'Select image', 'nestform' ); ?></button>
										<button type="button" class="nestform-btn nestform-btn--ghost nestform-btn--danger-text nestform-image-picker__clear" data-nestform-image-clear <?php echo $image_id > 0 ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove image', 'nestform' ); ?></button>
									</div>
								</div>
							</div>
							<label class="nestform-admin__field-control nestform-admin__field-control--full" data-nestform-show="html-content">
								<span class="nestform-admin__label">
									<?php esc_html_e( 'HTML content', 'nestform' ); ?>
									<?php self::render_field_tip( __( 'Static content — not saved with entries. Basic HTML allowed.', 'nestform' ) ); ?>
								</span>
								<textarea class="nestform-admin__input nestform-admin__textarea" name="<?php echo esc_attr( $prefix . '[options]' ); ?>" rows="5" placeholder="<?php esc_attr_e( '<p>Intro text or <img src=\"…\" alt=\"\">', 'nestform' ); ?>"<?php echo self::disabled_for_show( $type, 'html-content' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( (string) ( $field['options'] ?? '' ) ); ?></textarea>
							</label>
							<label class="nestform-admin__field-control nestform-admin__field-control--full" data-nestform-show="paragraph-text">
								<span class="nestform-admin__label"><?php esc_html_e( 'Paragraph', 'nestform' ); ?></span>
								<textarea class="nestform-admin__input nestform-admin__textarea" name="<?php echo esc_attr( $prefix . '[options]' ); ?>" rows="4" placeholder="<?php esc_attr_e( 'Intro or helper text shown on the form.', 'nestform' ); ?>"<?php echo self::disabled_for_show( $type, 'paragraph-text' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( 'paragraph' === $type ? (string) ( $field['options'] ?? '' ) : '' ); ?></textarea>
							</label>
							<label class="nestform-admin__field-control" data-nestform-show="spacer-size">
								<span class="nestform-admin__label"><?php esc_html_e( 'Spacer size', 'nestform' ); ?></span>
								<?php $spacer_size = in_array( (string) ( $field['options'] ?? '' ), array( 's', 'm', 'l' ), true ) ? (string) $field['options'] : 'm'; ?>
								<select class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[options]' ); ?>"<?php echo self::disabled_for_show( $type, 'spacer-size' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
									<option value="s" <?php selected( $spacer_size, 's' ); ?>><?php esc_html_e( 'Small', 'nestform' ); ?></option>
									<option value="m" <?php selected( $spacer_size, 'm' ); ?>><?php esc_html_e( 'Medium', 'nestform' ); ?></option>
									<option value="l" <?php selected( $spacer_size, 'l' ); ?>><?php esc_html_e( 'Large', 'nestform' ); ?></option>
								</select>
							</label>
							<label class="nestform-admin__field-control nestform-admin__field-control--full" data-nestform-show="phone-country">
								<span class="nestform-admin__label">
									<input type="checkbox" value="1" data-nestform-phone-picker <?php checked( $phone_picker ); ?> <?php echo self::disabled_for_show( $type, 'phone-country' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
									<?php esc_html_e( 'Country picker', 'nestform' ); ?>
								</span>
							</label>
							<label class="nestform-admin__field-control" data-nestform-show="phone-country" data-nestform-phone-iso-wrap<?php echo $phone_picker ? '' : ' hidden'; ?>>
								<span class="nestform-admin__label"><?php esc_html_e( 'Default country', 'nestform' ); ?></span>
								<input type="hidden" name="<?php echo esc_attr( $prefix . '[options]' ); ?>" value="" data-nestform-phone-off<?php echo ( 'tel' === $type && ! $phone_picker ) ? '' : ' disabled'; ?> />
								<select class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[options]' ); ?>" data-nestform-phone-iso<?php echo ( 'tel' === $type && $phone_picker ) ? '' : ' disabled'; ?>>
									<?php if ( class_exists( 'Nestform_Phone' ) ) : ?>
										<?php foreach ( Nestform_Phone::countries() as $country ) : ?>
											<option value="<?php echo esc_attr( $country['iso'] ); ?>" <?php selected( $phone_iso, $country['iso'] ); ?>>
												<?php echo esc_html( $country['iso'] . ' +' . $country['dial'] . ' ' . $country['name'] ); ?>
											</option>
										<?php endforeach; ?>
									<?php endif; ?>
								</select>
							</label>
							<label class="nestform-admin__field-control nestform-admin__field-control--full" data-nestform-show="file-limits">
								<span class="nestform-admin__label">
									<?php esc_html_e( 'Allowed extensions', 'nestform' ); ?>
									<?php self::render_field_tip( __( 'Comma-separated, e.g. jpg,png,pdf', 'nestform' ) ); ?>
								</span>
								<input type="text" class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[options]' ); ?>" value="<?php echo esc_attr( (string) ( $field['options'] ?? Nestform_Form_Config::file_default_extensions() ) ); ?>" placeholder="<?php echo esc_attr( Nestform_Form_Config::file_default_extensions() ); ?>"<?php echo self::disabled_for_show( $type, 'file-limits' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
							</label>
							<label class="nestform-admin__field-control" data-nestform-show="file-max">
								<span class="nestform-admin__label"><?php esc_html_e( 'Max size (MB)', 'nestform' ); ?></span>
								<input type="number" min="1" max="50" step="1" class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[placeholder]' ); ?>" value="<?php echo esc_attr( (string) ( $field['placeholder'] ?? Nestform_Form_Config::file_default_max_mb() ) ); ?>" data-nestform-file-max<?php echo self::disabled_for_show( $type, 'file-max' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
							</label>
							<label class="nestform-admin__field-control" data-nestform-show="file-max">
								<span class="nestform-admin__label">
									<?php esc_html_e( 'Max files', 'nestform' ); ?>
									<?php self::render_field_tip( __( '1–10. More than 1 enables multiple upload.', 'nestform' ) ); ?>
								</span>
								<input type="number" min="1" max="10" step="1" class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[default]' ); ?>" value="<?php echo esc_attr( (string) max( 1, (int) ( $field['default'] ?? 1 ) ) ); ?>"<?php echo self::disabled_for_show( $type, 'file-max' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
							</label>
							<label class="nestform-admin__field-control nestform-admin__field-control--full" data-nestform-show="options">
								<?php
								$options_label = __( 'Choices', 'nestform' );
								$options_tip   = __( 'One choice per line. Quizzes: Correct answer|10. Optional advanced: Label|saved_value|points', 'nestform' );
								$options_ph    = __( "Yes\nNo\nMaybe", 'nestform' );
								$options_hint  = __( 'One choice per line — the text visitors see. For quizzes, add points after | : Correct answer|10', 'nestform' );
								if ( 'range' === $type ) {
									$options_label = __( 'Min / max / step', 'nestform' );
									$options_tip   = __( 'Line 1 = min, line 2 = max, line 3 = step. Example: 0 / 100 / 1', 'nestform' );
									$options_ph    = "0\n100\n1";
									$options_hint  = __( 'Three lines: lowest value, highest value, and step size.', 'nestform' );
								} elseif ( 'rating' === $type ) {
									$options_label = __( 'Number of stars', 'nestform' );
									$options_tip   = __( 'A single number sets max stars (1–10). Or list one label per star.', 'nestform' );
									$options_ph    = '5';
									$options_hint  = __( 'Enter one number for how many stars to show (1–10), e.g. 5.', 'nestform' );
								} elseif ( 'scale' === $type ) {
									$options_label = __( 'Scale setup', 'nestform' );
									$options_tip   = __( 'Line 1–2 = number range, line 3–4 = labels under the ends of the scale.', 'nestform' );
									$options_ph    = __( "1\n5\nVery dissatisfied\nVery satisfied", 'nestform' );
									$options_hint  = __( 'Four lines: lowest number, highest number, left label, right label.', 'nestform' );
								} elseif ( 'matrix' === $type ) {
									$options_label = __( 'Rows and columns', 'nestform' );
									$options_tip   = __( 'Rows above ---, columns below. Each line is one label.', 'nestform' );
									$options_ph    = __( "Support\nProduct\n---\nPoor\nFair\nGood", 'nestform' );
									$options_hint  = __( 'List row labels, then a line with only ---, then column labels.', 'nestform' );
								}
								?>
								<span class="nestform-admin__label">
									<span data-nestform-options-label><?php echo esc_html( $options_label ); ?></span>
									<?php self::render_field_tip( $options_tip, array( 'data-nestform-options-tip' => '1' ) ); ?>
								</span>
								<textarea class="nestform-admin__input nestform-admin__textarea" name="<?php echo esc_attr( $prefix . '[options]' ); ?>" rows="3" data-nestform-options-input placeholder="<?php echo esc_attr( $options_ph ); ?>"<?php echo self::disabled_for_show( $type, 'options' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( 'calculated' === $type ? '' : (string) ( $field['options'] ?? '' ) ); ?></textarea>
								<p class="nestform-admin__hint" data-nestform-options-hint><?php echo esc_html( $options_hint ); ?></p>
							</label>
							<label class="nestform-admin__field-control nestform-admin__field-control--full" data-nestform-show="formula">
								<span class="nestform-admin__label">
									<?php esc_html_e( 'Formula', 'nestform' ); ?>
									<?php self::render_field_tip( __( 'Use field names in braces. Operators: + - * / ( ). Functions: min(), max(), round(). Example: {price} * {qty}', 'nestform' ) ); ?>
								</span>
								<textarea class="nestform-admin__input nestform-admin__textarea" name="<?php echo esc_attr( $prefix . '[options]' ); ?>" rows="2" placeholder="{price} * {qty}"<?php echo self::disabled_for_show( $type, 'formula' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_textarea( 'calculated' === $type ? (string) ( $field['options'] ?? '' ) : '' ); ?></textarea>
								<p class="nestform-admin__hint" data-nestform-show="formula">
									<?php esc_html_e( 'Names must match other fields’ Name (slug), e.g. price and qty → {price} * {qty}. The value is recalculated on the server on submit.', 'nestform' ); ?>
								</p>
							</label>
							<div class="nestform-admin__field-control nestform-admin__field-control--full" data-nestform-show="subfields" data-nestform-subfields>
								<span class="nestform-admin__label">
									<?php esc_html_e( 'Subfields', 'nestform' ); ?>
									<?php self::render_field_tip( __( 'Each row on the form repeats this set of fields. Visitors can add more rows.', 'nestform' ) ); ?>
								</span>
								<p class="nestform-admin__hint nestform-card__type-intro">
									<?php esc_html_e( 'Define the columns of one row. Choice fields need one option per line. Calculated fields need a formula with other subfield names.', 'nestform' ); ?>
								</p>
								<div class="nestform-subfields-preview" data-nestform-subfields-preview hidden>
									<span class="nestform-subfields-preview__label"><?php esc_html_e( 'Row columns', 'nestform' ); ?></span>
									<div class="nestform-subfields-preview__cols" data-nestform-subfields-preview-cols></div>
								</div>
								<?php
								$sub_type_labels = Nestform_Form_Config::input_field_type_labels();
								$subs            = isset( $field['subfields'] ) && is_array( $field['subfields'] ) ? $field['subfields'] : array();
								$subs_empty      = array() === $subs;
								?>
								<div class="nestform-subfields-empty" data-nestform-subfields-empty<?php echo $subs_empty ? '' : ' hidden'; ?>>
									<p class="nestform-subfields-empty__text"><?php esc_html_e( 'No columns yet. Add the fields that make up one repeater row.', 'nestform' ); ?></p>
									<button type="button" class="nestform-btn nestform-btn--outline" data-nestform-subfield-add>
										<?php esc_html_e( 'Add first subfield', 'nestform' ); ?>
									</button>
								</div>
								<div class="nestform-subfields" data-nestform-subfields-list<?php echo $subs_empty ? ' hidden' : ''; ?>>
									<?php
									foreach ( $subs as $si => $sub ) {
										self::render_subfield_row( $prefix . '[subfields][' . $si . ']', $sub, $sub_type_labels );
									}
									?>
								</div>
								<button type="button" class="nestform-btn nestform-btn--outline" data-nestform-subfield-add data-nestform-subfield-add-more<?php echo $subs_empty ? ' hidden' : ''; ?>>
									<?php esc_html_e( 'Add subfield', 'nestform' ); ?>
								</button>
								<template data-nestform-subfield-template>
									<?php
									self::render_subfield_row(
										$prefix . '[subfields][__SI__]',
										array(
											'type'  => 'text',
											'name'  => '',
											'label' => '',
										),
										$sub_type_labels
									);
									?>
								</template>
							</div>
							<div class="nestform-admin__other-row" data-nestform-other-row>
								<label class="nestform-admin__check nestform-admin__field-control nestform-admin__other-row__allow" data-nestform-show="choice-other">
									<input type="checkbox" name="<?php echo esc_attr( $prefix . '[allow_other]' ); ?>" value="1" <?php checked( ! empty( $field['allow_other'] ) ); ?> data-nestform-allow-other<?php echo self::disabled_for_show( $type, 'choice-other' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
									<span class="nestform-admin__other-row__allow-text"><?php esc_html_e( 'Allow “Other” with a text field', 'nestform' ); ?></span>
								</label>
								<label class="nestform-admin__field-control nestform-admin__other-row__label" data-nestform-show="choice-other" data-nestform-other-label<?php echo empty( $field['allow_other'] ) ? ' hidden' : ''; ?>>
									<span class="nestform-admin__label">
										<?php esc_html_e( 'Other label', 'nestform' ); ?>
										<?php self::render_field_tip( __( 'Text shown for the extra choice. Leave empty for “Other”.', 'nestform' ) ); ?>
									</span>
									<input type="text" class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[other_label]' ); ?>" value="<?php echo esc_attr( (string) ( $field['other_label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Other', 'nestform' ); ?>"<?php echo self::disabled_for_show( $type, 'choice-other' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
								</label>
							</div>
							</div>
						</div>
					</section>

					<details class="nestform-card__details" data-nestform-section="more" <?php echo $more_open ? 'open' : ''; ?>>
						<summary>
							<span class="nestform-card__details-chevron" aria-hidden="true"></span>
							<span class="nestform-card__details-copy">
								<span class="nestform-card__details-title"><?php esc_html_e( 'More', 'nestform' ); ?></span>
								<span class="nestform-card__details-hint"><?php esc_html_e( 'Placeholder, default value, help text, and width.', 'nestform' ); ?></span>
							</span>
						</summary>
						<div class="nestform-card__details-body">
							<div class="nestform-card__section-grid">
								<label class="nestform-admin__field-control" data-nestform-show="placeholder">
									<span class="nestform-admin__label">
										<?php esc_html_e( 'Placeholder', 'nestform' ); ?>
										<?php self::render_field_tip( __( 'Text inputs: hint inside the field. Select: label on the custom trigger when nothing is chosen.', 'nestform' ) ); ?>
									</span>
									<input type="text" class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[placeholder]' ); ?>" value="<?php echo esc_attr( $ph_val ); ?>" data-nestform-placeholder placeholder="<?php echo esc_attr( 'select' === $type ? __( 'Select…', 'nestform' ) : __( 'Optional hint', 'nestform' ) ); ?>"<?php echo self::disabled_for_show( $type, 'placeholder' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
								</label>
								<label class="nestform-admin__field-control" data-nestform-show="default">
									<span class="nestform-admin__label"><?php esc_html_e( 'Default value', 'nestform' ); ?></span>
									<input type="text" class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[default]' ); ?>" value="<?php echo esc_attr( $def_val ); ?>"<?php echo self::disabled_for_show( $type, 'default' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
								</label>
								<label class="nestform-admin__field-control" data-nestform-show="description">
									<span class="nestform-admin__label" data-nestform-description-label><?php echo 'image' === $type ? esc_html__( 'Alt text', 'nestform' ) : esc_html__( 'Help text', 'nestform' ); ?></span>
									<input type="text" class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[description]' ); ?>" value="<?php echo esc_attr( $desc_val ); ?>" data-nestform-description placeholder="<?php echo esc_attr( 'image' === $type ? __( 'Describe the image', 'nestform' ) : __( 'Shown under the field', 'nestform' ) ); ?>"<?php echo self::disabled_for_show( $type, 'description' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> />
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'Width', 'nestform' ); ?></span>
									<select class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[width]' ); ?>">
										<option value="full" <?php selected( $field_width, 'full' ); ?>><?php esc_html_e( 'Full', 'nestform' ); ?></option>
										<option value="half" <?php selected( $field_width, 'half' ); ?>><?php esc_html_e( 'Half', 'nestform' ); ?></option>
									</select>
								</label>
								<input type="hidden" name="<?php echo esc_attr( $prefix . '[step]' ); ?>" value="<?php echo esc_attr( (string) $step ); ?>" data-nestform-step />
							</div>
						</div>
					</details>

					<details class="nestform-card__details" data-nestform-section="condition" <?php echo $is_layout ? 'hidden' : ''; ?> <?php echo ( ! $is_layout && $cond_open ) ? 'open' : ''; ?>>
						<summary>
							<span class="nestform-card__details-chevron" aria-hidden="true"></span>
							<span class="nestform-card__details-copy">
								<span class="nestform-card__details-title"><?php esc_html_e( 'Logic', 'nestform' ); ?></span>
								<span class="nestform-card__details-hint"><?php esc_html_e( 'Optional — show this field only when another field matches a rule.', 'nestform' ); ?></span>
							</span>
						</summary>
						<div class="nestform-card__details-body">
							<div class="nestform-card__section-grid" data-nestform-show="condition" <?php echo $is_layout ? 'hidden' : ''; ?>>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label">
										<?php esc_html_e( 'Watch field', 'nestform' ); ?>
										<?php self::render_field_tip( __( 'Leave empty to always show.', 'nestform' ) ); ?>
									</span>
									<select class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[condition_field]' ); ?>" data-nestform-condition-field<?php echo $is_layout ? ' disabled' : ''; ?>>
										<option value=""><?php esc_html_e( '— Always show —', 'nestform' ); ?></option>
										<?php if ( ! $is_layout && $cond_field !== '' ) : ?>
											<option value="<?php echo esc_attr( $cond_field ); ?>" selected><?php echo esc_html( $cond_field ); ?></option>
										<?php endif; ?>
									</select>
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'Operator', 'nestform' ); ?></span>
									<select class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[condition_op]' ); ?>" data-nestform-condition-op<?php echo $is_layout ? ' disabled' : ''; ?>>
										<?php foreach ( Nestform_Form_Config::condition_operators() as $op_key => $op_label ) : ?>
											<option value="<?php echo esc_attr( $op_key ); ?>" <?php selected( $cond_op, $op_key ); ?>><?php echo esc_html( $op_label ); ?></option>
										<?php endforeach; ?>
									</select>
								</label>
								<label class="nestform-admin__field-control" data-nestform-condition-value-wrap>
									<span class="nestform-admin__label"><?php esc_html_e( 'Value', 'nestform' ); ?></span>
									<input type="text" class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[condition_value]' ); ?>" value="<?php echo esc_attr( $is_layout ? '' : $cond_value ); ?>" placeholder="<?php esc_attr_e( 'e.g. Yes', 'nestform' ); ?>" data-nestform-condition-value<?php echo $is_layout ? ' disabled' : ''; ?> />
								</label>
							</div>
						</div>
					</details>

					<details class="nestform-card__details" data-nestform-section="advanced" <?php echo $adv_open ? 'open' : ''; ?>>
						<summary>
							<span class="nestform-card__details-chevron" aria-hidden="true"></span>
							<span class="nestform-card__details-copy">
								<span class="nestform-card__details-title"><?php esc_html_e( 'Advanced', 'nestform' ); ?></span>
								<span class="nestform-card__details-hint"><?php esc_html_e( 'Slug for mail tokens, CSS class, and step placement.', 'nestform' ); ?></span>
							</span>
						</summary>
						<div class="nestform-card__details-body">
							<div class="nestform-card__section-grid">
								<label class="nestform-admin__field-control" data-nestform-show="name">
									<span class="nestform-admin__label">
										<?php esc_html_e( 'Name (slug)', 'nestform' ); ?>
										<?php self::render_field_tip( __( 'Used in mail/PDF tokens as {name}. Lowercase letters, numbers, and underscores only.', 'nestform' ) ); ?>
									</span>
									<input type="text" class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[name]' ); ?>" value="<?php echo esc_attr( $name ); ?>" pattern="[a-z0-9_]+" data-nestform-name placeholder="email" />
								</label>
								<label class="nestform-admin__field-control">
									<span class="nestform-admin__label"><?php esc_html_e( 'CSS class', 'nestform' ); ?></span>
									<input type="text" class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[css_class]' ); ?>" value="<?php echo esc_attr( $css_class ); ?>" placeholder="my-field" />
								</label>
								<label class="nestform-admin__field-control" data-nestform-move-step-wrap>
									<span class="nestform-admin__label"><?php esc_html_e( 'Move to step', 'nestform' ); ?></span>
									<select class="nestform-admin__input" data-nestform-move-step>
										<?php for ( $s = 1; $s <= 10; $s++ ) : ?>
											<option value="<?php echo esc_attr( (string) $s ); ?>" <?php selected( $step, $s ); ?>><?php echo esc_html( sprintf( /* translators: %d */ __( 'Step %d', 'nestform' ), $s ) ); ?></option>
										<?php endfor; ?>
									</select>
								</label>
							</div>
						</div>
					</details>
				</div>
				<aside class="nestform-card__preview" data-nestform-field-preview>
					<div class="nestform-card__preview-head">
						<span class="nestform-card__preview-title"><?php esc_html_e( 'Preview', 'nestform' ); ?></span>
						<span class="nestform-card__preview-note"><?php esc_html_e( 'Approximate front-end look', 'nestform' ); ?></span>
					</div>
					<div class="nestform-card__preview-stage nestform-live-preview" data-nestform-field-preview-stage aria-hidden="true"></div>
				</aside>
			</div>
		</article>
		<?php
	}

	/**
	 * Contextual title for the primary type-settings block.
	 *
	 * @param string $type Field type.
	 * @return string Empty when the type has no extra settings.
	 */
	private static function type_section_title( $type ) {
		$map = array(
			'heading'    => __( 'Heading', 'nestform' ),
			'image'      => __( 'Image', 'nestform' ),
			'html'       => __( 'HTML', 'nestform' ),
			'paragraph'  => __( 'Paragraph', 'nestform' ),
			'spacer'     => __( 'Spacer', 'nestform' ),
			'tel'        => __( 'Phone', 'nestform' ),
			'file'       => __( 'Upload limits', 'nestform' ),
			'select'     => __( 'Choices', 'nestform' ),
			'radio'      => __( 'Choices', 'nestform' ),
			'checkboxes' => __( 'Choices', 'nestform' ),
			'range'      => __( 'Range', 'nestform' ),
			'rating'     => __( 'Choices', 'nestform' ),
			'scale'      => __( 'Choices', 'nestform' ),
			'ranking'    => __( 'Choices', 'nestform' ),
			'matrix'     => __( 'Matrix', 'nestform' ),
			'calculated' => __( 'Formula', 'nestform' ),
			'repeater'   => __( 'Row fields', 'nestform' ),
		);
		$type = (string) $type;
		return isset( $map[ $type ] ) ? (string) $map[ $type ] : '';
	}

	/**
	 * Starter templates gallery (modal + empty state).
	 *
	 * @param int  $form_id Form post ID.
	 * @param bool $empty   Show empty-state hero.
	 */
	private static function render_templates_panel( $form_id, $empty = false ) {
		$form_id   = (int) $form_id;
		$can_apply = $form_id > 0 && Nestform_Post_Type::POST_TYPE === get_post_type( $form_id );
		unset( $empty );
		$templates = Nestform_Templates::all();
		?>
		<div class="nestform-templates" data-nestform-templates-drawer hidden>
			<div class="nestform-templates__backdrop" data-nestform-templates-close></div>
			<div class="nestform-templates__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Form templates', 'nestform' ); ?>">
				<header class="nestform-templates__head">
					<div>
						<strong><?php esc_html_e( 'Templates', 'nestform' ); ?></strong>
						<p class="description"><?php esc_html_e( 'Replaces fields, mail, and step settings. Validation messages stay as-is.', 'nestform' ); ?></p>
					</div>
					<button type="button" class="nestform-btn" data-nestform-templates-close><?php esc_html_e( 'Close', 'nestform' ); ?></button>
				</header>
				<div class="nestform-templates__filters" role="tablist">
					<button type="button" class="nestform-templates__chip is-active" data-nestform-templates-filter="all"><?php esc_html_e( 'All', 'nestform' ); ?></button>
					<button type="button" class="nestform-templates__chip" data-nestform-templates-filter="contact"><?php esc_html_e( 'Contact', 'nestform' ); ?></button>
					<button type="button" class="nestform-templates__chip" data-nestform-templates-filter="lead"><?php esc_html_e( 'Lead', 'nestform' ); ?></button>
					<button type="button" class="nestform-templates__chip" data-nestform-templates-filter="survey"><?php esc_html_e( 'Survey', 'nestform' ); ?></button>
					<button type="button" class="nestform-templates__chip" data-nestform-templates-filter="quiz"><?php esc_html_e( 'Quiz', 'nestform' ); ?></button>
					<button type="button" class="nestform-templates__chip" data-nestform-templates-filter="other"><?php esc_html_e( 'Other', 'nestform' ); ?></button>
				</div>
				<div class="nestform-templates__grid">
					<?php foreach ( $templates as $tpl_key => $tpl ) : ?>
						<?php
						$allowed  = Nestform_Templates::template_allowed( $tpl_key );
						$category = isset( $tpl['category'] ) ? sanitize_key( (string) $tpl['category'] ) : 'other';
						$requires = isset( $tpl['requires'] ) && is_array( $tpl['requires'] ) ? $tpl['requires'] : array();
						$upsell   = ! empty( $requires[0] ) ? (string) $requires[0] : 'quiz_survey';
						?>
						<?php if ( $allowed && $can_apply ) : ?>
							<a
								class="nestform-templates__card"
								data-nestform-templates-card
								data-category="<?php echo esc_attr( $category ); ?>"
								href="<?php echo esc_url( Nestform_Templates::url( $form_id, $tpl_key ) ); ?>"
								onclick="return confirm('<?php echo esc_js( __( 'Replace current fields with this template?', 'nestform' ) ); ?>');"
							>
								<span class="nestform-templates__card-label"><?php echo esc_html( $tpl['label'] ); ?></span>
								<?php if ( ! empty( $tpl['description'] ) ) : ?>
									<span class="nestform-templates__card-desc"><?php echo esc_html( $tpl['description'] ); ?></span>
								<?php endif; ?>
								<span class="nestform-templates__card-meta"><?php echo esc_html( ucfirst( $category ) ); ?></span>
							</a>
						<?php elseif ( ! $can_apply ) : ?>
							<button
								type="button"
								class="nestform-templates__card nestform-templates__card--disabled"
								data-nestform-templates-card
								data-nestform-templates-save-first
								data-category="<?php echo esc_attr( $category ); ?>"
							>
								<span class="nestform-templates__card-label"><?php echo esc_html( $tpl['label'] ); ?></span>
								<?php if ( ! empty( $tpl['description'] ) ) : ?>
									<span class="nestform-templates__card-desc"><?php echo esc_html( $tpl['description'] ); ?></span>
								<?php endif; ?>
								<span class="nestform-templates__card-meta"><?php esc_html_e( 'Save draft first', 'nestform' ); ?></span>
							</button>
						<?php else : ?>
							<button
								type="button"
								class="nestform-templates__card nestform-templates__card--pro"
								data-nestform-templates-card
								data-category="<?php echo esc_attr( $category ); ?>"
								data-nestform-pro-upsell="<?php echo esc_attr( $upsell ); ?>"
							>
								<span class="nestform-templates__card-label"><?php echo esc_html( $tpl['label'] ); ?> <?php echo Nestform_Upgrade::pill_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<?php if ( ! empty( $tpl['description'] ) ) : ?>
									<span class="nestform-templates__card-desc"><?php echo esc_html( $tpl['description'] ); ?></span>
								<?php endif; ?>
								<span class="nestform-templates__card-meta"><?php echo esc_html( ucfirst( $category ) ); ?></span>
							</button>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public static function render_shortcode_box( $post ) {
		$id     = (int) $post->ID;
		$slug   = $post->post_name ? $post->post_name : 'your-slug';
		$id_sc  = '[nestform id="' . $id . '"]';
		$slug_sc = '[nestform slug="' . $slug . '"]';
		$status = get_post_status( $post );
		$status_labels = array(
			'publish' => __( 'Published', 'nestform' ),
			'draft'   => __( 'Draft', 'nestform' ),
			'pending' => __( 'Pending', 'nestform' ),
			'private' => __( 'Private', 'nestform' ),
			'auto-draft' => __( 'Draft', 'nestform' ),
		);
		$status_label = $status_labels[ $status ] ?? ucfirst( (string) $status );
		$forms_url    = admin_url( 'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE );
		?>
		<div class="nestform-embed">
			<a class="nestform-btn nestform-btn--outline nestform-embed__back" href="<?php echo esc_url( $forms_url ); ?>">
				<?php nestform_admin_icon( 'back' ); ?>
				<?php esc_html_e( 'Forms', 'nestform' ); ?>
			</a>
			<div class="nestform-embed__status">
				<span class="nestform-embed__status-dot nestform-embed__status-dot--<?php echo esc_attr( 'publish' === $status ? 'live' : 'draft' ); ?>" aria-hidden="true"></span>
				<span class="nestform-embed__status-text"><?php echo esc_html( $status_label ); ?></span>
				<?php if ( $id > 0 && 'auto-draft' !== $status ) : ?>
					<span class="nestform-embed__status-id">#<?php echo esc_html( (string) $id ); ?></span>
				<?php endif; ?>
			</div>

			<label class="nestform-admin__label"><?php esc_html_e( 'Shortcode', 'nestform' ); ?></label>
			<div class="nestform-embed__row">
				<code class="nestform-embed__code" data-nestform-copy-text><?php echo esc_html( $id_sc ); ?></code>
				<button type="button" class="nestform-btn nestform-btn--outline nestform-embed__copy" data-nestform-copy aria-label="<?php esc_attr_e( 'Copy shortcode', 'nestform' ); ?>">
					<?php nestform_admin_icon( 'copy' ); ?>
				</button>
			</div>
			<label class="nestform-admin__label"><?php esc_html_e( 'By slug', 'nestform' ); ?></label>
			<div class="nestform-embed__row">
				<code class="nestform-embed__code" data-nestform-copy-text><?php echo esc_html( $slug_sc ); ?></code>
				<button type="button" class="nestform-btn nestform-btn--outline nestform-embed__copy" data-nestform-copy aria-label="<?php esc_attr_e( 'Copy shortcode', 'nestform' ); ?>">
					<?php nestform_admin_icon( 'copy' ); ?>
				</button>
			</div>
			<p class="description"><?php esc_html_e( 'Or pick this form in the Gutenberg Nestform block.', 'nestform' ); ?></p>

			<?php
			$embed_entries_url = '';
			$embed_entries_new = 0;
			if ( $id > 0 && 'auto-draft' !== $status && class_exists( 'Nestform_Submissions' ) ) {
				$embed_entries_new = (int) Nestform_Submissions::count_new_for_form( $id );
				$embed_entries_url = $embed_entries_new > 0
					? Nestform_Submissions::list_url( $id, Nestform_Submissions::STATUS_NEW )
					: Nestform_Submissions::list_url( $id );
			}
			$has_form_tools = ( $embed_entries_url !== '' )
				|| ( class_exists( 'Nestform_Form_IO' ) && $id > 0 && 'auto-draft' !== $status )
				|| ( $id > 0 && 'auto-draft' !== $status );
			?>
			<?php if ( $has_form_tools ) : ?>
			<div class="nestform-embed__tools">
				<span class="nestform-admin__label"><?php esc_html_e( 'Form', 'nestform' ); ?></span>
				<div class="nestform-embed__tools-list">
					<?php if ( $embed_entries_url !== '' ) : ?>
						<a class="nestform-btn nestform-btn--outline nestform-btn--accent" href="<?php echo esc_url( $embed_entries_url ); ?>">
							<?php nestform_admin_icon( 'entries' ); ?>
							<?php esc_html_e( 'Entries', 'nestform' ); ?>
							<?php if ( $embed_entries_new > 0 ) : ?>
								<span class="nestform-embed__entries-count"><?php echo esc_html( number_format_i18n( $embed_entries_new ) ); ?></span>
							<?php endif; ?>
						</a>
					<?php endif; ?>
					<?php if ( class_exists( 'Nestform_Form_IO' ) && $id > 0 && 'auto-draft' !== $status ) : ?>
						<a class="nestform-btn nestform-btn--outline" href="<?php echo esc_url( Nestform_Form_IO::export_url( $id ) ); ?>">
							<?php nestform_admin_icon( 'download' ); ?>
							<?php esc_html_e( 'Export', 'nestform' ); ?>
						</a>
					<?php endif; ?>
					<?php if ( $id > 0 && 'auto-draft' !== $status ) : ?>
						<a class="nestform-btn nestform-btn--outline" href="<?php echo esc_url( Nestform_Post_Type::duplicate_url( $id ) ); ?>">
							<?php esc_html_e( 'Duplicate', 'nestform' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>
			<?php endif; ?>

			<div class="nestform-embed__tools">
				<span class="nestform-admin__label"><?php esc_html_e( 'Templates', 'nestform' ); ?></span>
				<div class="nestform-embed__tools-list">
					<button type="button" class="nestform-btn nestform-btn--outline" data-nestform-templates-open>
						<?php nestform_admin_icon( 'forms' ); ?>
						<?php esc_html_e( 'Browse templates', 'nestform' ); ?>
					</button>
				</div>
			</div>

			<?php if ( 'publish' === $status || ( $id > 0 && 'auto-draft' !== $status ) ) : ?>
				<div class="nestform-embed__tools nestform-embed__tools--actions">
					<span class="nestform-admin__label"><?php esc_html_e( 'Actions', 'nestform' ); ?></span>
					<div class="nestform-embed__tools-list">
						<?php if ( 'publish' === $status ) : ?>
							<button type="submit" class="nestform-btn nestform-btn--warn" name="saveasdraft" value="1">
								<?php nestform_admin_icon( 'save' ); ?>
								<?php esc_html_e( 'Save draft', 'nestform' ); ?>
							</button>
						<?php endif; ?>
						<?php
						if ( $id > 0 && 'auto-draft' !== $status ) :
							$trash_url = get_delete_post_link( $id, '', false );
							if ( $trash_url && current_user_can( 'delete_post', $id ) ) :
								?>
								<a
									class="nestform-btn nestform-btn--danger"
									href="<?php echo esc_url( $trash_url ); ?>"
									onclick="return confirm('<?php echo esc_js( __( 'Move this form to Trash?', 'nestform' ) ); ?>');"
								>
									<?php nestform_admin_icon( 'trash' ); ?>
									<?php esc_html_e( 'Delete', 'nestform' ); ?>
								</a>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<div class="nestform-preview" data-nestform-preview-drawer hidden>
			<div class="nestform-preview__backdrop" data-nestform-preview-close></div>
			<div class="nestform-preview__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Form preview', 'nestform' ); ?>">
				<header class="nestform-preview__head">
					<strong><?php esc_html_e( 'Preview', 'nestform' ); ?></strong>
					<span class="description"><?php esc_html_e( 'Saved form only — save changes first.', 'nestform' ); ?></span>
					<button type="button" class="nestform-btn" data-nestform-preview-close><?php esc_html_e( 'Close', 'nestform' ); ?></button>
				</header>
				<iframe class="nestform-preview__frame" title="<?php esc_attr_e( 'Form preview', 'nestform' ); ?>" data-nestform-preview-frame></iframe>
			</div>
		</div>
		<?php
	}

	/**
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['nestform_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nestform_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['nestform'] ) || ! is_array( $_POST['nestform'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in Form_Config::save
		$raw    = wp_unslash( $_POST['nestform'] );
		$config = array(
			'fields'   => isset( $raw['fields'] ) && is_array( $raw['fields'] ) ? $raw['fields'] : array(),
			'messages' => isset( $raw['messages'] ) && is_array( $raw['messages'] ) ? $raw['messages'] : array(),
			'mail'     => isset( $raw['mail'] ) && is_array( $raw['mail'] ) ? $raw['mail'] : array(),
			'settings' => isset( $raw['settings'] ) && is_array( $raw['settings'] ) ? $raw['settings'] : array(),
		);

		foreach ( $config['fields'] as $i => $field ) {
			if ( ! is_array( $field ) ) {
				unset( $config['fields'][ $i ] );
				continue;
			}
			$config['fields'][ $i ]['required'] = ! empty( $field['required'] );
			if ( empty( $field['name'] ) && ! empty( $field['label'] ) ) {
				$config['fields'][ $i ]['name'] = sanitize_key( str_replace( '-', '_', sanitize_title( (string) $field['label'] ) ) );
			}
		}

		Nestform_Form_Config::save( $post_id, $config );
	}

	/**
	 * Map data-nestform-show → types (keep in sync with admin.js showForTypes).
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function show_for_types() {
		return array(
			'heading-level'  => array( 'heading' ),
			'image-picker'   => array( 'image' ),
			'html-content'   => array( 'html' ),
			'paragraph-text' => array( 'paragraph' ),
			'spacer-size'    => array( 'spacer' ),
			'phone-country'  => array( 'tel' ),
			'file-limits'    => array( 'file' ),
			'file-max'       => array( 'file' ),
			'options'        => array( 'select', 'radio', 'checkboxes', 'range', 'rating', 'scale', 'ranking', 'matrix' ),
			'formula'        => array( 'calculated' ),
			'subfields'      => array( 'repeater' ),
			'choice-other'   => array( 'select', 'radio', 'checkboxes' ),
			'placeholder'    => array( 'text', 'email', 'tel', 'url', 'password', 'number', 'textarea', 'select' ),
			'default'        => array( 'text', 'email', 'tel', 'url', 'password', 'number', 'range', 'textarea', 'hidden', 'select', 'radio', 'checkboxes', 'date', 'time', 'checkbox', 'rating', 'nps', 'scale' ),
			'description'    => array( 'text', 'email', 'tel', 'url', 'password', 'number', 'range', 'textarea', 'select', 'radio', 'checkboxes', 'checkbox', 'acceptance', 'file', 'image', 'date', 'time', 'hidden', 'rating', 'signature', 'nps', 'scale', 'ranking', 'paragraph', 'calculated', 'repeater' ),
			'acceptance-html'=> array( 'acceptance' ),
			'condition'      => array( 'text', 'email', 'tel', 'url', 'password', 'number', 'range', 'textarea', 'select', 'radio', 'checkboxes', 'checkbox', 'acceptance', 'file', 'date', 'time', 'hidden', 'rating', 'signature', 'nps', 'scale', 'ranking', 'calculated', 'repeater' ),
		);
	}

	/**
	 * Render one repeater subfield row in the builder.
	 *
	 * @param string                $prefix      Name prefix.
	 * @param array<string, mixed>  $sub         Subfield.
	 * @param array<string, string> $input_types Input type labels (from Form_Config).
	 */
	private static function render_subfield_row( $prefix, array $sub, array $input_types ) {
		$sub_type = (string) ( $sub['type'] ?? 'text' );
		$allowed  = array(
			'text'       => __( 'Text', 'nestform' ),
			'email'      => __( 'Email', 'nestform' ),
			'tel'        => __( 'Phone', 'nestform' ),
			'url'        => __( 'URL', 'nestform' ),
			'number'     => __( 'Number', 'nestform' ),
			'range'      => __( 'Range', 'nestform' ),
			'date'       => __( 'Date', 'nestform' ),
			'time'       => __( 'Time', 'nestform' ),
			'textarea'   => __( 'Textarea', 'nestform' ),
			'select'     => __( 'Select', 'nestform' ),
			'radio'      => __( 'Radio', 'nestform' ),
			'checkboxes' => __( 'Checkboxes', 'nestform' ),
			'checkbox'   => __( 'Checkbox', 'nestform' ),
			'acceptance' => __( 'Acceptance', 'nestform' ),
		);
		if ( ! empty( $input_types['calculated'] ) ) {
			$allowed['calculated'] = (string) $input_types['calculated'];
		} elseif ( class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::CALCULATED_FIELDS ) ) {
			$allowed['calculated'] = __( 'Calculated', 'nestform' );
		}
		// Prefer labels from the live input map when present.
		foreach ( $allowed as $key => $label ) {
			if ( isset( $input_types[ $key ] ) && is_string( $input_types[ $key ] ) && $input_types[ $key ] !== '' ) {
				$allowed[ $key ] = $input_types[ $key ];
			}
		}
		if ( ! isset( $allowed[ $sub_type ] ) ) {
			$sub_type = 'text';
		}
		$needs_options = in_array( $sub_type, array( 'select', 'radio', 'checkboxes', 'range', 'calculated' ), true );
		$options_label = 'calculated' === $sub_type
			? __( 'Formula', 'nestform' )
			: ( 'range' === $sub_type ? __( 'Min / max / step', 'nestform' ) : __( 'Choices (one per line)', 'nestform' ) );
		$options_ph    = 'calculated' === $sub_type
			? '{price} * {qty}'
			: ( 'range' === $sub_type ? "0\n100\n1" : __( "Yes\nNo", 'nestform' ) );
		?>
		<div class="nestform-subfield" data-nestform-subfield>
			<div class="nestform-subfield__grid">
				<label class="nestform-admin__field-control nestform-subfield__type">
					<span class="nestform-admin__label"><?php esc_html_e( 'Type', 'nestform' ); ?></span>
					<select class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[type]' ); ?>" data-nestform-subfield-type>
						<?php foreach ( $allowed as $t => $type_label ) : ?>
							<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $sub_type, $t ); ?>><?php echo esc_html( $type_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="nestform-admin__field-control nestform-subfield__label">
					<span class="nestform-admin__label"><?php esc_html_e( 'Label', 'nestform' ); ?></span>
					<input type="text" class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[label]' ); ?>" value="<?php echo esc_attr( (string) ( $sub['label'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Visible label', 'nestform' ); ?>" data-nestform-subfield-label />
				</label>
				<label class="nestform-admin__field-control nestform-subfield__name">
					<span class="nestform-admin__label"><?php esc_html_e( 'Name', 'nestform' ); ?></span>
					<input type="text" class="nestform-admin__input" name="<?php echo esc_attr( $prefix . '[name]' ); ?>" value="<?php echo esc_attr( (string) ( $sub['name'] ?? '' ) ); ?>" placeholder="item" pattern="[a-z0-9_]+" data-nestform-subfield-name />
				</label>
				<label class="nestform-admin__check nestform-subfield__required">
					<input type="checkbox" name="<?php echo esc_attr( $prefix . '[required]' ); ?>" value="1" <?php checked( ! empty( $sub['required'] ) ); ?> />
					<span><?php esc_html_e( 'Required', 'nestform' ); ?></span>
				</label>
				<button type="button" class="nestform-btn nestform-btn--ghost nestform-btn--danger-text nestform-subfield__remove" data-nestform-subfield-remove>
					<?php esc_html_e( 'Remove', 'nestform' ); ?>
				</button>
			</div>
			<label class="nestform-admin__field-control nestform-admin__field-control--full nestform-subfield__options" data-nestform-subfield-options<?php echo $needs_options ? '' : ' hidden'; ?>>
				<span class="nestform-admin__label" data-nestform-subfield-options-label><?php echo esc_html( $options_label ); ?></span>
				<textarea
					class="nestform-admin__input nestform-admin__textarea"
					name="<?php echo esc_attr( $prefix . '[options]' ); ?>"
					rows="3"
					data-nestform-subfield-options-input
					placeholder="<?php echo esc_attr( $options_ph ); ?>"
					<?php echo $needs_options ? '' : ' disabled'; ?>
				><?php echo esc_textarea( (string) ( $sub['options'] ?? '' ) ); ?></textarea>
				<p class="nestform-admin__hint" data-nestform-subfield-options-hint>
					<?php
					if ( 'calculated' === $sub_type ) {
						esc_html_e( 'Use other subfield names in braces, e.g. {qty} * {price}.', 'nestform' );
					} elseif ( 'range' === $sub_type ) {
						esc_html_e( 'Three lines: minimum, maximum, step.', 'nestform' );
					} else {
						esc_html_e( 'One choice per line — the text visitors see. Example: Yes', 'nestform' );
					}
					?>
				</p>
			</label>
		</div>
		<?php
	}

	/**
	 * Disable inactive named controls so shared keys (options/default) do not collide on save.
	 *
	 * @param string $type Current field type.
	 * @param string $key  data-nestform-show key.
	 * @return string
	 */
	private static function disabled_for_show( $type, $key ) {
		$map = self::show_for_types();
		if ( ! isset( $map[ $key ] ) ) {
			return '';
		}
		return in_array( $type, $map[ $key ], true ) ? '' : ' disabled';
	}
}
