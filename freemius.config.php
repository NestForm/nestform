<?php
/**
 * Freemius plan / pricing IDs for direct checkout URLs.
 *
 * Plan ID    — next to the plan name on the Plans list (required for checkout).
 * Pricing ID — inside a plan, next to each price row (optional, more precise).
 *
 * Product credentials live in nestform.php (Freemius SDK snippet).
 *
 * @package Nestform
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'NESTFORM_FS_PLAN_PRO' ) ) {
	define( 'NESTFORM_FS_PLAN_PRO', 63282 );
}
if ( ! defined( 'NESTFORM_FS_PLAN_AGENCY' ) ) {
	define( 'NESTFORM_FS_PLAN_AGENCY', 63298 );
}

// Optional pricing IDs (price rows inside each plan).
if ( ! defined( 'NESTFORM_FS_PRICING_PRO' ) ) {
	define( 'NESTFORM_FS_PRICING_PRO', 84761 );
}
if ( ! defined( 'NESTFORM_FS_PRICING_AGENCY' ) ) {
	define( 'NESTFORM_FS_PRICING_AGENCY', 84776 );
}

if ( ! defined( 'NESTFORM_FS_PRICING_PRO_MONTHLY' ) ) {
	define( 'NESTFORM_FS_PRICING_PRO_MONTHLY', NESTFORM_FS_PRICING_PRO );
}
if ( ! defined( 'NESTFORM_FS_PRICING_PRO_ANNUAL' ) ) {
	define( 'NESTFORM_FS_PRICING_PRO_ANNUAL', NESTFORM_FS_PRICING_PRO );
}
if ( ! defined( 'NESTFORM_FS_PRICING_AGENCY_MONTHLY' ) ) {
	define( 'NESTFORM_FS_PRICING_AGENCY_MONTHLY', NESTFORM_FS_PRICING_AGENCY );
}
if ( ! defined( 'NESTFORM_FS_PRICING_AGENCY_ANNUAL' ) ) {
	define( 'NESTFORM_FS_PRICING_AGENCY_ANNUAL', NESTFORM_FS_PRICING_AGENCY );
}
