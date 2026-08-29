<?php
/**
 * Plugin Name: Nestform
 * Plugin URI: https://nestform.app
 * Description: Build forms, quizzes and surveys for WordPress that convert — entries inbox, email, spam protection, and analytics.
 * Version: 2.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: A.CH
 * Author URI: https://nestform.app
 * Text Domain: nestform
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'nes_fs' ) ) {
	nes_fs()->set_basename( false, __FILE__ );
} else {
	if ( defined( 'NESTFORM_VERSION' ) ) {
		return;
	}

	if ( ! function_exists( 'nes_fs' ) ) {
		// Create a helper function for easy SDK access.
		function nes_fs() {
			global $nes_fs;

			if ( ! isset( $nes_fs ) ) {
				// Include Freemius SDK.
				require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';

				$nes_fs = fs_dynamic_init(
					array(
						'id'                  => '37981',
						'slug'                => 'nestform',
						'premium_slug'        => 'nestform-pro',
						'type'                => 'plugin',
						'public_key'          => 'pk_7337f922428c7467840c937e4cf5d',
						'is_premium'          => false,
						'premium_suffix'      => '(Pro)',
						'has_premium_version' => true,
						'has_addons'          => false,
						'has_paid_plans'      => true,
						'is_org_compliant'    => true,
						'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
						'menu'                => array(
							'slug'       => 'nestform-forms',
							'parent'     => array(
								'slug' => 'edit.php?post_type=nestform',
							),
							'first-path' => 'edit.php?post_type=nestform&page=nestform-forms',
							// Keep account registered under CPT (nestform-forms-account) for app shell.
							'account'    => true,
							'contact'    => false,
							'support'    => false,
							'pricing'    => true,
						),
					)
				);
			}

			return $nes_fs;
		}

		// Init Freemius.
		nes_fs();
		nes_fs()->add_filter( 'deactivate_on_activation', '__return_false' );
		nes_fs()->add_action( 'after_uninstall', 'nestform_fs_uninstall_cleanup' );
		nes_fs()->add_filter(
			'is_submenu_visible',
			static function ( $is_visible, $menu_id ) {
				// Hide contact/support from WP menu. Account stays registered (CSS-hidden)
				// so nestform-forms-account works inside Nestform app shell.
				if ( in_array( (string) $menu_id, array( 'contact', 'affiliation', 'support' ), true ) ) {
					return false;
				}
				return $is_visible;
			},
			10,
			2
		);
		// Signal that SDK was initiated.
		do_action( 'nes_fs_loaded' );
	}

	define( 'NESTFORM_VERSION', '2.2.0' );
	define( 'NESTFORM_PATH', trailingslashit( dirname( __FILE__ ) ) );
	define( 'NESTFORM_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );

require_once NESTFORM_PATH . 'freemius.config.php';
require_once NESTFORM_PATH . 'includes/class-freemius-bridge.php';
require_once NESTFORM_PATH . 'includes/uninstall-cleanup.php';

/**
 * Plugin logo URL.
 *
 * @return string
 */
function nestform_logo_url() {
	return NESTFORM_URL . 'assets/logo.png';
}

/**
 * Style deps for Nestform admin.css.
 *
 * @return array<int, string>
 */
function nestform_admin_style_deps() {
	return array( 'dashicons' );
}

/**
 * Admin counters for chrome (forms / entries).
 *
 * @return array{forms:int,new:int,entries:int,today:int}
 */
function nestform_admin_stats() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$forms_n = 0;
	if ( class_exists( 'Nestform_Post_Type' ) ) {
		$counts = wp_count_posts( Nestform_Post_Type::POST_TYPE );
		if ( $counts ) {
			$forms_n  = (int) $counts->publish;
			$forms_n += isset( $counts->draft ) ? (int) $counts->draft : 0;
			$forms_n += isset( $counts->private ) ? (int) $counts->private : 0;
			$forms_n += isset( $counts->pending ) ? (int) $counts->pending : 0;
		}
	}

	$new_n     = 0;
	$entries_n = 0;
	$today_n   = 0;
	if ( class_exists( 'Nestform_Submissions' ) ) {
		$new_n     = (int) Nestform_Submissions::count_entries(
			array(
				'status' => Nestform_Submissions::STATUS_NEW,
			)
		);
		$entries_n = (int) Nestform_Submissions::count_entries();
		$today_n   = (int) Nestform_Submissions::count_entries(
			array(
				'after'  => wp_date( 'Y-m-d' ) . ' 00:00:00',
				'before' => wp_date( 'Y-m-d' ) . ' 23:59:59',
			)
		);
	}

	$cache = array(
		'forms'   => $forms_n,
		'new'     => $new_n,
		'entries' => $entries_n,
		'today'   => $today_n,
	);
	return $cache;
}

