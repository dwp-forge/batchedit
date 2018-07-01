var batchedit = (function () {
    function getLang(id) {
        if (id in batcheditLang) {
            return batcheditLang[id];
        }

        return 'undefined';
    }

    function initializeTooltip() {
        jQuery('#batchedit').tooltip({
            tooltipClass: 'be-tooltip',
            show: {delay: 1000},
            track: true
        });
    }

    function initializeApplyCheckboxes() {
        jQuery('#be-applyall').click(function() {
            jQuery('.be-file input').prop('checked', this.checked);
        });

        jQuery('.be-file .be-stats input').click(function() {
            jQuery('.be-match input[id^=' + this.id.replace(/:/g, '\\:') + ']').prop('checked', this.checked);
        });

        // When all single matches of a file have been checked, mark the appropriate file box as checked, too.
        jQuery('.be-match input').click(function() {
            var pageIdEscaped = this.id.substr(0, this.id.indexOf('#')).replace(/:/g, '\\:');
            var pageMatches = jQuery('.be-match input[id^=' + pageIdEscaped + ']').get();

            jQuery('#' + pageIdEscaped).prop('checked', pageMatches.reduce(function(checked, input) {
                return checked && input.checked;
            }, true));
        });

        // Consolidate the list of all checked match ids into single hidden input field as
        // a JSON-encoded array string. This avoids problems for huge replacement sets
        // exceeding `max_input_vars` in `php.ini`.
        jQuery('#batchedit form').submit(function() {
            // Consolidate checked matches into a single string variable to be posted to the backend.
            jQuery('input[name=apply]').val(JSON.stringify(jQuery('.be-match input:checked').map(function() {
                return this.id;
            }).get()));
        });
    }

    function initializeTotalStatsFloater() {
        var $anchor = jQuery('#be-totalstats');
        var $floater = $anchor.children('div');

        var updateFloater = function() {
            if (window.pageYOffset > $anchor.offset().top) {
                $floater.addClass('be-floater').css('width', $anchor.css('width'));
            }
            else {
                $floater.removeClass('be-floater');
            }
        };

        jQuery(window).scroll(updateFloater);

        updateFloater();
    }

    function initializeSearchMode() {
        if (jQuery('input[name=searchmode]:checked').length == 0) {
            jQuery('input[name=searchmode][value=text]').prop('checked', true);
        }

        var $searchInputs = jQuery('#be-searchedit, #be-searcharea');
        var $replaceInputs = jQuery('#be-replaceedit, #be-replacearea');

        var onSearchModeUpdate = function() {
            var searchMode = jQuery('input[name=searchmode]:checked').val();

            $searchInputs.prop('placeholder', getLang('hnt_' + searchMode + 'search'));
            $replaceInputs.prop('placeholder', getLang('hnt_' + searchMode + 'replace'));
        };

        jQuery('input[name=searchmode]').click(onSearchModeUpdate);

        onSearchModeUpdate();
    }

    function initializeMultiline() {
        var $multiline = jQuery('input[name=multiline]');
        var $searchEdit = jQuery('#be-searchedit');
        var $searchArea = jQuery('#be-searcharea');
        var $replaceEdit = jQuery('#be-replaceedit');
        var $replaceArea = jQuery('#be-replacearea');

        $multiline.click(function() {
            if (this.checked) {
                $searchEdit.hide();
                $replaceEdit.hide();
                $searchArea.val($searchEdit.val()).show();
                $replaceArea.val($replaceEdit.val()).show();
            }
            else {
                $searchArea.hide();
                $replaceArea.hide();
                $searchEdit.val($searchArea.val().replace(/\n/g, '\\n')).show();
                $replaceEdit.val($replaceArea.val().replace(/\n/g, '\\n')).show();
            }
        });

        jQuery('#batchedit form').submit(function() {
            if (!$multiline.prop('checked')) {
                $searchArea.val($searchEdit.val());
                $replaceArea.val($replaceEdit.val());
            }
        });
    }

    function initialize() {
        initializeTooltip();
        initializeApplyCheckboxes();
        initializeTotalStatsFloater();
        initializeSearchMode();
        initializeMultiline();
    }

    return {
        initialize : initialize
    }
})();

jQuery(function () {
    batchedit.initialize();
});
