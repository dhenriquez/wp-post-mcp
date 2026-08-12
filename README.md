# WP Post MCP (WordPress Model Context Protocol Server)

A native WordPress plugin in PHP that exposes a **Model Context Protocol (MCP)** server with full support for **Server-Sent Events (SSE)** and **HTTP JSON-RPC 2.0** at `https://your-site.com/mcp`. It allows AI models such as **Gemini Spark**, **Claude Desktop**, **Cursor**, **Antigravity**, etc., to securely interact with your WordPress site to:

- 📋 **List existing categories & tags** to assign proper taxonomy terms.
- 🖼️ **Upload images via Base64** into the Media Library with complete SEO metadata (Alt Text, Title, Caption, Description) and attach them as `featured_image`.
- 📝 **Create posts strictly in draft status (`draft`)** with full support for clean HTML and native Gutenberg block markup (`<!-- wp:paragraph -->`, `<!-- wp:heading -->`, etc.).
- 📖 **Read existing posts (`read_post`)** with token-optimized sanitized text or HTML.
- ✏️ **Iteratively update drafts (`update_draft`)** with revised text, metadata, or images.
- 🧠 **Custom MCP Prompts** configurable right from the WordPress Admin panel.
- 🌐 **MCP Resources** (such as `wordpress://posts/recent`) to give the AI passive awareness of recent content for internal linking.

---

## 🚀 Installation & Activation

1. Copy the `wp-post-mcp` directory into your WordPress plugins folder:
   ```
   wp-content/plugins/wp-post-mcp/
   ```
2. Navigate to your WordPress Admin dashboard (`WP Admin > Plugins`).
3. Activate the **WP Post MCP** plugin.
4. Go to **Settings > WP Post MCP** to view your ready-to-use connection URL, auto-generated API Key, and manage your AI Prompts.

---

## ⚡ 1-Click Connection with Gemini Spark & Claude

The plugin automatically generates a **Master API Key** that eliminates issues with Application Passwords, username mismatches, or security plugins.

1. In your WordPress dashboard, go to **Settings > WP Post MCP**.
2. Click the **Copy URL** button (you will get a URL like `https://your-site.com/mcp?api_key=wpmcp_xxxxxxx`).
3. In **Gemini Spark** (`gemini.google.com > Settings > Connected Apps > Add a custom app`) or in **Claude**, paste that exact URL into the **Server URL** field.
4. Done! The AI will immediately have access to list categories, discover tags, upload media, draft and update articles.

---

## 🔐 Supported Authentication Methods

The plugin supports multiple authentication mechanisms:

1. **API Key in URL (Recommended)**: `https://your-site.com/mcp?api_key=YOUR_KEY` (configured in Settings > WP Post MCP or via `WP_MCP_API_KEY` constant in `wp-config.php`).
2. **Application Passwords in URL**: `https://your-site.com/mcp?user=USERNAME&app_password=PASSWORD_WITHOUT_SPACES`.
3. **HTTP Basic Auth**: `Authorization: Basic base64(username:application_password)`.
4. **Bearer Token**: `Authorization: Bearer YOUR_API_KEY` or `Bearer base64(username:application_password)`.

---

## 🛠️ Available MCP Tools

### 1. `list_categories`
Retrieves registered WordPress categories.
* **Parameters**:
  * `hide_empty` *(boolean, optional)*: Hide categories without posts. Default: `false`.
  * `search` *(string, optional)*: Filter categories by name query.

### 2. `list_tags`
Retrieves existing post tags for suggestion or reuse.
* **Parameters**:
  * `hide_empty` *(boolean, optional)*: Hide tags without posts. Default: `false`.
  * `search` *(string, optional)*: Filter tags by search term.
  * `number` *(integer, optional)*: Maximum number of tags to return (default: `50`).

