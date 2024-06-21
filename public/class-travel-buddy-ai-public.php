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
class Travel_Buddy_Ai_Public {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	public function enqueue_styles() {
		wp_enqueue_style(
			$this->plugin_name,
			plugin_dir_url( __FILE__ ) . 'css/travel-buddy-ai-public.css',
			array(),
			$this->version,
			'all'
		);
	}

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

	public function travelbuddy_search_shortcode() {
		ob_start();
		?>
		<form id="travelbuddy-search-form" class="travelbuddy-form">
			<input type="text" id="travelbuddy-query" name="query" placeholder="Enter your rental preferences" class="travelbuddy-input">
			<button type="submit" class="travelbuddy-button">Search</button>
			<div id="travelbuddy-loading" style="display:none;">Loading...</div>
		</form>
		<div id="travelbuddy-results" class="travelbuddy-results"></div>
		<?php
		return ob_get_clean();
	}

	public function register_shortcodes() {
		add_shortcode( 'travelbuddy_search', array( $this, 'travelbuddy_search_shortcode' ) );
	}

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
			// error_log( 'Error fetching assistant: ' . $response->get_error_message() );
			return array( 'error' => 'Error fetching assistant' );
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

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
			// error_log( 'Error creating thread: ' . $response->get_error_message() );
			return null;
		}

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! isset( $response_body['id'] ) ) {
			// error_log( 'Failed to create thread, API Response: ' . print_r( $response_body, true ) );
			return null;
		}

		return $response_body['id'];
	}

	private function add_message_and_run_thread( $api_key, $thread_id, $assistant_id, $query ) {
		$message_api_url = "https://api.openai.com/v1/threads/{$thread_id}/messages";
		$run_api_url     = "https://api.openai.com/v1/threads/{$thread_id}/runs";

		// Adding a message to the thread
		$body     = json_encode(
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
			// error_log( 'Error adding message to thread: ' . $response->get_error_message() );
			return 'Failed to add message';
		}

		// Running the thread
		$body     = json_encode(
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
			// error_log( 'Error running thread: ' . $response->get_error_message() );
			return 'Failed to run thread';
		}

		$response_body    = wp_remote_retrieve_body( $response );
		$decoded_response = json_decode( $response_body, true );

		if ( $decoded_response['status'] === 'queued' || $decoded_response['status'] === 'running' ) {
			return $this->wait_for_run_completion( $api_key, $decoded_response['id'], $thread_id );
		} elseif ( $decoded_response['status'] === 'completed' ) {
			return $this->fetch_messages_from_thread( $api_key, $thread_id );
		} else {
			// error_log( 'Run ended in non-completed status: ' . print_r( $decoded_response, true ) );
			return 'Run failed or was cancelled';
		}
	}

	private function wait_for_run_completion( $api_key, $run_id, $thread_id ) {
		// error_log( "Starting wait_for_run_completion for run ID $run_id in thread $thread_id" );
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
				// error_log( 'Error checking run status: ' . $response->get_error_message() );
				return 'Failed to check run status';
			}

			$response_body    = wp_remote_retrieve_body( $response );
			$decoded_response = json_decode( $response_body, true );

			if ( isset( $decoded_response['error'] ) ) {
				// error_log( 'Error in run status check: ' . $response_body );
				return 'Error retrieving run status: ' . $decoded_response['error']['message'];
			}

			if ( isset( $decoded_response['status'] ) && $decoded_response['status'] === 'completed' ) {
				// error_log( "Run $run_id in thread $thread_id has completed." );
				return $this->fetch_messages_from_thread( $api_key, $thread_id );
			} elseif ( isset( $decoded_response['status'] ) && ( $decoded_response['status'] === 'failed' || $decoded_response['status'] === 'cancelled' ) ) {
				// error_log( "Run $run_id in thread $thread_id failed or was cancelled." );
				return 'Run failed or was cancelled';
			} elseif ( isset( $decoded_response['status'] ) && $decoded_response['status'] === 'requires_action' ) {
				// error_log( "Run $run_id in thread $thread_id requires action." );
				return $this->handle_requires_action( $api_key, $run_id, $thread_id, $decoded_response['required_action'] );
			}

			++$attempts;
			// error_log( "Attempt $attempts: Run $run_id in thread $thread_id status is {$decoded_response['status']}." );
		}

		// error_log( "Run $run_id in thread $thread_id did not complete after $max_attempts attempts" );
		return 'Run did not complete in expected time';
	}

	/**
	 * Handle required actions for the run.
	 *
	 * @since    1.0.0
	 * @param    string $api_key          The OpenAI API key.
	 * @param    string $run_id           The ID of the run.
	 * @param    string $thread_id        The ID of the thread.
	 * @param    array  $required_action  The required action details.
	 * @return   mixed                       The run result or an error message.
	 */
	private function handle_requires_action( $api_key, $run_id, $thread_id, $required_action ) {
	    if ( $required_action['type'] === 'submit_tool_outputs' ) {
	        $tool_calls   = $required_action['submit_tool_outputs']['tool_calls'];
	        $tool_outputs = array();

	        foreach ( $tool_calls as $tool_call ) {
	            $output = '';
	            if ( $tool_call['type'] === 'function' ) {
	                switch ( $tool_call['function']['name'] ) {
	                    case 'parse_apartment_rental':
	                        $output = json_encode( array(
	                            'destination'   => 'San Francisco',
	                            'duration'      => 3,
	                            'start_date'    => '2023-07-01',
	                            'price_per_month' => 3000,
	                            'bedrooms'      => 2,
	                            'bathrooms'     => 1,
	                            'amenities'     => array( 'gym', 'Wi-Fi' ),
	                            'pet_friendly'  => true,
	                        ));
	                        break;

	                    case 'parse_storage_unit':
	                        $output = json_encode( array(
	                            'destination' => 'San Francisco',
	                            'duration'    => 3,
	                            'start_date'  => '2023-07-01',
	                            'price_per_month' => 150,
	                            'size'        => '10x10',
	                            'climate_controlled' => true,
	                            'access_hours' => '24/7',
	                            'security_features' => array( 'CCTV', 'Alarm' ),
	                        ));
	                        break;

	                    case 'parse_vacation_rental':
	                        $output = json_encode( array(
	                            'destination'   => 'Miami Beach',
	                            'duration'      => 14,
	                            'start_date'    => '2024-12-01',
	                            'property_type' => 'beachfront',
	                            'bedrooms'      => 3,
	                            'bathrooms'     => 2,
	                            'amenities'     => array( 'swimming pool', 'Wi-Fi' ),
	                            'price_per_night' => 500,
	                            'pet_friendly'  => true,
	                        ));
	                        break;

	                    default:
	                        $output = json_encode( array( 'success' => 'true' ) );
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
	                'body'    => json_encode( array( 'tool_outputs' => $tool_outputs ) ),
	            )
	        );

	        if ( is_wp_error( $response ) ) {
	            // error_log( 'Error submitting tool outputs: ' . $response->get_error_message() );
	            return 'Failed to submit tool outputs';
	        }

	        // error_log( 'Tool outputs submitted successfully, response: ' . wp_remote_retrieve_body( $response ) );
	        return $this->wait_for_run_completion( $api_key, $run_id, $thread_id );
	    }

	    return 'Unhandled requires_action';
	}


	/**
	 * Fetch messages from the thread.
	 *
	 * @since    1.0.0
	 * @param    string $api_key     The OpenAI API key.
	 * @param    string $thread_id   The ID of the thread.
	 * @return   mixed                  The messages from the thread or an error message.
	 */
	private function fetch_messages_from_thread( $api_key, $thread_id ) {
	    $messages_url = "https://api.openai.com/v1/threads/{$thread_id}/messages";
	    // error_log( "Attempting to fetch messages from thread: $thread_id" );

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
	        // error_log( 'Error fetching messages from thread: ' . $response->get_error_message() );
	        return 'Failed to fetch messages';
	    }

	    $response_body = wp_remote_retrieve_body( $response );
	    // error_log( 'Received response from fetching messages: ' . $response_body );

	    $decoded_response = json_decode( $response_body, true );
	    if ( ! isset( $decoded_response['data'] ) ) {
	        // error_log( 'No messages found in thread: ' . print_r( $decoded_response, true ) );
	        return 'No messages found';
	    }

	    $messages = array_map(
	        function ( $message ) {
	            foreach ( $message['content'] as $content ) {
	                if ( $content['type'] === 'text' ) {
	                    return json_decode( $content['text']['value'], true );
	                }
	            }
	            return 'No text content';
	        },
	        $decoded_response['data']
	    );

	    // error_log( 'Processed messages: ' . print_r( $messages, true ) );
	    return $messages[0];
	}


	public function travelbuddy_handle_ajax_request() {
		check_ajax_referer( 'travelbuddy_nonce', 'nonce' );
		// error_log( 'Handling AJAX request' );

		$query        = sanitize_text_field( $_POST['query'] );
		$options      = get_option( 'travelbuddy_settings' );
		$api_key      = $options['travelbuddy_api_key'];
		$assistant_id = $options['travelbuddy_assistant_id'];

		if ( empty( $api_key ) ) {
			wp_send_json_error( 'API key is not configured' );
			wp_die();
		}

		$assistant = $this->fetch_assistant( $api_key, $assistant_id );
		// error_log( 'Fetched Assistant: ' . print_r( $assistant, true ) );

		if ( isset( $assistant['error'] ) ) {
			wp_send_json_error( $assistant['error'] );
			wp_die();
		}

		if ( ! isset( $assistant['id'] ) ) {
			wp_send_json_error( 'Invalid assistant ID' );
			wp_die();
		}

		$thread_id = $this->create_thread( $api_key );
		if ( ! $thread_id ) {
			wp_send_json_error( 'Failed to create a thread' );
			wp_die();
		}
		// error_log( 'Created Thread ID: ' . $thread_id );

		$response = $this->add_message_and_run_thread( $api_key, $thread_id, $assistant_id, $query );
		if ( is_string( $response ) ) {
			wp_send_json_error( $response );
		} else {
			wp_send_json_success( $response );
		}
		wp_die();
	}
}
