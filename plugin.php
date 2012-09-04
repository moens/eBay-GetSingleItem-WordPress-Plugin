<?php
   /*
   Plugin Name: eBay GetSingleItem
   Plugin URI: https://github.com/moens/eBay-GetSingleItem-WordPress-Plugin
   Description: Embeds an ebay item view in a post via a short code
   Version: 0.0.1
   Author: Sy Moen
   Author URI: https://github.com/moens
   Network: false
   License: ISC (freeBSD style)
   License URI: http://www.isc.org/software/license
   */

// TODO: change 'Widget_Name' to the name of your actual plugin
class ebayGetSingleItem { // extends WP_Widget { <-- this class is not a widget

	protected $appId = '';
	protected $configIssues = array();

	/*--------------------------------------------------*/
	/* Constructor
	/*--------------------------------------------------*/
	
	/**
	 * The widget constructor. Specifies the classname and description, instantiates
	 * the widget, loads localization files, and includes necessary scripts and
	 * styles.
	 */
	public function __construct() {
	
		// TODO be sure to change 'ebayGetSingleItem' to the name of *your* plugin
//		load_plugin_textdomain( 'ebayGetSingleItem-locale', false, plugin_dir_path( __FILE__ ) . '/lang/' );
		
		// Manage plugin ativation/deactivation hooks
		register_activation_hook( __FILE__, array( &$this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );
		
		// TODO: update classname and description
		// TODO: replace 'ebayGetSingleItem-locale' to be named more plugin specific. other instances exist throughout the code, too.
		parent::__construct(
			'ebay-single-id',
			__( 'Widget Name', 'ebayApi-locale' ),
			array(
				'classname'		=>	'ebay-single-class',
				'description'	=>	__( 'Plugin to display a compact instance of a current eBay auction via shortcode.', 'ebayApi-locale' )
			)
		);
		
		if(!$this->setAppId) $this->setConfigIssues(__('The eBay AppID has not been set, please see Admin > Settings > eBayAPI. Thanks!'));
		
		// Setup ShortCodes
		add_shortcode('eBayItem', 'ebayGetSingleItemShortcode');

		// Setup Admin Area
		add_action( 'admin_menu', array( &$this, 'ebayApiSetupMenu' ) );
			// use the $ebayGetSingleItem->admin-menu method to add a Setup > Menu to backend
		add_action( 'admin_init', array( &$this, 'ebayApiAdminInit' ) );
			// add the ebayApiAdminInit method to the admin_init hook

		// Register admin styles and scripts
//		add_action( 'admin_print_styles', array( &$this, 'register_admin_styles' ) );
//		add_action( 'admin_enqueue_scripts', array( &$this, 'register_admin_scripts' ) );
	
		// Register site styles and scripts
		add_action( 'wp_enqueue_scripts', array( &$this, 'register_widget_styles' ) );
//		add_action( 'wp_enqueue_scripts', array( &$this, 'register_widget_scripts' ) );
		
	} // end constructor


	/*--------------------------------------------------*/
	/* Shortcode Functions
	/*--------------------------------------------------*/

	/**
	 * Outputs an html string to replace the [eBayItem id='1234567890'] or [eBayItem]1234567890[/eBayItem] shortcode
	 *
	 * @atts		The array of shortcode attributes if any
	 * @content		The data contained between the begin, /end shortcode if any
	 * @return		string : html
	 */
	function ebayGetSingleItemShortcode( $atts, $content ) {
		if($this->existsConfigIssues()) return getConfigIssues();
		extract( $atts );
		$id = trim($id);
		$content = trim($content);
		if(!preg_match('/^[0-9]{8,19}$/', $id)) {
			if(!preg_match('/^[0-9]{8,19}$/', $content)) {
				return NULL;
			} else {
				$eid = $content;
			}
		} else { 
			$eid = $id;
		}
		$url = "http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=JSON" .
			"&appid=" . $this->getAppId() . "&siteid=0&ItemID=" . $eid . "&version=783";
		$json = wp_remote_retrieve_body( wp_remote_get( $url ) );
		$ebayItemData = json_decode( $json, TRUE );

		//Get pertinent data:
		// linkURL (string/url)
		$linkURL = $ebayItemData['ViewItemURLForNaturalSearch'];
		// title (string/text)
		$title = $ebayItemData['Title'];
		preg_match_all('/extract>(.*)<\/extract/', $ebayItemData['Description'], $matchData);
		// description (string/html)
		$description = $matchData[1];
		// isEnded (bool)
		$isEnded = ($ebayItamData['ListingStatus'] == 'Completed')?TRUE:FALSE;
		if(!$isEnded) {
			date_default_timezone_set('America/Denver');
			$endingTimeStamp = strtotime($ebayItamData['EndTime']);
			// endingTime
			$endingTime = date('l, F jS Y T \a\t g:ia', $endingTimeStamp);
			$durationVals = $this->parseDuration($ebayItamData['TimeLeft']);
			$duration = '';
			foreach($durationVals as $name => $interval) {
				$durationText[] = $interval . ' ' . ( ($interval > 1)?$name:rtrim($name,'s') );
			}
			// duration
			$duration = implode(', ', $durationText);
		}
		// bidPrice
		$bidPrice = (isset($ebayItemData['ConvertedCurrentPrice']['Value']))?$ebayItemData['ConvertedCurrentPrice']['Value']:FALSE;
		// isBuyItNow
		$isBuyItNow = (isset($ebayItemData['BuyItNowAvailable']))?$ebayItemData['BuyItNowAvailable']:FALSE;
		// buyItNowPrice
		$buyItNowPrice = (isset($ebayItemData['ConvertedBuyItNowPrice']['Value']))?$ebayItemData['ConvertedBuyItNowPrice']['Value']:FALSE;
		// listingType
		$listingType = $ebayItemData['ListingType'];
		// isSold
		$isSold = ( !$isEnded ) ? FALSE : ( ( isset ( $ebayItemData['BidCount'] ) && ( $ebayItemData['BidCount'] > 0 ) ) ? TRUE : FALSE );
		//Layout
?>
		<h2><a href="<?php echo $linkURL ?>"><?php echo $title ?></a></h2>
		<div><?php echo $description ?></div>
		<div class="price">
			<?php _e('Current bid price:') ?> <?php echo $bidPrice ?>
<?php		if(!$isEnded): // show current bid, buy it now if available, end time and time left ?>
<?php			if($isBuyItNow && $buyItNowPrice): ?>
				&nbsp;&mdash;&nbsp;<?php _e('Or buy it now for:') ?> <?php echo $buyItNowPrice ?>
<?php			endif; ?>
<?php			if($listingType != 'FixedPriceItem' && $listingType != 'StoresFixedPrice'): ?>
			<div class="timeLeft">
				<?php echo __('Auction ends:') . ' ' . $endingTime . '(' . $duration . ')' ?>
			</div>
<?php			endif; ?>
<?php		else: ?>
			<div class="ended">
				<p><?php echo __('This auction has ended,') . $isSold?__(' and is no longer available.'):__(' but we still have this item... <a href="/contact.html">contact us</a> for more details.') ?></p>
			</div>
<?php		endif; ?>
		</div>
		<div class="seeAuction"><a href="<?php echo $linkURL ?>">See Auction on eBay &raquo;</a></div>
<?
	}

	/**
	* Parse an ISO 8601 duration string
	* @return array
	* @param string $str
	**/
	function parseDuration($str)
	{
	   $result = array();
	   preg_match('/^(?:P)([^T]*)(?:T)?(.*)?$/', trim($str), $sections);
	   if(!empty($sections[1]))
	   {
	      preg_match_all('/(\d+)([YMWD])/', $sections[1], $parts, PREG_SET_ORDER);
	      $units = array('Y' => 'years', 'M' => 'months', 'W' => 'weeks', 'D' => 'days');
	      foreach($parts as $part)
	      {
		 $result[$units[$part[2]]] = $part[1];
	      }
	   }
	   if(!empty($sections[2]))
	   {
	      preg_match_all('/(\d+)([HMS])/', $sections[2], $parts, PREG_SET_ORDER);
	      $units = array('H' => 'hours', 'M' => 'minutes', 'S' => 'seconds');
	      foreach($parts as $part)
	      {
		 $result[$units[$part[2]]] = $part[1];
	      }
	   }
	   return($result);
	}

	/*--------------------------------------------------*/
	/* Widget API Functions
	/*--------------------------------------------------*/
	
	/**
	 * Outputs the content of the widget.
	 *
	 * @args			The array of form elements
	 * @instance		The current instance of the widget
	 */
	/*
	public function widget( $args, $instance ) {
	
		extract( $args, EXTR_SKIP );
		
		echo $before_widget;
		
    	// TODO: This is where you retrieve the widget values.
    	// Note that this 'Title' is just an example
    	$title = apply_filters('widget_title', empty( $instance['title'] ) ? __( 'Widget Name', 'ebayApi-locale' ) : $instance['title'], $instance, $this->id_base);
    
		include( plugin_dir_path(__FILE__) . '/views/widget.php' );
		
		echo $after_widget;
		
	} // end widget
	*/
	
	/**
	 * Processes the widget's options to be saved.
	 *
	 * @new_instance	The previous instance of values before the update.
	 * @old_instance	The new instance of values to be generated via the update.
	 */
	public function update( $new_instance, $old_instance ) {
	
		$instance = $old_instance;
		
		// TODO Update the widget with the new values
		// Note that this 'Title' is just an example
		$instance['title'] = strip_tags( $new_instance['title'] );
    
		return $instance;
		
	} // end widget
	
	/**
	 * Generates the administration form for the widget.
	 *
	 * @instance	The array of keys and values for the widget.
	 */
	public function form( $instance ) {
	
    	// TODO define default values for your variables
		$instance = wp_parse_args(
			(array) $instance,
			array(
				'title'	=>	__( 'Widget Name', 'ebayApi-locale' ),
			)
		);
	
		// TODO store the values of widget in a variable
		
		// Display the admin form
		include( plugin_dir_path(__FILE__) . '/views/admin.php' );	
		
	} // end form

	/*--------------------------------------------------*/
	/* Public Functions
	/*--------------------------------------------------*/
	
	/**
	 * Fired when the plugin is activated.
	 *
	 * @params	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog 
	 */
	public function activate( $network_wide ) {
		// TODO define activation functionality here
		check to see wheather eBay appid is set
		if($setAppId) return TRUE;
		set admin alert
	} // end activate
	
	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @params	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog 
	 */
	public function deactivate( $network_wide ) {
		// TODO define deactivation functionality here		
	} // end deactivate
	
	/*--------------------------------------------------*/
	/* Create the Admin > Setup Page
	/*--------------------------------------------------*/
	
	/**
	 * Add plugin specific page to Admin > Settings >
	 */
	public function ebayApiSetupMenu() {
		add_options_page( 'eBay Api ID', 'eBay Api ID', 'manage_options', 'ebayApi', array($this, 'ebayApiOptions') );
	}

	/**
	 * Output plugin specific page to Admin > Setup > 
	 */
	function ebayApiOptions() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
?>
		<div class="wrap">
			<h2>Required for the ebayGetSingleItem Plugin</h2>
			<form action="options.php" method="post">
			<?php settings_fields('ebayApi_options'); ?>
			<?php do_settings_sections('ebayApi'); ?>

			<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
			</form>
		</div>
<?php
	}	

