<?php
namespace maider;
require_once realpath(dirname(__FILE__)) . '/../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;



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

	const DB_VERSION = 0.1;


	/**
	 * @throws \Exception
	 */
	public static function activate() {
		global $wpdb;


		//check to see if any tables are missing
		$b_force_create = false;
		$tables_to_check= [];
		foreach ($tables_to_check as $tb) {
			$table_name = "{$wpdb->base_prefix}$tb";
			//check if table exists
			if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
				$b_force_create = true;
			}
		}

		$installed_ver = floatval( get_option( "_".strtolower( PLUGIN_NAME) ."_db_version" ));


		if ( ($b_force_create) || ( Activator::DB_VERSION > $installed_ver) ) {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
//			$charset_collate = $wpdb->get_charset_collate();


			//do response table

//			$sql = "CREATE TABLE `{$wpdb->base_prefix}burp_responses` (
//              id int NOT NULL AUTO_INCREMENT,
//              survey_id int not null ,
//              question_id int not null ,
//              answer_id int not null,
//              PRIMARY KEY  (id),
//              KEY survey_id_key (survey_id),
//              KEY question_id_key (question_id),
//              KEY answer_id_key (answer_id),
//              UNIQUE KEY unique_survey_question (survey_id,question_id)
//              ) $charset_collate;";
//
//			dbDelta( $sql );
			update_option( "_".strtolower( PLUGIN_NAME) ."_db_version" , Activator::DB_VERSION );
		}


	}



}
