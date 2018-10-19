<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 *
 * @wordpress-plugin
 * Plugin Name:       Maider
 * Plugin URI:        mailto:willwoodlief@gmail.com
 * Description:       Configures WordPress
 * Version:           1.0.0
 * Author:            Will Woodlief
 * Author URI:        willwoodlief@gmail.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       maider
 * Domain Path:       /languages
 * Requires at least: 4.6
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
define( 'PLUGIN_VERSION', '1.0.0' );
define( 'PLUGIN_NAME', 'Maider' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-activator.php
 * @throws Exception
 */
function activate_maider() {
	/** @noinspection PhpIncludeInspection */
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-activator.php';
	\maider\Activator::activate();
}


/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-deactivator.php
 * @throws Exception
 */
function deactivate_maider() {
	/** @noinspection PhpIncludeInspection */
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-deactivator.php';
	\maider\Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_maider' );
register_deactivation_hook( __FILE__, 'deactivate_maider' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
/** @noinspection PhpIncludeInspection */
require plugin_dir_path( __FILE__ ) . 'includes/class-start.php';


/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_maider() {

	$plugin = new \maider\Start();
	$plugin->run();

}
run_maider();
