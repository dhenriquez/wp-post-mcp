<?php
/**
 * Plugin Name:       WP Post MCP
 * Plugin URI:        https://github.com/dhenriquez/wp-post-mcp
 * Description:       Native Model Context Protocol (MCP) server for WordPress to create draft posts, manage media, read/update posts, and handle custom prompts.
 * Version:           1.1.0
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Daniel Henriquez
 * Author URI:        https://github.com/dhenriquez
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-post-mcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Plugin Constants.
define( 'WP_MCP_VERSION', '1.1.0' );
define( 'WP_MCP_PLUGIN_FILE', __FILE__ );
define( 'WP_MCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_MCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Require includes.
require_once WP_MCP_PLUGIN_DIR . 'includes/class-wp-mcp-auth.php';
require_once WP_MCP_PLUGIN_DIR . 'includes/class-wp-mcp-tools.php';
require_once WP_MCP_PLUGIN_DIR . 'includes/class-wp-mcp-server.php';

/**
 * Register activation hook to add rewrite rules, generate default API key, and flush rewrite rules.
 */
function wp_mcp_activate() {
	wp_mcp_add_rewrite_rules();
	flush_rewrite_rules();

	// Ensure an API key exists.
	if ( ! get_option( 'wp_mcp_api_key' ) ) {
		update_option( 'wp_mcp_api_key', 'wpmcp_' . wp_generate_password( 24, false ) );
	}

	// Initialize default prompts if none exist.
	if ( false === get_option( 'wp_mcp_custom_prompts' ) ) {
		$default_prompts = array(
			array(
				'name'        => 'redactar_post_seo',
				'description' => 'Redacta un post optimizado para SEO y lo guarda automáticamente como borrador.',
				'prompt'      => 'Actúa como un redactor profesional de contenidos en WordPress. Escribe un artículo completo, estructurado con etiquetas Gutenberg (<!-- wp:heading -->, <!-- wp:paragraph -->, <!-- wp:list -->), con introducción atractiva, subtítulos H2/H3 y conclusión. Al finalizar, guárdalo como borrador con create_draft_post.',
			),
			array(
				'name'        => 'mejorar_borrador',
				'description' => 'Lee un borrador existente con read_post, mejora su estilo y lo actualiza con update_draft.',
				'prompt'      => 'Utiliza read_post para obtener el contenido del borrador indicado. Analiza su estructura, corrige errores ortográficos, mejora la fluidez y enriquece la información. Luego, actualiza el borrador llamando a update_draft.',
			),
		);
		update_option( 'wp_mcp_custom_prompts', $default_prompts );
	}
}
register_activation_hook( __FILE__, 'wp_mcp_activate' );

/**
 * Register deactivation hook to clean up rewrite rules.
 */
function wp_mcp_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wp_mcp_deactivate' );

/**
 * Add rewrite rule for /mcp endpoint.
 */
function wp_mcp_add_rewrite_rules() {
	add_rewrite_rule( '^mcp/?$', 'index.php?wp_mcp_endpoint=1', 'top' );
}
add_action( 'init', 'wp_mcp_add_rewrite_rules' );

/**
 * Register query var for /mcp endpoint.
 *
 * @param array $query_vars Existing query vars.
 * @return array Modified query vars.
 */
function wp_mcp_register_query_vars( $query_vars ) {
	$query_vars[] = 'wp_mcp_endpoint';
	return $query_vars;
}
add_filter( 'query_vars', 'wp_mcp_register_query_vars' );

/**
 * Handle incoming requests to /mcp endpoint.
 */
function wp_mcp_handle_endpoint_request() {
	if ( get_query_var( 'wp_mcp_endpoint' ) ) {
		WP_MCP_Server::handle_direct_request();
		exit;
	}
}
add_action( 'template_redirect', 'wp_mcp_handle_endpoint_request', 1 );

/**
 * Register fallback REST API route: /wp-json/mcp/v1/endpoint
 */
