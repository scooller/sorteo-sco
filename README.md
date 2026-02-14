# 🎲 Plugin Sorteo v1.9.31

Plugin completo para sorteos automáticos, productos sorpresa, avisos personalizados, exportación de ganadores, métricas avanzadas, gestión de stock con HPOS y marcos visuales en WooCommerce.

## 📋 Registro de Cambios

Para ver el historial completo de versiones y cambios detallados, consulta [CHANGELOG.md](CHANGELOG.md)

### 2026-02-12 (v1.9.31)
- 🐛 **Paquete SCO Nuevo - Fix display**: Ahora muestra el nombre de la categoría en el selector de cantidad (ej: "4 stickers" en vez de "4 productos").
- 🧹 Código optimizado: eliminados logs de debug y simplificada función save_meta.

### 2026-02-12 (v1.9.30)
- ✅ Nuevo tipo de producto "Paquete SCO (Nuevo)" para agregar X productos al azar.
- ✅ Selector de cantidades predefinidas en frontend (4, 8, 10, 20, 25, personalizables).
- ✅ Sin duplicados: cada producto seleccionado es único en el paquete.
- ✅ Stock gestionado directamente por WooCommerce (no requiere sistema de reservas).
- ✅ Agrega productos individuales al carrito y filtra productos sin stock.

### 2026-02-12 (v1.9.29)
- ✅ Nuevo tab "Precios Cantidad" en Extra WooCommerce.
- ✅ Reglas por categoría con tramos de precio por cantidad en carrito/checkout.
- ✅ Prioridad configurable cuando un producto pertenece a múltiples categorías.

---

## 🆕 Novedades v1.9.17

### ⚙️ Extra WooCommerce - Nueva Página de Configuración

Nueva sección en el menú de administración con herramientas avanzadas para gestión de WooCommerce:

#### 🔧 Stock y Ordenamiento (NUEVO)
Pestaña consolidada con configuración de stock y ordenamiento de productos.

**Gestión de Stock**:
- ✅ **Gestión de Stock Personalizada**: Habilita/deshabilita la gestión de stock por el plugin
- ✅ **Reserva de Stock**: Previene race conditions en ventas concurrentes
  - Stock se reserva al hacer checkout (no al pagar)
  - Se libera automáticamente si el pedido se cancela/falla
  - Configurable: activar/desactivar según necesidad
- ✅ **Selección de tipos de producto**:
  - Simple, Variable, Agrupado, Externo/Afiliado, Paquete SCO
  - Filtros adicionales: Virtual, Descargable
- ✅ **Compatibilidad HPOS Total**: Funciona con High-Performance Order Storage y posts tradicional
- ✅ **Detección automática**: Muestra el estado actual de HPOS en WooCommerce
- ✅ **Control granular**: Elige exactamente qué productos gestionar
- ✅ **Hooks optimizados**: Reducción de stock en `processing` y `completed`
- ✅ **Prevención de duplicados**: Evita reducción doble del mismo pedido
- ✅ **Notas en pedidos**: Registro automático de ajustes de stock

**Ordenamiento de Productos**:
- ✅ **Múltiples opciones de ordenamiento**:
  - Más Recientes (por fecha de creación)
  - Orden Aleatorio (ideal para sorteos)
  - Nombre (A-Z)
  - Precio (menor a mayor o viceversa)
  - Popularidad (productos más vendidos)
  - Calificación (mejor puntuados)
- ✅ **Dirección configurable**: Ascendente o Descendente
- ✅ **Productos destacados primero**: Los productos marcados como "Destacado" siempre aparecen primero, sin importar el ordenamiento

#### 📊 Actualización Masiva de Precios
- ✅ **Por categoría**: Selecciona categoría objetivo para actualizar precios
- ✅ **Filtros de exclusión**: Excluye productos que pertenezcan a categorías específicas
- ✅ **Tipos de actualización**:
  - Porcentaje (%) - Aumentar/reducir por porcentaje
  - Cantidad fija ($) - Aumentar/reducir cantidad específica
  - Precio exacto - Establecer precio fijo
- ✅ **Aplicar a**: Precio regular, precio de oferta, o ambos
- ✅ **Modo prueba**: Simula cambios antes de aplicar
- ✅ **Vista previa detallada**: Tabla con productos y precios antes/después
- ✅ **Procesamiento por lotes**: Optimizado para miles de productos (50 por solicitud)
- ✅ **Barra de progreso en tiempo real**: Muestra progreso actualizado (ej: 150/2500)

#### 📈 Monitor de Reservas de Stock (NUEVO)
- ✅ **Tabla en tiempo real**: Visualiza todos los productos con stock reservado
- ✅ **Información detallada**:
  - Nombre del producto y ID
  - Número de pedido (con enlace a edición)
  - Cantidad reservada
  - Tiempo de reserva
  - Tiempo para expiración (con color indicador)
- ✅ **Gestión individual**: Liberar reservas una por una
- ✅ **Gestión en lote**: Liberar todas las reservas de una vez
- ✅ **Indicadores de estado**: Colores que muestran si la reserva expirará pronto
- ✅ **Actualización automática**: Carga los datos al abrir el tab
- ✅ **Botón Actualizar**: Refresca la lista manualmente

#### 📈 Métricas de Paquetes (sco_package)
- ✅ **Dashboard de estadísticas**:
  - Total de paquetes vendidos
  - Productos descontados de stock
  - Emails de componentes enviados
  - Ingresos totales generados
- ✅ **Tabla de pedidos**: Últimos 50 pedidos con paquetes
- ✅ **Información detallada**: Cantidad, componentes, stock reducido, email enviado, fecha
- ✅ **Enlaces directos**: Acceso rápido a edición de pedidos

