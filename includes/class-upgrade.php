<?php
/**
 * Nestform Pro upgrade surface (upsell UI; capabilities come from Nestform Pro).
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Nestform_Upgrade {

	const PAGE_SLUG = 'nestform-upgrade';

	/**
	 * Free-plan form cap (hard-enforced via Nestform_Features / Post_Type).
	 */
	const FREE_FORM_LIMIT = 5;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
	}

	/**
	 * Whether this site is treated as Pro (set by Nestform Pro after license check).
	 *
	 * @return bool
	 */
	public static function is_pro() {
		if ( class_exists( 'Nestform_Freemius' ) && Nestform_Freemius::can_use_premium() ) {
			return true;
		}
		return (bool) apply_filters( 'nestform_is_pro', false );
	}

	/**
	 * @return int
	 */
	public static function free_form_limit() {
		return (int) apply_filters( 'nestform_free_form_limit', self::FREE_FORM_LIMIT );
	}

	/**
	 * Plan catalog (Free, Pro, Agency) — shared by Upgrade and Docs.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function plan_catalog() {
		$limit = max( 1, self::free_form_limit() );

		$free_features = array(
			sprintf(
				/* translators: %d: free form limit */
				__( 'Up to %d forms', 'nestform' ),
				$limit
			),
			__( 'Lead & contact forms with templates', 'nestform' ),
			__( 'Conditional logic & file uploads', 'nestform' ),
			__( 'Entries inbox, CSV export & captcha', 'nestform' ),
			__( 'Email notifications & spam protection', 'nestform' ),
			__( 'Basic analytics', 'nestform' ),
		);

		$pro_features = array(
			__( 'Unlimited forms', 'nestform' ),
			__( 'Quizzes & surveys with scoring and result bands', 'nestform' ),
			__( 'Multi-step forms & branch rules', 'nestform' ),
			__( 'HTML email designer & PDF attachments', 'nestform' ),
			__( 'Webhooks, automations & calculated fields', 'nestform' ),
			__( 'Advanced fields, analytics & lead insights', 'nestform' ),
		);

		$agency_features = array(
			__( 'Everything in Pro', 'nestform' ),
			__( 'Priority support & onboarding', 'nestform' ),
			__( 'Agency license for up to 5 client sites', 'nestform' ),
			__( 'White-label ready workflows', 'nestform' ),
			__( 'Early access to new integrations', 'nestform' ),
			__( 'Volume pricing for teams', 'nestform' ),
		);

		/**
		 * Filter the Nestform plan catalog (Upgrade, Docs, upsells).
		 *
		 * @param array<string, array<string, mixed>> $plans Plan definitions.
		 */
		return (array) apply_filters(
			'nestform_plan_catalog',
			array(
				'free'   => array(
					'id'                 => 'free',
					'name'               => __( 'Free', 'nestform' ),
					'tagline'            => __( 'Lead forms that ship', 'nestform' ),
					'price_monthly'      => '$0',
					'price_yearly'       => '$0',
					'price_unit_monthly' => __( '/ month', 'nestform' ),
					'price_unit_yearly'  => __( '/ year', 'nestform' ),
					'billed_yearly'      => '',
					'features'           => $free_features,
					'foot'               => __( 'Included with Nestform', 'nestform' ),
					'popular'            => false,
					'checkout_plan'      => '',
				),
				'pro'    => array(
					'id'                 => 'pro',
					'name'               => __( 'Pro', 'nestform' ),
					'tagline'            => __( 'Interactive forms & growth', 'nestform' ),
					'price_monthly'      => '$9.99',
					'price_yearly'       => '$89.99',
					'price_unit_monthly' => __( '/ month', 'nestform' ),
					'price_unit_yearly'  => __( '/ year', 'nestform' ),
					'billed_yearly'      => '',
					'features'           => $pro_features,
					'foot'               => __( 'Single WordPress site', 'nestform' ),
					'popular'            => true,
					'checkout_plan'      => 'pro',
				),
				'agency' => array(
					'id'                 => 'agency',
					'name'               => __( 'Agency', 'nestform' ),
					'tagline'            => __( 'For teams & client sites', 'nestform' ),
					'price_monthly'      => '$29.99',
					'price_yearly'       => '$269.99',
					'price_unit_monthly' => __( '/ month', 'nestform' ),
					'price_unit_yearly'  => __( '/ year', 'nestform' ),
					'billed_yearly'      => '',
					'features'           => $agency_features,
					'foot'               => __( 'Up to 5 client sites', 'nestform' ),
					'popular'            => false,
					'checkout_plan'      => 'agency',
				),
			)
		);
	}

	/**
	 * Human-readable price line for docs and tooltips.
	 *
	 * @param string $plan_key free|pro|agency.
	 * @return string
	 */
	public static function plan_price_summary( $plan_key ) {
		$catalog = self::plan_catalog();
		$key     = sanitize_key( (string) $plan_key );
		if ( ! isset( $catalog[ $key ] ) ) {
			return '';
		}
		$plan = $catalog[ $key ];
		if ( 'free' === $key ) {
			return (string) $plan['price_monthly'] . (string) $plan['price_unit_monthly'];
		}
		return sprintf(
			/* translators: 1: monthly price with unit, 2: yearly price with unit */
			__( '%1$s or %2$s', 'nestform' ),
			(string) $plan['price_monthly'] . (string) $plan['price_unit_monthly'],
			(string) $plan['price_yearly'] . (string) $plan['price_unit_yearly']
		);
	}

	/**
	 * Parse a display price string into a float (e.g. "$9.99" → 9.99).
	 *
	 * @param string $price Price label.
	 * @return float
	 */
	public static function parse_price_amount( $price ) {
		$price = (string) $price;
		if ( preg_match( '/[\d.,]+/', $price, $matches ) ) {
			return (float) str_replace( ',', '', $matches[0] );
		}
		return 0.0;
	}

	/**
	 * Yearly savings vs paying monthly for 12 months.
	 *
	 * @param string $plan_key free|pro|agency.
	 * @return int 0–100
	 */
	public static function plan_yearly_savings_percent( $plan_key ) {
		$catalog = self::plan_catalog();
		$key     = sanitize_key( (string) $plan_key );
		if ( ! isset( $catalog[ $key ] ) || empty( $catalog[ $key ]['checkout_plan'] ) ) {
			return 0;
		}

		$plan    = $catalog[ $key ];
		$monthly = self::parse_price_amount( (string) $plan['price_monthly'] );
		$yearly  = self::parse_price_amount( (string) $plan['price_yearly'] );
		if ( $monthly <= 0 || $yearly <= 0 ) {
			return 0;
		}

		$annual_from_monthly = $monthly * 12;
		if ( $annual_from_monthly <= $yearly ) {
			return 0;
		}

		return (int) round( ( ( $annual_from_monthly - $yearly ) / $annual_from_monthly ) * 100 );
	}

	/**
	 * Badge copy for the yearly billing toggle.
	 *
	 * @return string Empty when no paid yearly savings.
	 */
	public static function yearly_savings_badge_text() {
		$max     = 0;
		$amounts = array();

		foreach ( self::plan_catalog() as $plan_key => $plan ) {
			if ( empty( $plan['checkout_plan'] ) ) {
				continue;
			}
			$pct = self::plan_yearly_savings_percent( (string) $plan_key );
			if ( $pct <= 0 ) {
				continue;
			}
			$max       = max( $max, $pct );
			$amounts[] = $pct;
		}

		if ( $max <= 0 ) {
			return '';
		}

		$amounts = array_values( array_unique( $amounts ) );
		if ( count( $amounts ) > 1 ) {
			return sprintf(
				/* translators: %d: maximum yearly savings percent */
				__( 'Save up to %d%%', 'nestform' ),
				$max
			);
		}

		return sprintf(
			/* translators: %d: yearly savings percent */
			__( 'Save %d%%', 'nestform' ),
			$max
		);
	}

	/**
	 * Monthly equivalent when billed yearly (derived from catalog prices).
	 *
	 * @param string               $plan_key pro|agency.
	 * @param array<string, mixed> $plan     Plan row.
	 * @return string
	 */
	public static function plan_billed_yearly_label( $plan_key, $plan ) {
		$key = sanitize_key( (string) $plan_key );
		if ( empty( $plan['checkout_plan'] ) ) {
			return '';
		}

		$yearly = self::parse_price_amount( (string) $plan['price_yearly'] );
		if ( $yearly <= 0 ) {
			return isset( $plan['billed_yearly'] ) ? (string) $plan['billed_yearly'] : '';
		}

		$monthly_equiv = $yearly / 12;
		$symbol        = '$';
		if ( preg_match( '/^\s*([^\d\s.,]+)/', (string) $plan['price_yearly'], $matches ) ) {
			$symbol = (string) $matches[1];
		}

		$formatted = $symbol . number_format_i18n( $monthly_equiv, 2 );

		if ( 'agency' === $key ) {
			return sprintf(
				/* translators: %s: monthly equivalent price */
				__( '≈ %s / month, billed yearly · 5 sites', 'nestform' ),
				$formatted
			);
		}

		return sprintf(
			/* translators: %s: monthly equivalent price */
			__( '≈ %s / month, billed yearly', 'nestform' ),
			$formatted
		);
	}

	/**
	 * Public checkout / buy URL for Nestform Pro.
	 *
	 * @param string $plan Optional plan key (pro|agency).
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
		if ( class_exists( 'Nestform_Freemius' ) ) {
			return esc_url_raw( Nestform_Freemius::checkout_url( $plan, $billing ) );
		}
		$url = apply_filters( 'nestform_pro_checkout_url', 'https://nestform.app/pro', $plan, $billing );
		if ( 'agency' === $plan ) {
			$url = apply_filters( 'nestform_agency_checkout_url', add_query_arg( 'plan', 'agency', (string) $url ), $plan, $billing );
		}
		if ( 'yearly' === $billing ) {
			$url = add_query_arg( 'billing', 'yearly', (string) $url );
		}
		return esc_url_raw( (string) $url );
	}

	/**
	 * @param array<string, scalar> $args Query args.
	 * @return string
	 */
	public static function url( $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'post_type' => Nestform_Post_Type::POST_TYPE,
					'page'      => self::PAGE_SLUG,
				),
				$args
			),
			admin_url( 'edit.php' )
		);
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . Nestform_Post_Type::POST_TYPE,
			__( 'Upgrade', 'nestform' ),
			__( 'Upgrade', 'nestform' ),
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
			$classes .= ' nestform-admin-screen nestform-upgrade-screen';
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
	 * Compact PRO pill.
	 *
	 * @return string
	 */
	public static function pill_html() {
		return '<span class="nestform-pro-pill">'
			. nestform_admin_icon_html( 'crown' )
			. esc_html__( 'PRO', 'nestform' )
			. '</span>';
	}

	/**
	 * Primary Pro CTA label with arrow.
	 *
	 * @return string
	 */
	public static function cta_label_html() {
		return esc_html__( 'Upgrade to Pro', 'nestform' )
			. ' <span class="nestform-pro-cta__arrow" aria-hidden="true">&rarr;</span>';
	}

	/**
	 * Header chip (empty when already Pro).
	 *
	 * @return string
	 */
	public static function header_chip_html() {
		if ( self::is_pro() ) {
			return '';
		}
		$stats   = function_exists( 'nestform_admin_stats' ) ? nestform_admin_stats() : array();
		$forms_n = isset( $stats['forms'] ) ? (int) $stats['forms'] : 0;
		$limit   = class_exists( 'Nestform_Features' ) ? Nestform_Features::form_limit() : max( 1, self::free_form_limit() );
		if ( $limit <= 0 ) {
			return '';
		}
		$html = '';
		if ( $forms_n >= $limit - 1 ) {
			$html .= '<span class="nestform-upgrade-usage">'
				. esc_html(
					sprintf(
						/* translators: 1: forms used, 2: free limit */
						__( '%1$s / %2$s forms used', 'nestform' ),
						number_format_i18n( $forms_n ),
						number_format_i18n( $limit )
					)
				)
				. '</span>';
		}
		$html .= '<a class="nestform-upgrade-chip" href="' . esc_url( self::url() ) . '">'
			. nestform_admin_icon_html( 'crown' )
			. ' ' . esc_html__( 'Upgrade', 'nestform' )
			. '</a>';
		return $html;
	}

	/**
	 * Shared Pro upsell dialog.
	 */
	public static function render_modal() {
		if ( self::is_pro() ) {
			return;
		}
		?>
		<div class="nestform-pro-modal" hidden data-nestform-pro-modal>
			<button type="button" class="nestform-pro-modal__backdrop" data-nestform-pro-dismiss aria-label="<?php esc_attr_e( 'Close', 'nestform' ); ?>"></button>
			<div class="nestform-pro-modal__card" role="dialog" aria-modal="true" aria-labelledby="nestform-pro-modal-title">
				<div class="nestform-pro-modal__head">
					<h3 class="nestform-pro-modal__title" id="nestform-pro-modal-title" data-nestform-pro-modal-title><?php esc_html_e( 'Need more power?', 'nestform' ); ?></h3>
					<?php echo self::pill_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<p class="nestform-pro-modal__text" data-nestform-pro-modal-text><?php esc_html_e( 'Unlock multi-step forms, webhooks, advanced fields and analytics.', 'nestform' ); ?></p>
				<div class="nestform-pro-modal__viz" data-nestform-pro-modal-viz hidden>
					<span>Step 1</span>
					<span class="nestform-pro-modal__viz-bar" aria-hidden="true"></span>
					<span>Step 2</span>
					<span class="nestform-pro-modal__viz-bar" aria-hidden="true"></span>
					<span>Step 3</span>
				</div>
				<ul class="nestform-pro-modal__list" data-nestform-pro-modal-list>
					<li><?php esc_html_e( 'Multi-step forms', 'nestform' ); ?></li>
					<li><?php esc_html_e( 'Webhooks & integrations', 'nestform' ); ?></li>
					<li><?php esc_html_e( 'Rating, signature, NPS, scale, ranking', 'nestform' ); ?></li>
					<li><?php esc_html_e( 'Views & conversion analytics', 'nestform' ); ?></li>
					<li><?php esc_html_e( 'Lead insights', 'nestform' ); ?></li>
					<li><?php esc_html_e( 'Unlimited forms', 'nestform' ); ?></li>
				</ul>
				<a
					class="nestform-pro-cta nestform-pro-modal__cta"
					href="<?php echo esc_url( self::checkout_url( 'pro', 'monthly' ) ); ?>"
					data-nestform-checkout="pro"
					data-nestform-checkout-monthly="<?php echo esc_url( self::checkout_url( 'pro', 'monthly' ) ); ?>"
					data-nestform-checkout-yearly="<?php echo esc_url( self::checkout_url( 'pro', 'yearly' ) ); ?>"
				>
					<?php echo self::cta_label_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
				<button type="button" class="nestform-pro-modal__dismiss" data-nestform-pro-dismiss><?php esc_html_e( 'Not now', 'nestform' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Sidebar usage + Pro card.
	 *
	 * @param int $forms_n Form count.
	 */
	public static function render_sidebar( $forms_n ) {
		$forms_n     = (int) $forms_n;
		$unlimited   = class_exists( 'Nestform_Features' ) && Nestform_Features::can( Nestform_Features::UNLIMITED_FORMS );
		$limit       = class_exists( 'Nestform_Features' ) ? Nestform_Features::form_limit() : max( 1, self::free_form_limit() );
		$pct         = ( ! $unlimited && $limit > 0 ) ? min( 100, (int) round( ( $forms_n / $limit ) * 100 ) ) : 0;
		$at_cap      = ! $unlimited && $limit > 0 && $forms_n >= $limit;
		?>
		<div class="nestform-app__usage">
			<div class="nestform-app__usage-row">
				<span><?php esc_html_e( 'Forms', 'nestform' ); ?></span>
				<?php if ( $unlimited ) : ?>
					<strong><?php echo esc_html( number_format_i18n( $forms_n ) ); ?></strong>
				<?php else : ?>
					<strong><?php echo esc_html( number_format_i18n( $forms_n ) . ' / ' . number_format_i18n( $limit ) ); ?></strong>
				<?php endif; ?>
			</div>
			<?php if ( ! $unlimited ) : ?>
				<span class="nestform-app__usage-bar" aria-hidden="true">
					<span class="nestform-app__usage-fill<?php echo $at_cap ? ' is-full' : ''; ?>" style="width: <?php echo esc_attr( (string) $pct ); ?>%"></span>
				</span>
				<span class="nestform-app__usage-hint">
					<?php
					echo $at_cap
						? esc_html__( 'Form limit reached', 'nestform' )
						: esc_html(
							sprintf(
								/* translators: %d: percent used */
								__( '%d%% used', 'nestform' ),
								$pct
							)
						);
					?>
				</span>
			<?php endif; ?>
		</div>
		<?php if ( ! self::is_pro() ) : ?>
			<a class="nestform-app__pro" href="<?php echo esc_url( self::url() ); ?>">
				<span class="nestform-app__pro-kicker"><?php echo nestform_admin_icon_html( 'crown' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?> <?php esc_html_e( 'Nestform Pro', 'nestform' ); ?></span>
				<span class="nestform-app__pro-copy"><?php esc_html_e( 'Quizzes, multi-step flows, PDF, webhooks, and lead insights that convert.', 'nestform' ); ?></span>
				<span class="nestform-pro-cta nestform-app__pro-cta"><?php echo self::cta_label_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</a>
		<?php endif; ?>
		<?php
	}

	public static function render() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'nestform' ) );
		}

		$plans      = self::plan_catalog();
		$save_badge = self::yearly_savings_badge_text();
		?>
		<div class="wrap nestform-upgrade">
			<div class="nestform-upgrade__shell">
				<header class="nestform-upgrade__head">
					<span class="nestform-upgrade__badge">
						<?php echo nestform_admin_icon_html( 'crown' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG ?>
						<?php esc_html_e( 'Nestform Pro', 'nestform' ); ?>
					</span>
					<h1 class="nestform-upgrade__title"><?php esc_html_e( 'Forms, quizzes & surveys that convert', 'nestform' ); ?></h1>
					<p class="nestform-upgrade__lead"><?php esc_html_e( 'Turn WordPress forms into interactive lead flows — with results, PDF, webhooks, and insights.', 'nestform' ); ?></p>
					<div class="nestform-upgrade__billing" data-nestform-billing>
						<button type="button" class="nestform-upgrade__bill is-active" data-plan="monthly"><?php esc_html_e( 'Monthly', 'nestform' ); ?></button>
						<button type="button" class="nestform-upgrade__bill" data-plan="yearly"><?php esc_html_e( 'Yearly', 'nestform' ); ?></button>
						<?php if ( $save_badge ) : ?>
							<span class="nestform-upgrade__save"><?php echo esc_html( $save_badge ); ?></span>
						<?php endif; ?>
					</div>
				</header>

				<div class="nestform-upgrade__grid">
					<?php foreach ( $plans as $plan_key => $plan ) : ?>
						<?php
						$article_class = 'nestform-upgrade__plan';
						if ( 'pro' === $plan_key ) {
							$article_class .= ' nestform-upgrade__plan--pro';
						} elseif ( 'agency' === $plan_key ) {
							$article_class .= ' nestform-upgrade__plan--agency';
						}
						$is_paid = ! empty( $plan['checkout_plan'] );
						?>
						<article class="<?php echo esc_attr( $article_class ); ?>">
							<?php if ( ! empty( $plan['popular'] ) ) : ?>
								<span class="nestform-upgrade__popular"><?php esc_html_e( 'Most popular', 'nestform' ); ?></span>
							<?php endif; ?>
							<div class="nestform-upgrade__plan-top">
								<h2 class="nestform-upgrade__plan-name">
									<?php echo esc_html( (string) $plan['name'] ); ?>
									<?php
									if ( 'pro' === $plan_key ) {
										echo self::pill_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									}
									?>
								</h2>
								<p class="nestform-upgrade__plan-tag"><?php echo esc_html( (string) $plan['tagline'] ); ?></p>
							</div>
							<?php if ( $is_paid ) : ?>
								<p class="nestform-upgrade__price" data-nestform-price-monthly<?php echo 'agency' === $plan_key ? ' data-nestform-agency-monthly' : ''; ?>>
									<?php echo esc_html( (string) $plan['price_monthly'] ); ?><span class="nestform-upgrade__price-unit"><?php echo esc_html( (string) $plan['price_unit_monthly'] ); ?></span>
								</p>
								<p class="nestform-upgrade__price" data-nestform-price-yearly<?php echo 'agency' === $plan_key ? ' data-nestform-agency-yearly' : ''; ?> hidden>
									<?php echo esc_html( (string) $plan['price_yearly'] ); ?><span class="nestform-upgrade__price-unit"><?php echo esc_html( (string) $plan['price_unit_yearly'] ); ?></span>
								</p>
								<?php
								$billed_yearly = self::plan_billed_yearly_label( (string) $plan_key, $plan );
								if ( $billed_yearly ) :
									?>
									<p class="nestform-upgrade__billed" data-nestform-billed-yearly hidden><?php echo esc_html( $billed_yearly ); ?></p>
								<?php endif; ?>
							<?php else : ?>
								<p class="nestform-upgrade__price">
									<?php echo esc_html( (string) $plan['price_monthly'] ); ?><span class="nestform-upgrade__price-unit"><?php echo esc_html( (string) $plan['price_unit_monthly'] ); ?></span>
								</p>
							<?php endif; ?>
							<ul class="nestform-upgrade__list">
								<?php foreach ( (array) $plan['features'] as $item ) : ?>
									<li><?php echo esc_html( (string) $item ); ?></li>
								<?php endforeach; ?>
							</ul>
							<?php if ( $is_paid ) : ?>
								<?php
								$checkout_plan = sanitize_key( (string) $plan['checkout_plan'] );
								$cta_class     = 'pro' === $checkout_plan
									? 'nestform-pro-cta nestform-upgrade__cta'
									: 'nestform-btn nestform-btn--outline nestform-upgrade__cta nestform-upgrade__cta--agency';
								?>
								<a
									class="<?php echo esc_attr( $cta_class ); ?>"
									href="<?php echo esc_url( self::checkout_url( $checkout_plan, 'monthly' ) ); ?>"
									data-nestform-checkout="<?php echo esc_attr( $checkout_plan ); ?>"
									data-nestform-checkout-monthly="<?php echo esc_url( self::checkout_url( $checkout_plan, 'monthly' ) ); ?>"
									data-nestform-checkout-yearly="<?php echo esc_url( self::checkout_url( $checkout_plan, 'yearly' ) ); ?>"
								>
									<?php
									if ( 'pro' === $checkout_plan ) {
										echo self::cta_label_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									} else {
										esc_html_e( 'Get Agency', 'nestform' );
									}
									?>
								</a>
							<?php elseif ( ! empty( $plan['foot'] ) ) : ?>
								<span class="nestform-upgrade__plan-foot"><?php echo esc_html( (string) $plan['foot'] ); ?></span>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>

				<p class="nestform-upgrade__trust">
					<span class="nestform-upgrade__trust-item"><?php esc_html_e( '30-day money-back guarantee', 'nestform' ); ?></span>
					<span class="nestform-upgrade__trust-item"><?php esc_html_e( 'Cancel anytime', 'nestform' ); ?></span>
					<span class="nestform-upgrade__trust-item"><?php esc_html_e( 'Instant unlock after purchase', 'nestform' ); ?></span>
				</p>
			</div>
		</div>
		<?php
	}
}
