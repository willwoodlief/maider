<?php
namespace maider;
/** @noinspection PhpIncludeInspection */
require_once realpath( dirname( __FILE__ ) . "/../../vendor/autoload.php");
require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php' );

class MyInstallSkin extends \WP_Upgrader_Skin {
	/**
	 *
	 * @param string|\WP_Error $string
	 */
	public function feedback($string) {
		if ( isset( $this->upgrader->strings[$string] ) )
			$string = $this->upgrader->strings[$string];

		if ( strpos($string, '%') !== false ) {
			$args = func_get_args();
			$args = array_splice($args, 1);
			if ( $args ) {
				$args = array_map( 'strip_tags', $args );
				$args = array_map( 'esc_html', $args );
				$string = vsprintf($string, $args);
			}
		}
		if ( empty($string) )
			return;
		if ( is_wp_error($string) ){
			if ( $string->get_error_data() && is_string( $string->get_error_data() ) )
				$string = $string->get_error_message() . ': ' . $string->get_error_data();
			else
				$string = $string->get_error_message();
		}
		echo "<p>$string</p>\n";

	}

	/**
	 */
	public function header() {
		if ( $this->done_header ) {
			return;
		}
		$this->done_header = true;
		echo '<div class="wrap">';
		echo '<h1>' . $this->options['title'] . '</h1>';
	}

	/**
	 */
	public function footer() {
		if ( $this->done_footer ) {
			return;
		}
		$this->done_footer = true;
		echo '</div>';
	}

}

class Plugins {

	protected $installed_plugins = [];
	protected $runnable_plugins = [];
	/**
	 * @var Log|null
	 */
	public  $log = null;
	/**
	 * Options constructor.
	 * @param Log $log
	 * @throws ConfigException
	 */
	public function __construct($log) {

		$this->log = $log;
		$this->installed_plugins = $this->get_plugin_slugnames();

	//	$url = "https:\/\/wordpress.org\/plugins\/google-analytics-dashboard-for-wp\/";
	//	$path = 'https://gokabam.com/install_test.zip';
	//	$this->install_plugin($path);
		//$this->activate_plugin('install_test');


	}

	public function get_runnable_plugins() { return $this->runnable_plugins;}


	/**
	 * Goes through the options and sees if this is a legal plugin with a legal value
	 *
	 * @param array[] $plugins
	 *   -  each array of:
	 *          name    : ignored, for humans
	 *          slug    : for everything but install
	 *          resource  : only for install. Can be a url, slug or file path
	 *          action  : install|deactivate|activate|delete
	 *
	 *
	 *
	 * @return array
	 * @throws ConfigException
	 */
	public function validate_plugins( $plugins) {

		$ret = [];
		$copies = []; //allow only one entry per plugin

		//ignore if empty
		if (empty( $plugins)) {
			return $ret;
		}

		//prepare for anything
		if (!is_array( $plugins)) {
			throw new ConfigException("Plugin Instructions are not seen as array in php code: ");
		}

		foreach ( $plugins as $node) {
			if (!array_key_exists('action',$node)) {
				$show = '';
				try {
					$show = JsonHelper::toString($node);
				} catch (\Exception $e) {

				}

				throw new ConfigException("Plugin Instructions need a action: ". $show);
			}
			$cow = [];
			$action = trim($node['action']);
			switch ($action) {
				case 'install':
				case 'delete':
				case 'activate':
				case 'deactivate': {
					break;
				}
				default: {
					throw new ConfigException("Plugins action needs to be one of install|deactivate|activate|delete instead of [$action]");
				}
			}
			$cow['action'] = $action;


			if ($action === 'install') {
				//must have a resource
				if (!array_key_exists('resource',$node)) {
					$show = '';
					try {
						$show = JsonHelper::toString($node);
					} catch (\Exception $e) {

					}
					throw new ConfigException("Plugin Instructions need a url when action is install: ". $show);
				}
				$cow['resource'] = $node['resource'];
				$key = $node['resource'];
			} else {
				//must have slug
				if (!array_key_exists('slug',$node)) {
					$show = '';
					try {
						$show = JsonHelper::toString($node);
					} catch (\Exception $e) {

					}
					throw new ConfigException("Plugin Instructions need a slug when action is deactivate|activate|delete: ". $show);
				}
				$cow['slug'] = $node['slug'];
				$key = $node['slug'];
			}

			if (array_key_exists($key,$copies)) {
				throw new ConfigException("Each Plugin must only be mentioned once in the config : $key has more than one entry");
			}
			$copies[$key] = $cow;
			$ret[] = $cow;

		}
		$this->runnable_plugins = $ret;
		return $ret;

	}


