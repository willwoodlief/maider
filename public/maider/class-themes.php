<?php
namespace maider;
/** @noinspection PhpIncludeInspection */
require_once realpath( dirname( __FILE__ ) . "/../../vendor/autoload.php");
require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php' );

class MyInstallSkinTheme extends \WP_Upgrader_Skin {
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

class Themes {

	protected $installed_themes = [];
	protected $runnable_themes = [];
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

		$this->log              = $log;
		$this->installed_themes = $this->get_theme_slugnames();

	}

	public function get_runnable_themes() { return $this->runnable_themes;}


	/**
	 * Goes through the options and sees if this is a legal theme with a legal value
	 *
	 * @param array[] $themes
	 *   -  each array of:
	 *          name    : ignored, for humans
	 *          slug    : for everything but install
 *              resource     : only for install, can be a slug, url or file path
	 *          action  : install|switch|delete
	 *
	 *
	 *
	 * @return array
	 * @throws ConfigException
	 */
	public function validate_themes( $themes) {

		$ret = [];
		$copies = []; //allow only one entry per theme
		$count_actions = [];
		$count_actions['switch'] = 0;
		$count_actions['delete'] = 0;
		$count_actions['install'] = 0;

		//ignore if empty
		if (empty( $themes)) {
			return $ret;
		}

		//prepare for anything
		if (!is_array( $themes)) {
			throw new ConfigException("Theme Instructions are not seen as array in php code: ");
		}

		foreach ( $themes as $node) {
			if (!array_key_exists('action',$node)) {
				$show = '';
				try {
					$show = JsonHelper::toString($node);
				} catch (\Exception $e) {

				}

				throw new ConfigException("Theme Instructions need a action: ". $show);
			}
			$cow = [];
			$action = trim($node['action']);
			switch ($action) {
				case 'install': {
					$count_actions['install']++;
					break;
				}
				case 'delete': {
					$count_actions['delete']++;
					break;
				}
				case 'switch':{
					$count_actions['switch']++;
					break;
				}
				default: {
					throw new ConfigException("themes action needs to be one of install|switch|delete instead of [$action]");
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
					throw new ConfigException("Theme Instructions need a resource when action is install: ". $show);
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
					throw new ConfigException("Theme Instructions need a slug when action is switch|delete: ". $show);
				}
				$cow['slug'] = $node['slug'];
				$key = $node['slug'];
			}

			if (array_key_exists($key,$copies)) {
				throw new ConfigException("Each Theme must only be mentioned once in the config : $key has more than one entry");
			}
			$copies[$key] = $cow;
			$ret[] = $cow;

		}

		$this->runnable_themes = $ret;
		return $ret;


	}


	/**
	 * @param String $theme_name - theme slug
	 *
	 * @throws SecondTryException
	 */
	protected function delete_theme($theme_name) {
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		try {
			if ( ! array_key_exists( $theme_name, $this->installed_themes ) ) {
				throw new ConfigException( "Cannot delete $theme_name, its not on the theme list" );
			}

			$file_path = $this->installed_themes[ $theme_name ]['slug'];
			ob_start();
			$delete_ret = delete_theme(  $file_path );
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
					throw new ConfigException("Could not delete theme of $theme_name because: $error_message");
				} else {
					throw new ConfigException("Could not run delete  theme $theme_name for an unspecified reason. Check file permissions");
				}
			}



