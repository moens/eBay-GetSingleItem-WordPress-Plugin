<?php
   /*
   Plugin Name: eBay GetSingleItem
   Plugin URI: https://github.com/moens/eBay-GetSingleItem-WordPress-Plugin
   Description: Embeds an ebay item view in a post via a short code
   Version: 0.0.1
   Author: Sy Moen
   Author URI: https://github.com/moens
   Network: false
   Text Domain: ebayApi
   Domain Path: /lang
   License: ISC (freeBSD style)
   License URI: http://www.isc.org/software/license
   */

define('EBAPI_HANDLE', 'ebayApi');
define('EBAPI_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ));
define('EBAPI_PLUGIN_DIR', substr(plugin_basename(__FILE__), 0, strpos(plugin_basename(__FILE__), '/') -1) ); // this is the name of the folder your plugin lives in

if(!is_admin()) {	// frontend only

} else {	// admin only
	require_once( EBAPI_PLUGIN_DIR_PATH . 'vendor/updater.php' ); // load the updater class that allows github updates, see checkGithubUpdates()

}

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
	
		load_plugin_textdomain( EBAPI_HANDLE, false, EBAPI_PLUGIN_DIR_PATH . '/lang/' );
		
		// Manage plugin ativation/deactivation hooks
//		register_activation_hook( __FILE__, array( &$this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( &$this, 'deactivate' ) );

/* used in case of extending widget class...
		parent::__construct(
			EBAPI_HANDLE . '-id',
			__( 'eBay getSingleItem', EBAPI_HANDLE ),
			array(
				'classname'	=>	EBAPI_HANDLE . '-class',
				'description'	=>	__( 'Plugin to display a compact instance of a current eBay auction via shortcode.', EBAPI_HANDLE )
			)
		);
*/		

		if( !$this->setAppId() ) $this->setConfigIssues( __('The eBay AppID has not been set, please see Settings > eBay API. Thanks!') );
		if( $this->existsConfigIssues() ) add_action( 'admin_notices', array(&$this, 'getConfigIssues') );
		
		// Setup ShortCodes
		add_shortcode('eBayItem', array( &$this, 'ebayGetSingleItemShortcode' ) );

		// Setup Admin Area
		add_action( 'admin_menu', array( &$this, 'ebayApiSetupMenu' ) );
		add_action( 'admin_init', array( &$this, 'ebayApiAdminInit' ) );

		// Register admin styles and scripts
		add_action( 'admin_print_styles', array( &$this, 'register_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( &$this, 'register_admin_scripts' ) );

		// Other admin stuff
		if (is_admin()) {
			$this->checkGithubUpdates();
		}
	
		// Register site styles and scripts
		add_action( 'wp_enqueue_styles', array( &$this, 'register_plugin_styles' ) );
		add_action( 'wp_enqueue_scripts', array( &$this, 'register_plugin_scripts' ) );
		
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
	* @param string $str
	* @return array
	**/

	function parseDuration($str)
	{
	$result = array();
	preg_match('/^(?:P)([^T]*)(?:T)?(.*)?$/', trim($str), $sections);
	if(!empty($sections[1])) {
		preg_match_all('/(\d+)([YMWD])/', $sections[1], $parts, PREG_SET_ORDER);
		$units = array('Y' => 'years', 'M' => 'months', 'W' => 'weeks', 'D' => 'days');
		foreach($parts as $part) {
			$result[$units[$part[2]]] = $part[1];
		}
	}
	if(!empty($sections[2])) {
		preg_match_all('/(\d+)([HMS])/', $sections[2], $parts, PREG_SET_ORDER);
		$units = array('H' => 'hours', 'M' => 'minutes', 'S' => 'seconds');
		foreach($parts as $part) {
			$result[$units[$part[2]]] = $part[1];
		}
	}
	return($result);
	}

	/*--------------------------------------------------*/
	/* Public Functions
	/*--------------------------------------------------*/
	
	/**
	 * Fired when the plugin is activated.
	 *
	 * @params	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog 
	 */
	public function activate( $network_wide ) {
		if($network_wide) $this->setConfigIssues(__('This plugin requires an eBay developer ApiID which must be set per site.') );
	} // end activate
	
	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @params	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog 
	 */
	public function deactivate( $network_wide ) {
		$this->setConfigIssues(__('The eBay ApiId set by site admins in Settings > eBayAPI are retained in the database until the plugin is uninstalled.') );
	} // end deactivate
	
	
	/*--------------------------------------------------*/
	/* Create the Admin > Setup Page
	/*--------------------------------------------------*/
	
	/**
	 * Add plugin specific page to Admin > Settings >
	 */
	public function ebayApiSetupMenu() {
		add_options_page( $page_title = 'eBay API', $menu_title = 'eBay API', $capability = 'manage_options', $menu_slug = EBAPI_HANDLE, $function = array(&$this, 'ebayApiOptions') );
	}

	/**
	 * Output plugin specific page to Admin > Setup > 
	 */
	public function ebayApiOptions() {
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}
?>
		<div class="wrap">
			<h2>Required for the ebayGetSingleItem Plugin</h2>
			<form action="options.php" method="post">
			<?php settings_fields($option_group = EBAPI_HANDLE . '_options'); ?>
			<?php do_settings_sections($page = EBAPI_HANDLE); ?>

			<input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
			</form>
		</div>
<?php
	}	

	/**
	 * Set up the admin settings menu page options
	 */
	public function ebayApiAdminInit() {
		register_setting( $option_group = EBAPI_HANDLE . '_options', $option_name = 'appId', $sanitize_callback = array( &$this, 'appIdValidation' ) );
		add_settings_section($id = 'ebayApiBasicSettings', $title = 'Basic Settings', $callback = array(&$this, 'ebayApiBasicSettingsHeader' ), $page = EBAPI_HANDLE);
		add_settings_field($id = 'ebayAppId', $title = 'eBay AppID', $callback = array( &$this, 'ebayAppIdInputSring'), $page = EBAPI_HANDLE, $section = 'ebayApiBasicSettings');
	}

	/**
	 * Header info for the Admin > Settings > ebayApi page
	 */
	public function ebayApiBasicSettingsHeader() { ?>
		<p>You must have an eBay AppID so that this plugin can access the eBay API. If you do not have one, you can get one from eBay:</p>
		<ul>
			<li>Go to the eBay / x.com developer center: <a href="https://www.x.com/developers/ebay">https://www.x.com/developers/ebay</a></li>
			<li>Sign up or Log in to your <strong>Developer</strong> account (on the right side of the page <strong>below</strong> the main login. Yet another counter-intuitive UI from eBay.</li>
			<li>On the "My Account" page, will be a place to generate application keys. Generate a set of Production Keys.</li>
			<li>Copy the AppID, and paste it below... and, Done!</li>
		</ul>
	<?php }

	/**
	 * Form Inputs for the Admin > Settings > eBayApi > Basic Settings section
	 */
	public function ebayAppIdInputSring() {
		$options = get_option(EBAPI_HANDLE . '_options');
		
		?>
		<input id="ebayAppId" name="<?php echo EBAPI_HANDLE ?>_options[appId]" size="40" type="text" value="<?php echo $options['appId'] ?>" />
		<?php
	}

	/**
	 * Input Validation for the Admin > Settings > eBayApi > Basic Settings > appid field
	 */
	public function ebayAppIdValidation($input) {
		$trimmedInput['appId'] = trim($input['appId']);
		if(!preg_match('/^[a-zA-Z0-9\._-]{8}-([a-fA-F0-9]{4}-){3}[a-fA-F0-9]{12}$/', $trimmedInput['appId'])) {
			$trimmedInput['appId'] = '';
			add_settings_error('The AppID you entered does not appear to be valid. It should contain no spaces and look something like this: MyAccoun-de40-4e7a-b2c2-ac6dedac95ad');
		}
		return $trimmedInput;
	}
	
	/*--------------------------------------------------*/
	/* Load Styles and Scripts
	/*--------------------------------------------------*/


	/**
	 * Registers and enqueues admin-specific styles.
	 */
	public function register_admin_styles() {
	
		wp_register_style( 'ebayGetSingleItem-admin-styles', plugins_url( 'ebayGetSingleItem/css/admin.css' ) );
		wp_enqueue_style( 'ebayGetSingleItem-admin-styles' );
//		wp_enqueue_style('wp-pointer');
	
	} // end register_admin_styles

	/**
	 * Registers and enqueues admin-specific JavaScript.
	 */
	public function register_admin_scripts() {
	
		wp_register_script( 'ebayGetSingleItem-admin-script', plugins_url( 'ebayGetSingleItem/js/admin.js' ) );
		wp_enqueue_script( 'ebayGetSingleItem-admin-script', false, array('jquery') );
//		wp_localize_script( 'ebayGetSingleItem-admin-script', 'strings', $this->localizeJsPointerStrings() );

//		wp_enqueue_script( 'wp-pointer', false, array('jquery') );
		
	} // end register_admin_scripts

	/**
	 * Registers and enqueues plugin-specific styles.
	 */
	
	public function register_plugin_styles() {
	
		wp_register_style( 'ebayGetSingleItem-plugin-styles', plugins_url( 'ebayGetSingleItem/css/plugin.css' ) );
		wp_enqueue_style( 'ebayGetSingleItem-plugin-styles' );
		
	} // end register_widget_styles

	/**
	 * Registers and enqueues plugin-specific scripts.
	 */
	public function register_plugin_scripts() {
	
		wp_register_script( 'ebayGetSingleItem-plugin-script', plugins_url( 'ebayGetSingleItem/js/plugin.js' ) );
		wp_enqueue_script( 'ebayGetSingleItem-plugin-script' );
		
	} // end register_widget_scripts

	/**
	 * Define and localize js text before including the js. 
	 * @return localized js strings
	 */
/*
	public function localizeJsPointerStrings() {
	 
		$pointer_text = '<h3>' . esc_js( __( 'Configure the eBay API') ) . '</h3>';
		$pointer_text .= '<p>' . esc_js( __( 'Thanks for using the eBay getSingleItem plugin. You must configure the plugin before it will work by entering your eBay AppID, see Settings > eBay API for more details!' ) ). '</p>';
	 
		// Get the list of dismissed pointers for the user
		$dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
	 
		// Check whether our pointer has been dismissed
		if ( in_array( 'ebapi', $dismissed ) ) {
			$pointer_text = '';
		}
	 
		return array(
			'pointerText' => $pointer_text
		);
	}
*/

	/**
	 * Does all the fancy auto-update stuff using github instead of wp svn
	 * @return null
	 */
	protected function checkGithubUpdates() {
		$githubHandle = 'moens/eBay-GetSingleItem-WordPress-Plugin';
		$config = array(
			'slug' => plugin_basename(__FILE__), // this is the slug of your plugin
			'proper_folder_name' => EBAPI_PLUGIN_DIR, // this is the name of the folder your plugin lives in
			'api_url' => 'https://api.github.com/repos/' . $githubHandle, // the github API url of your github repo
			'raw_url' => 'https://raw.github.com/' . $githubHandle, // the github raw url of your github repo
			'github_url' => 'https://github.com/' . $githubHandle, // the github url of your github repo
			'zip_url' => 'https://github.com/' .  $githubHandle . '/zipball/master', // the zip url of the github repo
			'sslverify' => true, // wether WP should check the validity of the SSL cert when getting an update, see https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/2 and https://github.com/jkudish/WordPress-GitHub-Plugin-Updater/issues/4 for details
			'requires' => '3.3', // which version of WordPress does your plugin require?
			'tested' => '3.4', // which version of WordPress is your plugin tested up to?
		);
		new wp_github_updater($config);
	}
	
	
	/*--------------------------------------------------*/
	/* Variable Handlers - getters / setters / etc
	/*--------------------------------------------------*/

	public function setAppId() {
		$options = get_option(EBAPI_HANDLE . '_options');
		if(isset($options['appId']) && $options['appId'] != '') {
			$this->appId = $options['appId'];
			return TRUE;
		}
		return FALSE;
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
		echo '<div class="error"><dl><dt>' . __('The eBay getSingleItem plugin is reporting the following error(s):') . '</dt>' . PHP_EOL;
		foreach($this->configIssues as $issue) {
			echo '<dd>' . $issue . '</dd>' . PHP_EOL;
		}
		echo '</dl></div>' . PHP_EOL;
	}
	
} // end class

$ebayGetSingleItem = new ebayGetSingleItem;

register_uninstall_hook( __FILE__, 'ebayGetSingleItemUninstall' );

/**
 * Fired when the plugin is uninstalled
 *
 * @params	$network_wide	True if WPMU superadmin uses "Network Activate" action, false if WPMU is disabled or plugin is activated on an individual blog 
 */
function ebayGetSingleItemUninstall( $network_wide ) {
	if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) exit ();
	delete_option(EBAPI_HANDLE . '_option');
} // end uninstall
	
?>
