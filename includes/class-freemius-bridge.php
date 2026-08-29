<?php
/**
 * Freemius helpers (checkout, license state).
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Freemius {

	/**
	 * Legacy bridge slug (redirects to Freemius account when registered).
	 */
	const ACCOUNT_PAGE_SLUG = 'nestform-account';

	/**
	 * Freemius account page slug (menu slug nestform-forms + "-account").
	 */
	const FS_ACCOUNT_PAGE_SLUG = 'nestform-forms-account';

	/**
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_account_page' ), 1000000000 );
		add_action( 'admin_menu', array( __CLASS__, 'hook_fs_account_wrap' ), 1000000002 );
		add_action( 'admin_head', array( __CLASS__, 'hide_freemius_wp_submenu_css' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'account_assets' ) );
	}

	/**
	 * Visually hide Freemius / Account items under All Forms.
	 * Do not remove_submenu_page — WP then blocks access ("not allowed").
	 * Checkout needs the Freemius pricing page registered under the CPT parent.
	 *
	 * @return void
	 */
	public static function hide_freemius_wp_submenu_css() {
		echo '<style id="nestform-hide-fs-submenu">'
			. '#menu-posts-nestform .wp-submenu a[href*="page=pricing"],'
			. '#menu-posts-nestform .wp-submenu a[href*="page=contact"],'
			. '#menu-posts-nestform .wp-submenu a[href*="page=account"],'
			. '#menu-posts-nestform .wp-submenu a[href*="page=affiliation"],'
			. '#menu-posts-nestform .wp-submenu a[href*="page=wp-support-forum"],'
			. '#menu-posts-nestform .wp-submenu a[href*="page=nestform-account"],'
			. '#menu-posts-nestform .wp-submenu a[href*="page=nestform-forms-account"],'
			. '#menu-posts-nestform .wp-submenu .fs-submenu-item.pricing,'
			. '#menu-posts-nestform .wp-submenu .fs-submenu-item.contact,'
			. '#menu-posts-nestform .wp-submenu .fs-submenu-item.account,'
			. '#menu-posts-nestform .wp-submenu .fs-submenu-item.affiliation,'
			. '#menu-posts-nestform .wp-submenu .upgrade-mode'
			. '{display:none!important;}'
			. '</style>';
	}

	/**
	 * Take over Freemius Account page render so it stays inside Nestform chrome.
	 *
	 * @return void
	 */
	public static function hook_fs_account_wrap() {
		$parent = 'edit.php?post_type=nestform';
		$hook   = get_plugin_page_hookname( self::FS_ACCOUNT_PAGE_SLUG, $parent );
		if ( ! is_string( $hook ) || '' === $hook ) {
			return;
		}

		// Keep load-* hooks (Freemius _account_page_load); replace only the page body.
		remove_all_actions( $hook );
		add_action( $hook, array( __CLASS__, 'render_account_page' ) );
	}

	/**
	 * Nestform chrome for Freemius Account page.
	 *
	 * @return void
	 */
	public static function account_assets() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $page, array( self::FS_ACCOUNT_PAGE_SLUG, self::ACCOUNT_PAGE_SLUG ), true ) ) {
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
	 * @return Freemius|null
	 */
	public static function instance() {
		return function_exists( 'nes_fs' ) ? nes_fs() : null;
	}

	/**
	 * Whether Freemius SDK is loaded.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return function_exists( 'nes_fs' ) && null !== self::instance();
	}

	/**
	 * Whether premium code may run on this site (valid paid license or trial).
	 *
	 * @return bool
	 */
	public static function can_use_premium() {
		$fs = self::instance();
		if ( ! $fs ) {
			return false;
		}
		return (bool) $fs->can_use_premium_code();
	}

	/**
	 * Free plan or trial (contextual upsells). Paying license = false.
	 *
	 * @return bool
	 */
	public static function is_not_paying() {
		$fs = self::instance();
		if ( ! $fs ) {
			return true;
		}
		return (bool) $fs->is_not_paying();
	}

	/**
	 * Whether the active license is this plan or a higher one.
	 *
	 * @param string $plan  Freemius plan slug (pro|agency).
	 * @param bool   $exact Exact plan only.
	 * @return bool
	 */
	public static function is_plan( $plan, $exact = false ) {
		$fs = self::instance();
		if ( ! $fs || ! self::can_use_premium() ) {
			return false;
		}
		return (bool) $fs->is_plan( sanitize_key( (string) $plan ), (bool) $exact );
	}

	/**
	 * Freemius account / license page in wp-admin (Nestform app shell).
	 *
	 * @return string
	 */
	public static function account_url() {
		$fs = self::instance();
		if ( $fs && method_exists( $fs, 'get_account_url' ) && $fs->is_registered() ) {
			return (string) $fs->get_account_url();
		}
		return self::account_page_url();
	}

	/**
	 * Freemius license activation screen (paste sandbox / purchase key here).
	 *
	 * @return string
	 */
	public static function license_activation_url() {
		$fs = self::instance();
		if ( $fs && method_exists( $fs, 'get_account_url' ) ) {
			return (string) $fs->get_account_url( false, array( 'activate_license' => 'true' ) );
		}
		return add_query_arg( 'activate_license', 'true', self::account_page_url() );
	}

	/**
	 * Account page URL (Freemius native slug preferred).
	 *
	 * @return string
	 */
	public static function account_page_url() {
		return add_query_arg(
			array(
				'post_type' => 'nestform',
				'page'      => self::FS_ACCOUNT_PAGE_SLUG,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Register account submenu when Freemius skipped it (activation mode / CPT hub).
	 *
	 * @return void
	 */
	public static function register_account_page() {
		if ( ! self::is_configured() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( self::account_submenu_exists() ) {
			return;
		}

		$parent = 'edit.php?post_type=nestform';
		$hook   = add_submenu_page(
			$parent,
			__( 'Account', 'nestform' ),
			__( 'Account', 'nestform' ),
			'manage_options',
			self::FS_ACCOUNT_PAGE_SLUG,
			array( __CLASS__, 'render_account_page' )
		);

		if ( is_string( $hook ) && $hook !== '' ) {
			add_action( 'load-' . $hook, array( __CLASS__, 'load_account_page' ) );
		}
	}

	/**
	 * @return bool
	 */
	private static function account_submenu_exists() {
		global $submenu;

		$parent = 'edit.php?post_type=nestform';
		if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return false;
		}

		$slugs = array( self::FS_ACCOUNT_PAGE_SLUG, self::ACCOUNT_PAGE_SLUG, 'account' );
		foreach ( $submenu[ $parent ] as $item ) {
			if ( isset( $item[2] ) && in_array( (string) $item[2], $slugs, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return void
	 */
	public static function load_account_page() {
		$fs = self::instance();
		if ( $fs && is_callable( array( $fs, '_account_page_load' ) ) ) {
			$fs->_account_page_load();
		}
	}

	/**
	 * @return void
	 */
	public static function render_account_page() {
		$fs = self::instance();
		if ( ! $fs ) {
			wp_die( esc_html__( 'Freemius is not available.', 'nestform' ) );
		}

		echo '<div class="wrap nestform-admin nestform-license">';
		if ( function_exists( 'nestform_render_page_head' ) ) {
			nestform_render_page_head(
				array(
					'title'       => __( 'Account', 'nestform' ),
					'description' => __( 'License, billing, and Freemius account for Nestform Pro.', 'nestform' ),
					'icon'        => 'crown',
				)
			);
		}
		echo '<div class="nestform-admin__surface nestform-license__fs">';

		if ( $fs->is_registered() && is_callable( array( $fs, '_account_page_render' ) ) ) {
			$fs->_account_page_render();
		} elseif ( is_callable( array( $fs, '_connect_page_render' ) ) ) {
			if ( is_callable( array( 'Freemius', '_clean_admin_content_section' ) ) ) {
				Freemius::_clean_admin_content_section();
			}
			$fs->_connect_page_render();
		} else {
			echo '<p>' . esc_html__( 'Open the Freemius connection screen to continue.', 'nestform' ) . '</p>';
			echo '<p><a class="button button-primary" href="' . esc_url( $fs->get_activation_url() ) . '">' . esc_html__( 'Connect Freemius', 'nestform' ) . '</a></p>';
		}

		echo '</div></div>';
	}

	/**
	 * Pricing / upgrade URL (in-dashboard or checkout).
	 *
	 * @return string
	 */
	public static function upgrade_url() {
		$fs = self::instance();
		if ( $fs && method_exists( $fs, 'get_upgrade_url' ) ) {
			return (string) $fs->get_upgrade_url();
		}
		return 'https://nestform.app/pro';
	}

	/**
	 * Checkout URL for a plan + billing cycle.
	 *
	 * Opens Freemius in-dashboard checkout (same pattern as custom Upgrade CTAs).
	 *
	 * @param string $plan    pro|agency.
	 * @param string $billing monthly|yearly.
	 * @return string
	 */
	public static function checkout_url( $plan = 'pro', $billing = 'monthly' ) {
		$plan    = sanitize_key( (string) $plan );
		$billing = sanitize_key( (string) $billing );
		if ( ! in_array( $plan, array( 'pro', 'agency' ), true ) ) {
			$plan = 'pro';
		}
		if ( ! in_array( $billing, array( 'monthly', 'yearly' ), true ) ) {
			$billing = 'monthly';
		}

		$plan_id    = self::plan_id_for( $plan );
		$pricing_id = self::pricing_id_for( $plan, $billing );
		$fs         = self::instance();

		if ( $fs && $plan_id > 0 && method_exists( $fs, 'checkout_url' ) ) {
			$cycle = 'yearly' === $billing
				? ( defined( 'WP_FS__PERIOD_ANNUALLY' ) ? WP_FS__PERIOD_ANNUALLY : 'annual' )
				: ( defined( 'WP_FS__PERIOD_MONTHLY' ) ? WP_FS__PERIOD_MONTHLY : 'monthly' );

			$extra = array(
				'plan_id' => $plan_id,
			);
			if ( $pricing_id > 0 ) {
				$extra['pricing_id'] = $pricing_id;
			}

			return (string) $fs->checkout_url( $cycle, false, $extra );
		}

		if ( $fs && method_exists( $fs, 'get_upgrade_url' ) ) {
			return (string) $fs->get_upgrade_url();
		}

		$fallback = 'https://nestform.app/pro';
		if ( 'agency' === $plan ) {
			$fallback = add_query_arg( 'plan', 'agency', $fallback );
		}
		if ( 'yearly' === $billing ) {
			$fallback = add_query_arg( 'billing', 'yearly', $fallback );
		}

		/**
		 * Filter checkout URL when Freemius is unavailable.
		 *
		 * @param string $url     URL.
		 * @param string $plan    pro|agency.
		 * @param string $billing monthly|yearly.
		 */
		return (string) apply_filters( 'nestform_pro_checkout_url', $fallback, $plan, $billing );
	}

	/**
	 * Sandbox params for Freemius Overlay Checkout (local / WP_FS__DEV_MODE only).
	 *
	 * @return array{token:string,ctx:string}|null
	 */
	public static function checkout_sandbox_params() {
		if ( ! defined( 'WP_FS__DEV_MODE' ) || ! WP_FS__DEV_MODE ) {
			return null;
		}

		$fs         = self::instance();
		$product_id = $fs ? (string) $fs->get_id() : '37981';
		$public_key = $fs ? (string) $fs->get_public_key() : 'pk_7337f922428c7467840c937e4cf5d';
		$secret     = '';

		if ( defined( 'WP_FS__nestform_SECRET_KEY' ) ) {
			$secret = (string) constant( 'WP_FS__nestform_SECRET_KEY' );
		} elseif ( $fs && method_exists( $fs, 'get_secret_key' ) ) {
			$secret = (string) $fs->get_secret_key();
		}

		if ( '' === $secret || '' === $product_id || '' === $public_key ) {
			return null;
		}

		$ctx   = (string) time();
		$token = md5( $ctx . $product_id . $secret . $public_key . 'checkout' );

		return array(
			'token' => $token,
			'ctx'   => $ctx,
		);
	}

	/**
	 * Freemius plan ID for checkout.
	 *
	 * @param string $plan pro|agency.
	 * @return int
	 */
	public static function plan_id_for( $plan ) {
		if ( 'agency' === $plan ) {
			return (int) NESTFORM_FS_PLAN_AGENCY;
		}
		return (int) NESTFORM_FS_PLAN_PRO;
	}

	/**
	 * Freemius pricing ID for a plan + billing cycle (0 if unset).
	 *
	 * @param string $plan    pro|agency.
	 * @param string $billing monthly|yearly.
	 * @return int
	 */
	public static function pricing_id_for( $plan, $billing = 'monthly' ) {
		$billing = sanitize_key( (string) $billing );
		if ( 'agency' === $plan ) {
			return 'yearly' === $billing
				? (int) NESTFORM_FS_PRICING_AGENCY_ANNUAL
				: (int) NESTFORM_FS_PRICING_AGENCY_MONTHLY;
		}
		return 'yearly' === $billing
			? (int) NESTFORM_FS_PRICING_PRO_ANNUAL
			: (int) NESTFORM_FS_PRICING_PRO_MONTHLY;
	}
}
