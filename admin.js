jQuery(function () {
    jQuery('#batchedit').tooltip({
        tooltipClass: 'batchedit-tooltip',
        show: {delay: 1000},
        track: true
    });

    jQuery('#applyall').click(function(){
        jQuery('.file input').prop('checked', this.checked);
    });

    jQuery('.page .stats input').click(function(){
        jQuery('.match input[id^=' + this.id.replace(/:/g, '\\:') + ']').prop('checked', this.checked);
    });

    // When all single matches of a file have been checked, mark the appropriate "check all" box as checked, too.
    jQuery('.check-single-match').click(function(){
        var pageIdEscaped = this.id.substr(0, this.id.indexOf('#')).replace(/:/g, '\\:');
        var checkedAll = true;
        jQuery('.match input[id^=' + pageIdEscaped + ']').each(function(i, el) {
            checkedAll = checkedAll && jQuery(el).prop('checked');
            return checkedAll;
        });
        if (checkedAll) {
            jQuery('#'+pageIdEscaped).prop('checked', true);
        }
    });

    // Consolidate the list of all checked match ids into single hidden input field as
    // a comma-separated string. This avoids problems for huge replacement sets
    // exceeding `max_input_vars` in `php.ini`.
    jQuery('#replace-form').submit(function() {
      // Consolidate checked matches into a single string variable to be posted to the backend.
      var checkedMatchesList='';
      jQuery('.check-single-match').each(function(i, el) {
        if (jQuery(el).prop('checked')) {
          checkedMatchesList+=(el.id+',');
        }
      });
      // cut off the last comma:
      checkedMatchesList = checkedMatchesList.slice(0, -1);
      jQuery("input[name=\'checkedMatches\']").val(checkedMatchesList);
    });

    var totalStatsFloater = function() {
        var $anchor = jQuery('#totalstats');
        var $floater = $anchor.children('div');

        if (window.pageYOffset > $anchor.offset().top) {
            $floater.addClass('floater').css({width: $anchor.css('width')});
        }
        else {
            $floater.removeClass('floater');
        }
    };

    jQuery(window).scroll(totalStatsFloater);
});
