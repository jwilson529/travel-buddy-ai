<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://oneclickcontent.com
 * @since             1.0.0
 * @package           Travel_Buddy_Ai
 *
 * @wordpress-plugin
 * Plugin Name:       Travel Buddy AI
 * Plugin URI:        https://oneclickcontent.com
 * Description:       NLP to JSON
 * Version:           1.0.0
 * Author:            OneClickContent
 * Author URI:        https://oneclickcontent.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       travel-buddy-ai
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'TRAVEL_BUDDY_AI_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-travel-buddy-ai-activator.php
 */
function activate_travel_buddy_ai() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-travel-buddy-ai-activator.php';
	Travel_Buddy_Ai_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-travel-buddy-ai-deactivator.php
 */
function deactivate_travel_buddy_ai() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-travel-buddy-ai-deactivator.php';
	Travel_Buddy_Ai_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_travel_buddy_ai' );
register_deactivation_hook( __FILE__, 'deactivate_travel_buddy_ai' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-travel-buddy-ai.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_travel_buddy_ai() {

	$plugin = new Travel_Buddy_Ai();
	$plugin->run();

}
run_travel_buddy_ai();