### 3. `create_draft_post`
Creates a new post in WordPress strictly in `draft` status.
* **Parameters**:
  * `title` *(string, required)*: The title of the post.
  * `content` *(string, required)*: The post body in clean HTML or WordPress Gutenberg blocks markup.
  * `category_id` *(integer or array of integers, optional)*: Existing category ID(s) to assign.
  * `tags` *(array of strings or comma-separated string, optional)*: Tags to attach.
  * `excerpt` *(string, optional)*: Short summary or excerpt.
  * `slug` *(string, optional)*: Custom URL slug.
  * `featured_image_id` *(integer, optional)*: Media attachment ID to set as featured image.
* **Response**: Returns post ID, admin edit URL (`/wp-admin/post.php?post=ID&action=edit`), and preview URL.

### 4. `upload_media`
Uploads an image encoded in Base64 from the local PC into the WordPress Media Library.
* **Parameters**:
  * `file_base64` *(string, required)*: Base64 string of the image.
  * `filename` *(string, optional)*: Desired filename (e.g. `foto-articulo.jpg`).
  * `alt_text` *(string, optional)*: Alternative text for SEO / accessibility.
  * `title` *(string, optional)*: Title of the image in media library.
  * `caption` *(string, optional)*: Caption text.
  * `description` *(string, optional)*: Detailed image description.
* **Response**: Returns attachment ID (`attachment_id`), direct URL, and metadata summary.

### 5. `read_post`
Reads an existing post (draft or published) by ID.
* **Parameters**:
  * `post_id` *(integer, required)*: ID of the post.
  * `format` *(string, optional, `clean_text` | `html` | `raw`)*: Defaults to `clean_text` to save AI context tokens.
* **Response**: Returns title, status, author, categories, tags, featured image, and content.

### 6. `update_draft`
Updates an existing draft in WordPress.
* **Parameters**:
  * `post_id` *(integer, required)*: ID of the draft post.
  * `title` *(string, optional)*: New title.
  * `content` *(string, optional)*: New content body.
  * `category_id` *(integer or array, optional)*: Categories.
  * `tags` *(array of strings or string, optional)*: Tags.
  * `excerpt` *(string, optional)*: Excerpt.
  * `slug` *(string, optional)*: Slug.
  * `featured_image_id` *(integer, optional)*: Attachment ID to set or replace featured image.

---

## 🧠 MCP Prompts & Resources

### Prompts
You can manage custom prompt templates under **Settings > WP Post MCP**. The plugin includes default prompts for:
- `redactar_post_seo`: Instructs the AI on formatting Gutenberg blocks, headings, and drafting an SEO-optimized article.
- `mejorar_borrador`: Instructs the AI to read a draft, enhance its prose, and update it.

### Resources
- `wordpress://posts/recent`: Returns JSON with the 10 most recent published posts (titles, URLs, and summaries) so the AI can automatically create internal links.

---

## 💻 Configuration for Claude Desktop & Cursor (via stdio bridge)

If your desktop client connects via `stdio` using `mcp-remote`:

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": [
        "-y",
        "mcp-remote",
        "https://your-site.com/mcp?api_key=wpmcp_YOUR_SECRET_KEY"
      ]
    }
  }
}
```

---

## 🎨 Gutenberg Blocks Content Example

The `content` parameter of `create_draft_post` and `update_draft` accepts standard HTML as well as native WordPress block comments:

```html
<!-- wp:paragraph -->
<p>This is the introductory paragraph generated by the AI assistant.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":2} -->
<h2>Main Section</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
  <li>First key takeaway</li>
  <li>Second key takeaway</li>
</ul>
<!-- /wp:list -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><p>A noteworthy quote regarding the topic.</p></blockquote>
<!-- /wp:quote -->
```

---

## 🔒 Security & Guarantees

- **No Accidental Publishing**: Every post created or updated by this plugin is strictly restricted to draft status (`post_status = 'draft'`).
- **Capability Verification**: Every tool invocation verifies that the authenticated user possesses the `edit_posts` capability.
- **CORS Enabled**: Supports cross-origin web clients such as `https://gemini.google.com`.
- **Compatibility**: Works seamlessly with PHP 7.4, 8.0, 8.1, 8.2, 8.3, and WordPress 5.6+.