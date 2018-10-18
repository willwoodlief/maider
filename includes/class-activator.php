<?php
namespace maider;
require_once realpath(dirname(__FILE__) . '/../vendor/autoload.php');
require_once realpath(dirname(__FILE__) . '/../public/maider/class-log.php');





/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 */
class Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */

	const DB_VERSION = 0.11;


	/**
	 * @throws \Exception
	 */
	public static function activate() {
		//global $wpdb;


		$installed_ver = floatval( get_option( "_".strtolower( PLUGIN_NAME) ."_db_version" ));
		$b_force_create = false;

		if ( ($b_force_create) || ( Activator::DB_VERSION > $installed_ver) ) {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

			$sql = Log::get_log_table_create_sql();

			dbDelta( $sql );
			update_option( "_".strtolower( PLUGIN_NAME) ."_db_version" , Activator::DB_VERSION );
		}


	}



}
