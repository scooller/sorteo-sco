# 📋 Changelog - Plugin Sorteo SCO

**Autor**: scooller
**Última actualización**: 2026-03-08

---

## [1.9.33] - 2026-03-07

### 🐛 Corrección

**Exportar Ventas con Desglose de Paquetes no reflejaba edición manual del pedido**:
- ✅ Fix: misma lógica de prioridad de datos (meta manual del ítem primero, `_sco_package` como respaldo).
- ✅ Resultado: el desglose en CSV de Extra WooCommerce muestra los productos actuales del pedido editado.
- ✅ Archivo: `includes/class-sorteo-wc-extra.php`.

### 🔧 Ajuste

**Detección de duplicados en Exportar Ventas**:
- ✅ Ajuste final: los duplicados se calculan de forma global en todo el CSV exportado.
- ✅ Separa origen para evitar falsos positivos: `Paquete: ...` y `Venta directa` se evalúan por separado.
- ✅ Archivo: `includes/class-sorteo-wc-extra.php`.

### ℹ️ Nota

- `Usuario+Compras CSV` se mantiene con la lógica original en esta versión.

---

## [1.9.32] - 2026-03-01

### 🐛 Corrección

**Mensaje de sorteo automático no renderizaba HTML**:
- ✅ Fix: El aviso inmediato de sorteo automático mostraba etiquetas HTML como texto plano.
- ✅ Causa: `esc_js()` escapaba caracteres `<`, `>` impidiendo que `innerHTML` renderizara el HTML.
- ✅ Solución: Reemplazado `esc_js(wp_kses_post($notice))` por `wp_json_encode()` que preserva las etiquetas HTML para JavaScript mientras mantiene la sanitización de `wp_kses_post()`.
- ✅ Archivo: `includes/class-sorteo-core.php` (línea ~1664).

---

## [1.9.31] - 2026-02-12

### 🐛 Corrección

**Paquete SCO (Nuevo) - Display de categoría**:
- ✅ Fix: Ahora muestra el nombre de la categoría en el selector de cantidad.
- ✅ Ejemplo: "Cantidad de stickers:" en vez de "Cantidad de productos:".
- ✅ Las opciones también muestran la categoría: "4 stickers", "10 stickers", etc.

### 🧹 Optimización

**Código limpio**:
- ✅ Eliminados logs de debug (19 líneas de error_log).
- ✅ Simplificada función `sco_package_new_save_product_meta` (de 76 a 35 líneas).
- ✅ Reutilizada función helper `sco_pkg_new_get_category_label()` en el frontend.

---

## [1.9.30] - 2026-02-12

### 🆕 Nueva Funcionalidad

**Nuevo tipo de producto: Paquete SCO (Nuevo)**:
- ✅ Tipo de producto `paquete_sco_new` para agregar X productos aleatorios de una categoría.
- ✅ Selector de cantidades en frontend (4, 8, 10, 20, 25, configurables por admin).
- ✅ Garantiza usuarios no duplicados: cada producto del paquete es único.
- ✅ Stock gestionado directamente por WooCommerce (no requiere transientes de reserva).
- ✅ Configuración simple: categoría fuente + cantidad de opciones disponibles.
- ✅ Campos personalizados en admin para cada paquete.

### 🐛 Corrección

**Paquete SCO (Nuevo) - Agregado al carrito**:
- ✅ Ahora agrega los productos individuales al carrito (no el paquete).
- ✅ Filtra productos sin stock o no comprables para evitar errores al agregar.
- ✅ Excluye el propio paquete si pertenece a la categoría configurada.

**Diferencias con `sco_package`**:
- Más simple: siempre selecciona al azar de UNA categoría (no modos manual/random separados).
- Sin composición manual: el usuario elige cantidad, el sistema genera los productos.
- Stock nativo: WooCommerce gestiona directamente, sin transientes ni reservas.
- Ideal para: sorteos simples, promociones, paquetes de cantidad variable.

---

## [1.9.29] - 2026-02-12

### 🆕 Nueva Funcionalidad

**Precios por cantidad (cart/checkout)**:
- ✅ Nuevo tab "Precios Cantidad" en Extra WooCommerce.
- ✅ Reglas por categoria con tramos de precio segun cantidad.
- ✅ Prioridad configurable si un producto esta en multiples categorias.

---

## [1.9.28] - 2026-02-05

### 🐛 Bugfix Crítico

**Fix de NetworkError en Store API para productos individuales (cart.js)**:

**Problema**: Al agregar productos individuales (no paquetes) al carrito, se producía un `TypeError: NetworkError when attempting to fetch resource` en `cart.js`. Los paquetes (`sco_package`) funcionaban correctamente.

**Causa raíz**: La función `sco_pkg_sync_reservations_with_cart()` llamaba a `WC()->session->get_session_id()` sin verificar que el método existiera. `WC_Session_Handler` de WooCommerce **NO** tiene este método. Cuando `get_customer_id()` retornaba vacío (posible en contexto Store API/REST), se producía un fatal error PHP que mataba la respuesta HTTP, causando el NetworkError en el `fetch()` del cliente.

**Por qué solo afectaba productos individuales**: Los `sco_package` son productos virtuales (`is_virtual()` retorna `true`), por lo que el control de stock del tema los omite completamente. Los productos individuales con gestión de stock sí pasan por las rutas de Store API que ejecutan `woocommerce_before_calculate_totals`, disparando `sco_pkg_sync_reservations_with_cart()`.

**Correcciones aplicadas** (`class-sorteo-package-simple.php`):

1. **`sco_pkg_sync_reservations_with_cart()`**: Reemplazada llamada directa a `get_session_id()` por cadena de fallback segura con `method_exists()` y `property_exists()`
2. **`sco_pkg_hpos_bypass_stock_check()`**: Agregada verificación de `WC()->cart` nulo antes de llamar `is_empty()`
3. **`sco_pkg_get_reserved_by_others()`**: Agregado guard para contextos REST API donde la sesión no está disponible
4. **`sco_pkg_adjust_stock_for_current_user()`**: Agregado guard para contextos REST API donde la sesión no está disponible
5. **`sco_package_validate_duplicate_in_cart()`**: Agregada verificación de `WC()->cart` nulo antes de acceder al carrito

**Impacto**:
- Eliminación del NetworkError al agregar productos individuales al carrito
- Mayor robustez en contextos Store API/REST donde la sesión WooCommerce puede no estar inicializada
- Sin cambios funcionales: los 4 niveles de protección contra duplicados de v1.9.27 se mantienen intactos

---

## [1.9.27] - 2026-02-05

### 🐛 Bugfix Crítico

**Prevención de productos duplicados entre pedidos (cross-order)**:

**Problema**: Productos (stickers) se asignaban a múltiples pedidos de distintos clientes. El sistema solo verificaba duplicados dentro del mismo carrito, pero no contra pedidos existentes ni carritos de otros usuarios.

**Causa raíz**: Entre que un usuario agrega un paquete al carrito y el stock se reduce (al pasar a `processing`/`completed`), el producto sigue como `instock` y otro usuario puede recibirlo en su paquete.

**Solución implementada - 4 capas de protección**:

1. **Nueva función `sco_pkg_get_committed_product_ids()`** (`class-sorteo-package-simple.php`):
   - Consulta SQL (compatible HPOS) que obtiene TODOS los productos ya comprometidos desde 3 fuentes:
     - Ventas directas: `_product_id` de order items en pedidos activos
     - Componentes de paquetes: `_sco_package` meta deserializado de pedidos activos
     - Carritos de otros usuarios: transient `bootstrap_theme_stock_reservations` (siempre activo, independiente de config)
   - Cache por request via variable estática con reset manual
   - Pedidos consultados: `pending`, `processing`, `on-hold`, `completed`

2. **Modificación de `sco_package_generate_composition()`**:
   - Modo manual y random ahora excluyen productos comprometidos via `isset($committed_ids[$pid])`
   - Nuevo contador `committed` en exclusiones de modo random
   - Log actualizado con `committed_skip`

3. **Modificación de `sco_package_generate_composition_excluding_products()`**:
   - Misma lógica de exclusión aplicada en ambos modos

4. **Nueva validación en `sco_pkg_checkout_validation()`**:
   - Red de seguridad final: al momento de pagar, verifica que los productos del carrito no estén comprometidos en otros pedidos
   - Bloquea checkout con mensaje claro si detecta conflicto
   - Indica al cliente que elimine el paquete y lo agregue de nuevo

**Impacto**:
- Eliminación de duplicados entre pedidos distintos
- Protección en todas las fases: carrito, generación de composición, y checkout
- Funciona independiente de la configuración de "Reserva de Stock"

---

## [1.9.26] - 2026-02-05

### ⚡ Optimización

**Mejoras en Sistema de Reservas de Stock para Paquetes**:
- ✅ **Fix en modo random**: Corregido uso de variable `$reserved_skipped` que no se actualizaba correctamente
  - Ahora usa `$excluded_counts['reserved']` que se incrementa durante la validación
  - Los logs ahora muestran correctamente cuántos productos fueron excluidos por estar reservados
- ✅ **Mejora en función `sco_pkg_is_reserved_by_others_blocking()`**: 
  - Ahora **siempre** verifica reservas de otros usuarios durante add-to-cart
  - Previene race conditions donde dos usuarios podrían agregar el mismo producto reservado
  - Lógica simplificada y más robusta para productos con/sin gestión de stock
- ✅ **Mayor eficiencia**: Los logs muestran información precisa de exclusiones
  - Ejemplo: `SCO RANDOM EXCLUSIONS - Reserved: 6` refleja exactamente 6 productos reservados
  - `SORTEO SCO: skipped=6` muestra el conteo correcto en el log final

**Impacto**:
- 🛡️ Mayor protección contra conflictos de stock en ventas concurrentes
- 📊 Mejor visibilidad del estado de reservas en los logs
- 🎯 Selección más precisa de productos disponibles para paquetes