/**
 * Inline nav icon HTML.
 *
 * @param string $name    Icon key.
 * @param string $variant Unused (kept for call-site compatibility).
 * @return string
 */
function nestform_admin_icon_html( $name, $variant = '' ) {
	unset( $variant );
	$icons = array(
		'forms'     => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 13h4"/></svg>',
		'analytics' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 3v18h18"/><path d="M7 16l4-6 4 4 4-8"/></svg>',
		'entries'   => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
		'settings'  => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
		'plus'      => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
		'search'    => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
		'copy'      => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>',
		'save'      => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg>',
		'preview'   => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
		'undo'      => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>',
		'external'  => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>',
		'back'      => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 12H5"/><polyline points="12 19 5 12 12 5"/></svg>',
		'forward'   => '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M3 7.5H12.3333" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round" /> <path d="M8 3L13 7.5L8 12" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round" /></svg>',
		'download'  => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
		'crown'     => '<svg class="nestform-icon-crown" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 16L3 7l5.5 4L12 4l3.5 7L21 7l-2 9H5zm0 2h14v2H5v-2z"/></svg>',
		'sparkle'   => '<svg class="nestform-icon-crown" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 16L3 7l5.5 4L12 4l3.5 7L21 7l-2 9H5zm0 2h14v2H5v-2z"/></svg>',
		'check'     => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>',
		'developers'=> '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
		'docs'      => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/><line x1="8" y1="7" x2="16" y2="7"/><line x1="8" y1="11" x2="14" y2="11"/></svg>',
		'integrations' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
		'trash'     => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>',
	);

	return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
}

/**
 * Echo inline nav icon.
 *
 * @param string $name Icon key.
 */