	/**
	 * Set up the admin settings menu page options
	 */
	public function ebayApiAdminInit() {
		register_setting( $option_group = 'ebayApi_options', $option_name = 'appId', $sanitize_callback = 'appId_validation' );
		add_settings_section($id = 'ebayApi_basicSettings', $title = 'Basic Settings', $callback = 'ebayApiBasicSettingsHeader', $page = 'ebayApi');
		add_settings_field($id = 'ebayAppId', $title = 'eBay AppID', $callback = 'ebayAppIdInputSring', $page = 'ebayApi', $section = 'ebayApi_basicSettings');
	}

	/**
	 * Header info for the Admin > Settings > ebayApi page
	 */
	public function ebayApiBasicSettingsHeader() { ?>
		<p>You must have an eBay AppID in order to use the eBay API. If you do not have one, you can get one from eBay:</p>
		<ul>
			<li>Go to the eBay / x.com developer center: <a href="https://www.x.com/developers/ebay">https://www.x.com/developers/ebay</a></li>
			<li>Sign up or Log in to your <strong>Developer</strong> account (on the right side of the page <strong>below</strong> the main login. Yet another counter-intuitive UI from eBay.</li>
			<li>On your main "My Account" page, you will see a place to generate application keys. Generate a set of Production Keys.</li>
			<li>Copy the AppID, and paste it below... and, Done!</li>
		</ul>
	<?php }

	/**
	 * Form Inputs for the Admin > Settings > eBayApi > Basic Settings section
	 */
	private function ebayAppIdInputSring() {
		$options = get_option('ebayApi_options');
		?>
		<input id="ebayAppId" name="ebayApi_options[appId]" size="40" type="text" value="<?php echo $options['appId'] ?>" />
		<?php
	}

	/**
	 * Input Validation for the Admin > Settings > eBayApi > Basic Settings > appid field
	 */
	private function ebayAppId_validation($input) {
		$trimmedInput['text_string'] = trim($input['text_string']);
		if(!preg_match('/^[a-zA-Z0-9\._-]{8}-([a-fA-F0-9]{4}-){3}[a-fA-F0-9]{12}$/', $trimmedInput['text_string'])) {
			$trimmedInput['text_string'] = '';
			add_settings_error('The AppID you entered does not appear to be valid. It should contain no spaces and look something like this: MyAccoun-de40-4e7a-b2c2-ac6dedac95ad');
		}
		return $trimmedInput;
	}

	/**
	 * Registers and enqueues admin-specific styles.
	 */
	public function register_admin_styles() {
	
		// TODO change 'ebayGetSingleItem' to the name of your plugin
		wp_register_style( 'ebayGetSingleItem-admin-styles', plugins_url( 'ebayGetSingleItem/css/admin.css' ) );
		wp_enqueue_style( 'ebayGetSingleItem-admin-styles' );
	
	} // end register_admin_styles

	/**
	 * Registers and enqueues admin-specific JavaScript.
	 */
	/*
	public function register_admin_scripts() {
	
		wp_register_script( 'ebayGetSingleItem-admin-script', plugins_url( 'ebayGetSingleItem/js/admin.js' ) );
		wp_enqueue_script( 'ebayGetSingleItem-admin-script' );
		
	} // end register_admin_scripts

	*/

	/**
	 * Registers and enqueues widget-specific admin styles.
	 */
	/*
	public function register_widget_styles() {
	
		wp_register_style( 'ebayGetSingleItem-widget-styles', plugins_url( 'ebayGetSingleItem/css/admin.css' ) );
		wp_enqueue_style( 'ebayGetSingleItem-widget-styles' );
		
	} // end register_widget_styles

	*/

	/**
	 * Registers and enqueues widget-specific scripts.
	 */
	/*
	public function register_widget_scripts() {
	
		wp_register_script( 'ebayGetSingleItem-admin-script', plugins_url( 'ebayGetSingleItem/js/admin.js' ) );
		wp_enqueue_script( 'ebayGetSingleItem-widget-script' );
		
	} // end register_widget_scripts
	
	*/

	
	/*--------------------------------------------------*/
	/* Variable Handlers
	/*--------------------------------------------------*/

	public function setAppId() {
		$option = get_option('ebayApi_options');
		$this->appId = $options['appId'];
	}
	
	public function getAppId() {
		return $this->appId;
	}

	public function setConfigIssues($issueTextString = '') {
		if($issueTextString == '') $this->configIssues[] = __('An unknown error occured.');
		else $this->configIssues[] = $issueTextString;
	}

	public function existsConfigIssues() {
		return count($this->configIssues);
	}

	public function getConfigIssues() {
		if(!count($this->configIssues)) return NULL;
		echo '<dl><dt>' . __('The eBay getSingleItem plugin is reporting the following error(s):') . '</dt>' . PHP_EOL;
		foreach($this->configIssues as $issue) {
			echo '<dd>' . $issue . '</dd>' . PHP_EOL;
		}
		echo '</dl>' . PHP_EOL;
	}
	
} // end class

// TODO remember to change 'Widget_Name' to match the class name definition
add_action( 'widgets_init', create_function( '', 'register_widget("Widget_Name");' ) ); 
?>
