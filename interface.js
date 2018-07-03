var batchedit = (function () {
    function getLang(id) {
        if (id in batcheditLang) {
            return batcheditLang[id];
        }

        return 'undefined';
    }

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

    function initializeSearchMode() {
        if (jQuery('input[name=searchmode]:checked').length == 0) {
            jQuery('input[name=searchmode][value=text]').prop('checked', true);
        }

        var searchModeUpdate = function() {
            var searchMode = jQuery('input[name=searchmode]:checked').val();

            jQuery('input[name=search]').prop('placeholder', getLang('hnt_' + searchMode + 'search'));
            jQuery('input[name=replace]').prop('placeholder', getLang('hnt_' + searchMode + 'replace'));
        };

        jQuery('input[name=searchmode]').click(searchModeUpdate);

        searchModeUpdate();
    }

    function initialize() {
        initializeTooltip();
        initializeApplyCheckboxes();
        initializeTotalStatsFloater();
        initializeSearchMode();
    }

    return {
        initialize : initialize
    }
})();

jQuery(function () {
    batchedit.initialize();
});
