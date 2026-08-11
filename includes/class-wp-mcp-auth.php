<?php
/**
 * Authentication and authorization handler for WP Post MCP.
 *
 * @package WP_Post_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_MCP_Auth {

	/**
	 * Authenticate the current incoming request.
	 *
	 * Checks for API Key, HTTP Basic Auth, Bearer Auth, and URL Query credentials.
	 *
	 * @return WP_User|WP_Error WP_User if authenticated, WP_Error otherwise.
	 */
	public static function authenticate_request() {
		// If already authenticated via WordPress session/cookie.
		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			if ( self::can_user_edit_posts( $current_user->ID ) ) {
				return $current_user;
			}
			return new WP_Error(
				'mcp_forbidden',
				__( 'The authenticated user does not have permission to edit posts.', 'wp-post-mcp' ),
				array( 'status' => 403 )
			);
		}

		// 1. Check dedicated API Key (?api_key=xxx or WP_MCP_API_KEY constant or option).
		$api_key_user = self::check_api_key_auth();
		if ( $api_key_user instanceof WP_User ) {
			return self::finalize_login( $api_key_user );
		}

		// 2. Check Query Parameters (e.g. for Gemini Spark / web interfaces).
		$query_auth = self::get_query_credentials();
		if ( ! empty( $query_auth ) ) {
			return self::authenticate_credentials( $query_auth['username'], $query_auth['password'] );
		}

		// 3. Check Server direct variables (PHP_AUTH_USER / PHP_AUTH_PW).
		if ( ! empty( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			$username = sanitize_user( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) );
			$password = wp_unslash( $_SERVER['PHP_AUTH_PW'] );
			return self::authenticate_credentials( $username, $password );
		}

		// 4. Check Authorization header (Basic / Bearer).
		$auth_header = self::get_authorization_header();

		if ( empty( $auth_header ) ) {
			return new WP_Error(
				'mcp_unauthorized',
				__( 'Authorization missing. Please provide Basic Auth header or query parameters (?user=xxx&app_password=yyy).', 'wp-post-mcp' ),
				array( 'status' => 401 )
			);
		}

		// Handle Basic Auth.
		if ( 0 === stripos( $auth_header, 'Basic ' ) ) {
			$encoded_creds = substr( $auth_header, 6 );
			$decoded_creds = base64_decode( trim( $encoded_creds ) );

			if ( false === $decoded_creds || false === strpos( $decoded_creds, ':' ) ) {
				return new WP_Error(
					'mcp_malformed_auth',
					__( 'Malformed Basic Auth credentials.', 'wp-post-mcp' ),
					array( 'status' => 401 )
				);
			}

			list( $username, $password ) = explode( ':', $decoded_creds, 2 );
			return self::authenticate_credentials( sanitize_user( $username ), $password );
		}

		// Handle Bearer Auth containing base64(user:pass) or API Key.
		if ( 0 === stripos( $auth_header, 'Bearer ' ) ) {
			$bearer_token = trim( substr( $auth_header, 7 ) );

			// Check if Bearer is an API Key.
			$api_user = self::verify_api_key( $bearer_token );
			if ( $api_user instanceof WP_User ) {
				return self::finalize_login( $api_user );
			}

			// Check if Bearer is base64(user:pass).
			$decoded_creds = base64_decode( $bearer_token );
			if ( false !== $decoded_creds && false !== strpos( $decoded_creds, ':' ) ) {
				list( $username, $password ) = explode( ':', $decoded_creds, 2 );
				return self::authenticate_credentials( sanitize_user( $username ), $password );
			}
		}

		return new WP_Error(
			'mcp_invalid_auth_type',
			__( 'Invalid authorization format. Please provide Basic Auth, Bearer token, or query parameters.', 'wp-post-mcp' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Check if request has a valid API Key.
	 *
	 * @return WP_User|false
	 */
	private static function check_api_key_auth() {
		$key = '';
		if ( ! empty( $_GET['api_key'] ) ) {
			$key = sanitize_text_field( wp_unslash( $_GET['api_key'] ) );
		} elseif ( ! empty( $_GET['token'] ) ) {
			$key = sanitize_text_field( wp_unslash( $_GET['token'] ) );
		}

		if ( empty( $key ) ) {
			return false;
		}

		return self::verify_api_key( $key );
	}

	/**
	 * Verify an API key string against constant or option.
	 *
	 * @param string $key Provided key.
	 * @return WP_User|false
	 */
	public static function verify_api_key( $key ) {
		if ( empty( $key ) ) {
			return false;
		}

		$valid = false;

		// 1. Check WP_MCP_API_KEY constant in wp-config.php.
		if ( defined( 'WP_MCP_API_KEY' ) && ! empty( WP_MCP_API_KEY ) ) {
			if ( hash_equals( (string) WP_MCP_API_KEY, $key ) ) {
				$valid = true;
			}
		}

		// 2. Check saved option in wp_options.
		$saved_option = get_option( 'wp_mcp_api_key', '' );
		if ( ! empty( $saved_option ) && hash_equals( (string) $saved_option, $key ) ) {
			$valid = true;
		}

		if ( $valid ) {
			// Return first administrator user.
			$admins = get_users( array(
				'role'   => 'administrator',
				'number' => 1,
			) );

			if ( ! empty( $admins ) && $admins[0] instanceof WP_User ) {
				return $admins[0];
			}
		}

		return false;
	}

	/**
	 * Extract credentials from URL query parameters.
	 *
	 * @return array|null Array with 'username' and 'password' or null.
	 */
	private static function get_query_credentials() {
		// Option A: ?auth=base64(username:password)
		if ( ! empty( $_GET['auth'] ) ) {
			$decoded = base64_decode( trim( sanitize_text_field( wp_unslash( $_GET['auth'] ) ) ) );
			if ( false !== $decoded && false !== strpos( $decoded, ':' ) ) {
				list( $user, $pass ) = explode( ':', $decoded, 2 );
				return array(
					'username' => sanitize_user( $user ),
					'password' => $pass,
				);
			}
		}

		// Option B: ?user=username&app_password=xxxx or ?username=username&password=xxxx
		$username = '';
		if ( ! empty( $_GET['user'] ) ) {
			$username = sanitize_user( wp_unslash( $_GET['user'] ) );
		} elseif ( ! empty( $_GET['username'] ) ) {
			$username = sanitize_user( wp_unslash( $_GET['username'] ) );
		}

		$password = '';
		if ( ! empty( $_GET['app_password'] ) ) {
			$password = wp_unslash( $_GET['app_password'] );
		} elseif ( ! empty( $_GET['password'] ) ) {
			$password = wp_unslash( $_GET['password'] );
		}

		if ( ! empty( $password ) ) {
			return array(
				'username' => $username,
				'password' => $password,
			);
		}

		return null;
	}

	/**
	 * Authenticate user by username and password (supporting Application Passwords directly & nativamente).
	 *
	 * @param string $username Username or email.
	 * @param string $password Application password or user password.
	 * @return WP_User|WP_Error
	 */
	public static function authenticate_credentials( $username, $password ) {
		if ( empty( $password ) ) {
			return new WP_Error(
				'mcp_empty_credentials',
				__( 'Password cannot be empty.', 'wp-post-mcp' ),
				array( 'status' => 401 )
			);
		}

		// Clean and normalize password.
		$clean_password = preg_replace( '/[^a-z0-9]/i', '', $password );

		// Check if password itself is a valid API Key.
		$api_user = self::verify_api_key( $password );
		if ( ! $api_user && $clean_password !== $password ) {
			$api_user = self::verify_api_key( $clean_password );
		}
		if ( $api_user instanceof WP_User ) {
			return self::finalize_login( $api_user );
		}

		// 1. Direct user lookup by username, email, slug, nicename.
		if ( ! empty( $username ) ) {
			$user = get_user_by( 'login', $username );
			if ( ! $user ) {
				$user = get_user_by( 'email', $username );
			}
			if ( ! $user ) {
				$user = get_user_by( 'slug', $username );
			}

			if ( $user instanceof WP_User && self::check_user_app_password( $user, $clean_password, $password ) ) {
				return self::finalize_login( $user );
			}
		}

		// 2. Global lookup: search application passwords across all authorized users.
		$users = get_users(
			array(
				'capability' => 'edit_posts',
				'number'     => 50,
			)
		);

		foreach ( $users as $candidate_user ) {
			if ( self::check_user_app_password( $candidate_user, $clean_password, $password ) ) {
				return self::finalize_login( $candidate_user );
			}
		}

		// 3. Native WP Application Passwords hook.
		if ( ! empty( $username ) && function_exists( 'wp_authenticate_application_password' ) ) {
			$auth_user = wp_authenticate_application_password( null, $username, $password );
			if ( is_wp_error( $auth_user ) || null === $auth_user ) {
				$auth_user = wp_authenticate_application_password( null, $username, $clean_password );
			}
			if ( $auth_user instanceof WP_User && $auth_user->ID > 0 ) {
				return self::finalize_login( $auth_user );
			}
		}

		// 4. Standard account password fallback.
		if ( ! empty( $username ) ) {
			$auth_user = wp_authenticate( $username, $password );
			if ( $auth_user instanceof WP_User && $auth_user->ID > 0 ) {
				return self::finalize_login( $auth_user );
			}
		}

		// Provide helpful debug diagnostics if requested.
		$debug_info = '';
		if ( isset( $_GET['debug'] ) ) {
			$user_found = ! empty( $username ) ? (bool) ( get_user_by( 'login', $username ) || get_user_by( 'email', $username ) ) : false;
			$debug_info = sprintf( ' [Debug: username_provided="%s", user_exists=%s, app_pass_length=%d]', esc_html( $username ), $user_found ? 'yes' : 'no', strlen( $clean_password ) );
		}

		return new WP_Error(
			'mcp_invalid_credentials',
			__( 'Invalid credentials provided. Check username and application password.', 'wp-post-mcp' ) . $debug_info,
			array( 'status' => 401 )
		);
	}

	/**
	 * Verify an application password against a specific user.
	 *
	 * @param WP_User $user User object.
	 * @param string  $clean_password Alphanumeric password without spaces.
	 * @param string  $raw_password Raw password string.
	 * @return bool
	 */
	private static function check_user_app_password( $user, $clean_password, $raw_password ) {
		$app_passwords = get_user_meta( $user->ID, '_application_passwords', true );
		if ( ! is_array( $app_passwords ) || empty( $app_passwords ) ) {
			return false;
		}

		foreach ( $app_passwords as $entry ) {
			if ( ! empty( $entry['password'] ) ) {
				if ( wp_check_password( $clean_password, $entry['password'], $user->ID ) ||
					 wp_check_password( $raw_password, $entry['password'], $user->ID ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Finalize login and check user capability.
	 *
	 * @param WP_User $user User object.
	 * @return WP_User|WP_Error
	 */
	private static function finalize_login( $user ) {
		wp_set_current_user( $user->ID );

		if ( ! self::can_user_edit_posts( $user->ID ) ) {
			return new WP_Error(
				'mcp_forbidden',
				__( 'User does not have required permissions (edit_posts).', 'wp-post-mcp' ),
				array( 'status' => 403 )
			);
		}

		return $user;
	}

	/**
	 * Check if user has permission to edit posts.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function can_user_edit_posts( $user_id ) {
		return user_can( $user_id, 'edit_posts' );
	}

	/**
	 * Extract Authorization header across various server environments.
	 *
	 * @return string|null
	 */
	public static function get_authorization_header() {
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
		}

		if ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
		}

		if ( function_exists( 'apache_request_headers' ) ) {
			$headers = apache_request_headers();
			if ( ! empty( $headers['Authorization'] ) ) {
				return sanitize_text_field( $headers['Authorization'] );
			}
			if ( ! empty( $headers['authorization'] ) ) {
				return sanitize_text_field( $headers['authorization'] );
			}
		}

		return null;
	}
}
