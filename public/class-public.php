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


	public function do_parse_request($continue, /** @noinspection PhpUnusedParameterInspection */
		\WP $wp, /** @noinspection PhpUnusedParameterInspection */
		$extra_query_vars) {
		global /** @noinspection PhpUnusedLocalVariableInspection */
		$wpdb;
		if ( preg_match( '~(.*)\\/'.strtolower( PLUGIN_NAME) .'\\/command-(.*)$~', $_SERVER['REQUEST_URI'],$matches ) ) {
			$extra_info =  trim($matches[2]);
			if (empty($extra_info)) {
				//if reached here then things work, just return 200
				http_response_code(200);
				//get the number of logs
				die();
			}
			if (strcmp($extra_info,'stats') === 0) {
				$b_ok = true;
				try {
					$queue_resp = "Working";
				} catch (\Exception $e) {
					$queue_resp = $e->getMessage();
					$b_ok = false;
				}


				$what = [
					'status' => $b_ok,
					'queue_status' =>$queue_resp
				];

				http_response_code(200);
				echo json_encode($what);
				die();
			}
			//convert it to a query string

			die();
		}

		return $continue;
	}

}
