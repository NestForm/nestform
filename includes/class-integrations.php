<?php
/**
 * Integrations (Forms → Integrations). Captcha and future connectors.
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Integrations {

	const PAGE_SLUG = 'nestform-integrations';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 41 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
	}

	/**
	 * @return string
	 */
	public static function url() {
		return add_query_arg(
			array(
				'post_type' => Nestform_Post_Type::POST_TYPE,
				'page'      => self::PAGE_SLUG,
			),
			admin_url( 'edit.php' )
		);
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE,
			__( 'Integrations', 'nestform' ),
			__( 'Integrations', 'nestform' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function admin_body_class( $classes ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE_SLUG === $page ) {
			$classes .= ' nestform-admin-screen nestform-integrations-screen nestform-settings-screen';
		}
		return $classes;
	}

	/**
	 * @param string $hook Hook.
	 */
	public static function assets( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( self::PAGE_SLUG !== $page && false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}
		$ver = (string) filemtime( NESTFORM_PATH . 'assets/admin.css' );
		wp_enqueue_style(
			'nestform-admin',
			NESTFORM_URL . 'assets/admin.css',
			nestform_admin_style_deps(),
			$ver ? $ver : NESTFORM_VERSION
		);
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to edit these settings.', 'nestform' ) );
		}

		$s        = Nestform_Settings::get();
		$provider = (string) $s['captcha_provider'];
		$opt      = Nestform_Settings::OPTION;
		?>
		<div class="wrap nestform-admin nestform-settings nestform-integrations">
			<?php
			nestform_render_page_head(
				array(
					'title'       => __( 'Integrations', 'nestform' ),
					'description' => __( 'Connect Nestform to external services. Per-form toggles still apply where noted.', 'nestform' ),
					'icon'        => 'integrations',
				)
			);
			settings_errors();
			?>

			<form method="post" action="options.php" class="nestform-settings__form">
				<?php settings_fields( 'nestform_settings' ); ?>
				<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[_section]" value="integrations" />

				<div class="nestform-admin__surface nestform-settings__card">
					<div class="nestform-admin__panel-head">
						<div>
							<h2 class="nestform-admin__panel-title"><?php esc_html_e( 'reCAPTCHA', 'nestform' ); ?></h2>
							<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Keys from the Google reCAPTCHA admin console. Used only on forms where captcha is turned on.', 'nestform' ); ?></p>
						</div>
					</div>
					<table class="form-table nestform-settings__table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Enable captcha', 'nestform' ); ?></th>
							<td>
								<fieldset>
									<label class="nestform-admin__check" for="nestform_captcha_enabled">
										<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[captcha_enabled]" value="0" />
										<input
											type="checkbox"
											id="nestform_captcha_enabled"
											name="<?php echo esc_attr( $opt ); ?>[captcha_enabled]"
											value="1"
											<?php checked( (string) $s['captcha_enabled'], '1' ); ?>
										/>
										<span><?php esc_html_e( 'Allow reCAPTCHA on Nestform forms', 'nestform' ); ?></span>
									</label>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="nestform_captcha_provider"><?php esc_html_e( 'Provider', 'nestform' ); ?></label></th>
							<td>
								<select id="nestform_captcha_provider" class="nestform-admin__input" name="<?php echo esc_attr( $opt ); ?>[captcha_provider]">
									<option value="recaptcha_v2" <?php selected( $provider, 'recaptcha_v2' ); ?>><?php esc_html_e( 'reCAPTCHA v2 (checkbox)', 'nestform' ); ?></option>
									<option value="recaptcha_v3" <?php selected( $provider, 'recaptcha_v3' ); ?>><?php esc_html_e( 'reCAPTCHA v3 (invisible)', 'nestform' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="nestform_captcha_site_key"><?php esc_html_e( 'Site key', 'nestform' ); ?></label></th>
							<td>
								<input
									type="text"
									class="regular-text nestform-admin__input"
									id="nestform_captcha_site_key"
									name="<?php echo esc_attr( $opt ); ?>[captcha_site_key]"
									value="<?php echo esc_attr( (string) $s['captcha_site_key'] ); ?>"
									autocomplete="off"
									spellcheck="false"
								/>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="nestform_captcha_secret_key"><?php esc_html_e( 'Secret key', 'nestform' ); ?></label></th>
							<td>
								<input
									type="password"
									class="regular-text nestform-admin__input"
									id="nestform_captcha_secret_key"
									name="<?php echo esc_attr( $opt ); ?>[captcha_secret_key]"
									value="<?php echo esc_attr( (string) $s['captcha_secret_key'] ); ?>"
									autocomplete="new-password"
									spellcheck="false"
								/>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="nestform_captcha_v3_score"><?php esc_html_e( 'v3 min score', 'nestform' ); ?></label></th>
							<td>
								<input
									type="number"
									class="small-text nestform-admin__input"
									id="nestform_captcha_v3_score"
									name="<?php echo esc_attr( $opt ); ?>[captcha_v3_score]"
									min="0"
									max="1"
									step="0.1"
									value="<?php echo esc_attr( (string) $s['captcha_v3_score'] ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Only for reCAPTCHA v3 (0.0–1.0, default 0.5).', 'nestform' ); ?></p>
							</td>
						</tr>
					</table>
					<button type="submit" class="nestform-btn nestform-btn--primary" name="submit" value="1">
						<?php nestform_admin_icon( 'save' ); ?>
						<?php esc_html_e( 'Save integrations', 'nestform' ); ?>
					</button>
				</div>
			</form>

			<?php
			$webhook_url = class_exists( 'Nestform_Post_Type' )
				? admin_url( 'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE )
				: admin_url();
			$cards       = array(
				array(
					'title'  => __( 'Outbound webhook', 'nestform' ),
					'desc'   => __( 'POST JSON on every successful submission. Configure per form under Settings → Webhook.', 'nestform' ),
					'status' => __( 'Built-in (Pro)', 'nestform' ),
					'action' => array(
						'label' => __( 'Open forms', 'nestform' ),
						'url'   => $webhook_url,
					),
				),
				array(
					'title'  => __( 'Telegram', 'nestform' ),
					'desc'   => __( 'Push lead alerts to a Telegram chat or channel.', 'nestform' ),
					'status' => __( 'Coming soon', 'nestform' ),
					'action' => null,
				),
				array(
					'title'  => __( 'Slack', 'nestform' ),
					'desc'   => __( 'Notify a Slack channel when a form converts.', 'nestform' ),
					'status' => __( 'Coming soon', 'nestform' ),
					'action' => null,
				),
				array(
					'title'  => __( 'Google Sheets', 'nestform' ),
					'desc'   => __( 'Append rows to a spreadsheet automatically.', 'nestform' ),
					'status' => __( 'Coming soon', 'nestform' ),
					'action' => null,
				),
			);
			?>
			<div class="nestform-admin__surface nestform-settings__card nestform-integ-cards">
				<div class="nestform-admin__panel-head">
					<div>
						<h2 class="nestform-admin__panel-title"><?php esc_html_e( 'More connectors', 'nestform' ); ?></h2>
						<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Use webhooks today; native connectors land next. Automations can also fire a conditional webhook when IF matches.', 'nestform' ); ?></p>
					</div>
				</div>
				<div class="nestform-integ-cards__grid">
					<?php foreach ( $cards as $card ) : ?>
						<article class="nestform-integ-card">
							<div class="nestform-integ-card__top">
								<h3 class="nestform-integ-card__title"><?php echo esc_html( $card['title'] ); ?></h3>
								<span class="nestform-integ-card__status"><?php echo esc_html( $card['status'] ); ?></span>
							</div>
							<p class="nestform-integ-card__desc"><?php echo esc_html( $card['desc'] ); ?></p>
							<?php if ( ! empty( $card['action']['url'] ) ) : ?>
								<a class="nestform-btn nestform-btn--outline nestform-integ-card__action" href="<?php echo esc_url( $card['action']['url'] ); ?>">
									<?php echo esc_html( (string) $card['action']['label'] ); ?>
								</a>
							<?php else : ?>
								<span class="nestform-integ-card__action nestform-integ-card__action--muted"><?php esc_html_e( 'Notify me later', 'nestform' ); ?></span>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