function nestform_admin_icon( $name ) {
	echo nestform_admin_icon_html( $name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG
}

/**
 * Open Nestform app shell (sidebar + main).
 *
 * @param string $current Active nav: dashboard|forms|entries|settings|integrations|developers|upgrade|license|editor.
 */
function nestform_render_app_open( $current ) {
	$stats      = nestform_admin_stats();
	$forms_n    = (int) $stats['forms'];
	$new_n      = (int) $stats['new'];
	$entries_n  = isset( $stats['entries'] ) ? (int) $stats['entries'] : 0;
	$today_n    = isset( $stats['today'] ) ? (int) $stats['today'] : 0;
	$entries_url = class_exists( 'Nestform_Submissions' ) ? Nestform_Submissions::hub_url() : '';
	$new_url     = class_exists( 'Nestform_Submissions' )
		? Nestform_Submissions::hub_url( array( 'nestform_status' => Nestform_Submissions::STATUS_NEW ) )
		: $entries_url;
	$analytics_url = class_exists( 'Nestform_Dashboard' ) ? Nestform_Dashboard::url() : '';
	$items   = array(
		array(
			'id'    => 'dashboard',
			'label' => __( 'Dashboard', 'nestform' ),
			'url'   => class_exists( 'Nestform_Dashboard' ) ? Nestform_Dashboard::url() : '',
			'icon'  => 'analytics',
		),
		array(
			'id'    => 'forms',
			'label' => __( 'Forms', 'nestform' ),
			'url'   => class_exists( 'Nestform_Post_Type' ) ? Nestform_Post_Type::hub_url() : '',
			'icon'  => 'forms',
		),
		array(
			'id'    => 'entries',
			'label' => __( 'Entries', 'nestform' ),
			'url'   => class_exists( 'Nestform_Submissions' ) ? Nestform_Submissions::hub_url() : '',
			'icon'  => 'entries',
		),
		array(
			'id'    => 'settings',
			'label' => __( 'Settings', 'nestform' ),
			'url'   => class_exists( 'Nestform_Settings' ) ? Nestform_Settings::url() : '',
			'icon'  => 'settings',
		),
		array(
			'id'    => 'integrations',
			'label' => __( 'Integrations', 'nestform' ),
			'url'   => class_exists( 'Nestform_Integrations' ) ? Nestform_Integrations::url() : '',
			'icon'  => 'integrations',
		),
		array(
			'id'    => 'developers',
			'label' => __( 'Developers', 'nestform' ),
			'url'   => class_exists( 'Nestform_Developers' ) ? Nestform_Developers::url() : '',
			'icon'  => 'developers',
		),
		array(
			'id'    => 'docs',
			'label' => __( 'Docs', 'nestform' ),
			'url'   => class_exists( 'Nestform_Docs' ) ? Nestform_Docs::url() : '',
			'icon'  => 'docs',
		),
	);

	if ( class_exists( 'Nestform_Upgrade' ) && ! Nestform_Upgrade::is_pro() ) {
		$items[] = array(
			'id'    => 'upgrade',
			'label' => __( 'Upgrade', 'nestform' ),
			'url'   => Nestform_Upgrade::url(),
			'icon'  => 'crown',
		);
	}

	if ( current_user_can( 'manage_options' ) ) {
		if ( class_exists( 'Nestform_Freemius' ) && Nestform_Freemius::is_configured() ) {
			$items[] = array(
				'id'    => 'license',
				'label' => __( 'Account', 'nestform' ),
				'url'   => Nestform_Freemius::account_url(),
				'icon'  => 'crown',
			);
		} elseif ( class_exists( 'Nestform_Pro_License' ) ) {
			$items[] = array(
				'id'    => 'license',
				'label' => __( 'Account', 'nestform' ),
				'url'   => Nestform_Pro_License::url(),
				'icon'  => 'crown',
			);
		}
	}

	/**
	 * Filter Nestform app sidebar nav items.
	 *
	 * @param array<int, array{id:string,label:string,url:string,icon:string}> $items   Nav items.
	 * @param string                                                             $current Active view id.
	 */
	$items = apply_filters( 'nestform_app_nav_items', $items, $current );

	$nav_current = ( 'editor' === $current ) ? 'forms' : $current;
	?>
	<div class="nestform-app" data-nestform-app>
		<script>
		(function () {
			try {
				if ( window.localStorage.getItem( 'nestform_sidebar_collapsed' ) === '1' ) {
					var app = document.currentScript && document.currentScript.parentElement;
					if ( app ) {
						app.classList.add( 'nestform-app--sidebar-collapsed' );
					}
				}
			} catch ( e ) {}
		})();
		</script>
		<aside class="nestform-app__sidebar" id="nestform-app-sidebar">
			<div class="nestform-app__brand">
				<img
					class="nestform-app__logo"
					src="<?php echo esc_url( nestform_logo_url() ); ?>"
					alt="<?php esc_attr_e( 'Nestform', 'nestform' ); ?>"
					width="48"
					height="48"
				/>
				<div class="nestform-app__brand-copy">
					<div class="nestform-app__name">Nest<span class="nestform-app__name-accent">form</span></div>
					<div class="nestform-app__ver">v<?php echo esc_html( NESTFORM_VERSION ); ?></div>
				</div>
			</div>
			<nav class="nestform-app__nav" aria-label="<?php esc_attr_e( 'Nestform', 'nestform' ); ?>">
				<?php foreach ( $items as $item ) : ?>
					<?php
					if ( empty( $item['url'] ) ) {
						continue;
					}
					$is_on = ( $nav_current === $item['id'] );
					$class = 'nestform-app__nav-item' . ( $is_on ? ' nestform-app__nav-item--active' : '' );
					$label = (string) $item['label'];
					?>
					<a
						class="<?php echo esc_attr( $class ); ?>"
						href="<?php echo esc_url( $item['url'] ); ?>"
						title="<?php echo esc_attr( $label ); ?>"
					>
						<span class="nestform-app__nav-icon"><?php nestform_admin_icon( isset( $item['icon'] ) ? (string) $item['icon'] : 'forms' ); ?></span>
						<span class="nestform-app__nav-label"><?php echo esc_html( $label ); ?></span>
						<?php if ( 'entries' === $item['id'] && $new_n > 0 ) : ?>
							<span class="nestform-app__nav-count"><?php echo esc_html( number_format_i18n( $new_n ) ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<div class="nestform-app__foot">
				<?php if ( class_exists( 'Nestform_Upgrade' ) ) : ?>
					<?php Nestform_Upgrade::render_sidebar( $forms_n ); ?>
				<?php endif; ?>

				<div class="nestform-app__foot-card">
					<div class="nestform-app__foot-card-head">
						<span class="nestform-app__foot-card-title"><?php esc_html_e( 'Inbox', 'nestform' ); ?></span>
						<?php if ( $new_n > 0 ) : ?>
							<a class="nestform-app__foot-card-link" href="<?php echo esc_url( $new_url ); ?>">
								<?php esc_html_e( 'Review', 'nestform' ); ?>
								<?php echo nestform_admin_icon_html( 'forward' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?>
							</a>
						<?php elseif ( $entries_url !== '' ) : ?>
							<a class="nestform-app__foot-card-link" href="<?php echo esc_url( $entries_url ); ?>">
								<?php esc_html_e( 'Open', 'nestform' ); ?>
								<?php echo nestform_admin_icon_html( 'forward' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?>
							</a>
						<?php endif; ?>
					</div>

					<?php if ( $new_n > 0 ) : ?>
						<a class="nestform-app__foot-alert" href="<?php echo esc_url( $new_url ); ?>">
							<span class="nestform-app__foot-alert-dot" aria-hidden="true"></span>
							<span class="nestform-app__foot-alert-text">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: new entry count */
										_n( '%s new waiting', '%s new waiting', $new_n, 'nestform' ),
										number_format_i18n( $new_n )
									)
								);
								?>
							</span>
						</a>
					<?php else : ?>
						<p class="nestform-app__foot-clear">
							<?php esc_html_e( 'No new entries', 'nestform' ); ?>
						</p>
					<?php endif; ?>

					<div class="nestform-app__foot-grid" aria-label="<?php esc_attr_e( 'Quick stats', 'nestform' ); ?>">
						<a class="nestform-app__foot-cell<?php echo $new_n > 0 ? ' nestform-app__foot-cell--accent' : ''; ?>" href="<?php echo esc_url( $new_url ); ?>">
							<span class="nestform-app__foot-val"><?php echo esc_html( number_format_i18n( $new_n ) ); ?></span>
							<span class="nestform-app__foot-label"><?php esc_html_e( 'New', 'nestform' ); ?></span>
						</a>
						<a class="nestform-app__foot-cell" href="<?php echo esc_url( $analytics_url !== '' ? $analytics_url : $entries_url ); ?>">
							<span class="nestform-app__foot-val"><?php echo esc_html( number_format_i18n( $today_n ) ); ?></span>
							<span class="nestform-app__foot-label"><?php esc_html_e( 'Today', 'nestform' ); ?></span>
						</a>
						<?php
						$forms_url = class_exists( 'Nestform_Post_Type' ) ? Nestform_Post_Type::hub_url() : '';
						?>
						<a class="nestform-app__foot-cell" href="<?php echo esc_url( $forms_url !== '' ? $forms_url : '#' ); ?>">
							<span class="nestform-app__foot-val"><?php echo esc_html( number_format_i18n( $forms_n ) ); ?></span>
							<span class="nestform-app__foot-label"><?php esc_html_e( 'Forms', 'nestform' ); ?></span>
						</a>
						<a class="nestform-app__foot-cell" href="<?php echo esc_url( $entries_url ); ?>">
							<span class="nestform-app__foot-val"><?php echo esc_html( number_format_i18n( $entries_n ) ); ?></span>
							<span class="nestform-app__foot-label"><?php esc_html_e( 'Entries', 'nestform' ); ?></span>
						</a>
					</div>

					<p class="nestform-app__foot-hint">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: current date */
								__( 'Updated %s', 'nestform' ),
								wp_date( 'j M, H:i' )
							)
						);
						?>
					</p>
				</div>
			</div>
			<button
				type="button"
				class="nestform-app__sidebar-toggle"
				data-nestform-sidebar-toggle
				aria-controls="nestform-app-sidebar"
				aria-expanded="true"
				title="<?php esc_attr_e( 'Collapse sidebar', 'nestform' ); ?>"
			>
				<span class="nestform-app__sidebar-toggle-icon" aria-hidden="true">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
				</span>
				<span class="nestform-app__sidebar-toggle-label"><?php esc_html_e( 'Collapse', 'nestform' ); ?></span>
			</button>
			<a class="nestform-app__wp" href="<?php echo esc_url( admin_url() ); ?>" title="<?php esc_attr_e( 'WordPress admin', 'nestform' ); ?>">
				<span class="nestform-app__wp-icon"><?php nestform_admin_icon( 'external' ); ?></span>
				<span class="nestform-app__wp-label"><?php esc_html_e( 'WordPress admin', 'nestform' ); ?></span>
			</a>
		</aside>
		<div class="nestform-app__main">
	<?php
}

