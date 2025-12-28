# 🎲 Plugin Sorteo v1.9.15

Plugin completo para sorteos automáticos, productos sorpresa, avisos personalizados, exportación de ganadores, métricas avanzadas y marcos visuales en WooCommerce.

<!--
    Se han eliminado secciones duplicadas de "Novedades" — por favor consulte
    el "Registro de Cambios" consolidado más abajo en este archivo
    ("## 📝 Registro de Cambios (Histórico Consolidado)").
    Esto mantiene el README concentrado y evita notas de versión dispersas.
-->

## 📝 Registro de Cambios (Histórico Consolidado)

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

---

## 📋 Tabla de Contenidos

- [Características Principales](#características-principales)
- [Instalación](#instalación)
- [Configuración](#configuración)
- [Funcionalidades](#funcionalidades)
- [Métricas y Estadísticas](#métricas-y-estadísticas)
- [Gestión de Historial](#gestión-de-historial)
- [Personalización de Mensajes](#personalización-de-mensajes)
- [Integración con WooCommerce](#integración-con-woocommerce)
- [Producto tipo Paquete](#📦-producto-tipo-paquete-sorteo--nuevo-v170)
- [Registro de Cambios](#📝-registro-de-cambios-histórico-consolidado)
- [Soporte](#soporte)

## Configuración

- Periodo de sorteo: definir `inicio` y `fin` del período.
- Categorías de productos: multiselect con búsqueda (`wc-enhanced-select`).
- Productos ganadores: seleccionar productos especiales; si se compra uno, el usuario gana automáticamente.
- Estados de pedido: multiselect para elegir en qué estados se muestra el mensaje y se envía email.
- Marco visual: imagen para destacar productos especiales.

### Selects mejorados (SelectWoo/Select2)
- Búsqueda integrada visible y eliminación con “x” en el propio campo.
- Inicialización global aplicada a todos los `.wc-enhanced-select` en el admin.
- Usar `data-placeholder` para cada select: categorías, productos y estados.
- Requisitos: WooCommerce activo; el plugin carga `selectWoo` y `select2.css` con fallback si no están registrados.

### **Sorteos Automáticos**

Configurar ganancia mínima:
```php
Ganancia mínima: $500.00 USD
```

**Proceso automático:**
1. Sistema monitorea ganancias
2. Al alcanzar el mínimo → sorteo automático
3. Selección aleatoria de usuario elegible
4. Mensaje personalizado al ganador
5. Registro en historial

### **Sorteos Manuales**

Desde `Sorteo > Exportar`:
```
[Ejecutar Sorteo Manual]
```

**Proceso manual:**
1. Clic en botón
2. Selección aleatoria inmediata
3. Notificación automática
4. Registro en métricas

## 📈 Métricas y Estadísticas

### **Dashboard Principal**

**Tarjetas de métricas:**

| Métrica | Descripción | Color |
|---------|-------------|-------|
| **Ganancia Bruta** | Total ventas período | Azul |
| **Costo Premios** | Suma premios entregados | Rojo |
| **Ganancia Neta** | Bruta - Premios | Verde/Rojo |
| **Sorteos Realizados** | Número total | Azul |

### **Barra de Progreso**

Progreso hacia próximo sorteo automático:
```
75% ($375.00 / $500.00 USD)
████████████░░░░
```

### **Cálculos Automáticos**

**Fórmulas aplicadas:**
```php
Ganancia Bruta = Σ(ventas_período)
Costo Premios = Σ(premios_entregados)
Ganancia Neta = Ganancia Bruta - Costo Premios
ROI = (Ganancia Neta / Costo Premios) × 100
```

## � **Exportación Avanzada con Descarga Directa**

### **📥 Descargar Ganadores CSV**
Botón que descarga automáticamente un archivo con usuarios elegibles:

**Archivo generado:** `sorteo-ganadores-YYYY-MM-DD-HH-mm-ss.csv`
```csv
user_id,email
123,usuario@email.com
456,otro@email.com
```

### **📊 Descargar Usuario+Compras CSV** ⭐ **DESTACADO**
Botón que descarga archivo detallado con todas las compras:

**Archivo generado:** `sorteo-usuarios-compras-YYYY-MM-DD-HH-mm-ss.csv`
```csv
ID Usuario,Nombre Usuario,Email Usuario,ID Pedido,Fecha Compra,ID Producto,Nombre Producto,Cantidad,Total Linea,Total Pedido,Estado Pedido,Categorias
123,Juan Perez,juan@email.com,1001,2025-01-15 14:30:00,101,iPhone 15 Pro,1,999.00,999.00,completed,Electronicos Smartphones
123,Juan Perez,juan@email.com,1002,2025-01-20 09:15:00,102,AirPods Pro,1,249.00,249.00,completed,Electronicos Accesorios
456,Maria Garcia,maria@email.com,1003,2025-01-22 16:45:00,103,MacBook Air,1,1299.00,1299.00,processing,Electronicos Computadores
```

### **🚀 Características de la Exportación v1.6.5**
- ✅ **Cero filas vacías GARANTIZADO**: Eliminación total de líneas vacías en cualquier posición
- ✅ **Validación extrema**: Verificación individual de cada campo antes de procesamiento
- ✅ **Buffer ultra-limpio**: Limpieza completa de buffers que causaban líneas problemáticas
- ✅ **CSV perfecto**: Archivo garantizado sin comillas, espacios extras o caracteres problemáticos
- ✅ **Filtrado inteligente**: Rechazo automático de líneas con solo comas o contenido vacío
- ✅ **Datos validados**: Solo registros con información completa y verificada
- ✅ **Descarga automática**: Archivos CSV se descargan directamente al hacer clic
- ✅ **Nombres únicos**: Timestamp automático para evitar sobrescribir archivos
- ✅ **UTF-8 con BOM**: Perfecta compatibilidad con Excel y caracteres especiales
- ✅ **No agrupa compras**: Cada producto aparece como línea individual
- ✅ **Usuarios completos**: Registrados e invitados incluidos
- ✅ **Información detallada**: Productos, cantidades, montos, fechas y categorías
- ✅ **Respeta filtros**: Solo exporta según configuración del plugin
- ✅ **Headers HTTP optimizados**: Descarga segura y compatible con navegadores

## 🗂️ Gestión de Historial

### **Tabla de Historial**

| Fecha | Ganador | Email | Tipo | Premio | Valor | Período | Acciones |
|-------|---------|-------|------|--------|-------|---------|----------|
| 15/10 14:30 | Juan P. | juan@email.com | Auto | iPhone 15 | $999 USD | Oct 2025 | 🗑️ |
| 14/10 09:15 | María G. | maria@email.com | Manual | AirPods | $249 USD | Oct 2025 | 🗑️ |

### **Borrado Granular**

**Borrado Individual:**
- Botón 🗑️ por registro
- Confirmación JavaScript
- Email de notificación automático
- Actualización de métricas

**Borrado Completo:**
- Zona de peligro claramente marcada
- Confirmación doble (JS + servidor)
- Email con historial completo
- Irreversible con advertencias

### **Notificaciones por Email**

**Borrado individual:**
```
Asunto: [Sitio] Registro de Sorteo Eliminado

Un registro individual ha sido eliminado:
- Usuario: Admin (admin@sitio.com)
- Ganador eliminado: Juan Pérez
- Premio: iPhone 15 Pro
- Valor: $999.00 USD
- Fecha: 15/10/2025 14:30
```

**Borrado completo:**
```
Asunto: [Sitio] Historial de Sorteos Eliminado

ATENCIÓN: Historial completo eliminado.
- Total registros: 25
- Usuario responsable: Admin
- Acción irreversible
- [Tabla con últimos 10 registros]
```

## 🎨 Personalización de Mensajes

### **Campos Personalizados**

**Disponibles para usar:**
```php
{nombre}             // Nombre del ganador
{premio}             // Nombre del premio
{valor}              // Precio formateado
{fecha}              // Fecha del sorteo
{sitio}              // Nombre del sitio web
```

**Ejemplo de configuración en el admin:**
```
¡Felicidades {nombre}! 🎉

Has ganado {premio} valorado en {valor}.

Tu premio será enviado en los próximos días.

¡Gracias por tu compra en {sitio}!

Fecha del sorteo: {fecha}
```

**Resultado mostrado al usuario:**
```
¡Felicidades Juan Pérez! 🎉

Has ganado iPhone 15 Pro Max valorado en $999.00 USD.

Tu premio será enviado en los próximos días.

¡Gracias por tu compra en Mi Tienda!

Fecha del sorteo: 15/10/2025 14:30
```

### **Configuración Visual**

**Colores:**
```css
Color de fondo: #4CAF50
Color de texto: #FFFFFF
```

**Tipografía:**
```css
Familia: inherit (usa la fuente del tema/WYSIWYG)
Tamaño: inherit (usa el tamaño del tema/WYSIWYG)
Peso: inherit (usa el peso del tema/WYSIWYG)
```

**Posicionamiento:**
```css
Posición: Top / Center / Bottom
Ubicación horizontal: Derecha (fijo)
Ancho máximo: 400px
```

**Efectos de Animación:**
```css
Sin efecto: Aparición simple
Fade: Aparecer gradualmente
Slide: Deslizar desde arriba
Bounce: Efecto rebote
Pulse: Pulsación suave
Shake: Vibración
```

**Comportamiento:**
```javascript
Duración: 3-60 segundos (configurable)
Auto-cierre: true
Botón cerrar: ×
```

### **CSS Automático Generado**

**Posición Top:**
```css
.sorteo-immediate-notice {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
}
```

**Posición Center:**
```css
.sorteo-immediate-notice {
    position: fixed;
    top: 50%;
    right: 20px;
    transform: translateY(-50%);
    z-index: 9999;
}
```

**Posición Bottom:**
```css
.sorteo-immediate-notice {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
}
```

## 🛍️ Integración con WooCommerce

### **Monedas Soportadas**

Automática desde configuración WooCommerce:
- **USD**: Dólar estadounidense ($)
- **EUR**: Euro (€)
- **CLP**: Peso chileno ($)
- **GBP**: Libra esterlina (£)
- **JPY**: Yen japonés (¥)
- **+150 monedas más**

### **Posiciones de Símbolo**

Respeta configuración WooCommerce:
- `left`: $100.00
- `right`: 100.00$
- `left_space`: $ 100.00
- `right_space`: 100.00 $

### **Hooks de WooCommerce**

```php
// Al completar pedido
add_action('woocommerce_order_status_completed', 'check_sorteo_trigger');

// Al cambiar estado
add_action('woocommerce_order_status_changed', 'check_sorteo_eligibility');

// En checkout
add_action('woocommerce_checkout_order_processed', 'mark_sorteo_participant');
```

## 📦 Producto tipo "Paquete (Sorteo)" — NUEVO v1.7.0

Tipo de producto personalizado de WooCommerce que permite vender paquetes con precio fijo incluyendo múltiples productos, ya sea definidos manualmente o elegidos al azar desde categorías.

### 🎯 **Cómo Crear un Paquete**

1. **Crear nuevo producto** en WooCommerce
2. **Seleccionar tipo**: "Paquete (Sorteo)" del dropdown
3. **Configurar en pestaña "Paquete Sorteo"**:
   - **Modo de selección**: Manual o Sorpresa
   - **Productos por paquete**: Cantidad total de productos (ej: 3, 5, 10)
   
4. **Configuración por Modo**:
   
   **Modo Sorpresa (Aleatorio)**:
   - Selecciona una o más **categorías fuente**
   - Marca/desmarca **"Permitir productos sin stock"**
   - Al comprar: el sistema elige productos aleatorios de las categorías
   
   **Modo Manual (Fijos)**:
   - Busca y selecciona **productos específicos** con AJAX
   - Al comprar: el cliente recibe exactamente esos productos
   
5. **Definir Precio** en pestaña General:
   - **Precio regular**: Precio normal del paquete (ej: $150)
   - **Precio de oferta**: Precio promocional opcional (ej: $99)

### 🛒 **Experiencia de Compra**

**Al añadir al carrito**:
- Sistema valida disponibilidad de productos
- Genera composición (en Sorpresa: productos aleatorios únicos)
- Adjunta información al item del carrito

**En carrito y checkout**:
- Muestra lista de **productos incluidos** con nombres
- Indica **modo** (Manual o Sorpresa)
- Muestra **cantidad total** de productos

**Después del pago**:
- Composición se guarda en el pedido con metadatos
- Al cambiar a "Procesando" o "Completado":
  - Stock de cada producto incluido se reduce automáticamente
  - Sistema previene descuento doble con marcado interno

### ⚙️ **Características Técnicas**

**Producto Virtual**:
- Automáticamente marcado como virtual
- No requiere envío físico
- No gestiona stock propio

**Gestión de Stock**:
- Stock se gestiona en productos componentes
- Descuento automático al completar pedido
- Multiplicador por cantidad de paquetes (ej: 2 paquetes × 3 productos = 6 unidades)
- **Exclusión inteligente**: Los paquetes no aparecen como opción en modo sorpresa (evita paquetes recursivos)

**Validación Robusta**:
- Verifica productos comprables y disponibles
- Bloquea añadir al carrito si faltan productos
- Mensajes de error claros y descriptivos

**Interfaz Administrativa**:
- Tab personalizado "Paquete Sorteo"
- Tabs irrelevantes ocultos (Atributos, Productos vinculados)
- Tab de Inventario visible para SKU y notas
- JavaScript dinámico muestra/oculta campos según modo
- **Nueva opción**: Checkbox "Mostrar productos en carrito" para controlar visibilidad en frontend

**Visualización en Carrito** ⭐ **NUEVA**:
- Control opcional para mostrar/ocultar productos incluidos
- Contador dinámico que multiplica productos por cantidad de paquetes
- Ejemplo: 3 productos × 2 paquetes = muestra "total: 6"

### 📋 **Casos de Uso**

**Mystery Box / Caja Sorpresa**:
```
Modo: Sorpresa
Categorías: Accesorios, Gadgets, Decoración
Productos: 5 productos aleatorios
Precio: $49.99 (valor total productos > $80)
```

**Bundle Fijo de Productos**:
```
Modo: Manual
Productos: iPhone Case + Screen Protector + Charging Cable
Productos: 3 específicos
Precio: $39.99 (ahorro vs individual)
```

**Pack de Muestras**:
```
Modo: Sorpresa
Categorías: Cosméticos, Cuidado Personal
Productos: 10 mini productos
Precio: $24.99
Permitir sin stock: No
```

**Kit de Inicio**:
```
Modo: Manual
Productos: Curso Online + eBook + Templates
Productos: 3 digitales
Precio: $99 (precio lanzamiento)
```

### ⚠️ **Notas y Limitaciones**

**Productos Soportados**:
- ✅ Productos simples
- ✅ Productos virtuales
- ✅ Productos descargables
- ❌ Productos variables (no soportado actualmente)

**Consideraciones**:
- En **Modo Sorpresa**: cada compra genera composición única y diferente
- Si no hay suficientes productos disponibles: no permite añadir al carrito
- Stock se descuenta de componentes, no del paquete padre
- Composición se guarda permanentemente en el pedido para referencia

**Mejores Prácticas**:
- Define precio del paquete menor que suma de componentes para incentivo
- En Sorpresa: asegura categorías con suficientes productos activos
- En Manual: verifica stock de componentes antes de publicar
- Usa precio de oferta para crear urgencia en la compra

## Soporte

Para dudas o mejoras abre un ticket en el repositorio o contacta al autor. 
Indica **versión instalada** (v1.9.15) y pasos para reproducir problemas. 
Incluye logs de error si están disponibles (`wp-content/debug.log`).