---

## [1.9.25] - 2026-02-03

### 🐛 Bugfix

**Fix Error Handler en Regenerador de Duplicados**:
- ✅ Completado try-catch wrapper en función `ajax_fix_package_duplicates()`
- ✅ Ahora captura excepciones y muestra mensajes de error específicos
- ✅ Mejora en logging con `error_log()` para debugging
- ✅ Reemplaza error genérico "Error al procesar la solicitud" con descripciones detalladas
- 🔧 Añadido proper error handling: `} catch (Exception $e) { ... wp_send_json_error() }`

---

## [1.9.24] - 2026-02-03

### 🆕 Nueva Herramienta

**Regenerar Productos Duplicados en Paquetes**:
- 🔄 Nueva funcionalidad en pestaña "Extra WooCommerce" → "Exportar Ventas"
- 🔍 **Detección inteligente**: Busca paquetes con productos duplicados (mismo SKU aparece múltiples veces)
- 🔧 **Regeneración automática**: Reemplaza duplicados por productos diferentes
  - Para paquetes manuales: selecciona de la lista de productos del paquete
  - Para paquetes random: selecciona de las categorías configuradas
  - Garantiza que el reemplazo NO sea duplicado
- 📝 **Notas en pedido**: Agrega nota detallada con todos los cambios realizados
- 📊 **Log detallado**: Muestra tabla con:
  - Pedido modificado
  - Nombre del paquete
  - Cantidad de duplicados encontrados
  - Productos reemplazados con detalles (nombre y SKU antes/después)
- 🎯 **Filtrado**: Respeta filtros de fecha y estado de pedido
- ⚠️ **Seguridad**: Solo procesa pedidos en estado "Procesando" o "Completado"

**Funciones agregadas**:
- `ajax_fix_package_duplicates()`: Handler AJAX principal
- `find_replacement_product()`: Encuentra producto de reemplazo sin duplicar SKU

**Ejemplo de uso**:
```
Paquete original:
  - Sticker SR 10867 (SKU: sticker-sr-10867)
  - Sticker SR 10867 (SKU: sticker-sr-10867) ❌ DUPLICADO
  - Sticker SR 10679 (SKU: sticker-sr-10679)

Después de regenerar:
  - Sticker SR 10867 (SKU: sticker-sr-10867)
  - Sticker SR 11234 (SKU: sticker-sr-11234) ✅ NUEVO
  - Sticker SR 10679 (SKU: sticker-sr-10679)
```

---

## [1.9.23] - 2026-02-03

### 🐛 Bugfix Crítico

**Fix Duplicados en Reducción/Restauración de Stock**:
- 🔧 Corregida lógica de `sco_package_reduce_components_stock()` para prevenir reducciones duplicadas de stock
- 🔧 Corregida lógica de `sco_package_restore_components_stock()` para prevenir restauraciones duplicadas
- ✅ **Problema identificado**: Si un paquete contenía el mismo producto/SKU múltiples veces, el stock se reducía/restauraba por cada aparición individual
- ✅ **Solución implementada**: 
  - Los componentes se agrupan por SKU antes de operaciones de stock
  - Se suma la cantidad total de cada SKU único
  - Se reduce/restaura stock SOLO UNA VEZ por SKU con la cantidad total acumulada
- 📝 **Mejora en notas**: Las notas del pedido ahora incluyen el SKU en el formato `Producto (SKU: xxx, ID: nnn) xCantidad`
- 🎯 **Impacto**: Previene descuentos de stock incorrectos en paquetes con productos duplicados

**Ejemplo del fix**:
```
Antes: 
  Paquete con sticker-sr-10867 aparece 2 veces
  → reduce stock 2 veces = -2 unidades ❌

Ahora: 
  Paquete con sticker-sr-10867 aparece 2 veces
  → agrupa por SKU, suma cantidades (1+1=2)
  → reduce stock 1 vez con cantidad 2 = -2 unidades ✅
  (pero registra correctamente como UNA operación)
```

---

## [1.9.22] - 2026-02-03

### 🆕 Nuevas Características

**Exportar Ventas - Panel Extra WooCommerce**:
- 🆕 Nueva pestaña "Exportar Ventas" en "Extra WooCommerce" → "Stock y Ordenamiento"
- 📊 Exportación completa de ventas de paquetes SCO en formato CSV
- 🔍 **Desglose de Componentes**: Cada componente del paquete aparece como fila separada en la exportación
  - Ejemplo: Un paquete con 10 componentes = 10 filas en el CSV
  - Información por componente: ID producto, SKU, cantidad, precio, origen (dentro del paquete)
  - Útil para auditoría detallada de qué productos se vendieron dentro de cada paquete
- ⚠️ **Detección de Duplicados**: Marca con ⚠️ SÍ si un producto aparece múltiples veces en el mismo pedido
  - Control de calidad: identifica paquetes con productos repetidos
  - Ayuda a detectar errores en la composición o regeneración de paquetes
- 🔎 **Filtrado Avanzado**:
  - Rango de fechas customizable (default: últimos 30 días)
  - Filtro por estado de pedido: Pendiente, Procesando, Completado, Cancelado, Reembolsado
  - Exporta solo los pedidos que cumplen los criterios seleccionados
- 📋 **Columnas CSV**: Pedido, Fecha, Cliente, Email, Producto, ID, SKU, Cantidad, Precio, Origen, Duplicado
  - Formato diseñado para análisis en Excel o Google Sheets
- 🌐 **Formato Excel Compatible**: Incluye BOM UTF-8 para garantizar caracteres especiales (ñ, acentos) correctamente en Microsoft Excel

### 🔧 Detalles Técnicos

- 🆕 Función `render_export_sales_tab()` en `class-sorteo-wc-extra.php`
  - UI intuitiva con date pickers (jQuery UI Datepicker)
  - Checkboxes para selección de estado de pedidos
  - Botón para iniciar descarga AJAX
- 🆕 Función `ajax_export_sales()` en `class-sorteo-wc-extra.php`
  - Query de pedidos con filtros de fecha y estado
  - Iteración de componentes de paquetes para desglose
  - Detección de duplicados por producto dentro de cada orden
  - Generación de CSV con BOM UTF-8 y fputcsv
  - Headers: `Content-Type: text/csv; charset=utf-8-sig`, `Content-Disposition: attachment`
- 🔒 Seguridad: Verifica permisos `manage_woocommerce` en AJAX handler

### 📊 Casos de Uso

1. **Auditoría de Componentes**: Ver exactamente qué productos se vendieron dentro de cada paquete
2. **Detección de Duplicados**: Identificar paquetes con componentes repetidos para QA/testing
3. **Análisis de Ventas**: Reportes en Excel con desglose completo por componente
4. **Seguimiento de Inventario**: Exportar ventas para comparar con actualización de stock real

---

## [1.9.21] - 2026-02-02

### 🐛 Bugfixes

**Reservas entre carritos y duplicados en paquetes**:
- 🔧 Ajustada la validación de reservas para considerar productos reservados por otros usuarios incluso si no gestionan stock
- ✅ Previene que dos pedidos distintos incluyan el mismo producto cuando ya está reservado por otro carrito
- ✅ Mejora la consistencia en packs de 10 durante compras concurrentes

---

## [1.9.20] - 2026-01-04

### 🐛 Bugfixes Críticos

**Productos Duplicados en Paquetes**:
- 🔧 Corregida validación en `sco_package_validate_duplicate_in_cart()` para prevenir productos duplicados entre múltiples paquetes
- ✅ Ahora detecta y bloquea cuando se intenta agregar un paquete que contiene productos ya incluidos en otro paquete del carrito
- 🆕 **NUEVO - Regeneración Automática**: Para paquetes en modo sorpresa (random)
  - Nueva función `sco_package_generate_composition_excluding_products()` genera composición sin duplicados
  - El sistema automáticamente regenera el paquete en lugar de rechazarlo
  - Aviso informativo: "Se detectaron productos duplicados. Se sustituyeron automáticamente por [nuevos productos]"
  - Minimiza fricción del usuario: no tiene que hacer nada manualmente
  - **Autoregeneración en carrito**: Si paquetes ya añadidos comparten productos, se regeneran excluyendo los componentes existentes y se resincronizan las reservas de stock
- 🆕 **NUEVO - Aviso Solo para No Resueltos**: Para paquetes en modo manual (productos fijos)
  - Si no se puede regenerar (modo manual), muestra error claro
  - Aviso visual en carrito solo para duplicados que persisten

**Correos Duplicados por Paquete**:
- 🔧 Refactorizada función `send_package_component_downloads_email()` en `class-sorteo-email.php`
- ✅ Ahora procesa TODOS los paquetes del pedido en un solo email
- ✅ Eliminado loop que enviaba un email por cada paquete
- 🔧 Modificada lógica en `sorteo_sco_grant_package_downloads()`:
  - Verificación de envío a nivel de pedido (no por item)
  - Marca `_sco_pkg_downloads_email_sent` solo después del envío exitoso
  - Pasa `null` como parámetro para procesar todos los paquetes
- 📧 Subject del email ahora muestra número de pedido cuando hay múltiples paquetes
- 📊 Email incluye tabla consolidada con todas las descargas de todos los paquetes

**Notas de Pedido Duplicadas**:
- 🔧 Consolidadas notas de stock en `sco_package_reduce_components_stock()`
- 🔧 Consolidadas notas de restauración en `sco_package_restore_components_stock()`
- ✅ Ahora se agrega UNA SOLA nota por pedido para todos los paquetes
- 📋 Formato jerárquico con bullets:
  ```
  Stock descontado de componentes de 3 paquete(s):
  • Pack Promo 5:
    - Sticker SR 10706 (ID: 12311) x1
    - Sticker SR 10875 (ID: 12649) x1
  • Pack Promo 3:
    - Sticker SR 10981 (ID: 12861) x1
  ```
