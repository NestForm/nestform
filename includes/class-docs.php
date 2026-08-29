<?php
/**
 * In-app documentation (Forms → Docs).
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Docs {

	const PAGE_SLUG = 'nestform-docs';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 52 );
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

	/**
	 * @param string $file SVG filename in assets/docs/.
	 * @return string
	 */
	public static function illustration_url( $file ) {
		$file = sanitize_file_name( (string) $file );
		return NESTFORM_URL . 'assets/docs/' . $file;
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE,
			__( 'Docs', 'nestform' ),
			__( 'Docs', 'nestform' ),
			'edit_posts',
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
			$classes .= ' nestform-admin-screen nestform-docs-screen';
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

	/**
	 * Table of contents.
	 *
	 * @return array<int, array{id:string,label:string}>
	 */
	private static function toc() {
		return array(
			array(
				'id'    => 'getting-started',
				'label' => __( 'Getting started', 'nestform' ),
			),
			array(
				'id'    => 'forms',
				'label' => __( 'Forms hub', 'nestform' ),
			),
			array(
				'id'    => 'builder',
				'label' => __( 'Form builder', 'nestform' ),
			),
			array(
				'id'    => 'entries',
				'label' => __( 'Entries', 'nestform' ),
			),
			array(
				'id'    => 'dashboard',
				'label' => __( 'Dashboard', 'nestform' ),
			),
			array(
				'id'    => 'settings',
				'label' => __( 'Settings & spam', 'nestform' ),
			),
			array(
				'id'    => 'embed',
				'label' => __( 'Embed on site', 'nestform' ),
			),
			array(
				'id'    => 'plans',
				'label' => __( 'Plans & pricing', 'nestform' ),
			),
			array(
				'id'    => 'account',
				'label' => __( 'License & account', 'nestform' ),
			),
			array(
				'id'    => 'developers',
				'label' => __( 'Developers', 'nestform' ),
			),
		);
	}

	/**
	 * @param string $id       Section id.
	 * @param string $svg      Illustration filename.
	 * @param string $caption  Figure caption.
	 */
	private static function figure( $id, $svg, $caption ) {
		if ( '' === $svg ) {
			return;
		}
		?>
		<figure class="nestform-docs__figure" id="<?php echo esc_attr( $id ); ?>-figure">
			<img
				class="nestform-docs__figure-img"
				src="<?php echo esc_url( self::illustration_url( $svg ) ); ?>"
				alt=""
				width="720"
				height="405"
				loading="lazy"
				decoding="async"
			/>
			<figcaption class="nestform-docs__figure-caption"><?php echo esc_html( $caption ); ?></figcaption>
		</figure>
		<?php
	}

	/**
	 * Plan comparison cards (uses Nestform_Upgrade::plan_catalog()).
	 */
	private static function render_plan_cards() {
		if ( ! class_exists( 'Nestform_Upgrade' ) ) {
			return;
		}

		$plans       = Nestform_Upgrade::plan_catalog();
		$upgrade_url = Nestform_Upgrade::url();
		?>
		<div class="nestform-docs__plans">
			<?php foreach ( $plans as $plan_key => $plan ) : ?>
				<?php
				$card_class = 'nestform-docs__plan';
				if ( ! empty( $plan['popular'] ) ) {
					$card_class .= ' nestform-docs__plan--popular';
				}
				?>
				<div class="<?php echo esc_attr( $card_class ); ?>">
					<?php if ( ! empty( $plan['popular'] ) ) : ?>
						<span class="nestform-docs__plan-badge"><?php esc_html_e( 'Most popular', 'nestform' ); ?></span>
					<?php endif; ?>
					<h3 class="nestform-docs__plan-name"><?php echo esc_html( (string) $plan['name'] ); ?></h3>
					<p class="nestform-docs__plan-tag"><?php echo esc_html( (string) $plan['tagline'] ); ?></p>
					<p class="nestform-docs__plan-price"><?php echo esc_html( Nestform_Upgrade::plan_price_summary( $plan_key ) ); ?></p>
					<?php if ( ! empty( $plan['foot'] ) ) : ?>
						<p class="nestform-docs__plan-foot"><?php echo esc_html( (string) $plan['foot'] ); ?></p>
					<?php endif; ?>
					<ul class="nestform-docs__plan-features">
						<?php foreach ( (array) $plan['features'] as $feature ) : ?>
							<li><?php echo esc_html( (string) $feature ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>
		<?php if ( $upgrade_url ) : ?>
			<p class="nestform-docs__note">
				<?php
				printf(
					/* translators: %s: upgrade page URL */
					wp_kses_post( __( 'Full comparison with monthly/yearly toggle: <a href="%s">Upgrade</a>. Checkout uses Freemius once pricing is configured in your product dashboard.', 'nestform' ) ),
					esc_url( $upgrade_url )
				);
				?>
			</p>
		<?php endif; ?>
		<?php
	}

	public static function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'nestform' ) );
		}

		$forms_url    = class_exists( 'Nestform_Post_Type' ) ? Nestform_Post_Type::hub_url() : '';
		$entries_url  = class_exists( 'Nestform_Submissions' ) ? Nestform_Submissions::hub_url() : '';
		$settings_url = class_exists( 'Nestform_Settings' ) ? Nestform_Settings::url() : '';
		$integr_url   = class_exists( 'Nestform_Integrations' ) ? Nestform_Integrations::url() : '';
		$upgrade_url  = class_exists( 'Nestform_Upgrade' ) ? Nestform_Upgrade::url() : '';
		$dev_url      = class_exists( 'Nestform_Developers' ) ? Nestform_Developers::url() : '';
		$account_url  = class_exists( 'Nestform_Freemius' ) ? Nestform_Freemius::account_url() : '';
		$dash_url     = class_exists( 'Nestform_Dashboard' ) ? Nestform_Dashboard::url() : '';
		$form_limit   = class_exists( 'Nestform_Features' ) ? Nestform_Features::form_limit() : 5;
		?>
		<div class="wrap nestform-admin nestform-docs">
			<?php
			nestform_render_page_head(
				array(
					'title'       => __( 'Docs', 'nestform' ),
					'description' => __( 'How Nestform works — builder, entries, plans, and licensing.', 'nestform' ),
					'icon'        => 'docs',
				)
			);
			?>

			<div class="nestform-docs__layout">
				<nav class="nestform-docs__toc" aria-label="<?php esc_attr_e( 'Documentation sections', 'nestform' ); ?>">
					<p class="nestform-docs__toc-title"><?php esc_html_e( 'On this page', 'nestform' ); ?></p>
					<ul class="nestform-docs__toc-list">
						<?php foreach ( self::toc() as $item ) : ?>
							<li>
								<a class="nestform-docs__toc-link" href="#<?php echo esc_attr( $item['id'] ); ?>">
									<?php echo esc_html( $item['label'] ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>

				<div class="nestform-docs__content">
					<section class="nestform-admin__surface nestform-docs__section" id="getting-started">
						<h2 class="nestform-docs__heading"><?php esc_html_e( 'Getting started', 'nestform' ); ?></h2>
						<ol class="nestform-docs__steps">
							<li><?php esc_html_e( 'Install and activate Nestform from Plugins.', 'nestform' ); ?></li>
							<li>
								<?php
								if ( $forms_url ) {
									printf(
										/* translators: %s: link to Forms hub */
										wp_kses_post( __( 'Open <a href="%s">Forms</a> and click <strong>New form</strong> or pick a template.', 'nestform' ) ),
										esc_url( $forms_url )
									);
								} else {
									esc_html_e( 'Open Forms and click New form or pick a template.', 'nestform' );
								}
								?>
							</li>
							<li><?php esc_html_e( 'Add fields in the builder, save, then copy the shortcode or block.', 'nestform' ); ?></li>
							<li><?php esc_html_e( 'Publish the page and watch submissions in Entries.', 'nestform' ); ?></li>
						</ol>
						<p class="nestform-docs__note">
							<?php
							printf(
								/* translators: 1: free form limit, 2: plans anchor */
								wp_kses_post( __( 'Free plan: up to %1$d forms. See <a href="#plans">Plans &amp; pricing</a> for Pro and Agency.', 'nestform' ) ),
								(int) $form_limit
							);
							?>
						</p>
					</section>

					<section class="nestform-admin__surface nestform-docs__section" id="forms">
						<h2 class="nestform-docs__heading"><?php esc_html_e( 'Forms hub', 'nestform' ); ?></h2>
						<p><?php esc_html_e( 'All Forms lists every form with search, duplicate, and trash actions. Templates help you start from contact, survey, or quiz layouts.', 'nestform' ); ?></p>
						<ul class="nestform-docs__list">
							<li><?php esc_html_e( 'Duplicate — copy field layout and settings to a new form.', 'nestform' ); ?></li>
							<li><?php esc_html_e( 'Edit — opens the builder for that form.', 'nestform' ); ?></li>
							<li><?php esc_html_e( 'Usage bar in the sidebar shows how many forms you have vs. the free limit.', 'nestform' ); ?></li>
						</ul>
						<?php
						self::figure(
							'forms',
							'forms-hub.svg',
							__( 'Illustration: Forms hub with templates and new form action.', 'nestform' )
						);
						?>
					</section>

					<section class="nestform-admin__surface nestform-docs__section" id="builder">
						<h2 class="nestform-docs__heading"><?php esc_html_e( 'Form builder', 'nestform' ); ?></h2>
						<p><?php esc_html_e( 'The editor is split into tabs: Fields, Steps, Logic, Email, Quiz, and more depending on your plan.', 'nestform' ); ?></p>
						<ul class="nestform-docs__list">
							<li><strong><?php esc_html_e( 'Fields', 'nestform' ); ?></strong> — <?php esc_html_e( 'drag to reorder; text, email, select, file upload, etc.', 'nestform' ); ?></li>
							<li><strong><?php esc_html_e( 'Logic', 'nestform' ); ?></strong> — <?php esc_html_e( 'show/hide fields based on answers (free).', 'nestform' ); ?></li>
							<li><strong><?php esc_html_e( 'Preview', 'nestform' ); ?></strong> — <?php esc_html_e( 'test the form in admin; preview submits are not saved to Entries.', 'nestform' ); ?></li>
							<li><strong><?php esc_html_e( 'Embed', 'nestform' ); ?></strong> — <?php esc_html_e( 'shortcode and Gutenberg block in the sidebar meta box.', 'nestform' ); ?></li>
						</ul>
						<p class="nestform-docs__pro">
							<?php echo nestform_admin_icon_html( 'crown' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php esc_html_e( 'Pro: multi-step flows, branching, calculated fields, repeaters, HTML email designer, webhooks, automations, quizzes with scoring.', 'nestform' ); ?>
						</p>
						<?php
						self::figure(
							'builder',
							'form-builder.svg',
							__( 'Illustration: builder tabs, field cards, and preview.', 'nestform' )
						);
						?>
					</section>

					<section class="nestform-admin__surface nestform-docs__section" id="entries">
						<h2 class="nestform-docs__heading"><?php esc_html_e( 'Entries', 'nestform' ); ?></h2>
						<p><?php esc_html_e( 'Every front-end submission lands in the inbox. Filter by New, Read, or Starred; open a row to see field values and notes.', 'nestform' ); ?></p>
						<ul class="nestform-docs__list">
							<li><?php esc_html_e( 'Export CSV — download filtered entries for spreadsheets.', 'nestform' ); ?></li>
							<li><?php esc_html_e( 'Status badges — mark reviewed entries so your team knows what is new.', 'nestform' ); ?></li>
							<li><?php esc_html_e( 'Email notifications fire on each submit (configure per form and globally).', 'nestform' ); ?></li>
						</ul>
						<?php
						self::figure(
							'entries',
							'entries.svg',
							__( 'Illustration: Entries inbox with status filters.', 'nestform' )
						);
						?>
					</section>

					<section class="nestform-admin__surface nestform-docs__section" id="dashboard">
						<h2 class="nestform-docs__heading"><?php esc_html_e( 'Dashboard', 'nestform' ); ?></h2>
						<p><?php esc_html_e( 'Track views, submissions, and conversion rate across forms. Basic analytics are included in Free.', 'nestform' ); ?></p>
						<p class="nestform-docs__pro">
							<?php echo nestform_admin_icon_html( 'crown' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php esc_html_e( 'Pro: advanced analytics, lead insights, and survey chart breakdowns.', 'nestform' ); ?>
						</p>
						<?php
						self::figure(
							'dashboard',
							'dashboard.svg',
							__( 'Illustration: Dashboard metrics and trend chart.', 'nestform' )
						);
						?>
					</section>

					<section class="nestform-admin__surface nestform-docs__section" id="settings">
						<h2 class="nestform-docs__heading"><?php esc_html_e( 'Settings & spam protection', 'nestform' ); ?></h2>
						<p>
							<?php
							if ( $settings_url ) {
								printf(
									/* translators: 1: settings link, 2: integrations link */
									wp_kses_post( __( '<a href="%1$s">Settings</a> controls default email sender, date format, and uninstall data wipe. <a href="%2$s">Integrations</a> connects Google reCAPTCHA (v2 or v3).', 'nestform' ) ),
									esc_url( $settings_url ),
									esc_url( $integr_url )
								);
							} else {
								esc_html_e( 'Settings controls default email sender and Integrations connects reCAPTCHA.', 'nestform' );
							}
							?>
						</p>
						<ul class="nestform-docs__list">
							<li><?php esc_html_e( 'Honeypot and rate limiting are built in on every form.', 'nestform' ); ?></li>
							<li><?php esc_html_e( 'Per-form captcha toggle overrides the global integration when needed.', 'nestform' ); ?></li>
							<li><?php esc_html_e( 'Optional: wipe all Nestform data on plugin uninstall (off by default).', 'nestform' ); ?></li>
						</ul>
					</section>

					<section class="nestform-admin__surface nestform-docs__section" id="embed">
						<h2 class="nestform-docs__heading"><?php esc_html_e( 'Embed on site', 'nestform' ); ?></h2>
						<p><?php esc_html_e( 'After saving a form, use either embedding method:', 'nestform' ); ?></p>
						<pre class="nestform-docs__code" tabindex="0"><code>[nestform id="123"]</code></pre>
						<p><?php esc_html_e( 'Replace 123 with your form ID (shown in the embed meta box). The Gutenberg block “Nestform” does the same with a visual picker.', 'nestform' ); ?></p>
						<ul class="nestform-docs__list">
							<li><?php esc_html_e( 'Forms inherit theme styles; front.css handles layout and validation states.', 'nestform' ); ?></li>
							<li><?php esc_html_e( 'AJAX submit — no full page reload unless you set a redirect URL.', 'nestform' ); ?></li>
						</ul>
						<?php
						self::figure(
							'embed',
							'embed.svg',
							__( 'Illustration: Shortcode, block, and front-end form.', 'nestform' )
						);
						?>
					</section>

					<section class="nestform-admin__surface nestform-docs__section" id="plans">
						<h2 class="nestform-docs__heading"><?php esc_html_e( 'Plans & pricing', 'nestform' ); ?></h2>
						<p>
							<?php
							esc_html_e( 'Nestform ships as a free plugin. Pro is a separate add-on (nestform-pro) unlocked with a Freemius license. Agency covers multiple client sites with everything in Pro.', 'nestform' );
							?>
						</p>
						<?php self::render_plan_cards(); ?>
						<h3 class="nestform-docs__subheading"><?php esc_html_e( 'How to upgrade', 'nestform' ); ?></h3>
						<ol class="nestform-docs__steps">
							<li>
								<?php
								if ( $upgrade_url ) {
									printf(
										/* translators: %s: upgrade page URL */
										wp_kses_post( __( 'Compare billing on <a href="%s">Upgrade</a> and complete checkout (sandbox cards work in dev mode).', 'nestform' ) ),
										esc_url( $upgrade_url )
									);
								} else {
									esc_html_e( 'Open Upgrade in the sidebar and complete checkout.', 'nestform' );
								}
								?>
							</li>
							<li><?php esc_html_e( 'Install and activate the nestform-pro plugin.', 'nestform' ); ?></li>
							<li><?php esc_html_e( 'Paste your license key under Account if prompted.', 'nestform' ); ?></li>
						</ol>
					</section>

					<section class="nestform-admin__surface nestform-docs__section" id="account">
						<h2 class="nestform-docs__heading"><?php esc_html_e( 'License & account', 'nestform' ); ?></h2>
						<p>
							<?php
							if ( $account_url && current_user_can( 'manage_options' ) ) {
								printf(
									/* translators: %s: account page URL */
									wp_kses_post( __( 'Administrators manage billing on the <a href="%s">Account</a> screen: activate a license, sync, or disconnect.', 'nestform' ) ),
									esc_url( $account_url )
								);
							} else {
								esc_html_e( 'Administrators manage billing on the Account screen in the sidebar.', 'nestform' );
							}
							?>
						</p>
						<ul class="nestform-docs__list">
							<li><?php esc_html_e( 'Activate license — paste the key from Freemius Dashboard, your purchase email, or a sandbox key in dev mode.', 'nestform' ); ?></li>
							<li><?php esc_html_e( 'Sync — refresh license state after renewal or plan change.', 'nestform' ); ?></li>
							<li><?php esc_html_e( 'Both Nestform (free) and Nestform Pro must stay active; Pro extends Free.', 'nestform' ); ?></li>
							<li><?php esc_html_e( 'Until Pro and Agency plans exist in your Freemius product, the Account screen may not list plan names — use Docs or Upgrade for the feature comparison.', 'nestform' ); ?></li>
						</ul>
					</section>

					<section class="nestform-admin__surface nestform-docs__section" id="developers">
						<h2 class="nestform-docs__heading"><?php esc_html_e( 'Developers', 'nestform' ); ?></h2>
						<p>
							<?php
							if ( $dev_url ) {
								printf(
									/* translators: %s: developers page URL */
									wp_kses_post( __( 'Hooks, filters, and front-end events are listed on the <a href="%s">Developers</a> page. Use <code>nestform_submitted</code> for server-side automation and <code>nestform:success</code> in JavaScript.', 'nestform' ) ),
									esc_url( $dev_url )
								);
							} else {
								esc_html_e( 'See the Developers page for actions, filters, and JS events.', 'nestform' );
							}
							?>
						</p>
					</section>
				</div>
			</div>
		</div>
		<?php
	}
}