function wp_mcp_register_rest_routes() {
	register_rest_route(
		'mcp/v1',
		'/endpoint',
		array(
			'methods'             => array( 'GET', 'POST', 'OPTIONS' ),
			'callback'            => array( 'WP_MCP_Server', 'handle_rest_request' ),
			'permission_callback' => '__return_true', // Authentication is handled via MCP protocol / Basic Auth within handler.
		)
	);
}
add_action( 'rest_api_init', 'wp_mcp_register_rest_routes' );

/**
 * Add admin settings page under Settings > WP Post MCP.
 */
function wp_mcp_admin_menu() {
	add_options_page(
		'WP Post MCP Settings',
		'WP Post MCP',
		'manage_options',
		'wp-post-mcp',
		'wp_mcp_render_admin_page'
	);
}
add_action( 'admin_menu', 'wp_mcp_admin_menu' );

/**
 * Render admin settings page.
 */
function wp_mcp_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$message = '';

	// Handle saving custom API Key or regenerating.
	if ( isset( $_POST['wp_mcp_save_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_mcp_save_nonce'] ) ), 'wp_mcp_save_action' ) ) {
		if ( isset( $_POST['wp_mcp_regenerate'] ) ) {
			$new_key = 'wpmcp_' . wp_generate_password( 24, false );
			update_option( 'wp_mcp_api_key', $new_key );
			$message = '<div class="notice notice-success is-dismissible"><p><strong>¡Nueva Clave API generada con éxito!</strong></p></div>';
		} elseif ( isset( $_POST['wp_mcp_api_key'] ) ) {
			$custom_key = sanitize_text_field( wp_unslash( $_POST['wp_mcp_api_key'] ) );
			if ( ! empty( $custom_key ) ) {
				update_option( 'wp_mcp_api_key', $custom_key );
				$message = '<div class="notice notice-success is-dismissible"><p><strong>Clave API guardada con éxito.</strong></p></div>';
			}
		}
	}

	// Handle Prompts Actions (Add / Delete).
	if ( isset( $_POST['wp_mcp_prompt_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wp_mcp_prompt_nonce'] ) ), 'wp_mcp_prompt_action' ) ) {
		$prompts = get_option( 'wp_mcp_custom_prompts', array() );
		if ( ! is_array( $prompts ) ) {
			$prompts = array();
		}

		if ( isset( $_POST['wp_mcp_add_prompt'] ) ) {
			$prompt_name = sanitize_key( wp_unslash( $_POST['prompt_name'] ?? '' ) );
			$prompt_desc = sanitize_text_field( wp_unslash( $_POST['prompt_description'] ?? '' ) );
			$prompt_text = sanitize_textarea_field( wp_unslash( $_POST['prompt_text'] ?? '' ) );

			if ( ! empty( $prompt_name ) && ! empty( $prompt_text ) ) {
				// Remove existing prompt with same name if exists.
				$prompts = array_values(
					array_filter(
						$prompts,
						function( $p ) use ( $prompt_name ) {
							return $p['name'] !== $prompt_name;
						}
					)
				);

				$prompts[] = array(
					'name'        => $prompt_name,
					'description' => $prompt_desc,
					'prompt'      => $prompt_text,
				);

				update_option( 'wp_mcp_custom_prompts', $prompts );
				$message = '<div class="notice notice-success is-dismissible"><p><strong>Prompt guardado con éxito.</strong></p></div>';
			} else {
				$message = '<div class="notice notice-error is-dismissible"><p><strong>Error:</strong> El nombre y el contenido del prompt son obligatorios.</p></div>';
			}
		} elseif ( isset( $_POST['wp_mcp_delete_prompt'] ) ) {
			$delete_name = sanitize_key( wp_unslash( $_POST['delete_prompt_name'] ?? '' ) );
			$prompts     = array_values(
				array_filter(
					$prompts,
					function( $p ) use ( $delete_name ) {
						return $p['name'] !== $delete_name;
					}
				)
			);
			update_option( 'wp_mcp_custom_prompts', $prompts );
			$message = '<div class="notice notice-success is-dismissible"><p><strong>Prompt eliminado con éxito.</strong></p></div>';
		}
	}

	$current_key = get_option( 'wp_mcp_api_key' );
	if ( empty( $current_key ) ) {
		$current_key = 'wpmcp_' . wp_generate_password( 24, false );
		update_option( 'wp_mcp_api_key', $current_key );
	}

	$prompts = get_option( 'wp_mcp_custom_prompts', array() );
	if ( ! is_array( $prompts ) ) {
		$prompts = array();
	}

	$endpoint_url   = home_url( '/mcp?api_key=' . $current_key );
	$current_user   = wp_get_current_user();
	$app_passwords  = get_user_meta( $current_user->ID, '_application_passwords', true );
	$app_pass_count = is_array( $app_passwords ) ? count( $app_passwords ) : 0;

	echo '<div class="wrap">';
	echo '<h1>⚡ WP Post MCP - Servidor Model Context Protocol</h1>';
	if ( $message ) {
		echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	echo '<div style="background:#fff; padding:20px; border-radius:8px; border:1px solid #ccd0d4; max-width:900px; margin-top:20px;">';
	echo '<h2>📡 URL de Conexión para Gemini Spark / Claude / Cursor</h2>';
	echo '<p>Copia esta URL y pégala en el campo <strong>Server URL</strong> de tu cliente MCP:</p>';

	echo '<div style="display:flex; gap:10px; margin-bottom:20px;">';
	echo '<input type="text" id="wp_mcp_url" readonly value="' . esc_attr( $endpoint_url ) . '" style="width:100%; font-size:14px; padding:8px; font-family:monospace; background:#f6f7f7;" />';
	echo '<button type="button" class="button button-primary" onclick="navigator.clipboard.writeText(document.getElementById(\'wp_mcp_url\').value); alert(\'¡URL copiada al portapapeles!\');">Copiar URL</button>';
	echo '</div>';

	echo '<hr style="margin:20px 0; border:0; border-top:1px solid #eee;" />';

	echo '<h3>🔑 Gestión de Clave API Maestra</h3>';
	echo '<form method="post">';
	wp_nonce_field( 'wp_mcp_save_action', 'wp_mcp_save_nonce' );
	echo '<table class="form-table">';
	echo '<tr><th><label for="wp_mcp_api_key">Clave API:</label></th><td>';
	echo '<input type="text" name="wp_mcp_api_key" id="wp_mcp_api_key" value="' . esc_attr( $current_key ) . '" style="width:350px; font-family:monospace;" /> ';
	echo '<input type="submit" class="button" value="Guardar Clave" /> ';
	echo '<input type="submit" name="wp_mcp_regenerate" class="button" value="Regenerar Clave Aleatoria" onclick="return confirm(\'¿Regenerar la clave? Tendrás que actualizar la URL en Gemini/Claude.\');" />';
	echo '<p class="description">Esta clave autentica automáticamente como Administrador sin depender de contraseñas de aplicación ni verse afectada por plugins de seguridad.</p>';
	echo '</td></tr>';
	echo '</table>';
	echo '</form>';

	echo '<hr style="margin:25px 0; border:0; border-top:1px solid #eee;" />';

	echo '<h3>🧠 Prompts del Sistema para la IA (MCP Prompts)</h3>';
	echo '<p>Define plantillas de instrucciones que la IA puede invocar directamente desde su interfaz MCP.</p>';

	if ( ! empty( $prompts ) ) {
		echo '<table class="widefat striped" style="margin-bottom:20px;">';
		echo '<thead><tr><th style="width:25%;">Nombre</th><th style="width:30%;">Descripción</th><th>Instrucción / Prompt</th><th style="width:10%;">Acción</th></tr></thead>';
		echo '<tbody>';
		foreach ( $prompts as $p ) {
			echo '<tr>';
			echo '<td><code>' . esc_html( $p['name'] ) . '</code></td>';
			echo '<td>' . esc_html( $p['description'] ) . '</td>';
			echo '<td style="font-size:12px; color:#555;">' . esc_html( wp_trim_words( $p['prompt'], 25, '...' ) ) . '</td>';
			echo '<td>';
			echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'¿Eliminar este prompt?\');">';
			wp_nonce_field( 'wp_mcp_prompt_action', 'wp_mcp_prompt_nonce' );
			echo '<input type="hidden" name="delete_prompt_name" value="' . esc_attr( $p['name'] ) . '" />';
			echo '<input type="submit" name="wp_mcp_delete_prompt" class="button button-small button-link-delete" value="Eliminar" />';
			echo '</form>';
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p><em>No hay prompts configurados actualmente.</em></p>';
	}

	echo '<div style="background:#f9f9f9; padding:15px; border-radius:6px; border:1px solid #e5e5e5; margin-top:15px;">';
	echo '<h4 style="margin-top:0;">➕ Agregar / Editar Prompt</h4>';
	echo '<form method="post">';
	wp_nonce_field( 'wp_mcp_prompt_action', 'wp_mcp_prompt_nonce' );
	echo '<table class="form-table" style="margin-top:0;">';
	echo '<tr><th style="width:150px;"><label for="prompt_name">Nombre (slug):</label></th><td><input type="text" name="prompt_name" id="prompt_name" required placeholder="ej: redactor_seo" style="width:280px;" /><p class="description">Identificador único (letras minúsculas y guiones bajos).</p></td></tr>';
	echo '<tr><th><label for="prompt_description">Descripción:</label></th><td><input type="text" name="prompt_description" id="prompt_description" placeholder="ej: Escribe un artículo con tono formal y H2/H3" style="width:400px;" /></td></tr>';
	echo '<tr><th><label for="prompt_text">Instrucción (Prompt):</label></th><td><textarea name="prompt_text" id="prompt_text" rows="4" style="width:100%;" required placeholder="Escribe las directrices completas que seguirá la IA..."></textarea></td></tr>';
	echo '</table>';
	echo '<input type="submit" name="wp_mcp_add_prompt" class="button button-primary" value="Guardar Prompt" />';
	echo '</form>';
	echo '</div>';

	echo '<hr style="margin:25px 0; border:0; border-top:1px solid #eee;" />';

	echo '<h3>🔍 Diagnóstico del Sistema</h3>';
	echo '<table class="widefat striped" style="max-width:650px;">';
	echo '<tr><td><strong>Estado del Endpoint:</strong></td><td><span style="color:green; font-weight:bold;">● Activo</span> (' . esc_html( home_url( '/mcp' ) ) . ')</td></tr>';
	echo '<tr><td><strong>Transporte SSE:</strong></td><td><span style="color:green; font-weight:bold;">● Soportado</span> (Server-Sent Events habilitado)</td></tr>';
	echo '<tr><td><strong>Herramientas MCP:</strong></td><td><span style="color:green; font-weight:bold;">6 disponibles</span> (list_categories, list_tags, create_draft_post, upload_media, read_post, update_draft)</td></tr>';
	echo '<tr><td><strong>Recursos MCP:</strong></td><td><code>wordpress://posts/recent</code> (10 posts recientes)</td></tr>';
	echo '<tr><td><strong>Prompts Activos:</strong></td><td>' . count( $prompts ) . ' configurados</td></tr>';
	echo '<tr><td><strong>Usuario Actual:</strong></td><td>' . esc_html( $current_user->user_login ) . ' (ID: ' . esc_html( $current_user->ID ) . ')</td></tr>';
	echo '<tr><td><strong>Permisos de Edición:</strong></td><td>' . ( current_user_can( 'edit_posts' ) ? '<span style="color:green;">✓ Sí (edit_posts activo)</span>' : '<span style="color:red;">✗ No</span>' ) . '</td></tr>';
	echo '<tr><td><strong>Contraseñas de Aplicación Activas:</strong></td><td>' . esc_html( $app_pass_count ) . ' registradas</td></tr>';
	echo '</table>';

	echo '</div></div>';
}
