<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://oneclickcontent.com
 * @since      1.0.0
 *
 * @package    Travel_Buddy_Ai
 * @subpackage Travel_Buddy_Ai/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Travel_Buddy_Ai
 * @subpackage Travel_Buddy_Ai/admin
 * @author     OneClickContent <info@oneclickcontent.com>
 */
class Travel_Buddy_Ai_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string $plugin_name       The name of this plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/travel-buddy-ai-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/travel-buddy-ai-admin.js', array( 'jquery' ), $this->version, false );
	}


	/* Settings and Options */
	public static function travelbuddy_add_admin_menu() {
		add_options_page( 'TravelBuddy AI', 'TravelBuddy AI', 'manage_options', 'travelbuddy_ai', array( self::class, 'travelbuddy_options_page' ) );
	}

	public static function travelbuddy_settings_init() {
		register_setting( 'travelbuddy_settings', 'travelbuddy_settings' );

		add_settings_section(
			'travelbuddy_settings_section',
			__( 'Settings', 'travelbuddy' ),
			array( self::class, 'travelbuddy_settings_section_callback' ),
			'travelbuddy_settings'
		);

		add_settings_field(
			'travelbuddy_api_key',
			__( 'OpenAI API Key', 'travelbuddy' ),
			array( self::class, 'travelbuddy_api_key_render' ),
			'travelbuddy_settings',
			'travelbuddy_settings_section'
		);

		add_settings_field(
			'travelbuddy_assistant_id',
			__( 'Assistant ID', 'travelbuddy' ),
			array( self::class, 'travelbuddy_assistant_id_render' ),
			'travelbuddy_settings',
			'travelbuddy_settings_section'
		);
	}

	public static function travelbuddy_api_key_render() {
		$options = get_option( 'travelbuddy_settings' );
		?>
		<input type='text' name='travelbuddy_settings[travelbuddy_api_key]' value='<?php echo $options['travelbuddy_api_key']; ?>'>
		<?php
	}

	public static function travelbuddy_assistant_id_render() {
		$options = get_option( 'travelbuddy_settings' );
		?>
		<input type='text' name='travelbuddy_settings[travelbuddy_assistant_id]' value='<?php echo $options['travelbuddy_assistant_id']; ?>'>
		<?php
	}

	public static function travelbuddy_settings_section_callback() {
		echo __( 'Enter your OpenAI API key and Assistant ID to enable the TravelBuddy AI features.', 'travelbuddy' );
	}

	public static function travelbuddy_options_page() {
		?>
		<form action='options.php' method='post'>
			<h2>TravelBuddy AI</h2>
			<?php
			settings_fields( 'travelbuddy_settings' );
			do_settings_sections( 'travelbuddy_settings' );
			submit_button();
			?>
		</form>
		<?php
	}
}
