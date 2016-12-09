// (function($){



    // Plugin Config

    var socket = null;

    var config = {
        paymentWindowOpts: {
            headerColor: '#1c75bc',
            title:'Send DASH to ...',
            overlayClose: false,
            width: 500,
            height: 500,
            padding: 25,
            radius: 1,
            autoOpen: false,
            overlayColor: 'rgba(0, 0, 0, 0.6)'
        },
        network: 'testnet',
        provider: 'https://dev-test.dash.org',
        transactionOpts: {
            confirmations: 1,
            pendingNotificationInterval: 5,
            pollingInterval: 2000
        },
        functions: {
            transactionPending: transactionPending,
            transactionReceived: transactionReceived,
            transactionConfirmed: transactionConfirmed
        }
    };

    function transactionPending(res) {

        console.log(res);

    }

    function transactionReceived(res) {

        jQuery('#checkout_status').html('Transaction Received<br><span style="font-size:.8em">(0/1 confirmations)');

    }

    function transactionConfirmed(res) {

        window.location.replace(res.return_url);

    }


    var checkout = new Checkout();

    jQuery( document ).ready(function() {

        checkout.getExchangeRate({expiry:300}, function(err, res) {

            // exchange rate cached

        });

    });

    jQuery( 'body' ).on( 'updated_checkout', function() {

        checkout.checkoutActive = false; // set status to inactive

        jQuery(".siteCurrency").each(function() { // check for existence of div.siteCurrency created by checkout

            checkout.checkoutActive = true; // set status to active if so

        }).promise().done( function() {

            jQuery("input[name='payment_method']:checked").each(function() {
                if (jQuery(this).val() == 'spyr_authorizenet_aim') {
                    checkout.setCurrency();
                } else {
                    checkout.checkoutActive = false;
                    checkout.resetCurrency();
                }
            });

            jQuery("input[name='payment_method']").change(function() {
                if (jQuery(this).val() == 'spyr_authorizenet_aim') {
                    checkout.setCurrency();
                } else {
                    checkout.checkoutActive = false;
                    checkout.resetCurrency();
                }
            });

        });

        // checkout has refreshed, time to display QR Code

        // get Payment Receiver
        if (document.getElementById('receiver_id') && !checkout.initialized) {

            console.log("-payment receiver created-");

            // there seriously has to be a better way to do this....

            var paymentReceiver = {
                "receiver_id": jQuery('#receiver_id').text(),
                "username": jQuery('#username').text(),
                "dash_payment_address": jQuery('#dash_payment_address').text(),
                "amount_fiat": jQuery('#amount_fiat').text(),
                "type_fiat": jQuery('#type_fiat').text(),
                "base_fiat": jQuery('#base_fiat').text(),
                "amount_duffs": jQuery('#amount_duffs').text(),
                "description": jQuery('#description').text()
            };


            // verify that the payment address exists and retrieve associated order_id

            checkout.getOrderId(paymentReceiver.dash_payment_address, function(err, res) {

                console.log(res[0]);
                var orderId = res[0].id;

                if(orderId && !err) {

                    // set checkout orderId
                    checkout._orderId = orderId;

                    // Initialize Payment Receiver and (optional) Socket.io Listener
                    checkout.init(config, paymentReceiver, socket, function(err, checkout) {

                        // checkout has initialized
                        checkout.displayQRCode(function(err, res) {

                            jQuery("#modal").iziModal(config.paymentWindowOpts);

                            jQuery('#qrcode').qrcode(res);

                            jQuery('#modal').iziModal('open');

                            checkout.paymentWindowActive = true;

                            checkout.verifyTransaction(
                                config.transactionOpts,
                                config.functions.transactionPending,
                                config.functions.transactionReceived,
                                config.functions.transactionConfirmed
                            ); // begin polling insight for tx confirmations

                        });

                    });

                } else {

                    console.log("error: order not found for: " + paymentReceiver.dash_payment_address);

                }


            });



        }

    });















    

    function Checkout() {

        this._cache = [];
        this._pendingConfirmation = [];
        this._paymentReceiver = null;
        this._orderId = null; // external_id for Payment Receiver

    	this.initialized = false;
        this.checkoutActive = false;
        this.paymentWindowActive = false;
        this.socketConnected = false;

        this.provider = 'https://dev-test.dash.org';

        this._cachedPrice = null;

        // document handlers

        // on document.ready

        // on body.update_checkout

    }


    Checkout.prototype.init = function(opts, paymentReceiver, socket, cb) {
        var self = this;

	    this.network = opts.network || 'testnet';

        this.confirmations = opts.confirmations || 1;

        this.socket = socket;

        if (!this._paymentReceiver) {

            this._paymentReceiver = {
                "receiver_id": paymentReceiver.receiver_id,
                "username": paymentReceiver.username,
                "dash_payment_address": paymentReceiver.dash_payment_address,
                "amount_fiat": paymentReceiver.amount_fiat,
                "type_fiat": paymentReceiver.type_fiat,
                "base_fiat": paymentReceiver.base_fiat,
                "amount_duffs": paymentReceiver.amount_duffs,
                "description": paymentReceiver.description
            };
        }

        // if socket is passed connect and subscribe to paymentReceiver.dash_payment_address

        if(socket) this.socketConnected = this.initSocket(paymentReceiver.dash_payment_address);

        this.initialized = true;

        cb(null,self);
    };

    Checkout.prototype.initSocket = function(address) {
        var self = this;

        var socket = this.socket;

        console.log("-socketio-");
        console.log("listening to: " + address);

        if (address) {
            var address = address;
        } else {
            return false; // inactive socket status
        }

        socket.on('connect', function() {
            socket.emit('subscribe', 'block');
            socket.emit('subscribe', 'txlock');
            socket.emit('subscribe', 'bitcoind/addresstxid', [ address ]);
        });

        socket.on('block', function(data) {
            console.log('block: '+ data);

            // get transaction by txid
            // send update to WooCommerce API

            // update payment receiver window

        });

        socket.on('txlock', function(data) {
            console.log('txlock: '+ data);

            // TODO - extend bitcoind/addresstxlockid ?

            // or just filter it out to only relate to 'address'

        });

        socket.on('bitcoind/addresstxid', function(data) {
            console.log('addresstxid: '+ data.txid);

            var txid = '7a60a8c5409027492a4fefe340ac27297280ab530e3a4a988317508bbd503c43';
            self.getTx(txid, function(err, res) {

                self._pendingConfirmation.push(res);

                // get blockHeight of transaction

                self.getBlock(res.blockhash, function(err, res) {

                    if(err) cb(err, null);
                    console.log(res);

                    // query store API for receiver_id

                    
                });
            });


            // check woocommerce for txlock

            // get transaction by txid
            // send update to WooCommerce API

            // check time of last block
            // update payment receiver window

        });

        return true; // active socket status
    };


    Checkout.prototype.verifyTransaction = function(opts, transactionPending, transactionReceived, transactionConfirmed) {
        var self = this;

        var i = 0;

        setInterval( function() {
            self.getReceiverStatus(function(err, res) {


                if (i < opts.pendingNotificationInterval) {
                    i++;
                } else {

                    // TODO - update eCommerce database with confirmation ?

                    transactionPending(res);
                    i = 0;
                }

                if(res.txlock == 'true') {

                    console.log('txlock detected for txid: ' + res.txid);
                    console.log(res);

                    transactionConfirmed(res);

                }
                if (res.txid) {
                    self.getTx(res.txid, function(err, result) { // fetch tx from insight-api

                        if (result.confirmations < opts.confirmations) {

                            transactionReceived(res);

                        }

                        if (result.confirmations >= opts.confirmations) {

                            console.log(opts.confirmations + ' confirmations for txid: ' + result.txid);
                            console.log(res);

                            transactionConfirmed(res);

                        }
                    });
                }
            });
        }, opts.pollingInterval);

    };

    Checkout.prototype.resetCurrency = function() {
        var cachedPrice = this._cachedPrice;

        if (this.checkoutActive) {

            // TODO -- pull function into .init function

            jQuery('.amount').each(function() {

                jQuery(this).html(cachedPrice);

                this.checkoutActive = false;

            });

        }
    };

    Checkout.prototype.setCurrency = function() {
        var self = this;

        var opts = { expiry: 300 }; // price expiry = 5 minutes

        if (!this.checkoutActive) {

            this.getExchangeRate(opts, function(err, res) {

                // TODO -- pull function into .init function

                jQuery('#order_review .amount').each(function() {

                    var price = Number(jQuery(this).text().replace(/[^0-9\.]+/g,""));
                    var dashPrice = parseFloat(price/res.value).toFixed(2);

                    self._cachedPrice = jQuery(this).html();

                    jQuery(this).html(dashPrice + ' <span class="woocommerce-Price-currencySymbol"> DASH</span> <div class="siteCurrency">' + self._cachedPrice + '</div>');

                    self.checkoutActive = true;

                });

            });

        }
    };
    
    // display QR code
    Checkout.prototype.displayQRCode = function(cb) {

        var paymentReceiver = this._paymentReceiver;

        var address = paymentReceiver.dash_payment_address;
        var amount = paymentReceiver.amount_duffs;

        cb(null, 'dash:{{'+address+'}}?amount={{'+amount+'}}');
    };


    // API

    Checkout.prototype.getExchangeRate = function(opts, cb) {
        var self = this;
        var now = Math.round(+new Date()/1000);
        var expiry = opts.expiry || 60; // 60 seconds

        if (self._cache[0] && ( (now - self._cache[0].timestamp) < expiry ) ) {

            cb(null, self._cache[0]); // return from cache if exists and timestamp is less than 1 minute old

        } else {

            self._cache.pop(); // remove cached record

            // fetch new exchange rate
            this.getSiteCurrency(function(err, res) {
                if(err) cb(err, null);
                self.getFiatValue(res.currency, function(err, res) {
                    if(err) cb(err, null);
                    self._cache.unshift({
                        fiatCode: res.fiatCode,
                        value: res.value,
                        timestamp: Math.round(+new Date()/1000)
                    });
                    cb(null, res); // return current exchange rate
                });
            });

        }
    };

    Checkout.prototype.getChainTip = function(opts, cb) {
        var self = this;

        // fetch new exchange rate
        this.getBestBlockHash(function(err, res) {
            if(err) cb(err, null);
            self.getBlock(res.bestblockhash, function(err, res) {
                if(err) cb(err, null);
                cb(null, res); // return blockchain tip
            });
        });
    };

    Checkout.prototype.getFiatValue = function(fiatCode, cb) {

        var opts = {
            type: "POST",
            route: "/dash-payment-service/valuationService",
            data: {
                fiatCode: fiatCode
            }
        };

        this._fetch(opts, cb);
    };



    Checkout.prototype.getTx = function(txid, cb) {

        var opts = {
            type: "GET",
            route: "/insight-api-dash/tx/"+txid,
            data: {
                format: "json"
            }
        };

        this._fetch(opts, cb);
    };

    Checkout.prototype.getBestBlockHash = function(cb) {

        var opts = {
            type: "GET",
            route: "/insight-api-dash/status?q=getBestBlockHash",
            data: {
                format: "json"
            }
        };

        this._fetch(opts, cb);
    };

    Checkout.prototype.getBlock = function(hash, cb) {

        var opts = {
            type: "GET",
            route: "/insight-api-dash/block/"+hash,
            data: {
                format: "json"
            }
        };

        this._fetch(opts, cb);
    };

    Checkout.prototype.getSiteCurrency = function(cb) {

        var opts = {
            type: "POST",
            provider: "/",
            route: "?wc-api=spyr_authorizenet_aim",
            data: {
                site_currency: true
            }
        };

        this._fetch(opts, cb);
    };

    Checkout.prototype.getReceiverStatus = function(cb) {


        var opts = {
            type: "POST",
            provider: "/",
            route: "?wc-api=spyr_authorizenet_aim",
            data: {
                receiver_status: true,
                order_id: this._orderId
            }
        };

        this._fetch(opts, cb);
    };

    Checkout.prototype.getOrderId = function(address, cb) {

        var opts = {
            type: "POST",
            provider: "/",
            route: "?wc-api=spyr_authorizenet_aim",
            data: {
                get_order_id: true,
                dash_payment_address: address
            }
        };

        this._fetch(opts, cb);
    };


    Checkout.prototype._fetch = function(opts,cb) {
        var self = this;
        var provider = opts.provider || self.provider;

        if(opts.type && opts.route && opts.data) {

            jQuery.ajax({
                type: opts.type,
                url: provider + opts.route,
                data: JSON.stringify(opts.data),
                contentType: "application/json; charset=utf-8",
                crossDomain: true,
                dataType: "json",
                success: function (data, status, jqXHR) {
                    cb(null, data);
                },
                error: function (jqXHR, status, error) {
                    var err = eval("(" + jqXHR.responseText + ")");
                    cb(err, null);
                }
            });

        } else {
            cb('missing parameter',null);
        }
    };

    
// })(jQuery);
