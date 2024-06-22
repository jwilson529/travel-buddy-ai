<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://oneclickcontent.com
 * @since      1.0.0
 *
 * @package    Travel_Buddy_Ai
 * @subpackage Travel_Buddy_Ai/public
 */

/**
 * The public-facing functionality of the plugin.
 */
class Travel_Buddy_Ai_Public {

	/**
	 * The name of the plugin.
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * The version of the plugin.
	 *
	 * @var string
	 */
	private $version;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_name The name of the plugin.
	 * @param string $version     The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Enqueue styles for the public-facing side of the site.
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/travel-buddy-ai-public.css',
			array(),
			$this->version,
			'all'
		);
	}

	/**
	 * Enqueue scripts for the public-facing side of the site.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'js/travel-buddy-ai-public.js',
			array( 'jquery' ),
			$this->version,
			false
		);

		wp_localize_script(
			$this->plugin_name,
			'travelbuddy_ajax_object',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'travelbuddy_nonce' ),
			)
		);
	}

	/**
	 * The shortcode handler for the TravelBuddy search form.
	 *
	 * @return string The HTML content for the shortcode.
	 */
	public function travelbuddy_search_shortcode() {
		ob_start();
		?>
		<form id="travelbuddy-search-form" class="travelbuddy-form">
			<textarea id="travelbuddy-query" name="query" placeholder="Enter your rental preferences" class="travelbuddy-textarea"></textarea>
			<button type="submit" class="travelbuddy-button">Search</button>
			<div id="travelbuddy-loading" style="display:none;">Loading...</div>
		</form>
		<div id="travelbuddy-results" class="travelbuddy-results"></div>
		<?php
		return ob_get_clean();
	}


	/**
	 * Register the shortcode with WordPress.
	 */
	public function register_shortcodes() {
		add_shortcode( 'travelbuddy_search', array( $this, 'travelbuddy_search_shortcode' ) );
	}

	/**
	 * Fetch the assistant from the OpenAI API.
	 *
	 * @param string $api_key      The OpenAI API key.
	 * @param string $assistant_id The assistant ID.
	 * @return array The assistant data or an error message.
	 */
	private function fetch_assistant( $api_key, $assistant_id ) {
		$response = wp_remote_get(
			"https://api.openai.com/v1/assistants/{$assistant_id}",
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'OpenAI-Beta'   => 'assistants=v2',
					'Content-Type'  => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => 'Error fetching assistant.' );
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Create a new thread in the OpenAI API.
	 *
	 * @param string $api_key The OpenAI API key.
	 * @return string|null The thread ID or null if failed.
	 */
	private function create_thread( $api_key ) {
		$response = wp_remote_post(
			'https://api.openai.com/v1/threads',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
					'OpenAI-Beta'   => 'assistants=v2',
				),
				'body'    => '{}',
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! isset( $response_body['id'] ) ) {
			return null;
		}

		return $response_body['id'];
	}

	/**
	 * Add a message and run the thread in the OpenAI API.
	 *
	 * @param string $api_key      The OpenAI API key.
	 * @param string $thread_id    The thread ID.
	 * @param string $assistant_id The assistant ID.
	 * @param string $query        The query to add as a message.
	 * @return mixed The result of the run or an error message.
	 */
	private function add_message_and_run_thread( $api_key, $thread_id, $assistant_id, $query ) {
		$message_api_url = "https://api.openai.com/v1/threads/{$thread_id}/messages";
		$run_api_url     = "https://api.openai.com/v1/threads/{$thread_id}/runs";

		// Adding a message to the thread.
		$body     = wp_json_encode(
			array(
				'role'    => 'user',
				'content' => $query,
			)
		);
		$response = wp_remote_post(
			$message_api_url,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
					'OpenAI-Beta'   => 'assistants=v2',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return 'Failed to add message.';
		}

		// Running the thread.
		$body     = wp_json_encode(
			array(
				'assistant_id' => $assistant_id,
			)
		);
		$response = wp_remote_post(
			$run_api_url,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
					'OpenAI-Beta'   => 'assistants=v2',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return 'Failed to run thread.';
		}

		$response_body    = wp_remote_retrieve_body( $response );
		$decoded_response = json_decode( $response_body, true );

		if ( 'queued' === $decoded_response['status'] || 'running' === $decoded_response['status'] ) {
			return $this->wait_for_run_completion( $api_key, $decoded_response['id'], $thread_id );
		} elseif ( 'completed' === $decoded_response['status'] ) {
			return $this->fetch_messages_from_thread( $api_key, $thread_id );
		} else {
			return 'Run failed or was cancelled.';
		}
	}

	/**
	 * Wait for the run to complete in the OpenAI API.
	 *
	 * @param string $api_key  The OpenAI API key.
	 * @param string $run_id   The run ID.
	 * @param string $thread_id The thread ID.
	 * @return mixed The run result or an error message.
	 */
	private function wait_for_run_completion( $api_key, $run_id, $thread_id ) {
		$status_check_url = "https://api.openai.com/v1/threads/{$thread_id}/runs/{$run_id}";

		$attempts     = 0;
		$max_attempts = 20;

		while ( $attempts < $max_attempts ) {
			sleep( 5 );
			$response = wp_remote_get(
				$status_check_url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'OpenAI-Beta'   => 'assistants=v2',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return 'Failed to check run status.';
			}

			$response_body    = wp_remote_retrieve_body( $response );
			$decoded_response = json_decode( $response_body, true );

			if ( isset( $decoded_response['error'] ) ) {
				return 'Error retrieving run status: ' . $decoded_response['error']['message'];
			}

			if ( isset( $decoded_response['status'] ) && 'completed' === $decoded_response['status'] ) {
				return $this->fetch_messages_from_thread( $api_key, $thread_id );
			} elseif ( isset( $decoded_response['status'] ) && ( 'failed' === $decoded_response['status'] || 'cancelled' === $decoded_response['status'] ) ) {
				return 'Run failed or was cancelled.';
			} elseif ( isset( $decoded_response['status'] ) && 'requires_action' === $decoded_response['status'] ) {
				return $this->handle_requires_action( $api_key, $run_id, $thread_id, $decoded_response['required_action'] );
			}

			++$attempts;
		}

		return 'Run did not complete in expected time.';
	}

	/**
	 * Handle required actions for the run.
	 *
	 * @param string $api_key         The OpenAI API key.
	 * @param string $run_id          The run ID.
	 * @param string $thread_id       The thread ID.
	 * @param array  $required_action The required action details.
	 * @return mixed The run result or an error message.
	 */
	private function handle_requires_action( $api_key, $run_id, $thread_id, $required_action ) {
		if ( 'submit_tool_outputs' === $required_action['type'] ) {
			$tool_calls   = $required_action['submit_tool_outputs']['tool_calls'];
			$tool_outputs = array();

			foreach ( $tool_calls as $tool_call ) {
				$output = '';
				if ( 'function' === $tool_call['type'] ) {
					switch ( $tool_call['function']['name'] ) {
						case 'parse_apartment_rental':
							$output = wp_json_encode(
								array(
									'destination'     => 'San Francisco',
									'duration'        => 3,
									'start_date'      => '2023-07-01',
									'price_per_month' => 3000,
									'bedrooms'        => 2,
									'bathrooms'       => 1,
									'amenities'       => array( 'gym', 'Wi-Fi' ),
									'pet_friendly'    => true,
								)
							);
							break;

						case 'parse_storage_unit':
							$output = wp_json_encode(
								array(
									'destination'        => 'San Francisco',
									'duration'           => 3,
									'start_date'         => '2023-07-01',
									'price_per_month'    => 150,
									'size'               => '10x10',
									'climate_controlled' => true,
									'access_hours'       => '24/7',
									'security_features'  => array( 'CCTV', 'Alarm' ),
								)
							);
							break;

						case 'parse_vacation_rental':
							$output = wp_json_encode(
								array(
									'destination'     => 'Miami Beach',
									'duration'        => 14,
									'start_date'      => '2024-12-01',
									'property_type'   => 'beachfront',
									'bedrooms'        => 3,
									'bathrooms'       => 2,
									'amenities'       => array( 'swimming pool', 'Wi-Fi' ),
									'price_per_night' => 500,
									'pet_friendly'    => true,
								)
							);
							break;

						default:
							$output = wp_json_encode( array( 'success' => 'true' ) );
							break;
					}

					$tool_outputs[] = array(
						'tool_call_id' => $tool_call['id'],
						'output'       => $output,
					);
				}
			}

			$submit_tool_outputs_url = "https://api.openai.com/v1/threads/{$thread_id}/runs/{$run_id}/submit_tool_outputs";
			$response                = wp_remote_post(
				$submit_tool_outputs_url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $api_key,
						'OpenAI-Beta'   => 'assistants=v2',
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( array( 'tool_outputs' => $tool_outputs ) ),
				)
			);

			if ( is_wp_error( $response ) ) {
				return 'Failed to submit tool outputs.';
			}

			return $this->wait_for_run_completion( $api_key, $run_id, $thread_id );
		}

		return 'Unhandled requires_action.';
	}

	/**
	 * Fetch messages from the thread.
	 *
	 * @param string $api_key   The OpenAI API key.
	 * @param string $thread_id The thread ID.
	 * @return mixed The messages from the thread or an error message.
	 */
	private function fetch_messages_from_thread( $api_key, $thread_id ) {
		$messages_url = "https://api.openai.com/v1/threads/{$thread_id}/messages";

		$response = wp_remote_get(
			$messages_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'OpenAI-Beta'   => 'assistants=v2',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return 'Failed to fetch messages.';
		}

		$response_body    = wp_remote_retrieve_body( $response );
		$decoded_response = json_decode( $response_body, true );

		if ( ! isset( $decoded_response['data'] ) ) {
			return 'No messages found.';
		}

		$messages = array_map(
			function ( $message ) {
				foreach ( $message['content'] as $content ) {
					if ( 'text' === $content['type'] ) {
						return json_decode( $content['text']['value'], true );
					}
				}
				return 'No text content.';
			},
			$decoded_response['data']
		);

		return $messages[0];
	}

	/**
	 * Handle AJAX request from the front-end.
	 */
	public function travelbuddy_handle_ajax_request() {
		check_ajax_referer( 'travelbuddy_nonce', 'nonce' );

		if ( ! isset( $_POST['query'] ) ) {
			wp_send_json_error( 'Query is missing.' );
			wp_die();
		}

		$query        = sanitize_text_field( wp_unslash( $_POST['query'] ) );
		$options      = get_option( 'travelbuddy_settings' );
		$api_key      = $options['travelbuddy_api_key'];
		$assistant_id = $options['travelbuddy_assistant_id'];

		if ( empty( $api_key ) ) {
			wp_send_json_error( 'API key is not configured.' );
			wp_die();
		}

		$assistant = $this->fetch_assistant( $api_key, $assistant_id );

		if ( isset( $assistant['error'] ) ) {
			wp_send_json_error( $assistant['error'] );
			wp_die();
		}

		if ( ! isset( $assistant['id'] ) ) {
			wp_send_json_error( 'Invalid assistant ID.' );
			wp_die();
		}

		$current_date    = gmdate( 'Y-m-d' );
		$query_with_date = $query . ' Current date is ' . $current_date;

		$thread_id = $this->create_thread( $api_key );
		if ( ! $thread_id ) {
			wp_send_json_error( 'Failed to create a thread.' );
			wp_die();
		}

		$response = $this->add_message_and_run_thread( $api_key, $thread_id, $assistant_id, $query_with_date );
		if ( is_string( $response ) ) {
			wp_send_json_error( $response );
		} else {
			wp_send_json_success( $response );
		}
		wp_die();
	}
}
