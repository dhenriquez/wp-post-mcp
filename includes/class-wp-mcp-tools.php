<?php
/**
 * MCP Tools definitions and handlers for WP Post MCP.
 *
 * @package WP_Post_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_MCP_Tools {

	/**
	 * Get the list of all available MCP tools with their JSON schemas.
	 *
	 * @return array
	 */
	public static function get_tool_definitions() {
		return array(
			array(
				'name'        => 'list_categories',
				'description' => 'Retrieves existing categories in WordPress so you can choose an existing category ID for a post.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'hide_empty' => array(
							'type'        => 'boolean',
							'description' => 'Whether to hide categories that have no posts attached. Defaults to false.',
							'default'     => false,
						),
						'search'     => array(
							'type'        => 'string',
							'description' => 'Optional search query to filter categories by name.',
						),
					),
				),
			),
			array(
				'name'        => 'list_tags',
				'description' => 'Retrieves existing post tags from WordPress for discovery and suggestions.',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'hide_empty' => array(
							'type'        => 'boolean',
							'description' => 'Whether to hide tags with zero posts. Defaults to false.',
							'default'     => false,
						),
						'search'     => array(
							'type'        => 'string',
							'description' => 'Optional search term to filter tags.',
						),
						'number'     => array(
							'type'        => 'integer',
							'description' => 'Maximum number of tags to return. Defaults to 50.',
							'default'     => 50,
						),
					),
				),
			),
			array(
				'name'        => 'create_draft_post',
				'description' => 'Creates a new post in WordPress strictly in "draft" status. Supports full HTML and Gutenberg block markup in content.',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'title', 'content' ),
					'properties' => array(
						'title'             => array(
							'type'        => 'string',
							'description' => 'The title of the post.',
						),
						'content'           => array(
							'type'        => 'string',
							'description' => 'The body content of the post. Can be clean HTML or WordPress Gutenberg blocks (e.g., <!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->).',
						),
						'category_id'       => array(
							'description' => 'Category ID (integer) or array of category IDs to assign to the post.',
							'oneOf'       => array(
								array( 'type' => 'integer' ),
								array(
									'type'  => 'array',
									'items' => array( 'type' => 'integer' ),
								),
							),
						),
						'tags'              => array(
							'description' => 'Array of tag names or comma-separated string of tags. WordPress will automatically create any tags that do not already exist.',
							'oneOf'       => array(
								array( 'type' => 'string' ),
								array(
									'type'  => 'array',
									'items' => array( 'type' => 'string' ),
								),
							),
						),
						'excerpt'           => array(
							'type'        => 'string',
							'description' => 'Optional excerpt or short summary for the post.',
						),
						'slug'              => array(
							'type'        => 'string',
							'description' => 'Optional URL slug for the post.',
						),
						'featured_image_id' => array(
							'type'        => 'integer',
							'description' => 'Optional attachment ID from upload_media to set as featured image (post thumbnail).',
						),
					),
				),
			),
			array(
				'name'        => 'upload_media',
				'description' => 'Uploads an image from the user PC / AI client into the WordPress Media Library via Base64. Sets Title, Alt Text, Caption, and Description.',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'file_base64' ),
					'properties' => array(
						'file_base64' => array(
							'type'        => 'string',
							'description' => 'Base64-encoded image data string (can include or omit the data:image/...;base64, prefix).',
						),
						'filename'    => array(
							'type'        => 'string',
							'description' => 'Optional filename with extension (e.g., "articulo-imagen.jpg"). If omitted, an appropriate filename will be auto-generated.',
						),
						'alt_text'    => array(
							'type'        => 'string',
							'description' => 'Alternative text for accessibility and SEO describing the image content.',
						),
						'title'       => array(
							'type'        => 'string',
							'description' => 'Title of the image attachment in WordPress.',
						),
						'caption'     => array(
							'type'        => 'string',
							'description' => 'Caption text displayed beneath the image.',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Detailed description of the image stored in the media library.',
						),
					),
				),
			),
			array(
				'name'        => 'read_post',
				'description' => 'Retrieves the content, title, excerpt, metadata, and taxonomy terms of an existing WordPress post (draft or published) by ID.',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'post_id' ),
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'The ID of the post to retrieve.',
						),
						'format'  => array(
							'type'        => 'string',
							'enum'        => array( 'clean_text', 'html', 'raw' ),
							'description' => 'Format of post content. "clean_text" strips tags to save AI context tokens. "html" or "raw" returns original block markup/HTML. Defaults to "clean_text".',
							'default'     => 'clean_text',
						),
					),
				),
			),
			array(
				'name'        => 'update_draft',
				'description' => 'Updates an existing draft post in WordPress. Allows updating title, content, excerpt, category, tags, and featured image.',
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'post_id' ),
					'properties' => array(
						'post_id'           => array(
							'type'        => 'integer',
							'description' => 'The ID of the draft post to update.',
						),
						'title'             => array(
							'type'        => 'string',
							'description' => 'New title for the post.',
						),
						'content'           => array(
							'type'        => 'string',
							'description' => 'New body content in HTML or Gutenberg block markup.',
						),
						'category_id'       => array(
							'description' => 'Category ID (integer) or array of category IDs to assign.',
							'oneOf'       => array(
								array( 'type' => 'integer' ),
								array(
									'type'  => 'array',
									'items' => array( 'type' => 'integer' ),
								),
							),
						),
						'tags'              => array(
							'description' => 'Array of tag names or comma-separated string of tags.',
							'oneOf'       => array(
								array( 'type' => 'string' ),
								array(
									'type'  => 'array',
									'items' => array( 'type' => 'string' ),
								),
							),
						),
						'excerpt'           => array(
							'type'        => 'string',
							'description' => 'New excerpt for the post.',
						),
						'slug'              => array(
							'type'        => 'string',
							'description' => 'New URL slug for the post.',
						),
						'featured_image_id' => array(
							'type'        => 'integer',
							'description' => 'Attachment ID from upload_media to set as featured image.',
						),
					),
				),
			),
		);
	}

	/**
	 * Call an MCP tool by name.
	 *
	 * @param string $tool_name Name of the tool.
	 * @param array  $arguments Arguments passed to the tool.
	 * @return array MCP content result.
	 * @throws Exception If tool is not found or arguments are invalid.
	 */
	public static function execute_tool( $tool_name, $arguments = array() ) {
		if ( ! is_array( $arguments ) ) {
			$arguments = array();
		}

		switch ( $tool_name ) {
			case 'list_categories':
				return self::execute_list_categories( $arguments );

			case 'list_tags':
				return self::execute_list_tags( $arguments );

			case 'create_draft_post':
				return self::execute_create_draft_post( $arguments );

			case 'upload_media':
				return self::execute_upload_media( $arguments );

			case 'read_post':
				return self::execute_read_post( $arguments );

			case 'update_draft':
				return self::execute_update_draft( $arguments );

			default:
				throw new Exception( sprintf( 'Unknown tool: %s', esc_html( $tool_name ) ) );
		}
	}

	/**
	 * Handler for list_categories tool.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	private static function execute_list_categories( $args ) {
		$hide_empty = isset( $args['hide_empty'] ) ? (bool) $args['hide_empty'] : false;
		$search     = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';

		$cat_args = array(
			'taxonomy'   => 'category',
			'hide_empty' => $hide_empty,
		);

		if ( ! empty( $search ) ) {
			$cat_args['search'] = $search;
		}

		$categories = get_categories( $cat_args );

		if ( is_wp_error( $categories ) ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Error retrieving categories: ' . $categories->get_error_message(),
					),
				),
				'isError' => true,
			);
		}

		$formatted = array();
		foreach ( $categories as $cat ) {
			$formatted[] = array(
				'id'          => (int) $cat->term_id,
				'name'        => $cat->name,
				'slug'        => $cat->slug,
				'count'       => (int) $cat->count,
				'parent'      => (int) $cat->parent,
				'description' => $cat->description,
			);
		}

		$json_result = wp_json_encode( $formatted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		$summary_lines = array( 'Found ' . count( $formatted ) . ' categories:' );
		foreach ( $formatted as $item ) {
			$summary_lines[] = sprintf( '- [ID: %d] %s (slug: %s, posts: %d)', $item['id'], $item['name'], $item['slug'], $item['count'] );
		}

		$text_output = implode( "\n", $summary_lines ) . "\n\nRaw JSON Data:\n" . $json_result;

		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $text_output,
				),
			),
			'isError' => false,
		);
	}

	/**
	 * Handler for list_tags tool.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	private static function execute_list_tags( $args ) {
		$hide_empty = isset( $args['hide_empty'] ) ? (bool) $args['hide_empty'] : false;
		$search     = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';
		$number     = isset( $args['number'] ) ? absint( $args['number'] ) : 50;

		$tag_args = array(
			'taxonomy'   => 'post_tag',
			'hide_empty' => $hide_empty,
			'number'     => $number,
		);

		if ( ! empty( $search ) ) {
			$tag_args['search'] = $search;
		}

		$tags = get_tags( $tag_args );

		if ( is_wp_error( $tags ) ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Error retrieving tags: ' . $tags->get_error_message(),
					),
				),
				'isError' => true,
			);
		}

		$formatted = array();
		foreach ( $tags as $tag ) {
			$formatted[] = array(
				'id'    => (int) $tag->term_id,
				'name'  => $tag->name,
				'slug'  => $tag->slug,
				'count' => (int) $tag->count,
			);
		}

		$json_result = wp_json_encode( $formatted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		$summary_lines = array( 'Found ' . count( $formatted ) . ' tags:' );
		foreach ( $formatted as $item ) {
			$summary_lines[] = sprintf( '- [ID: %d] %s (slug: %s, count: %d)', $item['id'], $item['name'], $item['slug'], $item['count'] );
		}

		$text_output = implode( "\n", $summary_lines ) . "\n\nRaw JSON Data:\n" . $json_result;

		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $text_output,
				),
			),
			'isError' => false,
		);
	}

	/**
	 * Handler for create_draft_post tool.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	private static function execute_create_draft_post( $args ) {
		$title   = isset( $args['title'] ) ? trim( (string) $args['title'] ) : '';
		$content = isset( $args['content'] ) ? (string) $args['content'] : '';

		if ( empty( $title ) ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Validation error: "title" parameter is required and cannot be empty.',
					),
				),
				'isError' => true,
			);
		}

		if ( empty( $content ) ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Validation error: "content" parameter is required and cannot be empty.',
					),
				),
				'isError' => true,
			);
		}

		$post_data = array(
			'post_title'   => wp_strip_all_tags( $title ),
			'post_content' => $content,
			'post_status'  => 'draft', // Strictly draft status.
			'post_type'    => 'post',
		);

		// Post author: assign current authenticated user.
		$current_user_id = get_current_user_id();
		if ( $current_user_id > 0 ) {
			$post_data['post_author'] = $current_user_id;
		}

		// Optional excerpt.
		if ( ! empty( $args['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $args['excerpt'] );
		}

		// Optional slug.
		if ( ! empty( $args['slug'] ) ) {
			$post_data['post_name'] = sanitize_title( $args['slug'] );
		}

		// Optional categories.
		if ( isset( $args['category_id'] ) ) {
			$cats = $args['category_id'];
			if ( is_numeric( $cats ) ) {
				$post_data['post_category'] = array( absint( $cats ) );
			} elseif ( is_array( $cats ) ) {
				$post_data['post_category'] = array_map( 'absint', $cats );
			}
		}

		// Optional tags.
		if ( ! empty( $args['tags'] ) ) {
			$tags = $args['tags'];
			if ( is_array( $tags ) ) {
				$post_data['tags_input'] = array_map( 'sanitize_text_field', $tags );
			} elseif ( is_string( $tags ) ) {
				$post_data['tags_input'] = sanitize_text_field( $tags );
			}
		}

		// Insert post into WordPress.
		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Failed to create draft post: ' . $post_id->get_error_message(),
					),
				),
				'isError' => true,
			);
		}

		// Optional featured image.
		if ( ! empty( $args['featured_image_id'] ) ) {
			set_post_thumbnail( $post_id, absint( $args['featured_image_id'] ) );
		}

		// Retrieve admin edit and preview links.
		$edit_link    = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
		$preview_link = get_preview_post_link( $post_id );
		if ( empty( $preview_link ) ) {
			$preview_link = get_permalink( $post_id );
		}

		// Retrieve assigned category names for confirmation.
		$assigned_cats = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
		$assigned_tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );

		$response_data = array(
			'id'          => $post_id,
			'status'      => 'draft',
			'title'       => get_the_title( $post_id ),
			'edit_url'    => $edit_link,
			'preview_url' => $preview_link,
			'categories'  => $assigned_cats,
			'tags'        => $assigned_tags,
		);

		$message = sprintf(
			"✅ Draft post created successfully!\n\n" .
			"- **ID**: %d\n" .
			"- **Title**: %s\n" .
			"- **Status**: draft\n" .
			"- **Categories**: %s\n" .
			"- **Tags**: %s\n" .
			"- **Admin Edit URL**: %s\n" .
			"- **Preview URL**: %s\n\n" .
			"JSON Summary:\n%s",
			$post_id,
			$post_data['post_title'],
			! empty( $assigned_cats ) ? implode( ', ', $assigned_cats ) : 'None',
			! empty( $assigned_tags ) ? implode( ', ', $assigned_tags ) : 'None',
			$edit_link,
			$preview_link,
			wp_json_encode( $response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
		);

		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $message,
				),
			),
			'isError' => false,
		);
	}

	/**
	 * Handler for upload_media tool.
	 *
	 * Accepts base64 encoded image string, writes temporary file, and uses media_handle_sideload.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	private static function execute_upload_media( $args ) {
		$base64 = isset( $args['file_base64'] ) ? (string) $args['file_base64'] : '';

		if ( empty( $base64 ) ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Validation error: "file_base64" parameter is required and cannot be empty.',
					),
				),
				'isError' => true,
			);
		}

		// Strip data URI scheme prefix if present (e.g. data:image/png;base64,...).
		$mime_match = '';
		if ( preg_match( '/^data:(image\/[a-zA-Z0-9\+\.\-]+);base64,/', $base64, $matches ) ) {
			$mime_match = $matches[1];
			$base64     = substr( $base64, strlen( $matches[0] ) );
		}

		$binary_data = base64_decode( $base64, true );
		if ( false === $binary_data || empty( $binary_data ) ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Validation error: Invalid Base64 encoded image string.',
					),
				),
				'isError' => true,
			);
		}

		// Determine filename and extension.
		$filename = isset( $args['filename'] ) ? sanitize_file_name( $args['filename'] ) : '';
		if ( empty( $filename ) ) {
			$ext = 'jpg';
			if ( 'image/png' === $mime_match ) {
				$ext = 'png';
			} elseif ( 'image/gif' === $mime_match ) {
				$ext = 'gif';
			} elseif ( 'image/webp' === $mime_match ) {
				$ext = 'webp';
			}
			$filename = 'mcp-upload-' . time() . '-' . wp_generate_password( 6, false ) . '.' . $ext;
		}

		// Write to temporary file.
		$tmp_file = wp_tempnam( $filename );
		if ( ! $tmp_file ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Server error: Could not create temporary file for upload.',
					),
				),
				'isError' => true,
			);
		}

		file_put_contents( $tmp_file, $binary_data );

		// Ensure WordPress media upload functions are loaded.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp_file,
		);

		// Upload image to media library.
		$attachment_id = media_handle_sideload( $file_array, 0 );

		// Clean up temporary file if still exists.
		if ( file_exists( $tmp_file ) ) {
			@unlink( $tmp_file );
		}

		if ( is_wp_error( $attachment_id ) ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Failed to upload media to WordPress: ' . $attachment_id->get_error_message(),
					),
				),
				'isError' => true,
			);
		}

		// Update Attachment Metadata: Alt Text, Title, Caption, Description.
		$alt_text    = isset( $args['alt_text'] ) ? sanitize_text_field( $args['alt_text'] ) : '';
		$title       = isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : '';
		$caption     = isset( $args['caption'] ) ? sanitize_textarea_field( $args['caption'] ) : '';
		$description = isset( $args['description'] ) ? sanitize_textarea_field( $args['description'] ) : '';

		if ( ! empty( $alt_text ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
		}

		$attachment_post_update = array(
			'ID' => $attachment_id,
		);
		if ( ! empty( $title ) ) {
			$attachment_post_update['post_title'] = $title;
		}
		if ( ! empty( $caption ) ) {
			$attachment_post_update['post_excerpt'] = $caption;
		}
		if ( ! empty( $description ) ) {
			$attachment_post_update['post_content'] = $description;
		}

		if ( count( $attachment_post_update ) > 1 ) {
			wp_update_post( $attachment_post_update );
		}

		$image_url = wp_get_attachment_url( $attachment_id );

		$response_data = array(
			'attachment_id' => $attachment_id,
			'url'           => $image_url,
			'title'         => ! empty( $title ) ? $title : get_the_title( $attachment_id ),
			'alt_text'      => $alt_text,
			'caption'       => $caption,
			'description'   => $description,
		);

		$message = sprintf(
			"✅ Media uploaded successfully to WordPress Library!\n\n" .
			"- **Attachment ID**: %d\n" .
			"- **URL**: %s\n" .
			"- **Alt Text**: %s\n" .
			"- **Title**: %s\n" .
			"- **Caption**: %s\n" .
			"- **Description**: %s\n\n" .
			"JSON Summary:\n%s",
			$attachment_id,
			$image_url,
			! empty( $alt_text ) ? $alt_text : '(none)',
			! empty( $title ) ? $title : get_the_title( $attachment_id ),
			! empty( $caption ) ? $caption : '(none)',
			! empty( $description ) ? $description : '(none)',
			wp_json_encode( $response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);

		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $message,
				),
			),
			'isError' => false,
		);
	}

	/**
	 * Handler for read_post tool.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	private static function execute_read_post( $args ) {
		$post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		$format  = isset( $args['format'] ) ? sanitize_text_field( $args['format'] ) : 'clean_text';

		if ( $post_id <= 0 ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Validation error: Valid "post_id" is required.',
					),
				),
				'isError' => true,
			);
		}

		$post = get_post( $post_id );
		if ( ! $post || ! ( $post instanceof WP_Post ) ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => sprintf( 'Post with ID %d not found.', $post_id ),
					),
				),
				'isError' => true,
			);
		}

		$cats            = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
		$tags            = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
		$thumbnail_url   = get_the_post_thumbnail_url( $post_id, 'full' );
		$author_data     = get_userdata( $post->post_author );
		$author_name     = $author_data ? $author_data->display_name : 'Unknown';

		// Format content according to requested format.
		$content = $post->post_content;
		if ( 'clean_text' === $format ) {
			$content = wp_strip_all_tags( $content );
		}

		$post_info = array(
			'id'             => $post->ID,
			'title'          => $post->post_title,
			'status'         => $post->post_status,
			'slug'           => $post->post_name,
			'author'         => $author_name,
			'date'           => $post->post_date,
			'modified'       => $post->post_modified,
			'excerpt'        => $post->post_excerpt,
			'categories'     => $cats,
			'tags'           => $tags,
			'featured_image' => $thumbnail_url ? $thumbnail_url : null,
			'preview_url'    => get_preview_post_link( $post->ID ) ? get_preview_post_link( $post->ID ) : get_permalink( $post->ID ),
			'content'        => $content,
		);

		$message = sprintf(
			"📄 **Post Details (ID: %d)**\n\n" .
			"- **Title**: %s\n" .
			"- **Status**: %s\n" .
			"- **Author**: %s\n" .
			"- **Categories**: %s\n" .
			"- **Tags**: %s\n" .
			"- **Featured Image**: %s\n\n" .
			"### Content:\n%s",
			$post->ID,
			$post->post_title,
			$post->post_status,
			$author_name,
			! empty( $cats ) ? implode( ', ', $cats ) : 'None',
			! empty( $tags ) ? implode( ', ', $tags ) : 'None',
			$thumbnail_url ? $thumbnail_url : 'None',
			$content
		);

		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $message,
				),
			),
			'isError' => false,
		);
	}

	/**
	 * Handler for update_draft tool.
	 *
	 * @param array $args Arguments.
	 * @return array
	 */
	private static function execute_update_draft( $args ) {
		$post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;

		if ( $post_id <= 0 ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Validation error: Valid "post_id" is required.',
					),
				),
				'isError' => true,
			);
		}

		$post = get_post( $post_id );
		if ( ! $post || ! ( $post instanceof WP_Post ) ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => sprintf( 'Post with ID %d not found.', $post_id ),
					),
				),
				'isError' => true,
			);
		}

		$post_data = array(
			'ID' => $post_id,
		);

		if ( isset( $args['title'] ) && '' !== trim( (string) $args['title'] ) ) {
			$post_data['post_title'] = wp_strip_all_tags( $args['title'] );
		}

		if ( isset( $args['content'] ) ) {
			$post_data['post_content'] = (string) $args['content'];
		}

		if ( isset( $args['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_textarea_field( $args['excerpt'] );
		}

		if ( ! empty( $args['slug'] ) ) {
			$post_data['post_name'] = sanitize_title( $args['slug'] );
		}

		// Optional categories.
		if ( isset( $args['category_id'] ) ) {
			$cats = $args['category_id'];
			if ( is_numeric( $cats ) ) {
				$post_data['post_category'] = array( absint( $cats ) );
			} elseif ( is_array( $cats ) ) {
				$post_data['post_category'] = array_map( 'absint', $cats );
			}
		}

		// Optional tags.
		if ( ! empty( $args['tags'] ) ) {
			$tags = $args['tags'];
			if ( is_array( $tags ) ) {
				$post_data['tags_input'] = array_map( 'sanitize_text_field', $tags );
			} elseif ( is_string( $tags ) ) {
				$post_data['tags_input'] = sanitize_text_field( $tags );
			}
		}

		// Ensure status remains draft to prevent accidental publish.
		$post_data['post_status'] = 'draft';

		$updated_id = wp_update_post( $post_data, true );

		if ( is_wp_error( $updated_id ) ) {
			return array(
				'content' => array(
					array(
						'type' => 'text',
						'text' => 'Failed to update draft: ' . $updated_id->get_error_message(),
					),
				),
				'isError' => true,
			);
		}

		// Handle featured image update if passed.
		if ( isset( $args['featured_image_id'] ) ) {
			$featured_id = absint( $args['featured_image_id'] );
			if ( $featured_id > 0 ) {
				set_post_thumbnail( $post_id, $featured_id );
			} else {
				delete_post_thumbnail( $post_id );
			}
		}

		$edit_link    = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
		$preview_link = get_preview_post_link( $post_id );
		if ( empty( $preview_link ) ) {
			$preview_link = get_permalink( $post_id );
		}

		$assigned_cats = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
		$assigned_tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );

		$response_data = array(
			'id'          => $post_id,
			'status'      => 'draft',
			'title'       => get_the_title( $post_id ),
			'edit_url'    => $edit_link,
			'preview_url' => $preview_link,
			'categories'  => $assigned_cats,
			'tags'        => $assigned_tags,
		);

		$message = sprintf(
			"✅ Draft post updated successfully!\n\n" .
			"- **ID**: %d\n" .
			"- **Title**: %s\n" .
			"- **Status**: draft\n" .
			"- **Categories**: %s\n" .
			"- **Tags**: %s\n" .
			"- **Admin Edit URL**: %s\n" .
			"- **Preview URL**: %s\n\n" .
			"JSON Summary:\n%s",
			$post_id,
			get_the_title( $post_id ),
			! empty( $assigned_cats ) ? implode( ', ', $assigned_cats ) : 'None',
			! empty( $assigned_tags ) ? implode( ', ', $assigned_tags ) : 'None',
			$edit_link,
			$preview_link,
			wp_json_encode( $response_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE )
		);

		return array(
			'content' => array(
				array(
					'type' => 'text',
					'text' => $message,
				),
			),
			'isError' => false,
		);
	}
}
