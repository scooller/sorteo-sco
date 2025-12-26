<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Sorteo_SCO_Product_Frame {
	public static function add_frame_to_product( $product_id, $frame_url ) {
		// Agrega un marco visual (SVG, PNG, WEBP) al producto
		add_action('woocommerce_before_single_product_summary', function() use ($frame_url) {
			echo '<div class="sorteo-sco-frame"><img src="' . esc_url($frame_url) . '" alt="Marco especial" /></div>';
		}, 1);
		return true;
	}
}
