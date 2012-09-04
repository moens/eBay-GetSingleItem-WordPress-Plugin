(function ($) {
	"use strict";
	$(function () {
		// from http://www.generalthreat.com/2012/01/highlighting-features-with-wordpress-admin-pointers/
		/** Check that pointer support exists AND that text is not empty */
		if(typeof(jQuery().pointer) != 'undefined' && strings.pointerText != '') {
			jQuery('#menu-settings').pointer({
				content	: strings.pointerText,
				close	: function() {
					jQuery.post( ajaxurl, {
						pointer: 'ebapi',
						action: 'dismiss-wp-pointer'
					});
				}
			}).pointer('open');
		}
	});
}(jQuery));
