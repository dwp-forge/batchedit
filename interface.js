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
