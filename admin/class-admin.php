<?php
namespace maider;


class Admin {

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
	 * Holds the values to be used in the fields callbacks
	 */
	private $options;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

        $b_check = strpos($_SERVER['QUERY_STRING'], strtolower( PLUGIN_NAME));
        if ($b_check !== false) {
            wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/admin.css', array(), $this->version, 'all' );
        }


	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {


        $b_check = strpos($_SERVER['QUERY_STRING'], strtolower( PLUGIN_NAME));


        if ($b_check !== false) {


            wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/admin.js', array( 'jquery' ), $this->version, false );

            $title_nonce = wp_create_nonce(strtolower( PLUGIN_NAME) . '_admin');
            wp_localize_script($this->plugin_name, strtolower( PLUGIN_NAME) .'_backend_ajax_obj', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'action' => strtolower( PLUGIN_NAME) .'_admin',
                'nonce' => $title_nonce,
            ));
        }

	}

    public function my_admin_menu() {
//	    add_options_page( PLUGIN_NAME .'Options', PLUGIN_NAME, 'manage_options',
//		    strtolower( PLUGIN_NAME), array( $this, 'create_admin_interface') );//

        add_menu_page(
	        PLUGIN_NAME .'Options',
	        PLUGIN_NAME,
	        'manage_options',
	        strtolower( PLUGIN_NAME) .'/'. strtolower( PLUGIN_NAME) .'-admin-page.php',
	        array( $this, 'create_admin_interface' ),
	        'dashicons-admin-tools',
	        null
        );
    }

    /**
     * Callback function for the admin settings page.
     *
     * @since    1.0.0
     */
    public function create_admin_interface(){

	    $this->options = get_option( strtolower( PLUGIN_NAME). '_options' );
        /** @noinspection PhpIncludeInspection */
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/admin-gui.php';

    }



    public function add_settings() {



	    register_setting(
		    strtolower( PLUGIN_NAME).'-options-group', // Option group
		    strtolower( PLUGIN_NAME). '_options', // Option name
		    array( $this, 'sanitize' ) // Sanitize
	    );

	    add_settings_section(
		    strtolower( PLUGIN_NAME). '_section_id', // ID
		    'Sets A List of Options, Plugins and Themes', // Title
		    array( $this, 'print_section_info' ), // Callback
		    strtolower( PLUGIN_NAME). '-options' // Page
	    );





//	    add_settings_field(
//		    'redirect_url', // ID
//		    'Access', // Title
//		    array( $this, 'test_blocked_ports_callback' ), // Callback
//		    strtolower( PLUGIN_NAME). '-options', // Page
//		    strtolower( PLUGIN_NAME). '_section_id' // Section
//	    );


    }





	public function test_blocked_ports_callback() {
		$to = 'willwoodlief@gmail.com';
		$subject = 'test from wordpress';
		$body = 'Hello';
		$headers = array('Content-Type: text/html; charset=UTF-8');

		$b_what = wp_mail( $to, $subject, $body, $headers );
		if ($b_what) {
			printf(
				'
					<div style="display: inline-block">
					   The mail does not return false
 						
                    </div>'
			);
		} else {
			printf(
				'
					<div style="display: inline-block">
 						The mail was not sent out
                    </div>'
			);
		}
    }




	public function redirect_url_callback() {

    	if (array_key_exists('redirect_url',$this->options)) {
		    $redir =  $this->options['redirect_url'] ;
	    } else {
		    $redir =  '' ;
	    }

		printf(
			'
					<div style="display: inline-block">
 						<input type="url" value="%s" id="redirect_url" name="%s[redirect_url]" size="60" >
 						<br>
 						<span style="font-size: smaller"> Where do you want the user to go after the survey ? </span>
                    </div>',
			$redir,strtolower( PLUGIN_NAME). '_options'
		);

	}




	/**
	 * Sanitize each setting field as needed
	 *
	 * @param array $input Contains all settings fields as array keys
	 * @return array
	 */
	public function sanitize( $input )
	{

		$new_input = array();


		if( isset( $input['redirect_url'] ) ) {
			$new_input['redirect_url'] = sanitize_text_field( $input['redirect_url'] );
		} else {
			$new_input['redirect_url'] = '' ;
		}


		return $new_input;
	}

	/**
	 * Print the Section text
	 */
	public function print_section_info()
	{
		print 'Enter your settings below:';
	}

    public function query_survey_ajax_handler() {
	    /** @noinspection PhpIncludeInspection */
	    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/maider/config/class-config.php';
	    /** @noinspection PhpIncludeInspection */
	    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'lib/JsonHelper.php';

        /** @noinspection PhpIncludeInspection */
        check_ajax_referer(strtolower( PLUGIN_NAME).'_admin');

        if (array_key_exists( 'method',$_POST) && $_POST['method'] == 'combined_logs') {
            try {
	            $config_yaml_path = realpath( dirname( __FILE__ ) . "/../config/config.yaml" );
	            if ( ! $config_yaml_path ) {
		            throw new \Exception( "Cannot find the config file path at ../config/config.yaml " );
	            }

	            $config = new Config($config_yaml_path,'combined_logs');
	            $combined = $config->get_combined_info();
                wp_send_json(['is_valid' => true, 'data' => $combined, 'action' => 'combined_logs']);
                die();
            } catch (\Exception $e) {
                wp_send_json(['is_valid' => false, 'message' => $e->getMessage(), 'trace'=>$e->getTrace(), 'action' => 'stats' ]);
                die();
            }

        } elseif (array_key_exists( 'method',$_POST) && $_POST['method'] == 'run') {
	        try {
		        $config_yaml_path = realpath( dirname( __FILE__ ) . "/../config/config.yaml" );
		        if ( ! $config_yaml_path ) {
			        throw new \Exception( "Cannot find the config file path at ../config/config.yaml " );
		        }

		        $config = new Config($config_yaml_path,'combined_logs');
		        $config->run();
		        wp_send_json(['is_valid' => true, 'data' => null, 'action' => 'run']);
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



}
