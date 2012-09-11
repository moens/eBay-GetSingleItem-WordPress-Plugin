<?php

class getSingleItemView {

	/* Construct not needed I think, simple template + data cleaner */

	function renderTemplate ($ebayItemData = array()) {

/**
 * First we clean and format pertinent data
 */

		// linkURL (string/url)
		$linkURL = $ebayItemData['Item']['ViewItemURLForNaturalSearch'];

		// title (string/text)
		$title = $ebayItemData['Item']['Title'];

		// description (string/html)
		$description = stripslashes(html_entity_decode($ebayItemData['Item']['Description']));
		$description = str_replace( '<br />', '', $description );
		preg_match_all('/desc.*?dl.*?dd>(.*?)<\/dd/s', $description, $matchData);
		if($matchData[1][0]) $description = $matchData[1][0];

		// isEnded (bool)
		$isEnded = ($ebayItamData['Item']['ListingStatus'] == 'Completed')?TRUE:FALSE;
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
		$bidPrice = (isset($ebayItemData['Item']['ConvertedCurrentPrice']['Value']))?$ebayItemData['Item']['ConvertedCurrentPrice']['Value']:FALSE;

		// isBuyItNow
		$isBuyItNow = (isset($ebayItemData['Item']['BuyItNowAvailable']))?$ebayItemData['Item']['BuyItNowAvailable']:FALSE;

		// buyItNowPrice
		$buyItNowPrice = (isset($ebayItemData['Item']['ConvertedBuyItNowPrice']['Value']))?$ebayItemData['Item']['ConvertedBuyItNowPrice']['Value']:FALSE;

		// listingType
		$listingType = $ebayItemData['Item']['ListingType'];

		// isSold
		$isSold = ( !$isEnded ) ? FALSE : ( ( isset ( $ebayItemData['Item']['BidCount'] ) && ( $ebayItemData['Item']['BidCount'] > 0 ) ) ? TRUE : FALSE );

/**
 * This is the actual layout section which is sent directly to the output stream
 */

?>
		<div class="auctionTemplate">
		<h2><a href="<?php echo $linkURL ?>"><?php echo $title ?></a></h2>
		<div class="price">
		<?php _e('Current bid price:') ?> <?php echo '<span>$' . $bidPrice . '.00</span>' ?>
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
		<div class="description"><?php echo $description ?></div>
		</div> <!-- // end class="auctionTemplate" -->
<?php
	}

	/**
	* Parse an ISO 8601 duration string
	* @param string $str
	* @return array
	**/

	protected function parseDuration($str)
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
?>
