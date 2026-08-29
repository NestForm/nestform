<?php
/**
 * Dynamic render for nestform/form block.
 *
 * @package Nestform
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_id = isset( $attributes['formId'] ) ? (int) $attributes['formId'] : 0;
if ( $form_id <= 0 || ! class_exists( 'Nestform_Renderer' ) ) {
	if ( current_user_can( 'edit_posts' ) ) {
		echo '<p class="nestform-block nestform-block--empty">' . esc_html__( 'Select a Nestform in the block settings.', 'nestform' ) . '</p>';
	}
	return;
}

$wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'nestform-block',
	)
);

$html = Nestform_Renderer::render( $form_id );
if ( $html === '' ) {
	return;
}

echo '<div ' . $wrapper . '>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer returns escaped HTML.