- ✅ Aplica tanto para descuento de stock como para restauración por cancelación/reembolso

### 🎯 Mejoras

- 🔄 Backward compatibility: función de email acepta parámetro opcional para procesar item específico o todos
- 📝 Notas de pedido más descriptivas: indican cuántos archivos y de cuántos paquetes
- 🛡️ Validación robusta: verifica existencia de productos antes de procesamiento
- 🎨 Formato de notas profesional y fácil de leer
- 🆕 Función `sco_package_generate_composition_excluding_products()` para regeneración inteligente
- 🆕 Función `sco_pkg_get_substituted_products()` para obtener nombres de productos sustitutos

**Flujo de Validación en 3 Niveles (Inteligente):**
1. **Add-to-cart** (`sco_package_validate_duplicate_in_cart`): Detecta duplicados y marca para regeneración
2. **Agregar al carrito** (`sco_package_add_cart_item_data`): Regenera automáticamente si es posible en modo random
3. **Carrito** (`sco_pkg_display_cart_duplicate_warning`): Muestra aviso solo para duplicados no resueltos
4. **Checkout** (`sco_pkg_checkout_validation`): Bloquea pago si persisten duplicados

**Impacto**:
- ✅ Experiencia de usuario mejorada: eliminación automática de conflictos en modo sorpresa
- ✅ Reducción de spam: un solo email por pedido en lugar de uno por paquete
- ✅ Mensajes informativos claros en lugar de errores
- ✅ Mayor confiabilidad en el proceso de compra
- ✅ Notas de pedido limpias: de 6+ notas a solo 1 nota consolidada por operación
- ✅ Prevención de errores de pago debidos a productos duplicados

---

## [1.9.18.1] - 2026-01-04 (Hotfix)

### 🐛 Bugfixes

**Monitor de Reservas - Correcciones Finales de Query SQL**:
- 🔧 Removida columna inexistente `reservation_id` de la query
- 🔧 Removida columna inexistente `rs.reserved_at` de la query
- 🔧 Se utiliza ahora `CONCAT(rs.order_id, '-', rs.product_id)` como identificador único
- 🔧 Fecha de reserva se obtiene ahora desde `$order->get_date_created()` 
- 🔧 Cantidad se obtiene del item del pedido (`$order->get_items()`)
- 🔧 Corregida función `ajax_release_reservation()` para usar `order_id` y `product_id` como claves
- 🔧 Mejor sanitización en parseo de identificador compuesto
- ✅ Tabla `wp_wc_reserved_stock` funciona sin errores: solo usa columnas existentes
- ✅ Compatible con HPOS y post orders tradicionales

**Ordenamiento de Productos - Implementación Completa con Destacados**:
- 🔧 Agregada función `sorteo_sco_apply_product_ordering()` con hook `woocommerce_get_catalog_ordering_args`
- 🔧 Agregada función `sorteo_sco_prioritize_featured_products()` con hook `the_posts`
- 🔧 **Estrategia de dos pasos**: Primera query ordena productos, segunda reorganiza destacados al inicio
- 🔧 Ordenamiento funciona en: categorías, etiquetas, páginas de archivo, shop
- 🔧 Soporta: Fecha, Nombre, Precio, Popularidad, Calificación, Aleatorio
- 🔧 **Productos destacados SIEMPRE aparecen primero** mediante `array_merge($featured, $regular)`
- 🔧 Verifica `_featured = 'yes'` en post_meta para cada producto
- 🔧 Respeta ordenamiento relativo dentro de destacados y dentro de normales
- ✅ Funciona correctamente con orden aleatorio (rand)
- ✅ No interfiere con otras queries de WordPress

**Restaurar Stock Huérfano - Nueva Herramienta de Mantenimiento**:
- ➕ Agregada nueva funcionalidad "Restaurar Stock a Productos Huérfanos" en tab "Stock y Orden"
- 🛠️ **Productos huérfanos**: Productos que según configuración deberían tener stock gestionado pero no lo tienen
- 🛠️ Nueva función `ajax_restore_orphan_stock()` con validación de nonce y permisos
- 🛠️ Escanea todos los productos publicados y verifica con `sorteo_sco_should_manage_stock()`
- 🛠️ Activa `set_manage_stock(true)` en productos huérfanos y establece stock en 0 si es null
- 📊 Retorna estadísticas: productos procesados, restaurados, y ya gestionados
- 🎨 UI incluye: botón con icono, diálogo de confirmación, spinner, y área de resultados
- ✅ Útil para sincronizar stock después de cambios en configuración de tipos de producto
- ✅ Validación completa de sintaxis PHP

**Impacto**: 
- Monitor de Reservas completamente funcional en producción
- Ordenamiento de productos ahora aplica correctamente con productos destacados al inicio
- Nueva herramienta de mantenimiento para gestión de stock huérfano

**Detalles técnicos**:
```php
// Orden de prioridad:
1. Productos destacados (_featured = 'yes') - Ascendente
2. Criterio configurado (date/title/price/etc) - Ascendente o Descendente
```

---

## [1.9.18] - 2026-01-04

### ✨ Nueva Funcionalidad: Gestión de Stock con HPOS + Reserva de Stock + Ordenamiento

#### Tab "Stock y Orden" - Gestión Integral de Stock y Ordenamiento

**Nueva interfaz consolidada**:
- ✅ **Sección Stock**: Checkbox para habilitar/deshabilitar gestión de stock por el plugin
- ✅ **Sección Stock**: Checkbox de reserva de stock (previene race conditions en ventas concurrentes)
- ✅ **Sección Stock**: Selección de tipos de producto a gestionar:
  - Tipos base: Simple, Variable, Agrupado, Externo/Afiliado, Paquete SCO
  - Filtros adicionales: Virtual, Descargable
- ✅ **Sección Stock**: Detección automática de estado HPOS de WooCommerce
- ✅ **Sección Stock**: Información visual de compatibilidad y estado actual
- ✅ **Sección Ordenamiento**: 6 opciones de ordenamiento:
  - Más Recientes (por fecha de creación)
  - Orden Aleatorio (ideal para sorteos)
  - Nombre (A-Z)
  - Precio (menor a mayor)
  - Popularidad (productos más vendidos)
  - Calificación (mejor puntuados)
- ✅ **Sección Ordenamiento**: Dirección configurable (Ascendente/Descendente)
- ✅ **Sección Ordenamiento**: Nota visual: "Los productos destacados siempre aparecen primero"

**Sistema de Reserva de Stock implementado**:
- 🔧 `sorteo_sco_reserve_stock_on_checkout()`: Reserva stock al crear pedido
- 🔧 `sorteo_sco_release_reserved_stock()`: Libera stock si pedido se cancela/falla
- 🔧 Usa `wc_reserve_stock_for_order()` nativo de WooCommerce (v3.5+)
- 🔧 Hook en `woocommerce_checkout_order_created` para reservar
- 🔧 Hooks en `woocommerce_order_status_cancelled` y `failed` para liberar

**Problema resuelto: Race Conditions**
```
Antes (SIN reserva):
- Usuario A agrega producto al carrito (stock: 1)
- Usuario B compra paquete con ese producto → Stock = 0
- Usuario A intenta pagar → ERROR: Sin stock disponible

Ahora (CON reserva):
- Usuario A hace checkout → Stock se RESERVA
- Usuario B intenta comprar → "Stock no disponible"
- Usuario A completa pago → Stock se DESCUENTA → ÉXITO ✓
- Si Usuario A cancela → Stock se LIBERA automáticamente
```

**Funciones implementadas**:
- `sorteo_sco_should_manage_stock($product)`: Verifica si un producto debe ser gestionado
- `sorteo_sco_manage_stock_on_order_complete()`: Gestiona stock al completar pedido
- `sorteo_sco_reserve_stock_on_checkout()`: Reserva stock al crear pedido
- `sorteo_sco_release_reserved_stock()`: Libera stock reservado
- Hooks en `woocommerce_order_status_processing` y `woocommerce_order_status_completed`

**Características técnicas**:
- 🔧 Compatible con HPOS (High-Performance Order Storage) y posts tradicional
- 🔧 Prevención de reducción doble de stock con meta `_sorteo_stock_reduced`
- 🔧 Prevención de reserva doble con meta `_stock_reserved`
- 🔧 Usa `wc_update_product_stock()` y `wc_reserve_stock_for_order()` nativos de WooCommerce
- 🔧 Agrega notas automáticas a pedidos con detalles de stock reducido
- 🔧 Respeta configuración individual de "Gestionar stock" de cada producto
- 🔧 Filtrado combinado: tipo base + filtros adicionales (AND lógico)
- 🔧 Tiempo de reserva configurable en WooCommerce (default: 60 minutos)
- 🔧 Productos destacados siempre aparecen primero (comportamiento nativo de WooCommerce)

**Opciones de configuración**:
- `sorteo_wc_enable_stock_management`: Habilita/deshabilita gestión (default: '0')
- `sorteo_wc_enable_stock_reservation`: Habilita/deshabilita reserva (default: '1')
- `sorteo_wc_stock_product_types`: Array de tipos permitidos (default: [])
- `sorteo_wc_product_order_by`: Método de ordenamiento (default: 'date')
- `sorteo_wc_product_order_dir`: Dirección de ordenamiento (default: 'DESC')

**Casos de uso**:
```php
// Ejemplo: Gestión integral de stock y ordenamiento
Gestión: ✓ Habilitada
Reserva: ✓ Habilitada (recomendado)
Tipos: ['simple', 'virtual', 'downloadable']
Ordenamiento: Orden Aleatorio
Dirección: Ascendente
Resultado: Gestiona, reserva y ordena aleatoriamente productos que cumplan condiciones
```

#### Tab "Monitor de Reservas" - Control de Stock en Tiempo Real (NUEVO)

