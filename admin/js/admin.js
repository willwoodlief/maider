var maider_ajax_req = {}; //active ajax request
/**
 * Typedef the information sent from ajax
 *
 * @typedef {Object} MaiderNode
 * @property {string} title - the header for the information
 * @property {string} name
 * @property {string} value
 * @property {string} result
 * @property {boolean} is_error
 * @property {boolean} has_run
 */
jQuery(function ($) {

    $('.maider-load-icon').show();
    maider_talk_to_backend('combined_logs', {}, options_success);

    function options_success(d) {
        $('.maider-load-icon').hide();
        var table = $('.maider-option-table tbody');
        table.html('');
        var b_all_has_run = true;
        for(var i=0; i < d.length; i ++) {
            /**
             * @type {MaiderNode} node
             */
            var node = d[i];
            if (!node.has_run) {
                b_all_has_run = false;
            }
            var error_class = '';
            if (node.is_error) {
                error_class = 'maider-error';
            }

            var name_class = 'option';
            if (node.title !== 'Option') {
                name_class = 'action';
            }
            var line = '<tr class="'+error_class+ '">\n' +
                '                <td><span class="maider-option-header">' + node.title + '</span></td>\n' +
                '                <td><span class="maider-option-key '+ name_class + '">' + node.name + '</span></td>\n' +
                '                <td><span class="maider-option-value">' + node.value + '</span></td>\n' +
                '                <td><span class="maider-option-result">' + node.result + '</span></td>\n' +
                '            </tr>';
            table.append(line);
        }

        if (b_all_has_run) {
            $('#maider-do-update').prop('disabled',true).css('color', 'lightgray');
            $('.maider-already-run').show();
        }
    }

    $('#maider-do-update').click(function() {
        $('.maider-load-icon').show();
        maider_talk_to_backend('run', {}, options_success);
    });
});

function maider_talk_to_backend(method, server_options, success_callback, error_callback) {

    if (!server_options) {
        server_options = {};
    }

    // noinspection ES6ModulesDependencies
    var outvars = jQuery.extend({}, server_options);
    // noinspection JSUnresolvedVariable
    outvars._ajax_nonce = maider_backend_ajax_obj.nonce;
    // noinspection JSUnresolvedVariable
    outvars.action = maider_backend_ajax_obj.action;
    outvars.method = method;
    // noinspection ES6ModulesDependencies
    // noinspection JSUnresolvedVariable
    maider_ajax_req = jQuery.ajax({
        type: 'POST',
        beforeSend: function () {
            if (maider_ajax_req && (maider_ajax_req !== 'ToCancelPrevReq') && (maider_ajax_req.readyState < 4)) {
            //    maider_ajax_req.abort();
            }
        },
        dataType: "json",
        url: maider_backend_ajax_obj.ajax_url,
        data: outvars,
        success: success_handler,
        error: error_handler
    });

    function success_handler(data) {

        // noinspection JSUnresolvedVariable
        if (data.is_valid) {
            if (success_callback) {
                success_callback(data.data);
            } else {
                console.debug(data);
            }
        } else {
            if (error_callback) {
                error_callback(data.data);
            } else {
                console.debug(data);
            }

        }
    }

    /**
     *
     * @param {XMLHttpRequest} jqXHR
     * @param {Object} jqXHR.responseJSON
     * @param {string} textStatus
     * @param {string} errorThrown
     */
    function error_handler(jqXHR, textStatus, errorThrown) {
        if (errorThrown === 'abort' || errorThrown === 'undefined') return;
        var what = '';
        var message = '';
        if (jqXHR && jqXHR.responseText) {
            try {
                what = jQuery.parseJSON(jqXHR.responseText);
                if (what !== null && typeof what === 'object') {
                    if (what.hasOwnProperty('message')) {
                        message = what.message;
                    } else {
                        message = jqXHR.responseText;
                    }
                }
            } catch (err) {
                message = jqXHR.responseText;
            }
        } else {
            message = "textStatus";
            console.info('Fran Test ajax failed but did not return json information, check below for details', what);
            console.error(jqXHR, textStatus, errorThrown);
        }

        if (error_callback) {
            error_callback(message);
        } else {
            console.warn(message);
        }


    }
}




