<?php

class ebapiShortCode {

	public function __construct() {

		// Setup ShortCodes
		add_shortcode('eBayItem', array( &$this, 'ebayGetSingleItemShortcode' ) );
	}

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
}

$ebapiShortCode = new ebapiShortCode;
