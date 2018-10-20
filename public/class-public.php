<?php

namespace maider;

/**
 * The public-facing functionality of the plugin.
 *
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 */
class Plugin_Public
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string $plugin_name The name of the plugin.
     * @param      string $version The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {

        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/public.css', array(), $this->version, 'all');

    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {



        wp_enqueue_script($this->plugin_name. 'a', plugin_dir_url(__FILE__) . 'js/public.js', array('jquery'), $this->version, false);
        $title_nonce = wp_create_nonce(strtolower( PLUGIN_NAME) . 'public_nonce');
        wp_localize_script($this->plugin_name. 'a', strtolower( PLUGIN_NAME) . '_frontend_ajax_obj', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'action' => strtolower( PLUGIN_NAME) . '_submit_chart_step',
            'nonce' => $title_nonce,
            'plugins_url' => plugins_url()
        ));

    }


    public function send_survey_ajax_handler() {


	    check_ajax_referer( strtolower( PLUGIN_NAME) . 'public_nonce' );

	    if (array_key_exists( 'method',$_POST) && $_POST['method'] == 'survey_answer') {

		    try {
			    $response_id = null;
			    wp_send_json(['is_valid' => true, 'data' => $response_id, 'action' => 'updated_survey_answer']);
			    die();
		    } catch (\Exception $e) {
			    wp_send_json(['is_valid' => false, 'message' => $e->getMessage(), 'trace'=>$e->getTrace(), 'action' => 'stats' ]);
			    die();
		    }
	    }

	    else {
		    //unrecognized
		    wp_send_json(['is_valid' => false, 'message' => "unknown action"]);
		    die();
	    }
    }

    //JSON


    public function shortcut_code()
    {
    	global $is_wp_init_called;
	    $is_wp_init_called = true;
        add_shortcode($this->plugin_name, array($this, 'manage_shortcut'));

    }

    /**
     * @param array $attributes - [$tag] attributes
     * @param null $content - post content
     * @param string $tag
     * @return string - the html to replace the shortcode
     */
    public
    function manage_shortcut($attributes = [], $content = null, $tag = '')
    {
        global $shortcut_content;
// normalize attribute keys, lowercase
        $atts = array_change_key_case((array)$attributes, CASE_LOWER);

        // override default attributes with user attributes
	    /** @noinspection PhpUnusedLocalVariableInspection */
	    $our_atts = shortcode_atts([
            'border' => 1,
            'results' => 0,
        ], $atts, $tag);

        // start output
        $o = '';

        $shortcut_content = '';
        // enclosing tags
        if (!is_null($content)) {

            // run shortcode parser recursively
            $expanded__other_shortcodes = do_shortcode($content);
            // secure output by executing the_content filter hook on $content, allows site wide auto formatting too
            $shortcut_content .= apply_filters('the_content', $expanded__other_shortcodes);

        }

	    /** @noinspection PhpIncludeInspection */
	    require_once plugin_dir_path(dirname(__FILE__)) . 'public/partials/shortcode-gui.php';


        // return output
        return $o;
    }


	/**
	 * @param $continue
	 * @param \WP $wp
	 * @param $extra_query_vars
	 *
	 * @return mixed
	 */
	public function do_parse_request($continue, /** @noinspection PhpUnusedParameterInspection */
		\WP $wp, /** @noinspection PhpUnusedParameterInspection */
		$extra_query_vars) {

		/** @noinspection PhpIncludeInspection */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/maider/config/class-config.php';
		/** @noinspection PhpIncludeInspection */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/JsonHelper.php';
		/** @noinspection PhpIncludeInspection */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/Input.php';

		global /** @noinspection PhpUnusedLocalVariableInspection */
		$wpdb;

		$url_parts = parse_url($_SERVER['REQUEST_URI']);
		$url_path =  $url_parts['path'];


		if ( preg_match( '~(.*)\\/'.strtolower( PLUGIN_NAME) .'\\/command-(.*)$~', $url_path,$matches ) ) {
			$extra_info =  trim($matches[2]);

			$b_json = false;
			try {
				$return_type = Input::get( 'format', 'text' );

				switch ( $return_type ) {
					case 'json':
						{
							$b_json = true;
						}
					case 'text':
						{
							break;
						}
					default:
						{
							JsonHelper::printErrorJSONAndDie( "the format http param needs to be either text or json, with the default as text" );
						}
				}

				//open up the configs
				$config_yaml_path = realpath( dirname( __FILE__ ) . "/../config/config.yaml" );
				if ( ! $config_yaml_path ) {
					throw new \Exception( "Cannot find the config file path at ../config/config.yaml " );
				}

				$config = new Config($config_yaml_path,$extra_info);
				$secret_input = Input::get('secret',Input::THROW_IF_MISSING);
				$config->check_secret_access($secret_input) ;
				//if got here then authorized

				if ( strcmp( $extra_info, 'status' ) === 0 ) {
					try {

						$what = 'working';
						if ( $b_json ) {
							JsonHelper::printStatusJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					} catch ( \Exception $e ) {
						$queue_resp = $e->getMessage();
						$what       = [
							'status'  => 'error',
							'message' => $queue_resp
						];

						if ( $b_json ) {
							JsonHelper::printErrorJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					}
				}

				if ( strcmp( $extra_info, 'help' ) === 0 ) {
					try {
						$what = [
							[ 'command'     => 'logs',
							  'description' => 'lists all the logs'
							],
							[ 'command'     => 'clear-logs',
							  'description' => 'truncates the log'
							],
							[ 'command'     => 'run',
							  'description' => 'run the updating of options, plugins and themese'
							],
							[ 'command'     => 'list-config',
							  'description' => 'displays the processed configeration, which is ready to run'
							],
							[ 'command'     => 'list-config-raw',
							  'description' => 'displays the configeration before its processed. Will not process the config first'
							],


							[ 'command'     => 'list-available-options',
							  'description' => 'lists the options that can be changed by this plugin'
							],
							[ 'command'     => 'list-unused-options',
							  'description' => 'lists the options that will not be changed by this plugin'
							],
							[ 'command'     => 'check-defaults-with-current',
							  'description' => 'gives a list of everything in the set options which are different from the defaults'
							],
							[ 'command' => 'help', 'description' => 'This help command' ]

						];

						if ( $b_json ) {
							JsonHelper::printStatusJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}

						die();
					} catch ( \Exception $e ) {
						$queue_resp = $e->getMessage();
						$what       = [
							'status'  => 'error',
							'message' => $queue_resp
						];

						if ( $b_json ) {
							JsonHelper::printErrorJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					}

				}

				if ( strcmp( $extra_info, 'logs' ) === 0 ) {
					try {

						$what = $config->log->get_log_results();
						if ( $b_json ) {
							JsonHelper::printStatusJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					} catch ( \Exception $e ) {
						$queue_resp = $e->getMessage();
						$what       = [
							'status'  => 'error',
							'message' => $queue_resp
						];

						if ( $b_json ) {
							JsonHelper::printErrorJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					}
				}

				if ( strcmp( $extra_info, 'clear-logs' ) === 0 ) {
					try {

						$config->log->clear_logs();
						if ( $b_json ) {
							JsonHelper::printStatusJSONAndDie( "logs cleared" );
						} else {
							JsonHelper::print_nice( "logs cleared" );
						}
						die();
					} catch ( \Exception $e ) {
						$queue_resp = $e->getMessage();
						$what       = [
							'status'  => 'error',
							'message' => $queue_resp
						];

						if ( $b_json ) {
							JsonHelper::printErrorJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					}
				}
				

				if ( strcmp( $extra_info, 'run' ) === 0 ) {
					try {

						$config->run();
						$what = $config->log->get_log_results();
						$self_delete_log = $config->maybe_self_delete_now();
						$what[] = $self_delete_log;
						if ( $b_json ) {
							JsonHelper::printStatusJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					} catch ( \Exception $e ) {
						$queue_resp = $e->getMessage();
						$what       = [
							'status'  => 'error',
							'message' => $queue_resp
						];

						if ( $b_json ) {
							JsonHelper::printErrorJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					}
				}


				if ( strcmp( $extra_info, 'list-config' ) === 0 ) {
					try {

						$what          = $config->get_config();
						if ( $b_json ) {
							JsonHelper::printStatusJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					} catch ( \Exception $e ) {
						$queue_resp = $e->getMessage();
						$what       = [
							'status'  => 'error',
							'message' => $queue_resp
						];

						if ( $b_json ) {
							JsonHelper::printErrorJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					}
				}

				if ( strcmp( $extra_info, 'list-config-raw' ) === 0 ) {
					try {

						$what          = $config->get_raw_config();
						if ( $b_json ) {
							JsonHelper::printStatusJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					} catch ( \Exception $e ) {
						$queue_resp = $e->getMessage();
						$what       = [
							'status'  => 'error',
							'message' => $queue_resp
						];

						if ( $b_json ) {
							JsonHelper::printErrorJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					}
				}

				if ( strcmp( $extra_info, 'list-available-options' ) === 0 ) {
					try {

						$what          = $config->get_options()->get_options();
						if ( $b_json ) {
							JsonHelper::printStatusJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					} catch ( \Exception $e ) {
						$queue_resp = $e->getMessage();
						$what       = [
							'status'  => 'error',
							'message' => $queue_resp
						];

						if ( $b_json ) {
							JsonHelper::printErrorJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					}

				}

				if ( strcmp( $extra_info, 'list-unused-options' ) === 0 ) {
					try {
						$what          = $config->get_options()->get_unavaliable_options();
						if ( $b_json ) {
							JsonHelper::printStatusJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
					} catch ( \Exception $e ) {
						$queue_resp = $e->getMessage();
						$what       = [
							'status'  => 'error',
							'message' => $queue_resp
						];

						if ( $b_json ) {
							JsonHelper::printErrorJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					}

				}

				if ( strcmp( $extra_info, 'check-defaults-with-current' ) === 0 ) {
					try {

						$what          = $config->get_options()->do_wp_test_with_defaults();
						if ( $b_json ) {
							JsonHelper::printStatusJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					} catch ( \Exception $e ) {
						$queue_resp = $e->getMessage();
						$what       = [
							'status'  => 'error',
							'message' => $queue_resp
						];

						if ( $b_json ) {
							JsonHelper::printErrorJSONAndDie( $what );
						} else {
							JsonHelper::print_nice( $what );
						}
						die();
					}
				}

				$what = [
					'status'  => 'error',
					'message' => "unknown command: $extra_info"
				];

				if ( $b_json ) {
					JsonHelper::printErrorJSONAndDie( $what );
				} else {
					JsonHelper::print_nice( $what );
				}
				die();
			}//end try block
			catch (\Exception $fe) {
				$what = [
					'status'  => 'error',
					'message' => $fe->getMessage()
				];

				if ( $b_json ) {
					JsonHelper::printErrorJSONAndDie( $what );
				} else {
					JsonHelper::print_nice( $what );
				}
				die();
			}

		}

		return $continue;
	}

}
