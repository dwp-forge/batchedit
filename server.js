var batcheditServer = (function () {
    var cookie = '{7b4e584c-bf85-4f7b-953b-15e327df08ff}';
    var session = null;
    var transaction = null;

    function initialize() {
        session = jQuery('input[name=session]').val();
    }

    function sendRequest(request, data, onSuccess, onError) {
        if (transaction != null) {
            return false;
        }

        data.append('call', 'batchedit');
        data.append('session', jQuery('input[name=session]').val());
        data.append('command', request);

        transaction = request;

        function errorHandler(status, message) {
            console.log(status + ': ' + message);

            if (typeof onError != 'undefined') {
                onError(status, message);
            }
        }

        jQuery.ajax({
            cache : false,
            data : data,
            processData: false,
            contentType: false,
            global : false,
            type : 'POST',
            timeout : 1000,
            url : DOKU_BASE + 'lib/exe/ajax.php',
            success : function (data) {
                if (typeof data != 'object') {
                    errorHandler('invalid_data', 'Invalid data type');
                    return;
                }

                if (data.hasOwnProperty('error')) {
                    errorHandler(data['error'], data['message']);
                    return;
                }

                if (typeof onSuccess != 'undefined') {
                    onSuccess(data);
                }
            },
            error : function (xhr, status, message) {
                errorHandler(transaction + '_failed', message);
            },
            dataFilter : function (data) {
                var match = data.match(new RegExp(cookie + '(.+?)' + cookie));

                if ((match == null) || (match.length != 2)) {
                    return '{"error":"invalid_data","message":"Malformed response"}';
                }

                return match[1];
            },
            complete : function () {
                transaction = null;
            }
        });

        return true;
    }

    function checkProgress(onSuccess, onError) {
        return sendRequest('progress', new FormData(), onSuccess, onError);
    }

    function cancelOperation(onSuccess, onError) {
        return sendRequest('cancel', new FormData(), onSuccess, onError);
    }

    return {
        initialize : initialize,
        checkProgress : checkProgress,
        cancelOperation : cancelOperation
    }
})();

jQuery(function () {
    batcheditServer.initialize();
});
