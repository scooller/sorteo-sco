(function($){
    function showGeneralTab(){
        var type = $('#product-type').val();
        var isSco = type === 'sco_package';
        if(!isSco) return;
        $('a[href="#general_product_data"]').show();
        $('#general_product_data').show();
        $('#general_product_data .options_group').show();
        $('#general_product_data .form-field').show();
        $('.pricing').show();
        $('#_regular_price').closest('.form-field').show();
        $('#_sale_price').closest('.form-field').show();
        $('a[href="#inventory_product_data"]').show();
        $('#inventory_product_data').show();
    }
    function toggleScoPackageFields(){
        var type = $('#product-type').val();
        var isSco = type === 'sco_package';
        if(!isSco) return;
        // Mostrar TODOS los elementos con la clase show_if_sco_package
        $('.show_if_sco_package').each(function(){
            $(this).css('display', 'block').css('visibility', 'visible').show();
        });
        // Específicamente mostrar el checkbox de mostrar productos - buscar de múltiples formas
        $('#_sco_pkg_show_products').show().css('display', 'inline-block').css('visibility', 'visible');
        $('#_sco_pkg_show_products').closest('p').show().css('display', 'block').css('visibility', 'visible');
        $('#_sco_pkg_show_products').closest('.form-field').show().css('display', 'block').css('visibility', 'visible');
        $('#_sco_pkg_show_products').closest('.woocommerce_wp_checkbox').show().css('display', 'block').css('visibility', 'visible');
        $('label[for="_sco_pkg_show_products"]').show().css('display', 'block').css('visibility', 'visible');
        
        // Debug
        console.log('Checkbox existe:', $('#_sco_pkg_show_products').length > 0);
        console.log('Checkbox padre p:', $('#_sco_pkg_show_products').closest('p').length > 0);
        console.log('Elementos show_if_sco_package:', $('.show_if_sco_package').length);
        
        showGeneralTab();
        $('a[href="#shipping_product_data"]').hide();
        $('#shipping_product_data').hide();
        // Marcar como Virtual automáticamente
        $('#_virtual').prop('checked', true);
        var mode = $('#_sco_pkg_mode').val();
        $('.sco_pkg_random_only').each(function(){
            if(mode === 'random') $(this).css('display', 'block').css('visibility', 'visible').show();
            else $(this).css('display', 'none').hide();
        });
        $('.sco_pkg_manual_only').each(function(){
            if(mode === 'manual') $(this).css('display', 'block').css('visibility', 'visible').show();
            else $(this).css('display', 'none').hide();
        });
        if($('#_sco_pkg_categories').length && $.fn.select2){
            $('#_sco_pkg_categories').select2();
        }
    }
    $(document).on('change','#product-type',function(){setTimeout(toggleScoPackageFields,50);});
    $(document).on('change','#_sco_pkg_mode',function(){toggleScoPackageFields();});
    $('body').on('woocommerce-product-type-change',function(){setTimeout(toggleScoPackageFields,100);});
    $(document).ready(function(){
        setTimeout(toggleScoPackageFields,200);
        var recheck = setInterval(function(){
            var type = $('#product-type').val();
            if(type === 'sco_package'){
                showGeneralTab();
                $('.show_if_sco_package').show();
            }
        }, 500);
        setTimeout(function(){clearInterval(recheck);}, 3000);
        $('#post').on('submit',function(e){
            var type = $('#product-type').val();
            if(type === 'sco_package'){
                $('#product-type').prop('disabled',false);
                if($('input[name="product-type-backup"]').length === 0){
                    $('<input>').attr({type:'hidden',name:'product-type-backup',value:'sco_package'}).appendTo('#post');
                }
                console.log('Guardando producto tipo: sco_package');
            }
        });
    });
})(jQuery);