	/**
	 * @param String $plugin_name - plugin slug
	 *
	 * @throws SecondTryException
	 */
	protected function delete_plugin($plugin_name) {
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		try {
			if ( ! array_key_exists( $plugin_name, $this->installed_plugins ) ) {
				throw new ConfigException( "Cannot delete $plugin_name, its not on the plugin list" );
			}

			$file_path = $this->installed_plugins[ $plugin_name ]['partial'];
			ob_start();
			$delete_ret = delete_plugins(  [$file_path] );
			$results = ob_get_contents();
			if (ob_get_length()) ob_end_clean();
			if ($delete_ret !== true) {
				if (is_wp_error($delete_ret)) {
					$messages = $delete_ret->get_error_messages();
					if (empty($messages)) {
						$error_message = "WP Error Class returned but no information given";
					} else {
						$error_message = implode(', ', $messages);
					}
					throw new ConfigException("Could not delete plugin of $plugin_name because: $error_message");
				} else {
					throw new ConfigException("Could not run delete  plugin $plugin_name for an unspecified reason. Check file permissions");
				}
				}



			$this->log->log('plugin','delete',$plugin_name,$results);
		} catch (\Exception $e) {
			$this->log->log('error','delete_plugin',$plugin_name,$e);
		}
	}

	/**
	 * @param String $plugin_name - plugin slug
	 *
	 * @throws SecondTryException
	 */
	protected function deactivate_plugin($plugin_name) {
		try {
			if ( ! array_key_exists( $plugin_name, $this->installed_plugins ) ) {
				throw new ConfigException( "Cannot deactivate $plugin_name, its not on the plugin list" );
			}
			$file_path = $this->installed_plugins[ $plugin_name ]['file'];
			ob_start();
			deactivate_plugins( [ $file_path ], false );
			$results = ob_get_contents();
			if (ob_get_length()) ob_end_clean();
			$this->log->log('plugin','deactivate',$plugin_name,$results);
		} catch (\Exception $e) {
			$this->log->log('error','deactivate_plugin',$plugin_name,$e);
		}
	}


	/**
	 * @param String $plugin_name - plugin slug
	 *
	 * @throws SecondTryException
	 */
	protected function activate_plugin($plugin_name) {
		try {
			if ( ! array_key_exists( $plugin_name, $this->installed_plugins ) ) {
				throw new ConfigException( "Cannot activate $plugin_name, its not on the plugin list" );
			}
			$file_path = $this->installed_plugins[ $plugin_name ]['file'];
			ob_start();
			activate_plugins( [ $file_path ], false );
			$results = ob_get_contents();
			if (ob_get_length()) ob_end_clean();
			$this->log->log('plugin','activate',$plugin_name,$results);
		} catch (\Exception $e) {
			$this->log->log('error','activate_plugin',$plugin_name,$e);
		}
	}




	protected function get_plugin_slugnames() {

		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		$ret = array();
		$plugins_all = get_plugins() ;
        $frags = array_keys($plugins_all);
		$plugin_dir = ABSPATH . 'wp-content/plugins/';
		foreach ($frags as $key) {
			$slug =  explode('/',$key)[0];
			if (empty($slug)) {
				throw new ConfigException("Could not get plugin slugs!");
			}
			$node = [
				'slug' =>  $slug,
				'file' => $plugin_dir. $key,
				'partial' => $key
			];
			$ret[$slug] = $node;

		}
		return $ret;

	}

