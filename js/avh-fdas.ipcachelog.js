var theList, theExtraList;
(function($) {

setIpCacheLogList = function() {
	var totalInput, perPageInput, pageInput, lastConfidentTime = 0, dimAfter, delBefore, updateTotalCount, delAfter;

	totalInput = $('input[name="_total"]', '#ipcachelist-form');
	perPageInput = $('input[name="_per_page"]', '#ipcachelist-form');
	pageInput = $('input[name="_page"]', '#ipcachelist-form');

	dimAfter = function( r, settings ) {
		var c = $('#' + settings.element);

		if ( c.is('.spammed') ) {
			c.find('div.ip_status').html('1');
			c.find('td.spam').html('Spam');
		} else{
			c.find('div.ip_status').html('0');
			c.find('td.spam').html('Ham');
		}

		$('span.ham-count').each( function() {
			var a = $(this), n, dif;
			n = getCount(a);
			if ( isNaN(n) ) return;
			dif = $('#' + settings.element).is('.' + settings.dimClass) ? -1 : 1;
			n = n + dif;
			if ( n < 0 ) { n = 0; }
			updateCount(a, n);
		});
		$('span.spam-count').each( function() {
			var a = $(this), n, dif;
			n = getCount(a);
			if ( isNaN(n) ) return;
			dif = $('#' + settings.element).is('.' + settings.dimClass) ? 1 : -1;
			n = n + dif;
			if ( n < 0 ) { n = 0; }
			updateCount(a, n);
		});
	};

	// Send current total, page, per_page and url
	delBefore = function( settings, list ) {

		settings.data._total = totalInput.val() || 0;
		settings.data._per_page = perPageInput.val() || 0;
		settings.data._page = pageInput.val() || 0;
		settings.data._url = document.location.href;
		settings.data.ip_status = $('input[name=ip_status]', '#ipcachelist-form').val();

		return settings;
	};

	// Updates the current total (as displayed visibly)
	updateTotalCount = function( total, time, setConfidentTime ) {
		if ( time < lastConfidentTime )
			return;

		if ( setConfidentTime )
			lastConfidentTime = time;

		totalInput.val( total.toString() );
		$('span.total-type-count').each( function() {
			updateCount( $(this), total );
		});
	};

	function getCount(el) {
		var n = parseInt( el.html().replace(/[^0-9]+/g, ''), 10 );
		if ( isNaN(n) )
			return 0;
		return n;
	}

	function updateCount(el, n) {
		var n1 = '';
		if ( isNaN(n) )
			return;
		n = n < 1 ? '0' : n.toString();
		if ( n.length > 3 ) {
			while ( n.length > 3 ) {
				n1 = thousandsSeparator + n.substr(n.length - 3) + n1;
				n = n.substr(0, n.length - 3);
			}
			n = n + n1;
		}
		el.html(n);
	}

	// In admin-ajax.php, we send back the unix time stamp instead of 1 on success
	delAfter = function( r, settings ) {
		var total, pageLinks, N, spam, ham, blacklist, del, removed =  false;
		var unspam = $(settings.target).parent().is('span.set_ham');
		var unham = $(settings.target).parent().is('span.set_spam');
		var blacklist = $(settings.target).parent().is('span.set_blacklist');
		var del = $(settings.target).parent().is('span.set_delete');
		
		function getUpdate(s) {
			if ( $(settings.target).parent().is('span.' + s) )
				return 1;
			else if ( $('#' + settings.element).is('.' + s) )
				return -1;

			return 0;
		}
		
		if ( unspam ) {
			spam = -1;
			ham = 1;
		}
		if ( unham ) {
			ham = -1;
			spam = 1;
		}

		if (blacklist || del ) {
			ham = -1;
			spam = -1;
		}

		$('span.spam-count').each( function() {
			var a = $(this), n = getCount(a) + spam;
			updateCount(a, n);
		});

		$('span.ham-count').each( function() {
			var a = $(this), n = getCount(a) + ham;
			updateCount(a, n);
		});


		total = totalInput.val() ? parseInt( totalInput.val(), 10 ) : 0;
		total = total - spam - ham;
		if ( total < 0 )
			total = 0;

		if ( ( 'object' == typeof r ) && lastConfidentTime < settings.parsed.responses[0].supplemental.time ) {
			total_items_i18n = settings.parsed.responses[0].supplemental.total_items_i18n || '';
			if ( total_items_i18n ) {
				$('.displaying-num').text( total_items_i18n );
				$('.total-pages').text( settings.parsed.responses[0].supplemental.total_pages_i18n );
				$('.tablenav-pages').find('.next-page, .last-page').toggleClass('disabled', settings.parsed.responses[0].supplemental.total_pages == $('.current-page').val());
			}
			//updateTotalCount( total, settings.parsed.responses[0].supplemental.time, true );
		} else {
			//updateTotalCount( total, r, false );
		}


		if ( ! theExtraList || theExtraList.size() == 0 || theExtraList.children().size() == 0 || !removed ) {
			return;
		}

		theList.get(0).wpList.add( theExtraList.children(':eq(0)').remove().clone() );

		refillTheExtraList();
	};

	var refillTheExtraList = function(ev) {
		// var args = $.query.get(), total_pages = listTable.get_total_pages(), per_page = $('input[name=_per_page]', '#comments-form').val(), r;
		var args = $.query.get(), total_pages = $('.total-pages').text(), per_page = $('input[name=_per_page]', '#comments-form').val(), r;

		if (! args.paged)
			args.paged = 1;

		if (args.paged > total_pages) {
			return;
		}

		if (ev) {
			theExtraList.empty();
			args.number = Math.min(8, per_page); // see WP_Comments_List_Table::prepare_items() @ class-wp-comments-list-table.php
		} else {
			args.number = 1;
			args.offset = Math.min(8, per_page) - 1; // fetch only the next item on the extra list
		}

		args.no_placeholder = true;

		args.paged ++;

		// $.query.get() needs some correction to be sent into an ajax request
		if ( true === args.comment_type )
			args.comment_type = '';

		args = $.extend(args, {
			'action': 'fetch-list',
			'list_args': list_args,
			'_ajax_fetch_list_nonce': $('#_ajax_fetch_list_nonce').val()
		});

		$.ajax({
			url: ajaxurl,
			global: false,
			dataType: 'json',
			data: args,
			success: function(response) {
				theExtraList.get(0).wpList.add( response.rows );
			}
		});
	};

	theExtraList = $('#the-extra-ipcache-list').wpList( { alt: '', delColor: 'none', addColor: 'none' } );
	theList = $('#the-ipcache-list').wpList( { alt: '', delBefore: delBefore, dimAfter: dimAfter, delAfter: delAfter, addColor: 'none' } );
	// $(listTable).bind('changePage', refillTheExtraList);
};


$(document).ready(function(){
	setIpCacheLogList();
	$(document).delegate('span.avhfdas_is_delete a.avhfdas_is_delete', 'click', function(){return false;});

});

})(jQuery);