/**
 * Close Nestform app shell.
 */
function nestform_render_app_close() {
	if ( class_exists( 'Nestform_Upgrade' ) ) {
		Nestform_Upgrade::render_modal();
	}
	echo '</div></div>';
}

/**
 * Current Nestform admin view, or empty outside the plugin UI.
 *
 * @return string dashboard|forms|entries|settings|integrations|developers|upgrade|license|editor|
 */
function nestform_admin_current_view() {
	if ( ! is_admin() ) {
		return '';
	}
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$map  = array(
		'nestform-forms'       => 'forms',
		'nestform-dashboard'   => 'dashboard',
		'nestform-entries'     => 'entries',
		'nestform-settings'      => 'settings',
		'nestform-integrations'  => 'integrations',
		'nestform-developers'    => 'developers',
		'nestform-docs'          => 'docs',
		'nestform-upgrade'           => 'upgrade',
		'nestform-account'           => 'license',
		'nestform-forms-account'     => 'license',
		'nestform-pro-license'       => 'license',
	);
	if ( isset( $map[ $page ] ) ) {
		return $map[ $page ];
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen ) {
		return '';
	}
	if ( 'nestform' === $screen->post_type && in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
		return 'editor';
	}
	if ( 'nestform_entry' === $screen->post_type ) {
		return 'entries';
	}
	return '';
}

