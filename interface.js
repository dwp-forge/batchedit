var batcheditInterface = (function () {
    function debounce(callback, timeout) {
        var timeoutId = null;

        var wrapper = function() {
            var self = this;
            var args = arguments;

            if (timeoutId) {
                clearTimeout(timeoutId);
            }

            timeoutId = setTimeout(function () {
                callback.apply(self, args);

                timeoutId = null;
            }, timeout);
        };

        return wrapper;
    }

    function escapeId(id) {
        return id.replace(/([:.])/g, '\\$1');
    }

    function observeStyleMutations($element, callback) {
        return new MutationObserver(debounce(callback, 500)).observe($element.get(0), {
            attributes: true, attributeFilter: ['style']
        });
    }

    function getLang(id) {
        if (id in batcheditLang) {
            return batcheditLang[id];
        }

        return 'undefined';
    }

    function updateConfig(id, value) {
        var config = Cookies.getJSON('BatchEditConfig');

        if (typeof config != 'undefined') {
            config[id] = value;

            Cookies.set('BatchEditConfig', config, { path: '' });
        }
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
            jQuery('.be-match input[id^=' + escapeId(this.id) + ']').prop('checked', this.checked);
        });

        // When all single matches of a file have been checked, mark the appropriate file box as checked, too.
        jQuery('.be-match input').click(function() {
            var pageId = escapeId(this.id.substr(0, this.id.indexOf('#')));
            var pageMatches = jQuery('.be-match input[id^=' + pageId + ']').get();

            jQuery('#' + pageId).prop('checked', pageMatches.reduce(function(checked, input) {
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
                $floater.addClass('be-floater').width($anchor.css('width'));
            }
            else {
                $floater.removeClass('be-floater');
            }
        };

        jQuery(window).scroll(updateFloater);

        updateFloater();
    }

    function initializeSearchMode() {
        var $searchMode = jQuery('input[name=searchmode]');
        var $advancedRegexp = jQuery('input[name=advregexp]');
        var $searchInputs = jQuery('#be-searchedit, #be-searcharea');
        var $replaceInputs = jQuery('#be-replaceedit, #be-replacearea');

        function updatePlaceholders(searchMode, advancedRegexp) {
            var replaceMode = searchMode;

            if (searchMode == 'regexp' && advancedRegexp) {
                searchMode = 'advregexp';
            }

            $searchInputs.prop('placeholder', getLang('hnt_' + searchMode + 'search'));
            $replaceInputs.prop('placeholder', getLang('hnt_' + replaceMode + 'replace'));
        }

        $searchMode.click(function() {
            updatePlaceholders(this.value, $advancedRegexp.prop('checked'));
            updateConfig('searchmode', this.value);
        });

        $advancedRegexp.click(function() {
            updatePlaceholders($searchMode.filter(':checked').val(), this.checked);
            updateConfig('advregexp', this.checked);
        });
    }

    function initializeMatchCase() {
        jQuery('input[name=matchcase]').click(function() {
            updateConfig('matchcase', this.checked);
        });
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

            updateConfig('multiline', this.checked);
        });

        observeStyleMutations($searchArea, function() {
            // Avoid using $searchArea.outerHeight() as it will have to modify display style
            // of the element when it's hidden in order to get its height. This style mutation
            // will trigger the observer again, causing an infintite loop.
            if ($searchArea.get(0).offsetHeight > 0) {
                updateConfig('searchheight', $searchArea.get(0).offsetHeight);
            }
        });

        observeStyleMutations($replaceArea, function() {
            if ($replaceArea.get(0).offsetHeight > 0) {
                updateConfig('replaceheight', $replaceArea.get(0).offsetHeight);
            }
        });

        jQuery('#batchedit form').submit(function() {
            if (!$multiline.prop('checked')) {
                $searchArea.val($searchEdit.val());
                $replaceArea.val($replaceEdit.val());
            }
        });
    }

    function initializeAdvancedOptions() {
        $document = jQuery(document);
        $options = jQuery('#be-extoptions');

        function onClickOutside(event) {
            if (!$options.get(0).contains(event.target)) {
                console.log('outside');
                close();
            }
        }

        function open() {
            $options.show();
            $document.on('click', onClickOutside);
        }

        function close() {
            $options.hide();
            $document.off('click', onClickOutside);
        }

        jQuery('a[href="javascript:openAdvancedOptions();"]').click(function() {
            open();

            return false;
        });

        jQuery('a[href="javascript:closeAdvancedOptions();"]').click(function() {
            close();

            return false;
        });
    }

    function initializeMatchContext() {
        var $contextChars = jQuery('input[name=ctxchars]');
        var $contextLines = jQuery('input[name=ctxlines]');

        jQuery('input[name=matchctx]').click(function() {
            $contextChars.prop('disabled', !this.checked);
            $contextLines.prop('disabled', !this.checked);
            updateConfig('matchctx', this.checked);
        });

        $contextChars.change(function() {
            updateConfig('ctxchars', this.value);
        });

        $contextLines.change(function() {
            updateConfig('ctxlines', this.value);
        });
    }

    function initializeSearchLimit() {
        var $searchMax = jQuery('input[name=searchmax]');

        jQuery('input[name=searchlimit]').click(function() {
            $searchMax.prop('disabled', !this.checked);
            updateConfig('searchlimit', this.checked);
        });

        $searchMax.change(function() {
            updateConfig('searchmax', this.value);
        });
    }

    function initializeKeepMarks() {
        var $markPolicy = jQuery('select[name=markpolicy]');

        jQuery('input[name=keepmarks]').click(function() {
            $markPolicy.prop('disabled', !this.checked);
            updateConfig('keepmarks', this.checked);
        });

        $markPolicy.change(function() {
            updateConfig('markpolicy', this.value);
        });
    }

    function initializeCheckSummary() {
        jQuery('input[name=checksummary]').click(function() {
            updateConfig('checksummary', this.checked);
        });
    }

    function startProgressMonitor() {
        var hidden = true;
        var $progress = jQuery('#be-progress');

        function updateProgress(data) {
            $progress.text(data.operation).width((data.progress / 10) + "%");
        }

        function checkProgress() {
            setTimeout(function () {
                batcheditServer.checkProgress(onProgressUpdate);
            }, 500);
        }

        function onProgressUpdate(data) {
            if (hidden && data.progress < 400) {
                jQuery('#be-progressbar').css('display', 'flex');
                jQuery('input[name^=cmd').prop('disabled', true);

                hidden = false;
            }

            if (!hidden) {
                updateProgress(data);
                checkProgress();
            }
        }

        checkProgress();
    }

    function initializePreview() {
        jQuery('input[name=cmd\\[preview\\]]').click(function() {
            startProgressMonitor();
        });
    }

    function initializeApply() {
        jQuery('input[name=cmd\\[apply\\]]').click(function() {
            var proceed = true;

            if (jQuery('input[name=checksummary]').prop('checked') &&
                    jQuery('input[name=summary]').val().replace(/\s+/, '') == '') {
                proceed = confirm(getLang('war_nosummary'));
            }

            if (proceed) {
                startProgressMonitor();
            }

            return proceed;
        });
    }

    function initializeCancel() {
        jQuery('input[name=cancel]').click(function() {
            batcheditServer.cancelOperation();
        });
    }

    function initialize() {
        initializeTooltip();
        initializeApplyCheckboxes();
        initializeTotalStatsFloater();
        initializeSearchMode();
        initializeMatchCase();
        initializeMultiline();
        initializeAdvancedOptions();
        initializeMatchContext();
        initializeSearchLimit();
        initializeKeepMarks();
        initializeCheckSummary();
        initializePreview();
        initializeApply();
        initializeCancel();
    }

    return {
        initialize : initialize
    }
})();

jQuery(function () {
    batcheditInterface.initialize();
});