**Nueva interfaz de monitoreo**:
- ✅ Tabla dinámica que muestra todas las reservas activas
- ✅ Información para cada reserva:
  - Producto (nombre e ID)
  - Pedido (con enlace directo a edición)
  - Cantidad reservada
  - Tiempo de reserva
  - Tiempo para expiración (con indicador de color)
- ✅ Botones de acción:
  - Liberar individual: Cada reserva tiene botón para liberarla
  - Liberar todas: Elimina todas las reservas de un click
- ✅ Actualización automática: Se carga al abrir el tab
- ✅ Botón Actualizar: Refresca la lista manualmente
- ✅ Indicadores visuales:
  - Verde: Tiempo normal (>10 minutos)
  - Naranja: Expirará pronto (<10 minutos)
  - Rojo: Ya expirada

**Funciones implementadas**:
- `render_reserved_stock_tab()`: Renderiza la interfaz del monitor
- `ajax_get_reserved_stock()`: AJAX para obtener lista de reservas
- `ajax_release_reservation()`: AJAX para liberar una o todas las reservas
- Consulta directa a tabla `wp_wc_reserved_stock` de WooCommerce

**Casos de uso**:
```
1. Monitorear: Ver qué productos tienen stock reservado
2. Liberar individual: Si una reserva está defectuosa, liberarla
3. Liberar todas: Si hay un problema masivo, restaurar todos los stocks
4. Diagnosticar: Identificar pedidos bloqueados por reservas expiradas
```

#### Archivos modificados
- `sorteo-sco.php`: Funciones de reserva y liberación de stock
- `includes/class-sorteo-wc-extra.php`: Tab "Stock y Orden" consolidado + Monitor + AJAX
- `README.md`: Documentación con Stock y Ordenamiento + Monitor
- `CHANGELOG.md`: Este registro

---

## [1.9.17.2] - 2026-01-03

### 🐛 Bugfixes Críticos (Segunda Fase)

#### Errores Corregidos

**1. Fatal Error: `wc_downloadable_file_permission()` parámetros incorrectos**
- **Archivo**: `sorteo-sco.php` línea 166
- **Problema**: Pasaba array a función que espera 3 parámetros separados
- **Efecto**: Fatal error bloqueando completamente procesamiento de descargas
- **Solución**: Cambiar a inserción directa en BD `woocommerce_downloadable_product_permissions`

**2. Notas del pedido NO se persistían**
- **Archivos**: 
  - `class-sorteo-package-simple.php` línea 698 (nota de stock)
  - `class-sorteo-email.php` línea 115 (nota de email)
- **Problema**: `add_order_note()` sin `$order->save()` posterior
- **Efecto**: Las notas se agregaban a memoria pero se perdían al guardar
- **Solución**: Agregar `$order->save()` después de cada `add_order_note()`

#### Verificación en Producción

Todos los logs confirman funcionamiento correcto:
```
✅ Composición del paquete: product_id=13062, got=3 productos
✅ Email detectado: get_downloadable_items() retornó items
✅ Stock procesado: 3 productos descargables identificados
✅ Notas agregadas: Se registran en detalles del pedido
```

---

## [1.9.17.1] - 2026-01-03

### 🐛 Bugfixes Críticos

#### Errores Corregidos

**1. Fatal Error: `get_data_changes()` no definido**
- **Archivo**: `includes/class-sorteo-email.php` línea 930
- **Problema**: Método no disponible en `Automattic\WooCommerce\Admin\Overrides\Order`
- **Efecto**: Fatal error al cancelar pedidos
- **Solución**: Remover verificación de cambios, llamar directamente a `save()`

**2. Lectura incorrecta de composición del paquete**
- **Archivo**: `sorteo-sco.php` línea 120
- **Problema**: Intento de leer `_sco_package_composition` que NO existe
- **Meta key correcto**: `_sco_package` (guardado en checkout)
- **Efecto**: Descargas de componentes y email NO se procesaban
- **Solución**: Cambiar a lectura de `_sco_package` directamente

**3. Email de componentes NO se enviaba**
- **Archivo**: `sorteo-sco.php` líneas 184-207
- **Problema**: Código dentro de `if ($processed_files > 0)`, nunca se ejecutaba
- **Efecto**: Email con descargas de componentes NUNCA se enviaba
- **Solución**: Mover envío de email FUERA del condicional

**4. Lógica de detección de email muy restrictiva**
- **Archivo**: `class-sorteo-email.php` línea 943
- **Problema**: Requería que paquete padre fuera descargable (archivos propios)
- **Efecto**: No se enviaba email si paquete padre no tenía archivos propios
- **Solución**: Simplificar a solo requerir que sea virtual

---

## [1.9.17] - 2025-01-08

### ✨ Nueva Página: Extra WooCommerce

Agregada nueva sección de administración con herramientas avanzadas para gestión de productos WooCommerce.

#### 🎯 Funcionalidades Implementadas

**1. Actualización Masiva de Precios**
- ✅ **Selección por categoría**: Filtra productos de categoría objetivo
- ✅ **Exclusión inteligente**: Productos en categorías excluidas NO se actualizan (útil para productos multicategoría)
- ✅ **Tres tipos de actualización**:
  - **Porcentaje (%)**: Aumentar/reducir por % (ej: +10%, -15%)
  - **Cantidad fija ($)**: Sumar/restar monto exacto (ej: +50, -20)
  - **Precio exacto**: Establecer precio específico (ej: 99.99)
- ✅ **Flexibilidad**: Aplicar a precio regular, oferta, o ambos
- ✅ **Modo prueba (dry run)**: Simula sin aplicar cambios
- ✅ **Vista previa detallada**: Tabla con ID, nombre, precio anterior/nuevo
- ✅ **Procesamiento por lotes (optimizado)**:
  - Procesa 50 productos por solicitud AJAX
  - Evita timeouts con miles de productos
  - Barra de progreso en tiempo real (ej: 150/2500)
  - Porcentaje de avance visible
- ✅ **AJAX**: Procesamiento sin recargar página

**Ejemplo de uso**:
```
Escenario: Aumentar 15% electrónicos excepto ofertas
Categoría objetivo: Electrónicos
Excluir categorías: Ofertas, Liquidación
Tipo: Porcentaje
Valor: 15
Aplicar a: Precio regular

Resultado: Solo productos en Electrónicos que NO estén 
          en Ofertas/Liquidación aumentan 15%
```

**2. Métricas de Paquetes (sco_package)**
- ✅ **Dashboard de KPIs**:
  - 🎁 Total paquetes vendidos
  - 📦 Total productos descontados de stock
  - 📧 Total emails de componentes enviados
  - 💰 Ingresos totales generados
- ✅ **Tabla de últimos 50 pedidos** con:
  - Número de pedido (enlace directo)
  - Nombre del paquete
  - Cantidad vendida
  - Número de componentes
  - Estado de stock reducido (✓/✗)
  - Estado de email enviado (✓/✗)
  - Fecha de compra
- ✅ **Carga dinámica con AJAX**
- ✅ **Diseño con cards visuales** (colores diferenciados por tipo)

#### 🔧 Implementación Técnica

**Nuevo archivo**: `includes/class-sorteo-wc-extra.php`

**Clase**: `Sorteo_WC_Extra`

**Métodos principales**:
```php
add_submenu_page()           // Registro de página
render_price_updater_tab()   // UI actualización precios
render_package_metrics_tab() // UI métricas paquetes
ajax_update_prices()         // Handler AJAX precios
ajax_get_package_metrics()   // Handler AJAX métricas
calculate_new_price()        // Cálculo de precios
```

**Hooks utilizados**:
- `admin_menu` - Registro de submenú
- `admin_init` - Registro de settings
- `wp_ajax_sorteo_update_prices` - Contar productos (paso 1)
- `wp_ajax_sorteo_update_prices_batch` - Procesar por lotes (paso 2+)
- `wp_ajax_sorteo_get_package_metrics` - AJAX métricas

#### ⚡ Optimización: Procesamiento por Lotes

**Problema resuelto**: Actualizar precios de miles de productos causaba timeout

**Solución implementada**:
1. **Conteo inicial**: AJAX #1 obtiene cantidad total de productos
2. **Procesamiento iterativo**: AJAX #2+ procesa 50 productos por solicitud
3. **Barra de progreso visual**: Se actualiza en tiempo real (ej: 150/2500)
4. **Evita timeouts**: Cada solicitud tarda segundos en lugar de minutos

**Flujo técnico**:
```
1. Usuario envía formulario
   ↓
2. AJAX #1 → ajax_update_prices (step='count') 
   → Filtra por categoría/exclusiones
   → Retorna totalProducts = 2500
   ↓
3. AJAX #2+ → ajax_update_prices_batch (batch 0-49, 50-99, 100-149, etc)
   → Procesa 50 productos
   → Retorna array de productos actualizados
   → Actualiza: processed += 50, progreso = 100/2500
   ↓
4. Si processed < totalProducts, repite AJAX #2+
   ↓
5. Completado: Muestra tabla con 2500 productos procesados
```

**Ventajas**:
- ✅ Sin timeouts incluso con 10,000+ productos
- ✅ Feedback visual en tiempo real
- ✅ Usuario ve progreso constante
- ✅ Fácil de pausar/reanudar si fuera necesario

**Queries SQL**:
```sql
-- Obtener pedidos con paquetes
SELECT p.ID, p.post_date
FROM wp_posts p
INNER JOIN wp_woocommerce_order_items oi ON p.ID = oi.order_id
INNER JOIN wp_woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
WHERE p.post_type = 'shop_order'
AND oim.meta_key = '_sco_package'
AND p.post_status IN ('wc-processing', 'wc-completed')
```

#### 🎨 Interfaz de Usuario

**Navegación por tabs**:
```
Extra WooCommerce
├─ Actualizar Precios
└─ Métricas Paquetes
```

