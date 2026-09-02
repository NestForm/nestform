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
		$ver_css = (string) filemtime( nestform_admin_css_path() );
		wp_enqueue_style(
			'nestform-admin',
			nestform_admin_css_url(),
			nestform_admin_style_deps(),
			$ver_css ? $ver_css : NESTFORM_VERSION
		);

		$js = nestform_admin_js_path( 'integrations-captcha.js' );
		if ( is_readable( $js ) ) {
			$ver_js = (string) filemtime( $js );
			wp_enqueue_script(
				'nestform-integrations-captcha',
				nestform_admin_js_url( 'integrations-captcha.js' ),
				array(),
				$ver_js ? $ver_js : NESTFORM_VERSION,
				true
			);
		}
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to edit these settings.', 'nestform' ) );
		}

		$s         = Nestform_Settings::get();
		$provider  = (string) $s['captcha_provider'];
		$opt       = Nestform_Settings::OPTION;
		$providers = class_exists( 'Nestform_Captcha' ) ? Nestform_Captcha::providers() : array();
		$keys      = isset( $s['captcha_keys'] ) && is_array( $s['captcha_keys'] ) ? $s['captcha_keys'] : Nestform_Settings::empty_captcha_keys();
		$enabled   = '1' === (string) $s['captcha_enabled'];
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

				<div class="nestform-admin__surface nestform-settings__card nestform-captcha-integ" data-nestform-captcha-tabs>
					<div class="nestform-admin__panel-head">
						<div>
							<h2 class="nestform-admin__panel-title"><?php esc_html_e( 'Captcha', 'nestform' ); ?></h2>
							<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Only one provider can be active site-wide. Forms opt in under Spam & privacy → Enable captcha on this form.', 'nestform' ); ?></p>
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
											<?php checked( $enabled ); ?>
										/>
										<span><?php esc_html_e( 'Allow captcha on Nestform forms', 'nestform' ); ?></span>
									</label>
									<p class="description"><?php esc_html_e( 'Master switch. Individual forms still need “Enable captcha on this form”.', 'nestform' ); ?></p>
								</fieldset>
							</td>
						</tr>
					</table>

					<input
						type="hidden"
						name="<?php echo esc_attr( $opt ); ?>[captcha_provider]"
						value="<?php echo esc_attr( $provider ); ?>"
						data-nestform-captcha-provider
					/>

					<nav class="nestform-settings__subnav nestform-captcha-integ__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Captcha provider', 'nestform' ); ?>">
						<?php foreach ( $providers as $slug => $meta ) : ?>
							<?php
							$is_active = ( $provider === $slug );
							$tab_label = ! empty( $meta['short'] ) ? (string) $meta['short'] : (string) $meta['label'];
							$has_keys  = ! empty( $keys[ $slug ]['site'] ) && ! empty( $keys[ $slug ]['secret'] );
							?>
							<button
								type="button"
								class="nestform-settings__subnav-item<?php echo $is_active ? ' nestform-settings__subnav-item--active' : ''; ?>"
								role="tab"
								id="nestform-captcha-tab-<?php echo esc_attr( $slug ); ?>"
								aria-controls="nestform-captcha-panel-<?php echo esc_attr( $slug ); ?>"
								aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
								tabindex="<?php echo $is_active ? '0' : '-1'; ?>"
								data-nestform-captcha-tab="<?php echo esc_attr( $slug ); ?>"
							>
								<span class="nestform-captcha-integ__tab-label"><?php echo esc_html( $tab_label ); ?></span>
								<?php if ( $is_active && $enabled && $has_keys ) : ?>
									<span class="nestform-badge nestform-badge--ok nestform-captcha-integ__tab-badge"><?php esc_html_e( 'Active', 'nestform' ); ?></span>
								<?php elseif ( $has_keys ) : ?>
									<span class="nestform-badge nestform-badge--draft nestform-captcha-integ__tab-badge"><?php esc_html_e( 'Saved', 'nestform' ); ?></span>
								<?php endif; ?>
							</button>
						<?php endforeach; ?>
					</nav>

					<?php foreach ( $providers as $slug => $meta ) : ?>
						<?php
						$is_active   = ( $provider === $slug );
						$site_val    = isset( $keys[ $slug ]['site'] ) ? (string) $keys[ $slug ]['site'] : '';
						$secret_val  = isset( $keys[ $slug ]['secret'] ) ? (string) $keys[ $slug ]['secret'] : '';
						$site_id     = 'nestform_captcha_site_' . $slug;
						$secret_id   = 'nestform_captcha_secret_' . $slug;
						?>
						<div
							class="nestform-captcha-integ__panel<?php echo $is_active ? ' is-active' : ''; ?>"
							id="nestform-captcha-panel-<?php echo esc_attr( $slug ); ?>"
							role="tabpanel"
							aria-labelledby="nestform-captcha-tab-<?php echo esc_attr( $slug ); ?>"
							data-nestform-captcha-panel="<?php echo esc_attr( $slug ); ?>"
							<?php echo $is_active ? '' : ' hidden'; ?>
						>
							<p class="nestform-captcha-integ__panel-lead">
								<?php echo esc_html( (string) $meta['label'] ); ?>
								<?php if ( ! empty( $meta['keys_url'] ) ) : ?>
									—
									<a href="<?php echo esc_url( (string) $meta['keys_url'] ); ?>" target="_blank" rel="noopener noreferrer">
										<?php esc_html_e( 'Get API keys', 'nestform' ); ?>
									</a>
								<?php endif; ?>
							</p>
							<table class="form-table nestform-settings__table" role="presentation">
								<tr>
									<th scope="row"><label for="<?php echo esc_attr( $site_id ); ?>"><?php esc_html_e( 'Site key', 'nestform' ); ?></label></th>
									<td>
										<input
											type="text"
											class="regular-text nestform-admin__input"
											id="<?php echo esc_attr( $site_id ); ?>"
											name="<?php echo esc_attr( $opt ); ?>[captcha_keys][<?php echo esc_attr( $slug ); ?>][site]"
											value="<?php echo esc_attr( $site_val ); ?>"
											autocomplete="off"
											spellcheck="false"
										/>
									</td>
								</tr>
								<tr>
									<th scope="row"><label for="<?php echo esc_attr( $secret_id ); ?>"><?php esc_html_e( 'Secret key', 'nestform' ); ?></label></th>
									<td>
										<input
											type="password"
											class="regular-text nestform-admin__input"
											id="<?php echo esc_attr( $secret_id ); ?>"
											name="<?php echo esc_attr( $opt ); ?>[captcha_keys][<?php echo esc_attr( $slug ); ?>][secret]"
											value="<?php echo esc_attr( $secret_val ); ?>"
											autocomplete="new-password"
											spellcheck="false"
										/>
									</td>
								</tr>
								<?php if ( 'recaptcha_v3' === $slug ) : ?>
									<tr>
										<th scope="row"><label for="nestform_captcha_v3_score"><?php esc_html_e( 'Min score', 'nestform' ); ?></label></th>
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
											<p class="description"><?php esc_html_e( '0.0–1.0 (default 0.5). Lower accepts more traffic; higher is stricter.', 'nestform' ); ?></p>
										</td>
									</tr>
								<?php endif; ?>
							</table>
						</div>
					<?php endforeach; ?>

					<button type="submit" class="nestform-btn nestform-btn--primary" name="submit" value="1">
						<?php nestform_admin_icon( 'save' ); ?>
						<?php esc_html_e( 'Save integrations', 'nestform' ); ?>
					</button>
				</div>
			</form>

			<?php
			$cards = array();
			$webhook_url = class_exists( 'Nestform_Post_Type' )
				? admin_url( 'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE )
				: admin_url();
			$cards[]     = array(
				'title'  => __( 'Outbound webhooks', 'nestform' ),
				'desc'   => __( 'POST JSON on every successful submission to one or more HTTPS endpoints. Configure per form under Settings → Webhooks.', 'nestform' ),
				'status' => __( 'Built-in', 'nestform' ),
				'action' => array(
					'label' => __( 'Open forms', 'nestform' ),
					'url'   => $webhook_url,
				),
			);
			$cards = array_merge(
				$cards,
				array(
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
				)
			);
			?>
			<?php if ( array() !== $cards ) : ?>
			<div class="nestform-admin__surface nestform-settings__card nestform-integ-cards">
				<div class="nestform-admin__panel-head">
					<div>
						<h2 class="nestform-admin__panel-title"><?php esc_html_e( 'More connectors', 'nestform' ); ?></h2>
						<p class="nestform-admin__panel-desc">
							<?php esc_html_e( 'Webhooks are built into every form. Native connectors (Telegram, Slack, Sheets) are planned for Pro.', 'nestform' ); ?>
						</p>
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
			<?php endif; ?>
		</div>
		<?php
	}
}
