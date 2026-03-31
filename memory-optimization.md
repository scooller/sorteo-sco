# Optimización de Memoria - Plugin Sorteo SCO

## Problema Original

El plugin agotaba los 512MB de memoria PHP en hosting compartido. Los errores aparecían en:
- `abstract-wc-data.php` → `WC_Data::read_meta_data()`
- `abstract-wc-order.php` → métodos de `WC_Abstract_Order`
- `class-wc-order.php` → constructor/hydration de `WC_Order`

---

## Causa Raíz #1: Funciones pesadas en AJAX bootstrap

**Archivo:** `includes/class-sorteo-core.php`

Tres funciones se ejecutaban en CADA request a `admin-ajax.php` y cargaban TODAS las órdenes con `wc_get_orders(['limit' => -1])`:

| Función | Hook | Impacto |
|---------|------|---------|
| `maybe_run_auto_draw()` | `admin_init` | Carga todas las órdenes + itera items |
| `update_metrics_on_admin_init()` | `admin_init` | Llama `calculate_total_earnings()` → todas las órdenes |
| `check_recent_purchase()` | `wp_loaded` | Carga todas las órdenes del usuario |

### Fix aplicado

```php
if (wp_doing_ajax()) {
    return;
}
```

Al inicio de cada función. Ninguna necesita ejecutarse durante AJAX — son para admin UI y frontend page loads.

### Resultado

Memory base en AJAX: de >512MB (fatal) a **59.4MB**.

---

## Causa Raíz #2: `wc_get_product()` en AJAX handlers

**Archivo:** `includes/class-sorteo-package-new.php`

El handler AJAX llamaba `wc_get_product()` para verificar el tipo de producto, y `WC()->cart->get_cart()` que internamente llama `wc_get_product()` por cada item del carrito.

### Fix aplicado

```php
// ANTES (pesado):
$product = wc_get_product($product_id);
if ($product->get_type() !== 'paquete_sco_new') { ... }

// DESPUÉS (liviano):
$type_terms = get_the_terms($product_id, 'product_type');
$product_type = (!empty($type_terms) && !is_wp_error($type_terms)) ? $type_terms[0]->slug : '';
if ($product_type !== 'paquete_sco_new') { ... }
```

```php
// ANTES (pesado - carga objetos producto por cada item del carrito):
WC()->cart->get_cart()

// DESPUÉS (liviano - solo lee datos raw de la sesión):
WC()->session->get('cart', array())
```

---

## Causa Raíz #3: Loop de `add_to_cart()` en un solo request

**Archivo:** `includes/class-sorteo-package-new.php`

Agregar 25 productos en un solo request PHP causaba crecimiento cuadrático de memoria porque cada `add_to_cart()` dispara `calculate_totals()` que recalcula TODOS los items.

### Fix aplicado: Arquitectura Split-AJAX

1. **Step 1** (`sco_get_package_new_products`): Retorna array de IDs. Query SQL pura con `WP_Query` + `fields => 'ids'`. Zero `wc_get_product()`.
2. **Step 2** (`sco_add_single_to_cart`): Agrega 1 producto por request HTTP. Frontend JS encadena calls secuenciales con progreso visual.

Cada request PHP maneja solo 1 producto → memoria se libera entre requests.

---

## Patrones a Replicar

### 1. Guard en hooks pesados durante AJAX

Cualquier función en `admin_init`, `wp_loaded`, o `init` que cargue datos pesados debe verificar:

```php
if (wp_doing_ajax()) {
    return;
}
```

### 2. Evitar `wc_get_product()` cuando solo necesitas metadata

| Necesitas | Usa en vez de `wc_get_product()` |
|-----------|----------------------------------|
| Tipo de producto | `get_the_terms($id, 'product_type')` |
| Precio | `get_post_meta($id, '_price', true)` |
| Stock status | `get_post_meta($id, '_stock_status', true)` |
| SKU | `get_post_meta($id, '_sku', true)` |
| Nombre | `get_the_title($id)` |

### 3. Evitar `WC()->cart->get_cart()` cuando solo necesitas IDs

```php
// Liviano - solo IDs del carrito:
$cart_session = WC()->session->get('cart', array());
foreach ($cart_session as $item) {
    $product_ids[] = intval($item['product_id']);
}
```

### 4. Queries de productos: `fields => 'ids'`

```php
$query = new WP_Query([
    'post_type' => 'product',
    'fields' => 'ids',           // solo IDs (~8 bytes c/u vs ~100KB por objeto)
    'no_found_rows' => true,     // skip COUNT query
    'posts_per_page' => $limit,
]);
```

### 5. Queries de órdenes: `return => 'ids'`

```php
$order_ids = wc_get_orders([
    'limit' => -1,
    'return' => 'ids',  // solo integers, no objetos WC_Order
]);
// Luego procesar uno por uno:
foreach ($order_ids as $id) {
    $order = wc_get_order($id);
    // ... procesar ...
    unset($order); // liberar memoria
}
```

### 6. Split-AJAX para operaciones batch

Si necesitas ejecutar N operaciones pesadas, NO hacerlas en un solo request PHP:

```
Frontend JS:
  Step 1: GET /ajax → retorna lista de IDs (query liviana)
  Step 2: POST /ajax con ID[0] → procesa 1 item
  Step 3: POST /ajax con ID[1] → procesa 1 item
  ...
  Step N: POST /ajax con ID[N-1] → procesa último item
```

### 7. Liberar memoria con `unset($order)` en loops largos

Para exports/procesos que iteran miles de órdenes:

```php
foreach ($order_ids as $i => $order_id) {
    $order = wc_get_order($order_id);
    // ... procesar ...

    unset($order); // Liberar objeto WC_Order (~50-200KB c/u)
}
```

**IMPORTANTE:** NO usar `wp_cache_flush_runtime()` dentro de iteradores que usan closures con `wp_get_post_terms()` u otras funciones que dependen del WP object cache. El flush destruye caches internos de WP que causan errores fatales. `unset($order)` es suficiente para liberar la mayor parte de la memoria.

---

## Archivos Modificados

| Archivo | Cambio | Versión |
|---------|--------|---------|
| `includes/class-sorteo-core.php` | `wp_doing_ajax()` guards en 3 funciones | 1.9.36 |
| `includes/class-sorteo-package-new.php` | SQL pura, split-AJAX, session raw, taxonomía, reservas | 1.9.35-1.9.36 |
| `includes/class-sorteo-export.php` | `return => 'ids'`, procesamiento uno por uno | 1.9.36 |
| `includes/class-sorteo-wc-extra.php` | `unset($order)` en iterador de export | 1.9.36 |

---

## Diagnóstico de Memoria

Para diagnosticar futuros problemas, agregar al inicio de un handler:

```php
error_log('[PLUGIN] handler called. Memory: ' . round(memory_get_usage() / 1024 / 1024, 1) . 'MB / ' . ini_get('memory_limit'));
```

Si el handler NO aparece en el log pero hay fatal error → el problema está en el bootstrap (otro plugin/hook cargando datos pesados antes de llegar al handler).
