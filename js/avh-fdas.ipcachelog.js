/* global adminCommentsL10n, thousandsSeparator, list_args, QTags, ajaxurl, wpAjax */
var theList;
var theExtraList;

function avhfdasIpCacheLog($) {
  var setIpCacheLogList;
  setIpCacheLogList = function avhfasLogList() {
    var refillTheExtraList;
    var totalInput;
    var perPageInput;
    var pageInput;
    var lastConfidentTime = 0;
    var dimAfter;
    var delBefore;
    var updateTotalCount;
    var delAfter;
    totalInput = $('input[name="_total"]', '#ipcachelist-form');
    perPageInput = $('input[name="_per_page"]', '#ipcachelist-form');
    pageInput = $('input[name="_page"]', '#ipcachelist-form');

    function getCount(el) {
      var n = parseInt(el.html().replace(/[^0-9]+/g, ''), 10);
      if (isNaN(n)) {
        return 0;
      }
      return n;
    }

    function updateCount(el, number) {
      var n1 = '';
      var n = number;
      if (isNaN(n)) {
        return;
      }
      n = n < 1 ? '0' : n.toString();
      if (n.length > 3) {
        while (n.length > 3) {
          /* global thousandsSeparator */
          n1 = thousandsSeparator + n.substr(n.length - 3) + n1;
          n = n.substr(0, n.length - 3);
        }
        n = n + n1;
      }
      el.html(n);
    }

    dimAfter = function avhfdasDimAfter(r, settings) {
      var c = $('#' + settings.element);
      if (c.is('.spammed')) {
        c.find('div.ip_status').html('1');
        c.find('td.spam').html('Spam');
      } else {
        c.find('div.ip_status').html('0');
        c.find('td.spam').html('Ham');
      }
      $('span.ham-count').each(function avhfdasUpdateHamCount() {
        var a = $(this);
        var n;
        var dif;
        dif = $('#' + settings.element).is('.' + settings.dimClass) ? -1 : 1;
        n = getCount(a) + dif;
        updateCount(a, n);
      });
      $('span.spam-count').each(function avhfdasUpdateSpamCount() {
        var a = $(this);
        var n;
        var diff;
        diff = $('#' + settings.element).is('.' + settings.dimClass) ? 1 : -1;
        n = getCount(a) + diff;
        updateCount(a, n);
      });
    };
    // Send current total, page, per_page and url
    delBefore = function avhfdasDelBefore(oldSettings, list) {
      var settings = oldSettings;
      settings.data._total = totalInput.val() || 0;
      settings.data._per_page = perPageInput.val() || 0;
      settings.data._page = pageInput.val() || 0;
      settings.data._url = document.location.href;
      settings.data.ip_status = $('input[name=ip_status]', '#ipcachelist-form').val();
      return settings;
    };
    // Updates the current total (as displayed visibly)
    updateTotalCount = function avhfdasUpdateTotalCount(total, time, setConfidentTime) {
      if (time < lastConfidentTime) {
        return;
      }
      if (setConfidentTime) {
        lastConfidentTime = time;
      }
      totalInput.val(total.toString());
      $('span.total-type-count').each(function avhfdasTypeCount() {
        updateCount($(this), total);
      });
    };
    refillTheExtraList = function avhfdasRefillTheExtraList(ev) {
      /* var args = $.query.get(), total_pages = listTable.get_total_pages(), per_page =
       $('input[name=_per_page]', '#comments-form').val(), r;
       */
      var args;
      var totalPages;
      var perPage;
      args = $.query.get();
      totalPages = $('.total-pages')
        .text();
      perPage = $('input[name=_per_page]', '#ipcachelist-form').val();
      if (!args.paged) {
        args.paged = 1;
      }
      if (args.paged > totalPages) {
        return;
      }
      if (ev) {
        theExtraList.empty();
        /* see AVH_FDAS_IPCacheList::prepare_items() @ avh-fdas.ipcachelist.php */
        args.number = Math.min(8, perPage);
      } else {
        args.number = 1;
        /* fetch only the next item on the extra list */
        args.offset = Math.min(8, perPage) - 1;
      }
      args.no_placeholder = true;
      args.paged++;
      // $.query.get() needs some correction to be sent into an ajax request
      if (args.comment_type === true) {
        args.comment_type = '';
      }
      args = $.extend(args, {
        action: 'fetch-list',
        list_args: list_args,
        _ajax_fetch_list_nonce: $('#_ajax_fetch_list_nonce').val()
      });
      $.ajax({
        url: ajaxurl,
        global: false,
        dataType: 'json',
        data: args,
        success: function avhfdasSuccess(response) {
          theExtraList.get(0).wpList.add(response.rows);
        }
      });
    };

    // In admin-ajax.php, we send back the Unix time stamp instead of 1 on success
    delAfter = function avhfdasDelAfter(r, settings) {
      var total;
      var deltaSpam;
      var deltaHam;
      var unspam = $(settings.target).parent().is('span.set_ham');
      var unham = $(settings.target).parent().is('span.set_spam');
      var blacklist = $(settings.target).parent().is('span.set_blacklist');
      var del = $(settings.target).parent().is('span.set_delete');
      var typeHamSpam;
      var a;
      var n;
      var totalItemsI18n;

      if (unspam) {
        deltaSpam = -1;
        deltaHam = 1;
      }
      if (unham) {
        deltaHam = -1;
        deltaSpam = 1;
      }
      if (blacklist || del) {
        typeHamSpam = $('div.ip_hamspam', $('#inline-' + settings.data.id)).text();
        deltaHam = (typeHamSpam === 'ham') ? -1 : 0;
        deltaSpam = (typeHamSpam === 'spam') ? -1 : 0;
      }
      $('span.spam-count').each(function avhfdasSpamCount() {
        a = $(this);
        n = getCount(a) + deltaSpam;
        updateCount(a, n);
      });
      $('span.ham-count').each(function avhfdasHamCount() {
        a = $(this);
        n = getCount(a) + deltaHam;
        updateCount(a, n);
      });

      total = totalInput.val() ? parseInt(totalInput.val(), 10) : 0;
      total = total + deltaHam + deltaSpam;
      if (total < 0) {
        total = 0;
      }
      if ((typeof r === 'object') &&
        lastConfidentTime < settings.parsed.responses[0].supplemental.time) {
        totalItemsI18n = settings.parsed.responses[0].supplemental.total_items_i18n || '';
        if (totalItemsI18n) {
          $('.displaying-num').text(totalItemsI18n);
          $('.total-pages').text(settings.parsed.responses[0].supplemental.total_pages_i18n);
          $('.tablenav-pages')
            .find('.next-page, .last-page')
            .toggleClass('disabled', settings.parsed.responses[0].supplemental.total_pages ===
              $('.current-page').val());
        }
        updateTotalCount(total, settings.parsed.responses[0].supplemental.time, true);
      } else {
        updateTotalCount(total, r, false);
      }
      if (!theExtraList || theExtraList.size() === 0 || theExtraList.children().size() === 0) {
        return;
      }
      theList.get(0).wpList.add(theExtraList.children(':eq(0)').remove().clone());
      refillTheExtraList();
    };

    theExtraList =
      $('#the-extra-ipcache-list').wpList({ alt: '', delColor: 'none', addColor: 'none' });
    theList =
      $('#the-ipcache-list').wpList({
        alt: '',
        delBefore: delBefore,
        dimAfter: dimAfter,
        delAfter: delAfter,
        addColor: 'none'
      });
    // $(listTable).bind('changePage', refillTheExtraList);
  };
  $(document).ready(function avhfdasSetup() {
    setIpCacheLogList();
    $(document)
      .delegate('span.avhfdas_is_delete a.avhfdas_is_delete', 'click', function returnFalse() {
        return false;
      });
  });
}
avhfdasIpCacheLog(jQuery);
