<?php
// Mapeo de posiciones Bootstrap
$position_classes = array(
	'top' => 'top-0 start-50 translate-middle-x mt-3',
	'center' => 'top-50 start-50 translate-middle',
	'bottom' => 'bottom-0 start-50 translate-middle-x mb-3'
);
$position_class = isset($position_classes[$position]) ? $position_classes[$position] : $position_classes['top'];

// Mapeo de efectos CSS
$effect_classes = array(
	'shake' => 'animate__animated animate__shakeX',
	'bounce' => 'animate__animated animate__bounceIn',
	'fade' => 'animate__animated animate__fadeIn',
	'slide' => 'animate__animated animate__slideInDown',
	'none' => ''
);
$effect_class = isset($effect_classes[$effect]) ? $effect_classes[$effect] : '';
?>
<div class="position-fixed <?php echo esc_attr($position_class); ?> <?php echo esc_attr($effect_class); ?>" style="z-index: 9999; max-width: 90%; width: 600px;">
	<div class="alert alert-dismissible fade show shadow-lg m-0" role="alert" 
		style="background-color: <?php echo esc_attr($bg_color); ?>; 
			   color: <?php echo esc_attr($text_color); ?>; 
			   font-family: <?php echo esc_attr($font_family); ?>; 
			   border: none;
			   border-radius: 8px;
			   font-size: 1.1rem;
			   padding: 1.5rem;">
		<div class="d-flex align-items-center mb-2">
			<span style="font-size: 2rem; margin-right: 0.5rem;">🎉</span>
			<h4 class="alert-heading mb-0" style="color: <?php echo esc_attr($text_color); ?>;"><?php echo esc_html($titulo); ?></h4>
		</div>
		<div class="mb-0">
			<?php echo wp_kses_post($notice); ?>
		</div>
		<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="filter: brightness(0) invert(<?php echo ($text_color === '#ffffff' || $text_color === '#fff') ? '1' : '0'; ?>);"></button>
	</div>
</div>