			$this->log->log('theme','delete',$theme_name,$results);
		} catch (\Exception $e) {
			$this->log->log('error','delete_theme',$theme_name,$e);
		}
	}


	/**
	 * @param String $theme_name - theme slug
	 *
	 * @throws SecondTryException
	 */
	protected function switch_theme($theme_name) {
		try {
			if ( ! array_key_exists( $theme_name, $this->installed_themes ) ) {
				throw new ConfigException( "Cannot switch $theme_name, its not on the theme list" );
			}
			$theme_slug = $this->installed_themes[ $theme_name ]['slug'];
			ob_start();
			switch_theme($theme_slug);

			//the above does not return anything, so need to see if the current theme is what we just set it to
			$test_theme = wp_get_theme();
			$test_theme_slug = $test_theme->get_stylesheet();
			if ($test_theme_slug !== $theme_slug) {
				throw new ConfigException("After changing the stylesheet to $theme_slug, the active stylesheet is $test_theme_slug");
			}

			wp_cache_flush();
			$results = ob_get_contents();
			if (ob_get_length()) ob_end_clean();
			$this->log->log('theme','switch',$theme_name,$results);
		} catch (\Exception $e) {
			$this->log->log('error','switch_theme',$theme_name,$e);
		}
	}




	protected function get_theme_slugnames() {

		require_once( ABSPATH . 'wp-admin/includes/theme.php' );
		require_once( ABSPATH . 'wp-includes/class-wp-theme.php' );


		$ret = array();
		/**
		 * @var \WP_Theme[] $themes_all
		 */
		$themes_all = wp_get_themes() ;
		$theme_dir = ABSPATH . 'wp-content/themes/';
		foreach($themes_all as $theme_info) {
			$slug = $theme_info->get_stylesheet();
			$absolute_path = $theme_dir . $slug;
			$node = [
				'slug' =>  $slug,
				'file' => $absolute_path

			];
			$ret[$slug] = $node;
		}

		return $ret;

	}

	/**
	 * @param string $url
	 *   can be a slug,url or a full path to a zip on the local drive
	 * @return void
	 * @throws SecondTryException
	 */
	protected function install_theme($url) {


		require_once( ABSPATH . 'wp-admin/includes/misc.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/theme.php' );

		require_once( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );
		require_once( ABSPATH . 'wp-admin/includes/theme-install.php' );
		require_once( ABSPATH . 'wp-admin/includes/class-theme-upgrader.php' );
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
				$api = themes_api('theme_information', array('slug' => $slug, 'fields' => array('sections' => 'false')));
				if (is_wp_error($api)) {
					$messages = $api->get_error_messages();
					if (empty($messages)) {
						$error_message = "WP Error Class returned but no information given";
					} else {
						$error_message = implode(', ', $messages);
					}
					throw new ConfigException("Could not find theme of $url because: $error_message");
				}
				$download_link = $api->download_link;
			}

			ob_start(); //install always prints to the screen, capture the words for the log
			$upgrader = new \Theme_Upgrader();
			$upgrader->skin = new MyInstallSkinTheme();




			/**
			 * @var \WP_Error|null $upgrade_ret
			 */
			$upgrade_ret = $upgrader->install($download_link);
			$results = ob_get_contents();
			if (ob_get_length()) ob_end_clean();
			if (is_null($upgrade_ret)) {
				throw new ConfigException("Could not install theme of $url, is it already installed? -> $results");
			}
			if ($upgrade_ret === false) {
				throw new ConfigException("Could not install theme of $url, unknown error -> ". $results);
			}
			if (is_wp_error($upgrade_ret)) {
				$messages = $upgrade_ret->get_error_messages();
				if (empty($messages)) {
					$error_message = "WP Error Class returned but no information given. -> ". $results;
				} else {
					$error_message = implode(', ', $messages);
				}
				throw new ConfigException("Could not install theme of $url because: $error_message -> ".  $results);
			}


			$theme_to_switch = $upgrader->theme_info();
			$stylesheet = $theme_to_switch->get_stylesheet();
			switch_theme($stylesheet);

			//the above does not return anything, so need to see if the current theme is what we just set it to
			$test_theme = wp_get_theme();
			$test_theme_slug = $test_theme->get_stylesheet();
			if ($test_theme_slug !== $stylesheet) {
				throw new ConfigException("After changing the stylesheet to $stylesheet, the active stylesheet is $test_theme_slug");
			}

			wp_cache_flush();
			$this->log->log('theme','install',$url,$results);
		} catch (\Exception $e) {
			$this->log->log('error','install_theme',$url,$e);
		}
	}




	/**
	 * @throws SecondTryException
	 */
	public function run_themes() {
		foreach ($this->runnable_themes as $r) {
			$action = $r['action'];
			switch ($action) {
				case 'install': {
					$this->install_theme($r['resource']);
					break;
				}
				case 'delete': {
					$this->delete_theme($r['slug']);
					break;
				}
				case 'switch': {
					$this->switch_theme($r['slug']);
					break;
				}

				default: {
					throw new ConfigException("themes action needs to be one of install|switch|delete instead of [$action]");
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

		$themes_to_show = $this->runnable_themes;
		$logs = $this->log->get_log_results();

		$logged_options = [];
		//will show the last log for each theme, in case there are multiple runs
		foreach ($logs as $log) {
			$action = $log['action'];
			$log_name = $log['name'];
			$log_value = $log['value']; //slug or url of theme
			$log_result = $log['result'];
			$run_id = $log['run_id'];
			if ($action === 'theme') {

				switch ($log_name) {
					case 'install': {
						$result = 'Installed';
						break;
					}
					case 'delete': {
						$result = 'Deleted';
						break;
					}
					case 'switch': {
						$result = 'Switched';
						break;
					}
					default: {
						throw new ConfigException("Themes action needs to be one of install|switch|delete instead of [$action]");
					}
				}

				$logged_options[$log_value] = ['run_id' =>  $run_id,'title'=> 'Theme','name'=>$log_name,
				                               'value'=> $log_value, 'result' => $result,'is_error'=>false,
				                               'has_run' => true];
			}
			elseif (($action === 'error') ) {

				if (strpos($log_name, 'theme') !== false) {
					$logged_options[$log_value] = [ 'run_id' =>  $run_id,
					                                'title'=> 'Theme','name'=>$log_name,
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
		foreach ($themes_to_show as $op) {
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
					//log was for earlier value of the theme
					$b_new = true;
				}
			} else {
				$b_new = true;
			}

			if ($b_new) {
				$combined[] = ['title'=> 'Theme','name'=>$key,
				               'value'=> $value,
				               'result' => "Not Run Yet",
				               'is_error'=>false,
				               'has_run' => false];
			}
		}

		return $combined;
	}


}