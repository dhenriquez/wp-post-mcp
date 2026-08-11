# WP Post MCP (WordPress MCP Server)

Plugin nativo para WordPress en PHP que expone un servidor de **Model Context Protocol (MCP)** con soporte completo para transporte **Server-Sent Events (SSE)** y **HTTP JSON-RPC 2.0** en `https://tusitio.com/mcp`. Permite que modelos de inteligencia artificial como **Gemini Spark**, **Claude Desktop**, **Cursor**, **Antigravity**, etc., interactúen de forma segura con tu sitio WordPress para:

- 📋 **Listar categorías** existentes para asignar la categoría correcta.
- 🏷️ **Listar etiquetas** existentes para sugerir o asociar tags.
- 📝 **Crear entradas en estado borrador (`draft`)** con soporte completo para marcado HTML y bloques nativos de Gutenberg (`<!-- wp:paragraph -->`, `<!-- wp:heading -->`, etc.).

---

## 🚀 Instalación y Activación

1. Copia la carpeta `wp-post-mcp` en el directorio de plugins de tu instalación de WordPress:
   ```
   wp-content/plugins/wp-post-mcp/
   ```
2. Accede al panel de administración de WordPress (`WP Admin > Plugins`).
3. Activa el plugin **WP Post MCP**.
4. Dirígete a **Ajustes > WP Post MCP** para ver tu URL de conexión lista para usar y tu Clave API auto-generada.

---

## ⚡ Conexión con Gemini Spark y Claude (1 Clic)

El plugin genera automáticamente una **Clave API Maestra** que evita cualquier problema con contraseñas de aplicación o plugins de seguridad.

1. En tu panel de WordPress, ve a **Ajustes > WP Post MCP**.
2. Haz clic en el botón **Copiar URL** (obtendrás una URL del tipo `https://tusitio.com/mcp?api_key=wpmcp_xxxxxxx`).
3. En **Gemini Spark** (`gemini.google.com > Settings > Connected Apps > Add a custom app`) o en **Claude**, pega esa URL exacta.
4. ¡Listo! La IA tendrá acceso inmediato para listar categorías, etiquetas y crear borradores de posts.

---

## 🔐 Métodos de Autenticación Soportados

El plugin admite múltiples formas de autenticación:

1. **Clave API en URL (Recomendado)**: `https://tusitio.com/mcp?api_key=TU_CLAVE` (configurada en Ajustes > WP Post MCP o vía constante `WP_MCP_API_KEY` en `wp-config.php`).
2. **Application Passwords en URL**: `https://tusitio.com/mcp?user=TU_USUARIO&app_password=TU_PASSWORD_SIN_ESPACIOS`.
3. **HTTP Basic Auth**: `Authorization: Basic base64(usuario:application_password)`.
4. **Bearer Token**: `Authorization: Bearer TU_API_KEY` o `Bearer base64(usuario:application_password)`.

---

## 🛠️ Herramientas Disponibles (MCP Tools)

### 1. `list_categories`
Obtiene las categorías registradas en WordPress.
* **Parámetros**:
  * `hide_empty` *(boolean, opcional)*: Ocultar categorías sin posts. Por defecto `false`.
  * `search` *(string, opcional)*: Filtrar categorías por nombre.

### 2. `list_tags`
Obtiene las etiquetas existentes para sugerir o reutilizar.
* **Parámetros**:
  * `hide_empty` *(boolean, opcional)*: Ocultar etiquetas sin posts. Por defecto `false`.
  * `search` *(string, opcional)*: Filtrar etiquetas por término.
  * `number` *(integer, opcional)*: Límite de etiquetas a retornar (por defecto 50).

### 3. `create_draft_post`
Crea una nueva entrada en WordPress forzando siempre el estado `draft` (borrador).
* **Parámetros**:
  * `title` *(string, obligatorio)*: Título del artículo.
  * `content` *(string, obligatorio)*: Contenido del artículo en HTML limpio o con sintaxis de bloques Gutenberg.
  * `category_id` *(integer o array de integers, opcional)*: ID(s) de categoría(s) existentes.
  * `tags` *(array o string separado por comas, opcional)*: Etiquetas a asociar (si no existen, WordPress las crea).
  * `excerpt` *(string, opcional)*: Extracto o resumen.
  * `slug` *(string, opcional)*: Slug personalizado para la URL.
* **Respuesta**: Retorna el ID del post, la URL para editar en `/wp-admin/post.php?post=ID&action=edit` y el enlace de previsualización.

---

## 🎨 Ejemplo de Contenido en Bloques Gutenberg

El parámetro `content` de `create_draft_post` admite tanto HTML estándar como bloques nativos de WordPress:

```html
<!-- wp:paragraph -->
<p>Este es el párrafo inicial generado por el asistente de IA.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Sección Principal</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
  <li>Primer punto clave</li>
  <li>Segundo punto clave</li>
</ul>
<!-- /wp:list -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><p>Cita relevante sobre el tema.</p></blockquote>
<!-- /wp:quote -->
```

---

## 🔒 Seguridad y Garantías

- **Sin publicación involuntaria**: Todo post creado por este plugin se guarda con `post_status = 'draft'`. Ningún post se publicará sin revisión humana previa.
- **Validación de Capacidad**: Cada llamada a herramienta verifica que el usuario autenticado cuente con el permiso `edit_posts`.
- **CORS Habilitado**: Soporte para orígenes web como `https://gemini.google.com`.
- **Compatibilidad**: Funciona con PHP 7.4, 8.0, 8.1, 8.2, 8.3 y WordPress 5.6+.