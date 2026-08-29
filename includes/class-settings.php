<?php
/**
 * Plugin-wide settings (Forms → Settings).
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Settings {

	const OPTION    = 'nestform_settings';
	const PAGE_SLUG = 'nestform-settings';

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 40 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_import_theme_captcha' ), 5 );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'captcha_enabled'      => '0',
			'captcha_provider'     => 'recaptcha_v2',
			'captcha_site_key'     => '',
			'captcha_secret_key'   => '',
			'captcha_v3_score'     => '0.5',
			'email_from_name'      => '',
			'email_from_email'     => '',
			'date_format'          => 'site',
			'auto_mark_read'       => '1',
			'delete_data_on_uninstall' => '0',
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		$out    = array_merge( self::defaults(), $stored );

		if ( defined( 'NESTFORM_RECAPTCHA_SITE_KEY' ) && NESTFORM_RECAPTCHA_SITE_KEY !== '' ) {
			$out['captcha_site_key'] = (string) NESTFORM_RECAPTCHA_SITE_KEY;
		}
		if ( defined( 'NESTFORM_RECAPTCHA_SECRET_KEY' ) && NESTFORM_RECAPTCHA_SECRET_KEY !== '' ) {
			$out['captcha_secret_key'] = (string) NESTFORM_RECAPTCHA_SECRET_KEY;
		}

		/**
		 * Filter plugin-wide Nestform settings.
		 *
		 * @param array<string, mixed> $out Settings.
		 */
		return (array) apply_filters( 'nestform_settings', $out );
	}

	/**
	 * Whether plugin-level captcha is ready (toggle + keys).
	 *
	 * @return bool
	 */
	public static function captcha_ready() {
		$s = self::get();
		if ( '1' !== (string) ( $s['captcha_enabled'] ?? '0' ) ) {
			return false;
		}
		return (string) ( $s['captcha_site_key'] ?? '' ) !== '' && (string) ( $s['captcha_secret_key'] ?? '' ) !== '';
	}

	/**
	 * Default From name for notifications.
	 *
	 * @return string
	 */
	public static function mail_from_name() {
		$s = self::get();
		$n = trim( (string) ( $s['email_from_name'] ?? '' ) );
		return '' !== $n ? $n : (string) get_bloginfo( 'name' );
	}

	/**
	 * Default From email for notifications.
	 *
	 * @return string
	 */
	public static function mail_from_email() {
		$s = self::get();
		$e = trim( (string) ( $s['email_from_email'] ?? '' ) );
		if ( '' !== $e && is_email( $e ) ) {
			return $e;
		}
		$admin = get_option( 'admin_email' );
		return ( is_string( $admin ) && is_email( $admin ) ) ? $admin : '';
	}

	/**
	 * PHP date format for entry timestamps in admin.
	 *
	 * @return string
	 */
	public static function entry_date_format() {
		$s = self::get();
		$key = (string) ( $s['date_format'] ?? 'site' );
		$map = array(
			'site'     => trim( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'ymd_hi'   => 'Y-m-d H:i',
			'mdy_gia'  => 'M j, Y g:i a',
			'dmy_hi'   => 'd/m/Y H:i',
		);
		$format = isset( $map[ $key ] ) ? $map[ $key ] : $map['site'];
		return '' !== $format ? $format : 'Y-m-d H:i';
	}

	/**
	 * @param int|WP_Post $post Entry post.
	 * @return string
	 */
	public static function format_entry_datetime( $post ) {
		$ts = get_post_time( 'U', true, $post );
		if ( ! $ts ) {
			return '';
		}
		return wp_date( self::entry_date_format(), (int) $ts );
	}

	/**
	 * Whether opening an entry marks it read.
	 *
	 * @return bool
	 */
	public static function auto_mark_read_enabled() {
		$s = self::get();
		return '1' === (string) ( $s['auto_mark_read'] ?? '1' );
	}

	/**
	 * Whether to wipe Nestform data on uninstall.
	 *
	 * @return bool
	 */
	public static function delete_data_on_uninstall() {
		$s = self::get();
		return '1' === (string) ( $s['delete_data_on_uninstall'] ?? '0' );
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

	public static function register() {
		register_setting(
			'nestform_settings',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$stored  = get_option( self::OPTION, array() );
		$stored  = is_array( $stored ) ? $stored : array();
		$out     = array_merge( self::defaults(), $stored );
		$section = isset( $input['_section'] ) ? sanitize_key( (string) $input['_section'] ) : 'general';

		if ( 'integrations' === $section ) {
			$provider = isset( $input['captcha_provider'] ) ? sanitize_key( (string) $input['captcha_provider'] ) : 'recaptcha_v2';
			if ( ! in_array( $provider, array( 'recaptcha_v2', 'recaptcha_v3' ), true ) ) {
				$provider = 'recaptcha_v2';
			}
			$score = isset( $input['captcha_v3_score'] ) ? (float) $input['captcha_v3_score'] : 0.5;
			$score = min( 1.0, max( 0.0, $score ) );

			$out['captcha_enabled']    = ! empty( $input['captcha_enabled'] ) ? '1' : '0';
			$out['captcha_provider']   = $provider;
			$out['captcha_site_key']   = isset( $input['captcha_site_key'] ) ? sanitize_text_field( (string) $input['captcha_site_key'] ) : '';
			$out['captcha_secret_key'] = isset( $input['captcha_secret_key'] ) ? sanitize_text_field( (string) $input['captcha_secret_key'] ) : '';
			$out['captcha_v3_score']   = (string) $score;
			return $out;
		}

		$from_email = isset( $input['email_from_email'] ) ? sanitize_email( (string) $input['email_from_email'] ) : '';
		$date_key   = isset( $input['date_format'] ) ? sanitize_key( (string) $input['date_format'] ) : 'site';
		if ( ! in_array( $date_key, array( 'site', 'ymd_hi', 'mdy_gia', 'dmy_hi' ), true ) ) {
			$date_key = 'site';
		}

		$out['email_from_name']          = isset( $input['email_from_name'] ) ? sanitize_text_field( (string) $input['email_from_name'] ) : '';
		$out['email_from_email']         = $from_email;
		$out['date_format']              = $date_key;
		$out['auto_mark_read']           = ! empty( $input['auto_mark_read'] ) ? '1' : '0';
		$out['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] ) ? '1' : '0';

		return $out;
	}

	/**
	 * Copy keys from theme Auth captcha once, if Nestform has none yet.
	 */
	public static function maybe_import_theme_captcha() {
		if ( false !== get_option( self::OPTION, false ) ) {
			return;
		}
		if ( ! defined( 'VITE_THEME_AUTH_CAPTCHA_OPTION' ) ) {
			return;
		}

		$theme = get_option( VITE_THEME_AUTH_CAPTCHA_OPTION, array() );
		if ( ! is_array( $theme ) ) {
			return;
		}

		$site   = (string) ( $theme['site_key'] ?? '' );
		$secret = (string) ( $theme['secret_key'] ?? '' );
		if ( $site === '' && $secret === '' ) {
			return;
		}

		$imported = array_merge(
			self::defaults(),
			array(
				'captcha_enabled'    => ! empty( $theme['enabled'] ) ? '1' : '0',
				'captcha_provider'   => in_array( (string) ( $theme['provider'] ?? '' ), array( 'recaptcha_v2', 'recaptcha_v3' ), true )
					? (string) $theme['provider']
					: 'recaptcha_v2',
				'captcha_site_key'   => sanitize_text_field( $site ),
				'captcha_secret_key' => sanitize_text_field( $secret ),
				'captcha_v3_score'   => isset( $theme['v3_score'] ) ? (string) $theme['v3_score'] : '0.5',
			)
		);

		add_option( self::OPTION, $imported, '', false );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE,
			__( 'Settings', 'nestform' ),
			__( 'Settings', 'nestform' ),
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
			$classes .= ' nestform-admin-screen nestform-settings-screen';
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

		$s   = self::get();
		$opt = self::OPTION;
		$integrations_url = class_exists( 'Nestform_Integrations' ) ? Nestform_Integrations::url() : '';
		?>
		<div class="wrap nestform-admin nestform-settings">
			<?php
			nestform_render_page_head(
				array(
					'title'       => __( 'Settings', 'nestform' ),
					'description' => __( 'Plugin-wide defaults for email, entries, and data.', 'nestform' ),
					'icon'        => 'settings',
				)
			);
			settings_errors();
			?>

			<form method="post" action="options.php" class="nestform-settings__form">
				<?php settings_fields( 'nestform_settings' ); ?>
				<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[_section]" value="general" />

				<div class="nestform-admin__surface nestform-settings__card">
					<div class="nestform-admin__panel-head">
						<div>
							<h2 class="nestform-admin__panel-title"><?php esc_html_e( 'Email defaults', 'nestform' ); ?></h2>
							<p class="nestform-admin__panel-desc">
								<?php esc_html_e( 'Used by wp_mail when a form does not override From name. For delivery reliability, use WP Mail SMTP or FluentSMTP.', 'nestform' ); ?>
							</p>
						</div>
					</div>
					<table class="form-table nestform-settings__table" role="presentation">
						<tr>
							<th scope="row"><label for="nestform_email_from_name"><?php esc_html_e( 'From name', 'nestform' ); ?></label></th>
							<td>
								<input
									type="text"
									class="regular-text nestform-admin__input"
									id="nestform_email_from_name"
									name="<?php echo esc_attr( $opt ); ?>[email_from_name]"
									value="<?php echo esc_attr( (string) $s['email_from_name'] ); ?>"
									placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Fallback when a form leaves From name empty.', 'nestform' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="nestform_email_from_email"><?php esc_html_e( 'From email', 'nestform' ); ?></label></th>
							<td>
								<input
									type="email"
									class="regular-text nestform-admin__input"
									id="nestform_email_from_email"
									name="<?php echo esc_attr( $opt ); ?>[email_from_email]"
									value="<?php echo esc_attr( (string) $s['email_from_email'] ); ?>"
									placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Defaults to the WordPress admin email if empty.', 'nestform' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div class="nestform-admin__surface nestform-settings__card">
					<div class="nestform-admin__panel-head">
						<div>
							<h2 class="nestform-admin__panel-title"><?php esc_html_e( 'Entries', 'nestform' ); ?></h2>
							<p class="nestform-admin__panel-desc"><?php esc_html_e( 'How submissions look and behave in the admin.', 'nestform' ); ?></p>
						</div>
					</div>
					<table class="form-table nestform-settings__table" role="presentation">
						<tr>
							<th scope="row"><label for="nestform_date_format"><?php esc_html_e( 'Date format', 'nestform' ); ?></label></th>
							<td>
								<select id="nestform_date_format" class="nestform-admin__input" name="<?php echo esc_attr( $opt ); ?>[date_format]">
									<option value="site" <?php selected( (string) $s['date_format'], 'site' ); ?>><?php esc_html_e( 'Site default', 'nestform' ); ?></option>
									<option value="ymd_hi" <?php selected( (string) $s['date_format'], 'ymd_hi' ); ?>><?php echo esc_html( wp_date( 'Y-m-d H:i' ) ); ?></option>
									<option value="mdy_gia" <?php selected( (string) $s['date_format'], 'mdy_gia' ); ?>><?php echo esc_html( wp_date( 'M j, Y g:i a' ) ); ?></option>
									<option value="dmy_hi" <?php selected( (string) $s['date_format'], 'dmy_hi' ); ?>><?php echo esc_html( wp_date( 'd/m/Y H:i' ) ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Mark as read', 'nestform' ); ?></th>
							<td>
								<fieldset>
									<label class="nestform-admin__check" for="nestform_auto_mark_read">
										<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[auto_mark_read]" value="0" />
										<input
											type="checkbox"
											id="nestform_auto_mark_read"
											name="<?php echo esc_attr( $opt ); ?>[auto_mark_read]"
											value="1"
											<?php checked( (string) $s['auto_mark_read'], '1' ); ?>
										/>
										<span><?php esc_html_e( 'Automatically mark entries as read when opened', 'nestform' ); ?></span>
									</label>
								</fieldset>
							</td>
						</tr>
					</table>
				</div>

				<div class="nestform-admin__surface nestform-settings__card">
					<div class="nestform-admin__panel-head">
						<div>
							<h2 class="nestform-admin__panel-title"><?php esc_html_e( 'Data', 'nestform' ); ?></h2>
							<p class="nestform-admin__panel-desc"><?php esc_html_e( 'Control what happens when Nestform is removed.', 'nestform' ); ?></p>
						</div>
					</div>
					<table class="form-table nestform-settings__table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Uninstall cleanup', 'nestform' ); ?></th>
							<td>
								<fieldset>
									<label class="nestform-admin__check" for="nestform_delete_data_on_uninstall">
										<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[delete_data_on_uninstall]" value="0" />
										<input
											type="checkbox"
											id="nestform_delete_data_on_uninstall"
											name="<?php echo esc_attr( $opt ); ?>[delete_data_on_uninstall]"
											value="1"
											<?php checked( (string) $s['delete_data_on_uninstall'], '1' ); ?>
										/>
										<span><?php esc_html_e( 'Delete forms, entries, and settings when uninstalling the plugin', 'nestform' ); ?></span>
									</label>
									<p class="description"><?php esc_html_e( 'Off by default. Leave unchecked to keep data if you reinstall later.', 'nestform' ); ?></p>
								</fieldset>
							</td>
						</tr>
					</table>
					<?php if ( $integrations_url ) : ?>
						<p class="nestform-settings__hint">
							<?php
							echo wp_kses(
								sprintf(
									/* translators: %s: integrations URL */
									__( 'Looking for reCAPTCHA? Manage it under <a href="%s">Integrations</a>.', 'nestform' ),
									esc_url( $integrations_url )
								),
								array(
									'a' => array( 'href' => array() ),
								)
							);
							?>
						</p>
					<?php endif; ?>
					<button type="submit" class="nestform-btn nestform-btn--primary" name="submit" value="1">
						<?php nestform_admin_icon( 'save' ); ?>
						<?php esc_html_e( 'Save settings', 'nestform' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
	}
}
