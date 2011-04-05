/**
 * 
 */
var theList, theExtraList, toggleWithKeyboard = false;
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
			n = a.html().replace(/[^0-9]+/g, '');
			n = parseInt(n,10);
			if ( isNaN(n) ) return;
			dif = $('#' + settings.element).is('.' + settings.dimClass) ? -1 : 1;
			n = n + dif;
			if ( n < 0 ) { n = 0; }
			updateCount(a, n);
		});
		$('span.spam-count').each( function() {
			var a = $(this), n, dif;
			n = a.html().replace(/[^0-9]+/g, '');
			n = parseInt(n,10);
			if ( isNaN(n) ) return;
			dif = $('#' + settings.element).is('.' + settings.dimClass) ? 1 : -1;
			n = n + dif;
			if ( n < 0 ) { n = 0; }
			updateCount(a, n);
		});
	};

	// Send current total, page, per_page and url
	delBefore = function( settings, list ) {
		var cl = $(settings.target).attr('className'), id, el, n, h, a, author, action = false;

		settings.data._total = totalInput.val() || 0;
		settings.data._per_page = perPageInput.val() || 0;
		settings.data._page = pageInput.val() || 0;
		settings.data._url = document.location.href;
		settings.data.comment_status = $('input[name=comment_status]', '#comments-form').val();

		if ( cl.indexOf(':trash=1') != -1 )
			action = 'trash';
		else if ( cl.indexOf(':spam=1') != -1 )
			action = 'spam';

		if ( action ) {
			id = cl.replace(/.*?comment-([0-9]+).*/, '$1');
			el = $('#comment-' + id);
			note = $('#' + action + '-undo-holder').html();

			el.find('.check-column :checkbox').attr('checked', ''); // Uncheck the row so as not to be affected by Bulk Edits.

			if ( el.siblings('#replyrow').length && commentReply.cid == id )
				commentReply.close();

			if ( el.is('tr') ) {
				n = el.children(':visible').length;
				author = $('.author strong', el).text();
				h = $('<tr id="undo-' + id + '" class="undo un' + action + '" style="display:none;"><td colspan="' + n + '">' + note + '</td></tr>');
			} else {
				author = $('.comment-author', el).text();
				h = $('<div id="undo-' + id + '" style="display:none;" class="undo un' + action + '">' + note + '</div>');
			}

			el.before(h);

			$('strong', '#undo-' + id).text(author + ' ');
			a = $('.undo a', '#undo-' + id);
			a.attr('href', 'comment.php?action=un' + action + 'comment&c=' + id + '&_wpnonce=' + settings.data._ajax_nonce);
			a.attr('className', 'delete:the-comment-list:comment-' + id + '::un' + action + '=1 vim-z vim-destructive');
			$('.avatar', el).clone().prependTo('#undo-' + id + ' .' + action + '-undo-inside');

			a.click(function(){
				list.wpList.del(this);
				$('#undo-' + id).css( {backgroundColor:'#ceb'} ).fadeOut(350, function(){
					$(this).remove();
					$('#comment-' + id).css('backgroundColor', '').fadeIn(300, function(){ $(this).show() });
				});
				return false;
			});
		}

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

	function dashboardTotals(n) {
		var dash = $('#dashboard_right_now'), total, appr, totalN, apprN;

		n = n || 0;
		if ( isNaN(n) || !dash.length )
			return;

		total = $('span.total-count', dash);
		appr = $('span.approved-count', dash);
		totalN = getCount(total);

		totalN = totalN + n;
		apprN = totalN - getCount( $('span.pending-count', dash) ) - getCount( $('span.spam-count', dash) );
		updateCount(total, totalN);
		updateCount(appr, apprN);

	}

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
		var total, pageLinks, N, untrash = $(settings.target).parent().is('span.untrash'), unspam = $(settings.target).parent().is('span.unspam'), spam, trash;

		function getUpdate(s) {
			if ( $(settings.target).parent().is('span.' + s) )
				return 1;
			else if ( $('#' + settings.element).is('.' + s) )
				return -1;

			return 0;
		}
		spam = getUpdate('spam');
		trash = getUpdate('trash');

		if ( untrash )
			trash = -1;
		if ( unspam )
			spam = -1;

		$('span.pending-count').each( function() {
			var a = $(this), n = getCount(a), unapproved = $('#' + settings.element).is('.unapproved');

			if ( $(settings.target).parent().is('span.unapprove') || ( ( untrash || unspam ) && unapproved ) ) { // we "deleted" an approved comment from the approved list by clicking "Unapprove"
				n = n + 1;
			} else if ( unapproved ) { // we deleted a formerly unapproved comment
				n = n - 1;
			}
			if ( n < 0 ) { n = 0; }
			a.closest('#awaiting-mod')[ 0 == n ? 'addClass' : 'removeClass' ]('count-0');
			updateCount(a, n);
			dashboardTotals();
		});

		$('span.spam-count').each( function() {
			var a = $(this), n = getCount(a) + spam;
			updateCount(a, n);
		});

		$('span.trash-count').each( function() {
			var a = $(this), n = getCount(a) + trash;
			updateCount(a, n);
		});

		if ( $('#dashboard_right_now').length ) {
			N = trash ? -1 * trash : 0;
			dashboardTotals(N);
		} else {
			total = totalInput.val() ? parseInt( totalInput.val(), 10 ) : 0;
			total = total - spam - trash;
			if ( total < 0 )
				total = 0;

			if ( ( 'object' == typeof r ) && lastConfidentTime < settings.parsed.responses[0].supplemental.time ) {
				total_items_i18n = settings.parsed.responses[0].supplemental.total_items_i18n || '';
				if ( total_items_i18n ) {
					$('.displaying-num').text( total_items_i18n );
					$('.total-pages').text( settings.parsed.responses[0].supplemental.total_pages_i18n );
					$('.tablenav-pages').find('.next-page, .last-page').toggleClass('disabled', settings.parsed.responses[0].supplemental.total_pages == $('.current-page').val());
				}
				updateTotalCount( total, settings.parsed.responses[0].supplemental.time, true );
			} else {
				updateTotalCount( total, r, false );
			}
		}


		if ( ! theExtraList || theExtraList.size() == 0 || theExtraList.children().size() == 0 || untrash || unspam ) {
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
	theList = $('#the-ipcache-list').wpList( { alt: '', delBefore: delBefore, dimAfter: dimAfter, delAfter: delAfter, addColor: 'none' } )
		.bind('wpListDelEnd', function(e, s){
			var id = s.element.replace(/[^0-9]+/g, '');

			if ( s.target.className.indexOf(':spam=1') != -1 )
				$('#undo-' + id).fadeIn(300, function(){ $(this).show() });
		});
	// $(listTable).bind('changePage', refillTheExtraList);
};


$(document).ready(function(){
	var make_hotkeys_redirect, edit_comment, toggle_all, make_bulk;

	setIpCacheLogList();
	$(document).delegate('span.avhfdas_is_delete a.avhfdas_is_delete', 'click', function(){return false;});

});

})(jQuery);
