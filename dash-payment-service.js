(function($){

  jQuery( 'body' ).on( 'updated_checkout', function() {

  var checkoutComplete = false;

  var getOrderStatus = function() {

	var order_id = 0;

	if ( document.getElementById('order_id') ) order_id = parseInt(jQuery('#order_id').text());

	data = {
                "receiver_status": true,
		"description": order_id,
            };

            jQuery.ajax({
                type: "POST",
                url: "/?wc-api=spyr_authorizenet_aim",
                data: JSON.stringify(data),
                contentType: "application/json; charset=utf-8",
                crossDomain: true,
                dataType: "json",
                success: function (data, status, jqXHR) {

                    if (data.order_status === 'processing') {
			checkoutComplete = true;
		    }

                },
                error: function (jqXHR, status, error) {
                    console.log(jqXHR);
                    var err = eval("(" + jqXHR.responseText + ")");
                    alert(err.Message);

                }
            });

  if (!checkoutComplete) {
    setTimeout(getOrderStatus, 500);
  } else {
    console.log("order complete! redirecting to...");

    var returnUrl = jQuery('#return_url').text();
    console.log(returnUrl);

    window.location.replace(returnUrl);

  }

  }

  getOrderStatus();

  });

})(jQuery);

