jQuery(function () {
    jQuery('#batchedit').tooltip({
        tooltipClass: 'batchedit-tooltip',
        show: {delay: 1000},
        track: true
    });

    jQuery('.page .stats input').click(function(){
        jQuery('.match input[id^=' + this.id.replace(/:/g, '\\:') + ']').prop('checked', this.checked);
    });
});