/**
 * Pin notices to the bottom before first paint (avoids WP default flash).
 */
function nestform_admin_notice_boot_css() {
	if ( '' === nestform_admin_current_view() ) {
		return;
	}
	echo '<style id="nestform-notice-boot">'
		. 'body.nestform-admin-screen div.notice:not(.inline):not(.hidden):not(.update-nag),'
		. 'body.nestform-admin-screen div.updated:not(.inline):not(.hidden),'
		. 'body.nestform-admin-screen div.error:not(.inline):not(.hidden){'
		. 'position:fixed!important;top:auto!important;right:0!important;bottom:24px!important;'
		. 'left:200px!important;width:min(440px,calc(100vw - 248px))!important;margin:0 auto!important;'
		. 'z-index:100000!important;box-sizing:border-box!important;'
		. 'padding:10px 40px 10px 14px!important;border:1px solid #bfdbfe!important;'
		. 'border-radius:8px!important;background:#eff6ff!important;box-shadow:0 4px 24px rgba(0,0,0,.06)!important;'
		. 'color:#1d4ed8!important;font-size:13px!important;line-height:1.45!important;'
		. '}'
		. 'body.nestform-admin-screen div.notice.notice-success:not(.inline):not(.hidden),'
		. 'body.nestform-admin-screen div.updated:not(.inline):not(.hidden),'
		. 'body.nestform-admin-screen div.notice.updated:not(.inline):not(.hidden){'
		. 'border-color:#86efac!important;background:#dcfce7!important;color:#166534!important;'
		. '}'
		. 'body.nestform-admin-screen div.notice.notice-error:not(.inline):not(.hidden),'
		. 'body.nestform-admin-screen div.error:not(.inline):not(.hidden){'
		. 'border-color:#fca5a5!important;background:#fee2e2!important;color:#b91c1c!important;'
		. '}'
		. 'body.nestform-admin-screen div.notice.notice-warning:not(.inline):not(.hidden){'
		. 'border-color:#fdba74!important;background:#ffedd5!important;color:#c2410c!important;'
		. '}'
		. 'body.nestform-admin-screen div.notice.notice-info:not(.inline):not(.hidden){'
		. 'border-color:#bfdbfe!important;background:#dbeafe!important;color:#1d4ed8!important;'
		. '}'
		. 'body.nestform-admin-screen .postbox div.notice,'
		. 'body.nestform-admin-screen .postbox div.updated,'
		. 'body.nestform-admin-screen .postbox div.error,'
		. 'body.nestform-admin-screen #screen-meta div.notice,'
		. 'body.nestform-admin-screen .media-modal div.notice{position:relative!important;left:auto!important;right:auto!important;bottom:auto!important;width:auto!important;margin:0 0 12px!important;}'
		. 'body.nestform-editor-app div.notice:not(.inline):not(.hidden):not(.update-nag),'
		. 'body.nestform-editor-app div.updated:not(.inline),'
		. 'body.nestform-editor-app div.error:not(.inline){right:240px!important;}'
		. '@media screen and (max-width:782px){body.nestform-admin-screen div.notice:not(.inline):not(.hidden):not(.update-nag),body.nestform-admin-screen div.updated:not(.inline),body.nestform-admin-screen div.error:not(.inline){left:16px!important;right:16px!important;width:auto!important;}}'
		. '</style>';
}