**Características UI**:
- ✅ Tabs nativos de WordPress
- ✅ Formularios con validación HTML5
- ✅ Spinners de carga
- ✅ Notices de éxito/error
- ✅ Tablas WP List Table estándar
- ✅ Enlaces a edición de productos/pedidos

#### 🔒 Seguridad

- ✅ **Capability check**: `manage_options` en todas las funciones
- ✅ **Nonce verification**: AJAX con verificación de nonce
- ✅ **Sanitización**: `sanitize_text_field()`, `intval()`, `floatval()`
- ✅ **Escapado de salida**: `esc_html()`, `esc_attr()`, `esc_url()`
- ✅ **Prepared statements**: Queries SQL seguras

#### 📁 Archivos Modificados

| Archivo | Cambios |
|---------|---------|
| `sorteo-sco.php` | Versión 1.9.17, include de `class-sorteo-wc-extra.php` |
| `includes/class-sorteo-wc-extra.php` | **NUEVO** - Clase completa con 600+ líneas |
| `README.md` | Documentación v1.9.17 |
| `CHANGELOG.md` | Este archivo |

#### 💡 Mejores Prácticas

**Para actualización de precios**:
1. Usar **modo prueba** primero para verificar resultados
2. Excluir categorías de ofertas/liquidación si aplica
3. Revisar vista previa antes de aplicar cambios
4. Backup de base de datos recomendado antes de cambios masivos

**Para métricas de paquetes**:
- Dashboard se actualiza en tiempo real
- Límite de 50 pedidos más recientes
- Filtrado automático por estados `processing` y `completed`

---

## [1.9.16] - 2025-01-08

### ✨ Nuevo: Sistema de Emails Personalizados para Paquetes Descargables

Implementado sistema que separa emails de descargas cuando un paquete (`sco_package`) es virtual+descargable CON archivo propio.

#### Funcionalidad
**Dos emails automáticos**:
1. **Email WooCommerce**: Archivo del paquete principal
2. **Email Sorteo**: Archivos de productos componentes (de categorías fuente + productos manuales)

#### Condiciones de Activación
- ✅ Producto tipo `sco_package`
- ✅ Virtual (`is_virtual()`)
- ✅ Descargable (`is_downloadable()`)
- ✅ Con archivo propio (`get_downloads() > 0`)

#### Nuevas Funciones
```php
sorteo_sco_package_needs_custom_downloads_email($product)
sorteo_sco_get_package_component_downloads($order, $item)
Sorteo_SCO_Email::send_package_component_downloads_email($order_id, $item)
Sorteo_SCO_Email::render_component_downloads_email_html($order, $downloads, $name)
```

#### Características
- ✅ **Prevención de duplicados**: Meta key `_sco_pkg_components_email_sent_{item_id}`
- ✅ **Filtrado inteligente**: Email principal excluye paquetes con email personalizado
- ✅ **Cleanup automático**: Limpieza de metas en cancelaciones
- ✅ **Trazabilidad**: Notas detalladas en pedidos con lista de productos
- ✅ **Template responsive**: HTML compatible con clientes de email
- ✅ **Reutilización**: Usa `get_email_colors()` y `get_header_image()` existentes

#### Notas en Pedido
```
Stock descontado de componentes del paquete "Nombre" (item #123):
- Producto A (ID: 45) x2
- Producto B (ID: 67) x1

Email de descargas de componentes enviado para paquete "Nombre" (item #123) con 3 archivo(s):
- Producto A - ebook.pdf
- Producto B - video.mp4
- Producto C - audio.mp3
```

#### Seguridad
- ✅ Sanitización completa (`esc_html`, `esc_attr`, `esc_url`)
- ✅ Prepared statements en queries SQL
- ✅ Validación de tipos con `wc_get_order()`, `wc_get_product()`
- ✅ URLs de descarga con `order_key` y `email` para validación WooCommerce

### 🛡️ Mejoras de Seguridad en Paquetes

#### Prevención de Loops Recursivos
- ✅ **Exclusión de paquetes en modo aleatorio**: Los productos tipo `sco_package` NO aparecen como componentes de otros paquetes
- ✅ **Validación de tipo**: Query verifica `$p->get_type() === 'sco_package'` y los excluye automáticamente
- ✅ **Código mejorado** (línea 847 en `class-sorteo-package-simple.php`):
```php
if ($p->get_type() === 'sco_package') {
    continue; // Excluir paquetes de selección aleatoria
}
```

#### Notas Automáticas en Pedido
- ✅ **Stock descontado**: Lista detallada de productos con cantidades
- ✅ **Emails enviados**: Productos incluidos en cada email
- ✅ **Formato estructurado**: Fácil de leer en panel de pedidos

### 📁 Archivos Modificados

| Archivo | Cambios |
|---------|---------|
| `includes/class-sorteo-email.php` | +3 funciones globales, +2 métodos estáticos, filtrado en `send_order_downloads_email()` |
| `includes/class-sorteo-package-simple.php` | +Notas de stock, prevención de loops recursivos |
| `sorteo-sco.php` | Versión 1.9.16, disparador de email personalizado |
| `README.md` | Documentación v1.9.16 |
| `CHANGELOG.md` | Este archivo |

---

## [1.9.15] - 2025-12-26

### 🐛 Fixes Críticos en Paquetes

#### Eliminación de Duplicados
- ✅ `array_unique()` ANTES de validación en modos Manual y Aleatorio
- ✅ Verificación final después de `shuffle()` con doble chequeo
- ✅ Logging automático si se detectan duplicados

#### Validación Robusta
- ✅ Verificar cantidad suficiente ANTES de `array_slice()`
- ✅ Mensajes descriptivos con categorías y cantidades
- ✅ Logging mejorado con contexto completo

#### Pool Ampliado
- ✅ `posts_per_page` aumentado a 500 para mejor selección aleatoria
- ✅ Garantiza variedad en paquetes sorpresa grandes

### 🎨 Compatibilidad Multi-Tema
- ✅ `Sorteo_Theme_Compat::is_bootstrap_theme_active()`
- ✅ AJAX con fragmentos automáticos de WooCommerce
- ✅ Feedback visual (botón verde temporal)
- ✅ Selector de cantidad funcional en single product

---

## [1.9.14] - 2025-12-08

### 📝 Notas en Retornos Tempranos
- ✅ Email desactivado
- ✅ Estado no configurado
- ✅ Pedido sin paquetes
- ✅ Reintento programado con fecha/hora

### 🎯 Admin: SelectWoo/Select2
- ✅ Categorías, Productos especiales, Estados de pedido
- ✅ Búsqueda integrada con eliminación rápida
- ✅ Fallback si WooCommerce no registró assets

---

## [1.9.13] - 2025-12-04

### 📋 Trazabilidad de Emails
- ✅ Notas con destinatario y cantidad de enlaces
- ✅ Errores con sugerencias de configuración
- ✅ Reenvío manual registrado con usuario actor

---

## [1.9.12] - 2025-12-04

### 🐛 Fix Duplicación en Carrito
- ✅ Eliminado disparo manual de `click.ajax_add_to_cart`
- ✅ Delegado a WooCommerce con `data-quantity`

---

## [1.9.11] - 2025-11-20

### 🔄 Reenvío Manual de Emails
- ✅ Endpoint + acción rápida en pedidos
- ✅ Limpieza de metas en refunded/failed/cancelled
- ✅ Logging mínimo (solo errores críticos)

---

## [1.9.10] - 2025-11-20

### ⚡ Performance: Race Condition Fix
- ✅ Espera permisos antes de enviar email
- ✅ Dedupe por `product_id|download_id`
- ✅ Reintentos programados

---

## [1.9.9] - 2025-11-10

### 🎨 Sistema de Compatibilidad de Tema
- ✅ `Sorteo_Theme_Compat` class
- ✅ Dropdown adaptativo
- ✅ Fallback CSS standalone

---

## [1.9.8] - 2025-11-06

### 📧 Email de Descargas para Paquetes
- ✅ Permisos automáticos para componentes descargables
- ✅ Fallback a DB si `get_downloadable_items()` vacío
- ✅ Compatible HPOS

---

## [1.9.6] - 2025-11-05

### 💚 UX: Feedback Visual
- ✅ Botón verde temporal post-add-to-cart
- ✅ Opción mostrar/ocultar mensaje de reemplazos

---

## [1.9.5] - 2025-11-04

### 📊 Métricas con Chart.js
- ✅ Gráficos de línea y circulares
- ✅ Rangos rápidos (7d/30d/90d) + personalizado
- ✅ Otorgar premio manual

---

## [1.9.4] - 2025-10-28

### 🛒 Dropdown de Cantidad
- ✅ Selector 1-10 con ícono +
- ✅ Add to cart vía AJAX

---

## [1.7.0] - 2025-01-10

### 🎁 Producto Tipo Paquete
- ✅ Modo Manual (productos fijos)
- ✅ Modo Sorpresa (aleatorio por categorías)
- ✅ Reducción automática de stock componentes
- ✅ Metadatos en pedidos

---

## [1.6.5] - 2024-12-15

### 📥 CSV Perfecto
- ✅ Cero filas vacías (validación rigurosa)
- ✅ Buffer limpio con UTF-8 BOM

---

## [1.6.0] - 2024-12-01

### 🎲 Sistema de Sorteos Inteligente
- ✅ Sorteo inmediato vs por umbral
- ✅ Métricas básicas y logging

---

## Versionado

