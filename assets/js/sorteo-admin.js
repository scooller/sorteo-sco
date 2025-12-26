/**
 * Sorteo SCO - Admin JavaScript
 * Funciones para la página de administración
 */

(function($) {
    'use strict';

    /**
     * Inicializar cuando el DOM esté listo
     */
    $(document).ready(function() {
        SorteoAdmin.init();
    });

    /**
     * Objeto principal del admin
     */
    var SorteoAdmin = {
        
        /**
         * Inicializar todas las funciones
         */
        init: function() {
            this.initTabs();
            this.initMediaUploader();
            this.initFormValidation();
            this.initEnhancedSelects();
            this.initOrderSearch();
        },

        /**
         * Inicializar sistema de tabs
         */
        initTabs: function() {
            // Manejar clics en tabs de WordPress
            $('.nav-tab-wrapper .nav-tab').on('click', function(e) {
                e.preventDefault();
                
                var targetTab = $(this).attr('href');
                
                // Remover clase active de todos los tabs y contenidos
                $('.nav-tab-wrapper .nav-tab').removeClass('nav-tab-active');
                $('.tab-content').removeClass('active');
                
                // Activar tab clickeado y su contenido
                $(this).addClass('nav-tab-active');
                $('#content-' + targetTab.substring(1)).addClass('active');
                
                // Guardar tab activo en localStorage
                localStorage.setItem('sorteo_active_tab', targetTab);
                
                // Actualizar visibilidad del botón de guardar
                SorteoAdmin.updateSaveButtonVisibility(targetTab);
            });

            // Restaurar tab activo desde localStorage
            var activeTab = localStorage.getItem('sorteo_active_tab');
            if (activeTab && $('#content-' + activeTab.substring(1)).length) {
                $('.nav-tab-wrapper .nav-tab[href="' + activeTab + '"]').click();
            } else {
                // Por defecto, mostrar/ocultar botón según el primer tab
                SorteoAdmin.updateSaveButtonVisibility('#configuracion');
            }
        },

        /**
         * Actualizar visibilidad del botón de guardar según el tab activo
         */
        updateSaveButtonVisibility: function(tabId) {
            var $saveSection = $('.sorteo-save-section');
            
            // Solo mostrar en tabs que tienen campos editables
            if (tabId === '#configuracion' || tabId === '#mensaje') {
                $saveSection.slideDown(200);
            } else {
                $saveSection.slideUp(200);
            }
        },

        /**
         * Inicializar selector de medios de WordPress
         */
        initMediaUploader: function() {
            var mediaUploader;

            $('#seleccionar_marco_visual').on('click', function(e) {
                e.preventDefault();

                // Si el uploader ya existe, ábrelo
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }

                // Crear nuevo uploader
                mediaUploader = wp.media.frames.file_frame = wp.media({
                    title: sorteo_admin_vars.media_title || 'Seleccionar Marco Visual',
                    button: {
                        text: sorteo_admin_vars.media_button || 'Seleccionar'
                    },
                    multiple: false,
                    library: {
                        type: ['image']
                    }
                });

                // Cuando se selecciona una imagen
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    
                    // Establecer la URL en el input
                    $('#sorteo_sco_marco_visual').val(attachment.url);
                    
                    // Mostrar preview
                    SorteoAdmin.updateMarcoPreview(attachment.url);
                });

                // Abrir el uploader
                mediaUploader.open();
            });

            // Botón para quitar imagen
            $('#quitar_marco_visual').on('click', function(e) {
                e.preventDefault();
                $('#sorteo_sco_marco_visual').val('');
                $('.marco-visual-preview').hide();
            });

            // Actualizar preview si ya hay una imagen
            var currentMarco = $('#sorteo_sco_marco_visual').val();
            if (currentMarco) {
                SorteoAdmin.updateMarcoPreview(currentMarco);
            }
        },

        /**
         * Actualizar preview del marco visual
         */
        updateMarcoPreview: function(url) {
            var $preview = $('.marco-visual-preview');
            if ($preview.length === 0) {
                $('#sorteo_sco_marco_visual').after('<img class="marco-visual-preview" />');
                $preview = $('.marco-visual-preview');
            }
            $preview.attr('src', url).show();
        },

        /**
         * Validación de formularios
         */
        initFormValidation: function() {
            // Validar fechas
            $('input[name="sorteo_sco_periodo_inicio"], input[name="sorteo_sco_periodo_fin"]').on('change', function() {
                var inicio = $('input[name="sorteo_sco_periodo_inicio"]').val();
                var fin = $('input[name="sorteo_sco_periodo_fin"]').val();
                
                if (inicio && fin && inicio > fin) {
                    alert('La fecha de inicio no puede ser posterior a la fecha de fin.');
                    $(this).val('');
                }
            });

            // Validar mínimo de ganancia
            $('input[name="sorteo_sco_min_ganancia"]').on('input', function() {
                var value = parseFloat($(this).val());
                if (value < 0) {
                    $(this).val(0);
                }
            });

            // Mejorar selects múltiples
            $('select[multiple]').on('focus', function() {
                $(this).addClass('focused');
            }).on('blur', function() {
                $(this).removeClass('focused');
            });
        },

        initEnhancedSelects: function() {
            var $selects = $('.wc-enhanced-select');
            if (!$selects.length) { return; }
            try {
                $selects.each(function() {
                    var $el = $(this);
                    var opts = {
                        width: 'style',
                        placeholder: $el.attr('data-placeholder') || '',
                        minimumResultsForSearch: 0,
                        closeOnSelect: false
                    };
                    if ($.fn.selectWoo) {
                        $el.selectWoo(opts);
                    } else if ($.fn.select2) {
                        $el.select2(opts);
                    }
                });
            } catch(e) {}
        },

        /**
         * Inicializar buscador de pedidos
         */
        initOrderSearch: function() {
            var $searchInput = $('#sorteo_order_search');
            var $select = $('#sorteo_order_select');
            
            if (!$searchInput.length || !$select.length) {
                return;
            }
            
            $searchInput.on('input', function() {
                var searchTerm = $(this).val().toLowerCase();
                
                $select.find('option').each(function() {
                    var $option = $(this);
                    
                    // Saltar la opción por defecto
                    if ($option.val() === '') {
                        return;
                    }
                    
                    var searchData = $option.data('search') || $option.text().toLowerCase();
                    
                    if (searchData.indexOf(searchTerm) !== -1 || searchTerm === '') {
                        $option.show();
                    } else {
                        $option.hide();
                    }
                });
                
                // Resetear selección si el valor actual está oculto
                var $selectedOption = $select.find('option:selected');
                if (!$selectedOption.is(':visible') && $selectedOption.val() !== '') {
                    $select.val('');
                }
            });
        },

        /**
         * Función para debugging
         */
        debug: function(message) {
            if (window.console && console.log) {
                console.log('[Sorteo SCO Admin] ' + message);
            }
        }
    };

    // Exponer el objeto globalmente
    window.SorteoAdmin = SorteoAdmin;

})(jQuery);
