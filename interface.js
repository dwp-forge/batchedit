var batchedit = (function () {
    function initializeTooltip() {
        jQuery('#batchedit').tooltip({
            tooltipClass: 'batchedit-tooltip',
            show: {delay: 1000},
            track: true
        });
    }

    function initializeApplyCheckboxes() {
        jQuery('#applyall').click(function() {
            jQuery('.file input').prop('checked', this.checked);
        });

        jQuery('.page .stats input').click(function() {
            jQuery('.match input[id^=' + this.id.replace(/:/g, '\\:') + ']').prop('checked', this.checked);
        });
    }

    function initializeTotalStatsFloater() {
        jQuery(window).scroll(function() {
            var $anchor = jQuery('#totalstats');
            var $floater = $anchor.children('div');

            if (window.pageYOffset > $anchor.offset().top) {
                $floater.addClass('floater').css({width: $anchor.css('width')});
            }
            else {
                $floater.removeClass('floater');
            }
        });
    }

    function initialize() {
        initializeTooltip();
        initializeApplyCheckboxes();
        initializeTotalStatsFloater();
    }

    return {
        initialize : initialize
    }
})();

jQuery(function () {
    batchedit.initialize();
});
