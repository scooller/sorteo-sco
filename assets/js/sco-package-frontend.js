(function($){
  $(function(){
    /**
 * Sorteo Package Selector - Frontend
 * Maneja feedback visual después de agregar paquetes al carrito
 */
jQuery(function($) {
    'use strict';

    // Escuchar evento de WooCommerce cuando se agrega un producto al carrito
    $(document.body).on('added_to_cart', function(event, fragments, cart_hash, button) {
        // Verificar si el botón pertenece a un producto sco_package
        console.log('added_to_cart event detected', button);
        if (button && button.closest('.sco-pkg-qty-dropdown').length) {
            var $dropdown = button.closest('.sco-pkg-qty-dropdown');
            var $toggleBtn = $dropdown.find('.dropdown-toggle');
            
            // Guardar texto original si no existe
            if (!$toggleBtn.data('original-text')) {
                $toggleBtn.data('original-text', $toggleBtn.html());
            }
            
            // Mostrar texto de confirmación
            $toggleBtn.html('<i class="fa-solid fa-cart-plus"></i> Paquete(s)<br>agregado(s)');
            $toggleBtn.removeClass('btn-outline-success').addClass('btn-success');
            
            console.log('Package added to cart, updated button text.');
            // Restaurar después de 3 segundos
            setTimeout(function() {
                $toggleBtn.html($toggleBtn.data('original-text'));
                $toggleBtn.removeClass('btn-success').addClass('btn-outline-success');
            }, 3000);
        }
    });
});
    // Bootstrap cierra el dropdown automáticamente al hacer click.
  });
})(jQuery);
