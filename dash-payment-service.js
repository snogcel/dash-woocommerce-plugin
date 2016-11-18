(function($){

    $( 'body' ).on( 'updated_checkout', function() {
        var checkoutComplete = false;
        getOrderStatus();
    });

    var getOrderStatus = function() {

        var order_id = 0;

        if ( document.getElementById('order_id') ) order_id = parseInt($('#order_id').text());

        data = {
            "receiver_status": true,
            "description": order_id,
        };

        $.ajax({
            type: "POST",
            url: "/?wc-api=spyr_authorizenet_aim",
            data: JSON.stringify(data),
            contentType: "application/json; charset=utf-8",
            crossDomain: true,
            dataType: "json",
            success: function (data, status, jqXHR) {

                if (data.order_status === 'processing') {

                    // 0-conf tx received
                    console.log('status: '+data.order_status);
                    console.log('txid: '+data.txid);
                    console.log('txlock: '+data.txlock);

                    // check insight API
                    verifyTx(data.txid, function(err, res) {
                        var txConfirmations = parseInt(res);
                        console.log('confirmations: '+txConfirmations);

                        // TODO - setInterval here or some other way to avoid hammering the insight server waiting for confirmations...

                        if (txConfirmations >= 1) {
                            checkoutComplete = true;
                            $('#payment_receiver_container').text("waiting for one confirmation...");
                            $('#payment_receiver_container').removeClass('hidden');
                        }

                        if (data.txlock == 'true') {
                            checkoutComplete = true;
                        }
                    });
                }

            },
            error: function (jqXHR, status, error) {
                console.log(jqXHR);
                var err = eval("(" + jqXHR.responseText + ")");
                alert(err.Message);

            }
        });

        if (!checkoutComplete) {
            setTimeout(getOrderStatus, 1000);
        } else {
            console.log("order complete! redirecting to...");

            var returnUrl = $('#return_url').text();
            console.log(returnUrl);

            window.location.replace(returnUrl);
        }
    }

    var verifyTx = function(txid, cb) {
        // var txid = "0b708cd0514d994ce3942a53a541aa30febae56f89b841276bd3ad4a4f6cfe67";
        var insightAPI = "https://dev-test.dash.org/";
        var insightPrefix = "insight-api-dash";
        var url = insightAPI + insightPrefix + "/tx/" + txid;

        $.ajax({
            type: "GET",
            url: url,
            data: {
                format: 'json'
            },
            contentType: "application/json; charset=utf-8",
            crossDomain: true,
            dataType: "json",
            success: function (data, status, jqXHR) {

                cb(null, data.confirmations);
            },
            error: function (jqXHR, status, error) {
                console.log(jqXHR);
                var err = eval("(" + jqXHR.responseText + ")");
                cb(err, null);
            }
        });
    }

})(jQuery);