Este proyecto usa [Versionado Semántico](https://semver.org/):
- **MAJOR**: Cambios incompatibles con versiones anteriores
- **MINOR**: Nuevas funcionalidades compatibles
- **PATCH**: Correcciones de bugs compatibles

---

## Soporte

Para reportar bugs o solicitar features:
1. Verifica que usas la última versión
2. Incluye logs relevantes (`wp-content/debug.log`)
3. Describe pasos para reproducir el problema
4. Indica versión de WordPress y WooCommerce

---

**Desarrollado por scooller** | [Bio](https://scooller.bio)

---

## 🎯 Objetivo

Implementar un sistema de emails personalizados para paquetes descargables que envía **dos emails separados** cuando un paquete es virtual+descargable Y tiene su propio archivo descargable:

1. **Email estándar de WooCommerce**: Contiene el archivo del paquete principal
2. **Email personalizado automático**: Contiene las descargas de los productos componentes (de categorías fuente y productos por paquete)

---

## ✨ Características Implementadas

### 1. **Detección Inteligente de Paquetes**

**Función**: `sorteo_sco_package_needs_custom_downloads_email()`  
**Ubicación**: `includes/class-sorteo-email.php` (después del hook de cancelación)

**Condiciones para activar email personalizado**:
- ✅ Producto es de tipo `sco_package`
- ✅ Es virtual (`$product->is_virtual()`)
- ✅ Es descargable (`$product->is_downloadable()`)
- ✅ Tiene al menos un archivo descargable propio (`count($product->get_downloads()) > 0`)

**Ejemplo de uso**:
```php
if (sorteo_sco_package_needs_custom_downloads_email($product)) {
    // Enviar email personalizado
}
```

---

### 2. **Extracción de Descargas de Componentes**

**Función**: `sorteo_sco_get_package_component_downloads($order, $item)`  
**Ubicación**: `includes/class-sorteo-email.php`

**Características**:
- ✅ Extrae SOLO descargas de productos componentes (NO del paquete padre)
- ✅ Lee composición desde `_sco_package` meta del item
- ✅ Consulta tabla `woocommerce_downloadable_product_permissions` por cada componente
- ✅ Dedupe por `product_id|download_id` para evitar duplicados
- ✅ Genera URLs de descarga con `order_key` y `user_email`
- ✅ Retorna array con `download_url`, `download_name`, `product_name`

**Query SQL**:
```sql
SELECT product_id, download_id, order_key, user_email 
FROM wp_woocommerce_downloadable_product_permissions 
WHERE order_id = %d AND product_id = %d
```

---

### 3. **Envío de Email Personalizado**

**Método**: `Sorteo_SCO_Email::send_package_component_downloads_email($order_id, $package_item)`  
**Ubicación**: `includes/class-sorteo-email.php`

**Flujo de ejecución**:
1. ✅ Verifica si ya se envió usando meta `_sco_pkg_components_email_sent_{item_id}`
2. ✅ Obtiene descargas de componentes usando función global
3. ✅ Early return si no hay descargas (con nota en pedido)
4. ✅ Genera subject personalizado: `[Sitio] Descargas adicionales de tu paquete: Nombre Paquete`
5. ✅ Renderiza HTML usando template adaptado
6. ✅ Configura headers con `From` personalizado
7. ✅ Envía email con `wp_mail()`
8. ✅ Actualiza meta para evitar reenvíos
9. ✅ Agrega nota en pedido con resultado

**Meta key**: `_sco_pkg_components_email_sent_{item_id}` = `'yes'`

---

### 4. **Template HTML del Email de Componentes**

**Método**: `Sorteo_SCO_Email::render_component_downloads_email_html($order, $downloads, $package_name)`  
**Ubicación**: `includes/class-sorteo-email.php`

**Características del template**:
- ✅ Reutiliza helpers existentes: `get_email_colors()`, `get_header_image()`
- ✅ DOCTYPE compatible con clientes de email
- ✅ Estructura HTML responsive con tablas
- ✅ Header con colores personalizables de WooCommerce
- ✅ Tabla de descargas con 3 columnas: Producto | Archivo | Descarga
- ✅ Botones de descarga con iconos (⬇)
- ✅ Nota aclaratoria diferenciando de email principal
- ✅ Personalización con nombre de usuario y número de pedido

**Estructura**:
```
┌─────────────────────────────────┐
│   Logo/Header Image (opcional)  │
├─────────────────────────────────┤
│  Descargas de tu paquete        │ ← Header con color base
├─────────────────────────────────┤
│  Hola {Usuario},                │
│                                 │
│  Aquí están los archivos de los │
│  productos incluidos en tu      │
│  paquete "{Nombre}" (pedido #X) │
│                                 │
│  ┌───────────────────────────┐  │
│  │ Producto │ Archivo │ Desc │  │
│  ├──────────┼─────────┼──────┤  │
│  │ Prod 1   │ File 1  │  ⬇   │  │
│  │ Prod 2   │ File 2  │  ⬇   │  │
│  └───────────────────────────┘  │
│                                 │
│  Nota: Este email contiene las  │
│  descargas de los productos     │
│  dentro del paquete. El archivo │
│  del paquete principal se envía │
│  en el email estándar de        │
│  WooCommerce.                   │
└─────────────────────────────────┘
```

---

### 5. **Modificación del Email Principal**

**Método**: `Sorteo_SCO_Email::send_order_downloads_email($order_id)`  
**Ubicación**: `includes/class-sorteo-email.php`

**Cambios implementados**:

#### Filtrado de descargas
```php
// Filtrar paquetes que tienen email personalizado
$filtered_downloads = array();
foreach ($downloads as $d) {
    $product = wc_get_product($d['product_id'] ?? 0);
    // Solo incluir si NO es paquete con email personalizado
    if (!$product || !sorteo_sco_package_needs_custom_downloads_email($product)) {
        $filtered_downloads[] = $d;
    }
}
```

#### Early return sin error
```php
if (empty($filtered_downloads)) {
    delete_transient($lock_key);
    $order->add_order_note('Email descargas: Todas las descargas se envían por emails personalizados.');
    return true; // No es error, solo que todo va por email personalizado
}
```

#### Uso de descargas filtradas
- ✅ `render_email_html($order, $filtered_downloads)` en vez de `$downloads`
- ✅ Nota en pedido con `count($filtered_downloads)` en vez de `count($downloads)`

---

### 6. **Disparador en Grant Permissions**

**Función**: `sorteo_sco_grant_package_downloads($order_id, $order)`  
**Ubicación**: `sorteo-sco.php`

**Código agregado** (después de `$order->save()`):
```php
// NUEVO: Enviar email personalizado si algún paquete es virtual+descargable con archivo propio
if (class_exists('Sorteo_SCO_Email') && function_exists('sorteo_sco_package_needs_custom_downloads_email')) {
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        if ($product && $product->get_type() === 'sco_package') {
            if (sorteo_sco_package_needs_custom_downloads_email($product)) {
                Sorteo_SCO_Email::send_package_component_downloads_email($order_id, $item);
            }
        }
    }
}
```

**Orden de ejecución**:
1. ✅ Otorgar permisos de descarga para componentes
2. ✅ Guardar meta `_sco_pkg_downloads_granted`
3. ✅ **NUEVO**: Enviar email(s) personalizado(s) para paquetes con archivo
4. ✅ Enviar email principal de descargas (filtrado)

---

### 7. **Cleanup en Cancelaciones**

**Hook**: `woocommerce_order_status_cancelled`  
**Ubicación**: `includes/class-sorteo-email.php`

**Código agregado**:
```php
// Limpiar también metas de email de componentes
foreach ($order->get_items() as $item_id => $item) {
    $sent_key = '_sco_pkg_components_email_sent_' . $item_id;
    if ($order->get_meta($sent_key)) {
        $order->delete_meta_data($sent_key);
    }
}
if ($order->get_data_changes()) {
    $order->save();
}
```

**Propósito**: Permitir reenvío de emails si el pedido se reactiva después de cancelación.

---

## 📁 Archivos Modificados

### 1. `includes/class-sorteo-email.php`
**Líneas afectadas**: ~900-1000 (archivo ahora tiene ~1020 líneas)

**Cambios**:
- ✅ Agregadas 3 funciones globales antes de la clase
- ✅ Agregados 2 métodos estáticos a la clase
- ✅ Modificado método `send_order_downloads_email()` (filtrado)
- ✅ Modificado hook de cancelación (cleanup de metas)

**Nuevas funciones**:
1. `sorteo_sco_package_needs_custom_downloads_email($product)` - Helper de detección
2. `sorteo_sco_get_package_component_downloads($order, $item)` - Extractor de descargas
3. `Sorteo_SCO_Email::send_package_component_downloads_email($order_id, $item)` - Envío de email
4. `Sorteo_SCO_Email::render_component_downloads_email_html($order, $downloads, $name)` - Template HTML

---

### 2. `sorteo-sco.php`
**Líneas afectadas**: 8, 27, 185-200

**Cambios**:
- ✅ **Línea 8**: Versión actualizada de `1.9.15` a `1.9.16`
- ✅ **Línea 27**: Constante `SORTEO_SCO_VERSION` actualizada a `'1.9.16'`
- ✅ **Líneas 185-200**: Agregado disparador de email personalizado en `sorteo_sco_grant_package_downloads()`

---

### 3. `README.md`
**Líneas afectadas**: 1, 16-45

**Cambios**:
- ✅ **Línea 1**: Título actualizado a `v1.9.16`
- ✅ **Líneas 16-45**: Agregada sección de changelog para v1.9.16 con documentación completa de la feature

---

### 4. `CHANGELOG-v1.9.16.md` (NUEVO)
**Archivo creado**: Documentación completa de la versión

---

## 🔄 Flujo Completo de Ejecución

### Escenario: Pedido completado con paquete virtual+descargable con archivo

```
1. WooCommerce: order_status → processing/completed
   ↓
2. Hook (priority 5): sorteo_sco_grant_package_downloads()
   ↓
3. Crear permisos de descarga para componentes
   ↓
4. Guardar meta: _sco_pkg_downloads_granted = 'yes'
   ↓
5. ¿Paquete es virtual+descargable+con archivo?
   ├─ SÍ → Enviar email personalizado de componentes
   │        └─ Guardar meta: _sco_pkg_components_email_sent_{item_id} = 'yes'
   │        └─ Nota: "Email de descargas de componentes enviado..."
   └─ NO → Continuar
   ↓
6. Enviar email principal de descargas
   ↓
7. Filtrar paquetes con email personalizado
   ↓
8. ¿Hay descargas filtradas?
   ├─ SÍ → Enviar email con descargas restantes
   │        └─ Guardar meta: _sco_pkg_downloads_email_sent = 'yes'
   │        └─ Nota: "Email de descargas enviado con X archivo(s)."
   └─ NO → Early return
           └─ Nota: "Todas las descargas se envían por emails personalizados."
   ↓
9. FIN ✅
```

---

## 📧 Ejemplo de Ejecución

### Configuración del Paquete
```
Nombre: "Pack Premium"
Tipo: sco_package
Virtual: ✅ Sí
Descargable: ✅ Sí
Archivo del paquete: "manual-premium.pdf"

Componentes:
├─ Producto A (de Categoría Fuente)
│  └─ Archivo: "ebook-a.pdf"
├─ Producto B (de Categoría Fuente)
│  └─ Archivo: "video-b.mp4"
└─ Producto C (de Productos por Paquete)
   └─ Archivo: "audio-c.mp3"
```

### Emails Enviados

#### Email 1: WooCommerce Estándar (Archivo del Paquete)
```
Asunto: [Sitio] Tus descargas del pedido #1234
Contenido:
- manual-premium.pdf [Descargar ⬇]
```

#### Email 2: Personalizado Sorteo (Archivos de Componentes)
```
Asunto: [Sitio] Descargas adicionales de tu paquete: Pack Premium
Contenido:
┌───────────────────────────────────────┐
│ Producto   │ Archivo       │ Descarga │
├────────────┼───────────────┼──────────┤
│ Producto A │ ebook-a.pdf   │    ⬇     │
│ Producto B │ video-b.mp4   │    ⬇     │
│ Producto C │ audio-c.mp3   │    ⬇     │
└───────────────────────────────────────┘

Nota: Este email contiene las descargas de los
productos dentro de tu paquete. El archivo del
paquete principal se envía en el email estándar
de WooCommerce.
```

### Notas en Pedido
```
✅ Permisos de descarga otorgados: 3 archivo(s) para paquete "Pack Premium" x1.
✅ Email de descargas de componentes enviado para paquete "Pack Premium" (item #45) con 3 archivo(s).
✅ Email de descargas del pedido #1234 (ID 1234) enviado a cliente@example.com con 1 enlace(s).
```

### Meta Keys Creados
```
_sco_pkg_downloads_granted = 'yes'
_sco_pkg_components_email_sent_45 = 'yes'
_sco_pkg_downloads_email_sent = 'yes'
```

---

## 🧪 Casos de Prueba

### ✅ Caso 1: Paquete Virtual+Descargable CON Archivo
**Entrada**:
- Paquete: Virtual ✅, Descargable ✅, Tiene archivo ✅
- Componentes: 3 productos descargables

**Resultado esperado**:
- ✅ 2 emails enviados (WooCommerce + Personalizado)
- ✅ Email WooCommerce: 1 archivo (del paquete)
- ✅ Email Personalizado: 3 archivos (de componentes)

---

### ✅ Caso 2: Paquete Virtual+Descargable SIN Archivo
**Entrada**:
- Paquete: Virtual ✅, Descargable ✅, Tiene archivo ❌
- Componentes: 3 productos descargables

**Resultado esperado**:
- ✅ 1 email enviado (WooCommerce)
- ✅ Email WooCommerce: 3 archivos (de componentes)
- ❌ Email Personalizado: NO enviado

---

### ✅ Caso 3: Paquete NO Virtual
**Entrada**:
- Paquete: Virtual ❌, Descargable ✅, Tiene archivo ✅
- Componentes: 3 productos descargables

**Resultado esperado**:
- ✅ 1 email enviado (WooCommerce)
- ✅ Email WooCommerce: 4 archivos (paquete + componentes)
- ❌ Email Personalizado: NO enviado

---

### ✅ Caso 4: Pedido con 2 Paquetes (1 con archivo, 1 sin archivo)
**Entrada**:
- Paquete A: Virtual ✅, Descargable ✅, Tiene archivo ✅ (2 componentes)
- Paquete B: Virtual ✅, Descargable ✅, Tiene archivo ❌ (3 componentes)

**Resultado esperado**:
- ✅ 2 emails enviados
- ✅ Email Personalizado (Paquete A): 2 archivos de componentes
- ✅ Email WooCommerce: 1 archivo (Paquete A) + 3 archivos (componentes Paquete B)

---

### ✅ Caso 5: Cancelación de Pedido
**Entrada**:
- Pedido completado con emails enviados
- Estado cambia a: Cancelled

**Resultado esperado**:
- ✅ Metas eliminadas:
  - `_sco_pkg_downloads_email_sent`
  - `_sco_pkg_components_email_sent_{item_id}`
- ✅ Si pedido se reactiva: Emails se reenvían

---

## 🔍 Debugging

### Logs de Error
```php
error_log(sprintf('Sorteo SCO: ERROR al enviar email de componentes para item #%d', $item_id));
```

### Notas en Pedido
```
Paquete componentes: No hay descargas de componentes para item #123.
Email de descargas de componentes enviado para paquete "Nombre" (item #123) con 3 archivo(s).
ERROR: No se pudo enviar email de descargas de componentes para paquete "Nombre" (item #123).
```

### Verificación de Metas
```php
// En pedido
get_post_meta($order_id, '_sco_pkg_downloads_granted', true); // 'yes'
get_post_meta($order_id, '_sco_pkg_downloads_email_sent', true); // 'yes'
get_post_meta($order_id, '_sco_pkg_components_email_sent_45', true); // 'yes'
```

### Query de Permisos
```sql
SELECT * FROM wp_woocommerce_downloadable_product_permissions 
WHERE order_id = 1234 
ORDER BY product_id, download_id;
```

---

## 📝 Consideraciones Técnicas

### Compatibilidad
- ✅ WordPress 5.0+
- ✅ WooCommerce 6.0+
- ✅ PHP 7.4+
- ✅ Compatible con HPOS (High-Performance Order Storage)
- ✅ Compatible con Guest Checkout
- ✅ Compatible con multi-site (sin cambios)

### Performance
- ✅ Caché de permisos reutilizado (ya existente)
- ✅ Dedupe eficiente con `$seen_keys` array
- ✅ Query SQL optimizada con `prepare()` y `%d` placeholders
- ✅ Early returns para evitar procesamiento innecesario
- ✅ Transient locks para evitar emails duplicados (ya existente)

### Seguridad
- ✅ Sanitización de inputs con `esc_html()`, `esc_attr()`, `esc_url()`
- ✅ Prepared statements en queries SQL
- ✅ Validación de tipos con `wc_get_order()`, `wc_get_product()`
- ✅ Verificación de permisos (solo emails al comprador del pedido)
- ✅ URLs de descarga con `order_key` y `email` para validación WooCommerce

### Mantenibilidad
- ✅ Funciones con nombres descriptivos
- ✅ Parámetros con type hints cuando posible
- ✅ Reutilización de helpers existentes (`get_email_colors()`, `get_header_image()`)
- ✅ Comentarios DocBlock con `@since`, `@param`, `@return`
- ✅ Separación de responsabilidades (detección, extracción, envío, rendering)

---

## 🚀 Despliegue

### Pasos de Actualización
1. ✅ Backup de base de datos
2. ✅ Backup de archivos del plugin
3. ✅ Subir archivos modificados vía FTP/SSH
4. ✅ Verificar versión en Admin > Plugins (debe mostrar 1.9.16)
5. ✅ Probar con pedido de prueba en modo staging
6. ✅ Verificar emails recibidos (revisar spam)
7. ✅ Verificar notas en pedido
8. ✅ Limpiar caché (WP, WooCommerce, servidor)

### Rollback (si es necesario)
1. Restaurar backup de archivos
2. Restaurar backup de base de datos
3. Limpiar caché

---

## 📚 Referencias

### Hooks Utilizados
- `woocommerce_order_status_processing` (priority 5)
- `woocommerce_order_status_completed` (priority 5)
- `woocommerce_order_status_cancelled` (priority 20)

### Funciones WooCommerce
- `wc_get_order($order_id)`
- `wc_get_product($product_id)`
- `$product->get_type()`
- `$product->is_virtual()`
- `$product->is_downloadable()`
- `$product->get_downloads()`
- `$order->get_items()`
- `$order->get_meta($key)`
- `$order->update_meta_data($key, $value)`
- `$order->add_order_note($note)`

### Tablas de Base de Datos
- `{$wpdb->prefix}woocommerce_downloadable_product_permissions`

### Metas Utilizadas
- `_sco_package` (item meta) - Composición del paquete
- `_sco_pkg_downloads_granted` (order meta) - Permisos otorgados
- `_sco_pkg_downloads_email_sent` (order meta) - Email principal enviado
- `_sco_pkg_components_email_sent_{item_id}` (order meta) - Email personalizado enviado

---

## ✅ Checklist de Implementación

- [x] Función de detección `sorteo_sco_package_needs_custom_downloads_email()`
- [x] Función de extracción `sorteo_sco_get_package_component_downloads()`
- [x] Método de envío `send_package_component_downloads_email()`
- [x] Template HTML `render_component_downloads_email_html()`
- [x] Filtrado en email principal
- [x] Disparador en grant permissions
- [x] Cleanup en cancelaciones
- [x] Actualización de versión a 1.9.16
- [x] Actualización de README.md
- [x] Creación de CHANGELOG-v1.9.16.md
- [x] Documentación completa
- [x] Validación de sintaxis PHP
- [x] Testing básico

---

## 🎉 Conclusión

La versión 1.9.16 introduce un sistema robusto y eficiente de emails personalizados para paquetes descargables, mejorando significativamente la experiencia del usuario al separar claramente:

1. **Archivo del paquete principal** → Email estándar WooCommerce
2. **Archivos de productos componentes** → Email personalizado Sorteo

Esta implementación mantiene la compatibilidad con todas las funcionalidades existentes del plugin mientras agrega valor sin aumentar complejidad innecesaria.

---

**Desarrollado por**: scooller  
**Versión**: 1.9.16  
**Fecha**: 2025-01-08

## 📝 Registro de Cambios (Histórico Consolidado)

### v1.9.16 (2025-01-08)
✅ **Sistema de Emails Personalizados para Paquetes Descargables**:
- **Emails separados**: Paquetes virtual+descargables con archivo propio envían dos emails:
  - Email estándar de WooCommerce: Contiene el archivo del paquete principal
  - Email personalizado automático: Contiene descargas de productos componentes (categorías fuente + productos por paquete)
- **Detección inteligente**: Solo se activa cuando el paquete cumple 3 condiciones:
  - Es virtual (`is_virtual()`)
  - Es descargable (`is_downloadable()`)
  - Tiene al menos un archivo descargable propio (`get_downloads()`)
- **Funciones nuevas**:
  - `sorteo_sco_package_needs_custom_downloads_email()`: Helper de detección
  - `sorteo_sco_get_package_component_downloads()`: Extrae descargas SOLO de componentes
  - `Sorteo_SCO_Email::send_package_component_downloads_email()`: Envía email personalizado
  - `Sorteo_SCO_Email::render_component_downloads_email_html()`: Template HTML adaptado
- **Prevención de duplicados**: 
  - Email principal filtra paquetes con email personalizado
  - Meta key `_sco_pkg_components_email_sent_{item_id}` evita reenvíos
  - Cleanup automático en cancelaciones de pedido
- **Trazabilidad mejorada**:
  - Notas en pedido documentan ambos envíos
  - Logs de error para debugging
  - Integración con sistema de permisos existente

### v1.9.15 (2025-12-26)
✅ **Mejoras Críticas en Paquetes (sco_package)**:
- **Fix duplicados**: Eliminación temprana de productos repetidos con `array_unique()` antes de validación
- **Validación robusta**: Verifica cantidad suficiente ANTES de `array_slice()`
- **Mensajes descriptivos**: Errores claros indicando categorías, cantidades necesarias vs disponibles
- **Logging mejorado**: `error_log()` con información completa para debugging
- **Verificación final**: Doble chequeo de unicidad después de `shuffle()` en modo aleatorio
- **Pool ampliado**: Aumentado `posts_per_page` a 500 para mejor selección aleatoria
- **Excluye recursión**: Paquetes no aparecen como componentes de otros paquetes

✅ **Compatibilidad Multi-Tema**:
- **Sistema de detección**: `Sorteo_Theme_Compat::is_bootstrap_theme_active()`
- **AJAX mejorado**: Usa URL nativa de WooCommerce con fragmentos automáticos
- **Feedback visual**: Botón verde con check temporal al agregar al carrito
- **Single product**: Selector de cantidad funcional en página de detalle para temas no-Bootstrap
- **Manejo de errores**: Alertas claras cuando falla el AJAX

✅ **Garantías de Composición**:
- ✅ Solo productos de categorías configuradas
- ✅ Cero duplicados en el paquete
- ✅ Validación correcta de cantidad solicitada
- ✅ Mensajes de error cuando no hay suficientes productos
- ✅ Contador de carrito se actualiza automáticamente

### v1.9.14 (2025-12-08)
✅ Notas en retornos tempranos del envío de descargas:
- Email desactivado: agrega nota en pedido
- Estado no configurado: agrega nota con estado actual
- Pedido sin paquetes: agrega nota aclaratoria
- Reintento programado: agrega nota con fecha/hora y hook

✅ Admin: selects múltiples mejorados con SelectWoo/Select2
- Aplicado a Categorías, Productos especiales y Estados de pedido
- Búsqueda integrada visible y "x" para quitar elementos seleccionados
- Inicialización global de `.wc-enhanced-select` con `data-placeholder`
- Carga de assets `selectWoo`/`select2.css` con fallback si WooCommerce no los registró

### v1.9.13 (2025-12-04)
✅ Notas en pedido para trazabilidad del email de descargas:
- Enviado: destinatario y cantidad de enlaces
- Error: destinatario y sugerencia revisar configuración
- Sin descargas: aviso y número/ID de pedido
- Reintento programado: fecha/hora y hook, incluyendo número/ID de pedido
✅ Reenvío manual agrega nota con resultado y usuario actor.

### v1.9.12 (2025-12-04)
✅ Fix: evitar duplicación al agregar al carrito cuando el tema Bootstrap no está activo.
➡ Cambio: eliminado disparo manual de `click.ajax_add_to_cart` en fallback no-Bootstrap; se mantiene `data-quantity` y se delega a WooCommerce.

### v1.9.11 (2025-11-20)
✅ Manual resend endpoint + acción rápida y dropdown en pedidos.
✅ Limpieza de meta `_sco_pkg_downloads_email_sent` en estados refunded/failed/cancelled.
✅ Logging mínimo (solo errores críticos en permisos y envío de email).
➡ Visibilidad: Acción rápida solo si hay productos `sco_package`; dropdown siempre disponible (se puede restringir si se solicita).

### v1.9.10 (2025-11-20)
✅ Fix race condition: espera permisos antes de enviar email de descargas.
✅ Dedupe de enlaces por `product_id|download_id`.
✅ Reintentos programados si permisos no listos + intento forzado tras crearlos.
✅ Eliminación de logs de depuración intermedios.

### v1.9.9 (2025-11-10)
✅ Sistema de compatibilidad de tema (`Sorteo_Theme_Compat`).
✅ Dropdown adaptativo (Bootstrap vs select nativo).
✅ Fallback CSS automático y funcionamiento standalone sin Bootstrap Theme.

### v1.9.8 (2025-11-06)
✅ Email de pedido completado incluye descargas de productos dentro de paquetes (`sco_package`).
✅ Creación automática de permisos para componentes descargables.
✅ Fallback a permisos DB si `get_downloadable_items()` vacío.
✅ HTML inline simplificado y soporte guest checkout.
✅ Compatibilidad HPOS en consultas de permisos.

### v1.9.6 (2025-11-05)
✅ Feedback visual post-add-to-cart para paquetes (botón verde temporal).
✅ Nueva opción para mostrar/ocultar mensaje de reemplazos por reservas.

### v1.9.5 (2025-11-04)
✅ Métricas con gráficos Chart.js (línea días / circular tipos).
✅ Rangos rápidos 7d/30d/90d y rango personalizado vía AJAX.
✅ Otorgar premio manual a pedido específico (selector + búsqueda).

### v1.9.4 (2025-10-28)
✅ Dropdown de cantidad 1–10 para paquetes con ícono “+” y add via AJAX.

### v1.9.3 (2025-01-25)
✅ Botón "Agregar al carrito" para paquetes en el loop.
✅ Fix recursión / memoria; uso simplificado de filtros.

### v1.9.2 (2025-01-25)
✅ Mensaje de ganador solo en pedidos ganadores (meta verificada + protección contra duplicados).

### v1.9.1 (2025-01-25)
✅ Productos únicos correctamente manejados en cálculo total de paquetes (sin duplicados).

### v1.9.0 (2025-01-25)
✅ Personalización de remitente (email y nombre) en sorteos.
✅ Validaciones y fallbacks automáticos.

### v1.8.9 (2025-01-24)
✅ Estados dinámicos desde configuración (sin hardcoding) con normalización de prefijos.

### v1.8.5 (2025-10-24)
✅ Logs extendidos: sorteos ejecutados + envíos de emails (últimos registros, resaltado visual).

### v1.8.4 (2025-10-24)
✅ Sección de errores del sistema (últimos 50, filtrados y resaltados).

### v1.8.3 (2025-10-24)
✅ Tab "Premios" con historial completo y métricas actualizadas tras cada sorteo.

### v1.8.2 (2025-10-24)
✅ Validación excluyente de período (fecha fin inclusiva hasta 23:59:59).

### v1.8.1 (2025-10-24)
✅ Limpieza de logs de debug (solo errores críticos permanecen).

### v1.8.0 (2025-10-24)
✅ Rediseño visual ganador (Bootstrap 5.3, mensaje configurable, responsive, permanencia hasta cerrar).
✅ Separación mensaje visual / email y variables dinámicas.

### v1.7.8 (2025-10-24)
✅ Sistema de debug completo (activable con WP_DEBUG_LOG) para trazabilidad.

### v1.7.7 (2025-10-24)
✅ Avisos funcionamiento con guest checkout (sesión + cookies).

### v1.7.6 (2025-10-24)
✅ Selector de productos especiales solo con stock + búsqueda en tiempo real.

### v1.7.5 (2025-10-24)
✅ Sistema de email reescrito basado en pedidos (incluye invitados) + alertas reactivadas.

### v1.7.4 (2025-10-24)
✅ Visualización correcta de cantidad total de productos en paquetes (carrito/pedido).

### v1.7.0 (2025-01-10)
✅ Nuevo tipo de producto Paquete (Sorteo) con modos Manual y Sorpresa.
✅ Generación de composición, reducción de stock componentes, metadatos en pedido.

### v1.6.5 (2024-12-15)
✅ CSV sin filas vacías (validación rigurosa y buffer limpio).

### v1.6.2 (2024-12-10)
✅ Descarga directa de CSV con nombres timestamp + BOM UTF-8.

### v1.6.1 (2024-12-05)
✅ Exportación Usuario+Compras detallada (sin agrupación).

### v1.6.0 (2024-12-01)
✅ Sistema de sorteos inteligente (inmediato vs umbral) + métricas básicas y logging.