	/**
	 * @param string $url
	 *   can be a url or a full path to a zip on the local drive
	 * @return void
	 * @throws SecondTryException
	 */
	protected function install_plugin($url) {


		require_once( ABSPATH . 'wp-admin/includes/misc.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
		require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
		require_once( ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php' );
		$url = trim( $url );
		try {
				if (strstr($url, '.zip') != FALSE) {
					$download_link = $url;
				} else {
					$slug = $url;
					$slug_test = explode('/', $url);
					if (sizeof($slug_test) >= 2) {
						$slug = $slug_test[count($slug_test) - 2];
					}

					$api = plugins_api('plugin_information', array('slug' => $slug, 'fields' => array('sections' => 'false')));
					if (is_wp_error($api)) {
						$messages = $api->get_error_messages();
						if (empty($messages)) {
							$error_message = "WP Error Class returned but no information given";
						} else {
							$error_message = implode(', ', $messages);
						}
						throw new ConfigException("Could not find plugin of $url because: $error_message");
					}
					$download_link = $api->download_link;
				}

			    ob_start(); //install always prints to the screen, capture the words for the log
				$upgrader = new \Plugin_Upgrader();
				$upgrader->skin = new MyInstallSkin();




				/**
				 * @var \WP_Error|null $upgrade_ret
				 */
				$upgrade_ret = $upgrader->install($download_link);
				$results = ob_get_contents();
				if (ob_get_length()) ob_end_clean();
				if (is_null($upgrade_ret)) {
					throw new ConfigException("Could not install plugin of $url, is it already installed? -> ". $results);
				}
				if ($upgrade_ret === false) {
					throw new ConfigException("Could not install plugin of $url, unknown error -> ".  $results  );
				}
				if (is_wp_error($upgrade_ret)) {
					$messages = $upgrade_ret->get_error_messages();
					if (empty($messages)) {
						$error_message = "WP Error Class returned but no information given -> ". $results;
					} else {
						$error_message = implode(', ', $messages);
					}
					throw new ConfigException("Could not install plugin of $url because: $error_message -> ". $results);
				}


				$plugin_to_activate = $upgrader->plugin_info();
				/**
				 * @var \WP_Error|null $activate
				 */
				$activate = activate_plugin($plugin_to_activate);
				if (is_wp_error($activate)) {
					$messages = $activate->get_error_messages();
					if (empty($messages)) {
						$error_message = "WP Error Class returned but no information given";
					} else {
						$error_message = implode(', ', $messages);
					}
					throw new ConfigException("Could not activate plugin of $url because: $error_message");
				}
				wp_cache_flush();
			$this->log->log('plugin','install',$url,$results);
		} catch (\Exception $e) {
			$this->log->log('error','install_plugin',$url,$e);
		}
	}




	/**
	 * @throws SecondTryException
	 */
	public function run_plugins() {
		foreach ($this->runnable_plugins as $r) {
			$action = $r['action'];
			switch ($action) {
				case 'install': {
					$this->install_plugin($r['resource']);
					break;
				}
				case 'delete': {
					$this->delete_plugin($r['slug']);
					break;
				}
				case 'activate': {
					$this->activate_plugin($r['slug']);
					break;
				}
				case 'deactivate': {
					$this->deactivate_plugin($r['slug']);
					break;
				}
				default: {
					throw new ConfigException("Plugins action needs to be one of install|deactivate|activate|delete instead of [$action]");
				}
			}
		}
	}

	/**
	 * @return array
	 * @throws \Exception
	 *
	 */
	public function get_combined_info() {

		$plugins_to_show = $this->runnable_plugins;
		$logs = $this->log->get_log_results();

		$logged_options = [];
		//will show the last log for each plugin, in case there are multiple runs
		foreach ($logs as $log) {
			$action = $log['action'];
			$log_name = $log['name'];
			$log_value = $log['value']; //slug or url or file path of plugin
			$log_result = $log['result'];
			$run_id = $log['run_id'];
			if ($action === 'plugin') {

				switch ($log_name) {
					case 'install': {
						$result = 'Installed';
						break;
					}
					case 'delete': {
						$result = 'Deleted';
						break;
					}
					case 'activate': {
						$result = 'Activated';
						break;
					}
					case 'deactivate': {
						$result = 'De-activated';
						break;
					}
					default: {
						throw new ConfigException("Plugins action needs to be one of install|deactivate|activate|delete instead of [$action]");
					}
				}

				$logged_options[$log_value] = ['run_id' =>  $run_id,'title'=> 'Plugin','name'=>$log_name,
				                                     'value'=> $log_value, 'result' => $result,'is_error'=>false,
				                               'has_run' => true];
			}
			elseif (($action === 'error') ) {

				if (strpos($log_name, 'plugin') !== false) {
					$logged_options[$log_value] = [ 'run_id' =>  $run_id,
													'title'=> 'Plugin','name'=>$log_name,
					                               'value'=> $log_value,
					                               'result' => $log_result['message'],
					                               'is_error'=>true,
					                                'has_run' => true];
				} else {
					continue;
				}

			} else {
				continue;
			}


		}


		//make hash coordinating the logs and the options
		$combined = [];
		foreach ($plugins_to_show as $op) {
			$key = $op['action'];
			if ($key === 'install') {
				$value = $op['resource'];
			} else {
				$value = $op['slug'];
			}

			$b_new = false;
			if (array_key_exists($value,$logged_options)    ) {
				$logged_value =  $logged_options[$value]['value'];
				if (is_numeric($logged_value)) {
					$logged_value = strval($logged_value);
				}
				if (is_numeric($value)) {
					$value = strval($value);
				}
				if ($logged_value === $value) {
					$combined[] = $logged_options[$value];
				} else {
					//log was for earlier value of the plugin
					$b_new = true;
				}
			} else {
				$b_new = true;
			}

			if ($b_new) {
				$combined[] = ['title'=> 'Plugin','name'=>$key,
				               'value'=> $value,
				               'result' => "Not Run Yet",
				               'is_error'=>false,
				               'has_run' => false];
			}
		}

		return $combined;
	}


}