#### 🎯 Casos de Uso
**Gestión de Stock**:
```
✓ Habilitar gestión de stock
✓ Habilitar reserva de stock (RECOMENDADO)
Tipos: [x] Simple [x] Virtual [x] Descargable
Resultado: El plugin gestionará stock de productos 
          que sean Simple Y Virtual Y Descargable,
          reservando el stock al checkout para prevenir
          ventas concurrentes del mismo producto
```

**Problema que soluciona la Reserva**:
```
SIN RESERVA:
Usuario A: Agrega Sticker al carrito (stock: 1)
Usuario B: Compra paquete con ese Sticker → Stock = 0
Usuario A: Intenta pagar → ERROR: Sin stock

CON RESERVA (RECOMENDADO):
Usuario A: Agrega Sticker → hace checkout → Stock reservado
Usuario B: Intenta comprar → "Stock no disponible"
Usuario A: Completa pago → Stock descontado → ÉXITO ✓
```

**Actualización de precios**:
```
Categoría: Electrónicos
Excluir: Ofertas, Liquidación
Tipo: Porcentaje
Valor: 10
Resultado: Aumenta 10% solo en productos de Electrónicos 
          que NO estén en Ofertas ni Liquidación
```

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
- [Producto tipo "Paquete (Sorteo)"](#📦-producto-tipo-paquete-sorteo--nuevo-v170)
- [Producto tipo "Paquete SCO (Nuevo)"](#📦-producto-tipo-paquete-sco-nuevo--v1930)
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

---

## 📦 Producto tipo "Paquete SCO (Nuevo)" — v1.9.30

Tipo de producto simplificado para vender paquetes donde el cliente elige **cuántos productos** desea recibir. El sistema selecciona automáticamente esa cantidad de productos **diferentes** (sin duplicados) de una categoría configurada.

### 🎯 **Principales características**

- **Selección aleatoria**: Cada producto del paquete es diferente
- **Sin duplicados**: Garantiza que no hay repeticiones
- **Stock nativo**: WooCommerce gestiona el stock directamente (sin transientes)
- **Configuración simple**: Solo 2 pasos (categoría + opciones de cantidad)
- **Ideal para**: Sorteos, cajas sorpresa, promociones de cantidad variable

### 🎯 **Cómo Crear un Paquete SCO Nuevo**

1. **Crear nuevo producto** en WooCommerce
2. **Seleccionar tipo**: "Paquete SCO (Nuevo)" del dropdown
3. **Configurar en pestaña "Paquete SCO Nuevo"**:
   - **Categoría fuente**: Selecciona la categoría de donde se tomarán productos
   - **Opciones de cantidad**: Define qué cantidades puede elegir el cliente (ej: `4,8,10,20,25`)
4. **Definir Precio** en pestaña General

### 🛒 **Experiencia de Compra**

**En la página del producto**:
- Aparece dropdown con las opciones de cantidad configuradas
- Cliente elige cuántos productos desea (ej: "10 productos")
- Al agregar al carrito, se aplican automáticamente

**En carrito y checkout**:
- Muestra la cantidad total de productos: "Cantidad de productos: 10"
- Lista los productos incluidos individualmente
- Cada combinación se trata como única (no se fusionan paquetes iguales de diferente composición)

**Después del pago**:
- Composición se guarda en el pedido
- Stock de cada producto componente se reduce automáticamente
- Al reembolsar/cancelar, se restituye el stock

### ⚙️ **Diferencias con "Paquete (Sorteo)"**

| Característica | Paquete Sorteo | Paquete SCO Nuevo |
|---|---|---|
| **Selección de productos** | Manual o 1+ categorías | Solo 1 categoría |
| **Cantidad fija** | Sí (definida en producto) | No (cliente elige) |
| **Stock del paquete** | Transientes + validación | Stock nativo WC |
| **Duplicados** | Control avanzado | Garantizado sin duplicados |
| **Composición** | Guardada con paquete | Generada al agregar carrito |
| **Complejidad** | Media (admin configura mucho) | Baja (2 campos) |
| **Casos de uso** | Paquetes sorteo complejos | Sorteos simples/promociones |

### ⚙️ **Validaciones**

- Verifica que la categoría tenga suficientes productos
- Valida que el cliente seleccione una cantidad permitida  
- Bloquea agregar si no hay suficientes productos disponibles
- Previene agregar productos componentes directamente (evita confusiones)

---

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

---

## ⚠️ Issues Conocidos

### Gateway Flow - Error Genérico en Checkout

**Síntoma:**
En algunas configuraciones, durante el checkout puede aparecer brevemente un mensaje de error genérico del gateway Flow, aunque el stock esté disponible y la compra sea válida.

**Impacto:**
- 🟢 Bajo - El error se suprime automáticamente
- 🟢 La compra puede completarse correctamente
- 🟡 Puede causar confusión temporal al usuario

**Causa:**
El gateway Flow realiza validaciones adicionales que pueden eludir algunos filtros de WordPress/WooCommerce.

**Estado:**
- 🔧 En investigación para v1.9.20
- ✅ Workarounds activos:
  - Intercepción automática de errores falsos
  - Limpieza de notices en frontend
  - Validación mejorada de stock disponible

**Recomendaciones:**
1. Activar el flag de debug `sorteo_sco_debug_logs` solo para troubleshooting
2. Monitorear el tab "Reservas de Stock" para verificar funcionamiento correcto
3. Liberar reservas expiradas manualmente si es necesario

---

## Soporte

Para dudas o mejoras abre un ticket en el repositorio o contacta al autor. 
Indica **versión instalada** (v1.9.15) y pasos para reproducir problemas. 
Incluye logs de error si están disponibles (`wp-content/debug.log`).