add_action( 'admin_head', 'nestform_admin_notice_boot_css', 1 );

add_filter(
	'admin_body_class',
	static function ( $classes ) {
		$view = nestform_admin_current_view();
		if ( '' === $view ) {
			return $classes;
		}
		$classes .= ' nestform-admin-screen nestform-app-screen';
		if ( 'editor' === $view ) {
			$classes .= ' nestform-editor-app';
		}
		if ( 'license' === $view ) {
			$classes .= ' nestform-license-screen';
		}
		return $classes;
	}
);

add_action(
	'in_admin_header',
	static function () {
		$view = nestform_admin_current_view();
		if ( '' === $view ) {
			return;
		}
		nestform_render_app_open( $view );
	}
);

add_action(
	'admin_footer',
	static function () {
		if ( '' === nestform_admin_current_view() ) {
			return;
		}
		nestform_render_app_close();
	},
	1
);

/**
 * Page title row inside the app main column.
 *
 * @param array<string, mixed> $args {
 *     @type string $title         Page title.
 *     @type string $description   Short help text.
 *     @type string $actions_html  Escaped HTML for the right side.
 *     @type string $icon          Optional lead icon key (forms|analytics|entries|settings|developers|crown).
 * }
 */
function nestform_render_page_head( array $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'title'        => '',
			'description'  => '',
			'actions_html' => '',
			'icon'         => '',
		)
	);

	$icon = sanitize_key( (string) $args['icon'] );
	if ( $icon === '' ) {
		$view = nestform_admin_current_view();
		$map  = array(
			'forms'        => 'forms',
			'editor'       => 'forms',
			'dashboard'    => 'analytics',
			'analytics'    => 'analytics',
			'entries'      => 'entries',
			'settings'     => 'settings',
			'integrations' => 'integrations',
			'developers'   => 'developers',
			'docs'         => 'docs',
			'upgrade'      => 'crown',
			'license'      => 'crown',
		);
		$icon = isset( $map[ $view ] ) ? $map[ $view ] : '';
	}
	$icon_html = $icon !== '' ? nestform_admin_icon_html( $icon ) : '';
	$actions   = (string) $args['actions_html'];
	$view      = nestform_admin_current_view();
	if ( ! in_array( $view, array( 'upgrade', 'license' ), true ) && class_exists( 'Nestform_Upgrade' ) ) {
		$chip = Nestform_Upgrade::header_chip_html();
		if ( $chip !== '' ) {
			$actions = $chip . ( $actions !== '' ? ' ' . $actions : '' );
		}
	}
	?>
	<header class="nestform-page-head">
		<div class="nestform-page-head__lead">
			<?php if ( $icon_html !== '' ) : ?>
				<span class="nestform-page-head__icon nestform-page-head__icon--<?php echo esc_attr( $icon ); ?>" aria-hidden="true"><?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?></span>
			<?php endif; ?>
			<div class="nestform-page-head__copy">
				<h1 class="nestform-page-head__title"><?php echo esc_html( (string) $args['title'] ); ?></h1>
				<?php if ( (string) $args['description'] !== '' ) : ?>
					<p class="nestform-page-head__desc"><?php echo esc_html( (string) $args['description'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php if ( $actions !== '' ) : ?>
			<div class="nestform-page-head__actions">
				<?php echo $actions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by trusted admin screens ?>
			</div>
		<?php endif; ?>
	</header>
	<hr class="wp-header-end" />
	<?php
}

require_once NESTFORM_PATH . 'includes/class-migration.php';
require_once NESTFORM_PATH . 'includes/class-compat.php';
require_once NESTFORM_PATH . 'includes/class-features.php';
require_once NESTFORM_PATH . 'includes/class-formula.php';
require_once NESTFORM_PATH . 'includes/class-mail-html.php';
require_once NESTFORM_PATH . 'includes/class-form-config.php';
require_once NESTFORM_PATH . 'includes/class-security.php';
require_once NESTFORM_PATH . 'includes/class-phone.php';
require_once NESTFORM_PATH . 'includes/class-post-type.php';
require_once NESTFORM_PATH . 'includes/class-submissions.php';
require_once NESTFORM_PATH . 'includes/class-dashboard.php';
require_once NESTFORM_PATH . 'includes/class-settings.php';
require_once NESTFORM_PATH . 'includes/class-integrations.php';
require_once NESTFORM_PATH . 'includes/class-developers.php';
require_once NESTFORM_PATH . 'includes/class-docs.php';
require_once NESTFORM_PATH . 'includes/class-upgrade.php';
require_once NESTFORM_PATH . 'includes/class-admin-ui.php';
require_once NESTFORM_PATH . 'includes/class-renderer.php';
require_once NESTFORM_PATH . 'includes/class-submit.php';
require_once NESTFORM_PATH . 'includes/class-captcha.php';
require_once NESTFORM_PATH . 'includes/class-export.php';
require_once NESTFORM_PATH . 'includes/class-form-io.php';
require_once NESTFORM_PATH . 'includes/class-webhook.php';
require_once NESTFORM_PATH . 'includes/class-templates.php';
require_once NESTFORM_PATH . 'includes/class-block.php';
require_once NESTFORM_PATH . 'includes/class-elementor.php';

add_action(
	'plugins_loaded',
	static function () {
		Nestform_Migration::init();
		Nestform_Compat::init();
		Nestform_Post_Type::init();
		if ( class_exists( 'Nestform_Security' ) ) {
			Nestform_Security::init();
		}
		Nestform_Submissions::init();
		Nestform_Dashboard::init();
		Nestform_Settings::init();
		Nestform_Integrations::init();
		Nestform_Developers::init();
		Nestform_Docs::init();
		Nestform_Upgrade::init();
		Nestform_Freemius::init();
		Nestform_Admin_UI::init();
		Nestform_Renderer::init();
		Nestform_Submit::init();
		Nestform_Captcha::init();
		Nestform_Export::init();
		Nestform_Form_IO::init();
		Nestform_Webhook::init();
		Nestform_Templates::init();
		Nestform_Block::init();
		Nestform_Elementor::init();

		/**
		 * Fires after Nestform Free is loaded. Pro registers features here.
		 */
		do_action( 'nestform_loaded' );
	},
	5
);

add_action(
	'admin_enqueue_scripts',
	static function () {
		if ( '' === nestform_admin_current_view() ) {
			return;
		}
		$ver = (string) filemtime( NESTFORM_PATH . 'assets/admin.css' );
		wp_enqueue_style(
			'nestform-admin',
			NESTFORM_URL . 'assets/admin.css',
			nestform_admin_style_deps(),
			$ver ? $ver : NESTFORM_VERSION
		);
		$ver_js = (string) filemtime( NESTFORM_PATH . 'assets/admin-notices.js' );
		wp_enqueue_script(
			'nestform-admin-notices',
			NESTFORM_URL . 'assets/admin-notices.js',
			array( 'jquery', 'common' ),
			$ver_js ? $ver_js : NESTFORM_VERSION,
			true
		);
		$ver_pro = (string) filemtime( NESTFORM_PATH . 'assets/admin-pro.js' );
		wp_enqueue_script(
			'freemius-checkout',
			'https://checkout.freemius.com/js/v1/',
			array(),
			null,
			true
		);
		wp_enqueue_script(
			'nestform-admin-pro',
			NESTFORM_URL . 'assets/admin-pro.js',
			array( 'freemius-checkout' ),
			$ver_pro ? $ver_pro : NESTFORM_VERSION,
			true
		);
		$features = array();
		if ( class_exists( 'Nestform_Features' ) ) {
			foreach ( Nestform_Features::all_keys() as $key ) {
				$features[ $key ] = Nestform_Features::can( $key );
			}
		}

		$user = wp_get_current_user();
		$fs   = class_exists( 'Nestform_Freemius' ) ? Nestform_Freemius::instance() : null;

		wp_localize_script(
			'nestform-admin-pro',
			'nestformPro',
			array(
				'isPro'      => class_exists( 'Nestform_Upgrade' ) ? Nestform_Upgrade::is_pro() : false,
				'url'        => class_exists( 'Nestform_Upgrade' ) ? Nestform_Upgrade::url() : '',
				'accountUrl' => class_exists( 'Nestform_Freemius' ) ? Nestform_Freemius::account_url() : '',
				'features'   => $features,
				'userEmail'  => ( $user && $user->exists() ) ? (string) $user->user_email : '',
				'fs'         => array(
					'productId' => $fs ? (int) $fs->get_id() : 37981,
					'publicKey' => $fs ? (string) $fs->get_public_key() : 'pk_7337f922428c7467840c937e4cf5d',
					'plans'     => array(
						'pro'    => array(
							'planId'    => defined( 'NESTFORM_FS_PLAN_PRO' ) ? (int) NESTFORM_FS_PLAN_PRO : 0,
							'pricingId' => defined( 'NESTFORM_FS_PRICING_PRO' ) ? (int) NESTFORM_FS_PRICING_PRO : 0,
							'title'     => __( 'Nestform Pro', 'nestform' ),
						),
						'agency' => array(
							'planId'    => defined( 'NESTFORM_FS_PLAN_AGENCY' ) ? (int) NESTFORM_FS_PLAN_AGENCY : 0,
							'pricingId' => defined( 'NESTFORM_FS_PRICING_AGENCY' ) ? (int) NESTFORM_FS_PRICING_AGENCY : 0,
							'title'     => __( 'Nestform Agency', 'nestform' ),
						),
					),
				),
				'i18n'       => array(
					'conversionTitle' => __( 'Track your conversion rate', 'nestform' ),
					'conversionText'  => __( 'See which forms convert visitors into submissions.', 'nestform' ),
					'stepsTitle'      => __( 'Build multi-step forms with Nestform Pro', 'nestform' ),
					'stepsText'       => __( 'Split long forms into simple steps and increase completion rate.', 'nestform' ),
					'fieldTitle'      => __( 'Advanced fields in Nestform Pro', 'nestform' ),
					'fieldText'       => __( 'Rating, signature, NPS, scale, ranking, and matrix unlock with Pro.', 'nestform' ),
					'webhookTitle'    => __( 'Webhooks with Nestform Pro', 'nestform' ),
					'webhookText'     => __( 'Send submissions to Zapier, Make, n8n, or your own endpoint.', 'nestform' ),
					'limitTitle'      => __( 'Form limit reached', 'nestform' ),
					'limitText'       => __( 'Free includes up to 5 forms. Upgrade to Nestform Pro for unlimited forms.', 'nestform' ),
					'emailTitle'      => __( 'HTML email designer in Nestform Pro', 'nestform' ),
					'emailText'       => __( 'Visual templates, logo, and live preview for notification emails.', 'nestform' ),
					'calcTitle'       => __( 'Calculated fields in Nestform Pro', 'nestform' ),
					'calcText'        => __( 'Auto-compute totals and scores from other fields.', 'nestform' ),
					'repeaterTitle'   => __( 'Repeaters in Nestform Pro', 'nestform' ),
					'repeaterText'    => __( 'Let visitors add repeating groups — line items, contacts, and more.', 'nestform' ),
					'pdfTitle'        => __( 'PDF export in Nestform Pro', 'nestform' ),
					'pdfText'         => __( 'Download entries as PDF and attach them to notification emails.', 'nestform' ),
					'quizTitle'       => __( 'Quizzes & surveys in Nestform Pro', 'nestform' ),
					'quizText'        => __( 'Scoring, result bands, timers, attempts, and shareable results that convert.', 'nestform' ),
					'autoTitle'       => __( 'Automations in Nestform Pro', 'nestform' ),
					'autoText'        => __( 'When a submission matches a condition, set status, alert email, or fire a webhook.', 'nestform' ),
					'sidebar'         => array(
						'collapse' => __( 'Collapse', 'nestform' ),
						'expand'   => __( 'Expand', 'nestform' ),
					),
				),
			)
		);
	},
	5
);
}
