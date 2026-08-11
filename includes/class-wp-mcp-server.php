<?php
/**
 * MCP Server and JSON-RPC 2.0 Dispatcher for WP Post MCP.
 *
 * @package WP_Post_MCP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_MCP_Server {

	const MCP_PROTOCOL_VERSION = '2024-11-05';

	/**
	 * Send standard CORS and Content-Type headers for MCP over HTTP/SSE.
	 */
	public static function send_headers() {
		if ( ! headers_sent() ) {
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS, HEAD' );
			header( 'Access-Control-Allow-Headers: Authorization, Content-Type, Accept, X-Requested-With, mcp-session-id, Last-Event-ID, Cache-Control' );
			header( 'Access-Control-Expose-Headers: *' );
			header( 'Access-Control-Allow-Credentials: true' );
		}
	}

	/**
	 * Determine whether the incoming request is asking for an SSE stream.
	 *
	 * @return bool
	 */
	public static function is_sse_request() {
		if ( isset( $_GET['sse'] ) ) {
			return true;
		}

		$accept = isset( $_SERVER['HTTP_ACCEPT'] ) ? (string) $_SERVER['HTTP_ACCEPT'] : '';
		if ( false !== stripos( $accept, 'text/event-stream' ) ) {
			return true;
		}

		// Also check user agents like Gemini Spark or cloud agents that send Accept: */* on GET.
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
		if ( false !== stripos( $user_agent, 'Google-Gemini' ) || false !== stripos( $user_agent, 'mcp' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Handle direct HTTP request to /mcp endpoint.
	 */
	public static function handle_direct_request() {
		self::send_headers();

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET';

		if ( 'OPTIONS' === $method ) {
			status_header( 204 );
			exit;
		}

		if ( 'GET' === $method ) {
			// Handle Server-Sent Events (SSE) connection handshake for Gemini Spark, Claude, & remote MCP clients.
			if ( self::is_sse_request() || ! isset( $_GET['format'] ) || 'sse' === $_GET['format'] ) {
				self::serve_sse_endpoint( home_url( '/mcp' ) );
				exit;
			}

			// Informative JSON status for browser navigation.
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode(
				array(
					'status'          => 'online',
					'server'          => 'wp-post-mcp',
					'version'         => WP_MCP_VERSION,
					'protocolVersion' => self::MCP_PROTOCOL_VERSION,
					'endpoint'        => home_url( '/mcp' ),
					'sse_endpoint'    => home_url( '/mcp?sse=1' ),
					'message'         => 'WP Post MCP server is running. Supports MCP SSE and Streamable HTTP JSON-RPC 2.0.',
				),
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
			);
			exit;
		}

		if ( 'POST' !== $method ) {
			status_header( 405 );
			header( 'Content-Type: application/json; charset=utf-8' );
			echo wp_json_encode(
				array(
					'jsonrpc' => '2.0',
					'error'   => array(
						'code'    => -32600,
						'message' => 'Method Not Allowed. Only POST, GET, and OPTIONS are supported.',
					),
				)
			);
			exit;
		}

		header( 'Content-Type: application/json; charset=utf-8' );
		$raw_input = file_get_contents( 'php://input' );
		$response  = self::process_jsonrpc_payload( $raw_input );

		if ( null !== $response ) {
			echo wp_json_encode( $response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}
		exit;
	}

	/**
	 * Handle REST API request callback (/wp-json/mcp/v1/endpoint).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|void
	 */
	public static function handle_rest_request( $request ) {
		self::send_headers();
		$method = $request->get_method();

		if ( 'OPTIONS' === $method ) {
			return new WP_REST_Response( null, 204 );
		}

		if ( 'GET' === $method ) {
			if ( self::is_sse_request() || ! isset( $_GET['format'] ) || 'sse' === $_GET['format'] ) {
				self::serve_sse_endpoint( rest_url( 'mcp/v1/endpoint' ) );
				exit;
			}

			return new WP_REST_Response(
				array(
					'status'          => 'online',
					'server'          => 'wp-post-mcp',
					'version'         => WP_MCP_VERSION,
					'protocolVersion' => self::MCP_PROTOCOL_VERSION,
					'endpoint'        => rest_url( 'mcp/v1/endpoint' ),
					'sse_endpoint'    => rest_url( 'mcp/v1/endpoint?sse=1' ),
					'message'         => 'WP Post MCP server is running.',
				),
				200
			);
		}

		$raw_input = $request->get_body();
		$response  = self::process_jsonrpc_payload( $raw_input );

		if ( null === $response ) {
			return new WP_REST_Response( null, 204 );
		}

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Stream Server-Sent Events (SSE) handshake with event: endpoint.
	 *
	 * @param string $base_endpoint_url Base endpoint URL.
	 */
	private static function serve_sse_endpoint( $base_endpoint_url ) {
		// Clean and disable all output buffering to ensure stream is sent immediately.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-transform' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );

		// Preserve any query parameters (such as credentials/auth) on the POST target URL.
		$post_url = $base_endpoint_url;
		if ( ! empty( $_GET ) ) {
			$clean_params = $_GET;
			unset( $clean_params['sse'], $clean_params['format'], $clean_params['wp_mcp_endpoint'] );
			if ( ! empty( $clean_params ) ) {
				$post_url = add_query_arg( $clean_params, $post_url );
			}
		}

		// Dispatch MCP endpoint event.
		echo "event: endpoint\n";
		echo "data: " . esc_url_raw( $post_url ) . "\n\n";

		// Dispatch keepalive ping.
		echo ": keepalive\n\n";

		if ( function_exists( 'flush' ) ) {
			flush();
		}
	}

	/**
	 * Process a raw JSON-RPC 2.0 payload string.
	 *
	 * @param string $raw_json Raw JSON request body.
	 * @return array|null Response array or null for notifications.
	 */
	public static function process_jsonrpc_payload( $raw_json ) {
		if ( empty( $raw_json ) ) {
			return self::format_error( null, -32700, 'Parse error: Empty request body.' );
		}

		$request = json_decode( $raw_json, true );

		if ( null === $request || ! is_array( $request ) ) {
			return self::format_error( null, -32700, 'Parse error: Invalid JSON.' );
		}

		// Handle batch requests if an array of requests is sent.
		if ( isset( $request[0] ) && is_array( $request[0] ) ) {
			$batch_responses = array();
			foreach ( $request as $single_req ) {
				$res = self::dispatch_single_request( $single_req );
				if ( null !== $res ) {
					$batch_responses[] = $res;
				}
			}
			return ! empty( $batch_responses ) ? $batch_responses : null;
		}

		return self::dispatch_single_request( $request );
	}

	/**
	 * Dispatch a single JSON-RPC request.
	 *
	 * @param array $request Single request array.
	 * @return array|null
	 */
	private static function dispatch_single_request( $request ) {
		$id = isset( $request['id'] ) ? $request['id'] : null;

		if ( ! isset( $request['jsonrpc'] ) || '2.0' !== $request['jsonrpc'] ) {
			return self::format_error( $id, -32600, 'Invalid Request: jsonrpc must be "2.0".' );
		}

		if ( ! isset( $request['method'] ) || ! is_string( $request['method'] ) ) {
			return self::format_error( $id, -32600, 'Invalid Request: method is required and must be a string.' );
		}

		$method = $request['method'];
		$params = isset( $request['params'] ) && is_array( $request['params'] ) ? $request['params'] : array();

		// Dispatch methods.
		switch ( $method ) {
			case 'initialize':
				return self::handle_initialize( $id, $params );

			case 'notifications/initialized':
			case 'initialized':
				// Notifications do not strictly require a response in JSON-RPC, but if id is present return success.
				if ( null !== $id ) {
					return self::format_result( $id, array() );
				}
				return null;

			case 'ping':
				return self::format_result( $id, new stdClass() );

			case 'tools/list':
				return self::handle_tools_list( $id );

			case 'tools/call':
				return self::handle_tools_call( $id, $params );

			default:
				return self::format_error( $id, -32601, sprintf( 'Method not found: %s', $method ) );
		}
	}

	/**
	 * Handle MCP initialize method.
	 *
	 * @param mixed $id Request ID.
	 * @param array $params Client parameters.
	 * @return array
	 */
	private static function handle_initialize( $id, $params ) {
		$result = array(
			'protocolVersion' => self::MCP_PROTOCOL_VERSION,
			'capabilities'    => array(
				'tools' => array(
					'listChanged' => false,
				),
			),
			'serverInfo'      => array(
				'name'    => 'wp-post-mcp',
				'version' => WP_MCP_VERSION,
			),
		);

		return self::format_result( $id, $result );
	}

	/**
	 * Handle tools/list method.
	 *
	 * Returns available tools to the MCP client without blocking on discovery.
	 *
	 * @param mixed $id Request ID.
	 * @return array
	 */
	private static function handle_tools_list( $id ) {
		$tools = WP_MCP_Tools::get_tool_definitions();

		return self::format_result(
			$id,
			array(
				'tools' => $tools,
			)
		);
	}

	/**
	 * Handle tools/call method.
	 *
	 * Enforces authentication and user capability check (edit_posts) on execution.
	 *
	 * @param mixed $id Request ID.
	 * @param array $params Tool call params.
	 * @return array
	 */
	private static function handle_tools_call( $id, $params ) {
		// Authenticate and authorize request before executing the tool.
		$auth_result = WP_MCP_Auth::authenticate_request();
		if ( is_wp_error( $auth_result ) ) {
			return self::format_error( $id, -32001, $auth_result->get_error_message() );
		}

		if ( ! isset( $params['name'] ) || ! is_string( $params['name'] ) ) {
			return self::format_error( $id, -32602, 'Invalid params: "name" is required for tools/call.' );
		}

		$tool_name = $params['name'];
		$arguments = isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : array();

		try {
			$result = WP_MCP_Tools::execute_tool( $tool_name, $arguments );
			return self::format_result( $id, $result );
		} catch ( Exception $e ) {
			return self::format_error( $id, -32000, $e->getMessage() );
		}
	}

	/**
	 * Format a standard JSON-RPC 2.0 success response.
	 *
	 * @param mixed $id Request ID.
	 * @param mixed $result Result data.
	 * @return array
	 */
	public static function format_result( $id, $result ) {
		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'result'  => $result,
		);
	}

	/**
	 * Format a standard JSON-RPC 2.0 error response.
	 *
	 * @param mixed  $id Request ID.
	 * @param int    $code Error code.
	 * @param string $message Error message.
	 * @param mixed  $data Optional error data.
	 * @return array
	 */
	public static function format_error( $id, $code, $message, $data = null ) {
		$error = array(
			'code'    => (int) $code,
			'message' => (string) $message,
		);

		if ( null !== $data ) {
			$error['data'] = $data;
		}

		return array(
			'jsonrpc' => '2.0',
			'id'      => $id,
			'error'   => $error,
		);
	}
}
