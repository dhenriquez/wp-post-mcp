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
						'title'       => array(
							'type'        => 'string',
							'description' => 'The title of the post.',
						),
						'content'     => array(
							'type'        => 'string',
							'description' => 'The body content of the post. Can be clean HTML or WordPress Gutenberg blocks (e.g., <!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->).',
						),
						'category_id' => array(
							'description' => 'Category ID (integer) or array of category IDs to assign to the post.',
							'oneOf'       => array(
								array( 'type' => 'integer' ),
								array(
									'type'  => 'array',
									'items' => array( 'type' => 'integer' ),
								),
							),
						),
						'tags'        => array(
							'description' => 'Array of tag names or comma-separated string of tags. WordPress will automatically create any tags that do not already exist.',
							'oneOf'       => array(
								array( 'type' => 'string' ),
								array(
									'type'  => 'array',
									'items' => array( 'type' => 'string' ),
								),
							),
						),
						'excerpt'     => array(
							'type'        => 'string',
							'description' => 'Optional excerpt or short summary for the post.',
						),
						'slug'        => array(
							'type'        => 'string',
							'description' => 'Optional URL slug for the post.',
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

		$summary_lines = array( "Found " . count( $formatted ) . " categories:" );
		foreach ( $formatted as $item ) {
			$summary_lines[] = sprintf( "- [ID: %d] %s (slug: %s, posts: %d)", $item['id'], $item['name'], $item['slug'], $item['count'] );
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

		$summary_lines = array( "Found " . count( $formatted ) . " tags:" );
		foreach ( $formatted as $item ) {
			$summary_lines[] = sprintf( "- [ID: %d] %s (slug: %s, count: %d)", $item['id'], $item['name'], $item['slug'], $item['count'] );
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
			'id'           => $post_id,
			'status'       => 'draft',
			'title'        => get_the_title( $post_id ),
			'edit_url'     => $edit_link,
			'preview_url'  => $preview_link,
			'categories'   => $assigned_cats,
			'tags'         => $assigned_tags,
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
}
