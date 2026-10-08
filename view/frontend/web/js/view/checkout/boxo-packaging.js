/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'jquery',
    'ko',
    'underscore',
    'uiComponent',
    'uiRegistry',
    'Magento_Checkout/js/action/get-totals',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Magento_Checkout/js/model/quote',
    'Magmodules_Boxo/js/model/boxo-state',
    'Magmodules_Boxo/js/model/logger',
    'Magmodules_Boxo/js/model/selection-validator'
], function ($, ko, _, Component, registry, getTotalsAction, fullScreenLoader, additionalValidators, quote, boxoState, logger, selectionValidator) {
    'use strict';

    var config = window.checkoutConfig && window.checkoutConfig.boxo
        ? window.checkoutConfig.boxo
        : {enabled: false};

    var SUPPORTED_COUNTRY = config.supportedCountry || 'NL';
    // Magento's native In-Store Pickup delivery method. Matched on the rate
    // rather than through the MSI module so this file has no hard dependency
    // on it — stores without in-store pickup simply never match.
    var PICKUP_CARRIER_CODE = 'instore';
    var PICKUP_METHOD_CODE = 'pickup';
    var VALID_SELECTIONS = ['reusable', 'disposable'];
    // Dutch postcode: 4 digits (never leading zero) + 2 letters, space optional.
    // Guards against firing the availability probe on half-typed input.
    var POSTCODE_PATTERN = /^[1-9][0-9]{3}\s*[A-Za-z]{2}$/;
    var validatorRegistered = false;

    return Component.extend({
        defaults: {
            template: 'Magmodules_Boxo/checkout/boxo-packaging'
        },

        /** @inheritdoc */
        initialize: function () {
            this._super();

            this.boxoEnabled = config.enabled === true;
            this.heading = config.heading || '';
            this.description = config.description || '';
            this.singleUseLabel = config.singleUseLabel || '';
            this.reusableLabel = config.reusableLabel || '';
            this.depositFormatted = config.depositFormatted || '';
            this.disposableAmount = parseFloat(config.disposableAmount || 0);
            this.disposableFormatted = config.disposableFormatted || '';
            this.infoUrl = config.infoUrl || '';
            // Withdrawn for the whole cart when it holds a product the retailer
            // excluded from reusable packaging. BOXO is then out of the picture
            // entirely — offering single-use on its own is not a BOXO choice.
            this.reusableAllowed = config.reusableAllowed !== false;

            this.defaultSelection = _.contains(VALID_SELECTIONS, config.defaultSelection)
                ? config.defaultSelection
                : null;

            this.isAvailable = ko.observable(false);
            this.isChecking = ko.observable(false);
            this.errorMessage = ko.observable('');
            // In-store pickup is collected at the counter, so it needs no
            // shipping packaging — the options stay hidden and unrequired.
            this.isPickup = ko.observable(false);
            // Restore a prior choice (BOXO product already in the cart); null
            // until the customer picks one — selecting a packaging is required.
            // Anything restored is dropped when the cart has since gained a
            // product that is not allowed in reusable packaging.
            var restored = config.currentSelection || null;
            if (!this.reusableAllowed) {
                restored = null;
            }

            this.selection = ko.observable(restored);
            this._lastSelection = restored;

            this._lastCheckedKey = null;
            this._lastCheckedPostcode = '';
            this._lastCheckedCountry = '';
            this._suppressSelectionPost = false;
            this._selectionRequest = null;
            this._selectionToken = 0;

            this._triggerCheck = _.debounce(this._maybeCheckAvailability.bind(this), 400);

            // Single source of truth for "the customer must pick a packaging":
            // offered for this address and not being picked up in store.
            this.showOptions = ko.computed(function () {
                return this.boxoEnabled
                    && this.reusableAllowed
                    && this.isAvailable()
                    && !this.isPickup();
            }, this);

            // Sync into the shared state used by the place-order validator. The
            // restored selection must be carried over — seeding this with null
            // made the validator block checkout ("select a packaging option")
            // while a BOXO product was already in the cart.
            boxoState.enabled(this.boxoEnabled);
            boxoState.available(this.showOptions());
            boxoState.selection(this.selection());

            this.showOptions.subscribe(function (value) {
                boxoState.available(!!value);
            });
            this.selection.subscribe(function (value) {
                boxoState.selection(value);
            });

            this._logBlockReason();

            if (this.boxoEnabled && !this.reusableAllowed && config.currentSelection) {
                // The cart gained a disallowed product after a choice was made;
                // detach it so no packaging is charged for an order BOXO will
                // not carry.
                this._postSelection(null);
            }

            if (this.boxoEnabled) {
                if (!validatorRegistered) {
                    additionalValidators.registerValidator(selectionValidator);
                    validatorRegistered = true;
                }
                this._watchShippingMethod();
                this._watchAddressFields();
                this._watchSelection();

                logger.info('Ready');
            }

            return this;
        },

        /**
         * Turn a rejected save into a line an agent can act on. The packaging
         * products being unreachable in the store is a configuration fault the
         * retailer can fix, and saying so beats reporting a server error.
         *
         * @param {?Object} response
         * @returns {String}
         * @private
         */
        _describeFailure: function (response) {
            var error = response && response.error;

            if (error === 'packaging_unavailable') {
                return 'Packaging product not available in this store. '
                    + (response.detail || '')
                    + ' Check that the BOXO products are enabled, assigned to this website,'
                    + ' and in stock for the stock serving it.';
            }

            if (error === 'server_error' || !error) {
                return 'Internal server error.';
            }

            return 'Packaging could not be saved (' + error + ')';
        },

        /**
         * Report why the server withheld reusable packaging for this cart.
         * @private
         */
        _logBlockReason: function () {
            var reason = config.blockReason;

            if (!this.boxoEnabled || !reason || !reason.code) {
                return;
            }

            if (reason.code === 'product_not_eligible') {
                logger.info('Product id ' + reason.product_id + ' (' + reason.sku + ') not eligible');
                return;
            }

            if (reason.code === 'cart_limit_exceeded') {
                logger.info('Cart limit exceeded (' + reason.qty + ' of max ' + reason.max + ')');
                return;
            }

            logger.info('Reusable packaging not offered (' + reason.code + ')');
        },

        /**
         * Track the selected delivery method so switching to in-store pickup
         * hides the packaging options and detaches anything already chosen.
         * @private
         */
        _watchShippingMethod: function () {
            var self = this;

            function apply(method) {
                var pickup = !!method
                    && method.carrier_code === PICKUP_CARRIER_CODE
                    && method.method_code === PICKUP_METHOD_CODE;

                if (pickup === self.isPickup()) {
                    return;
                }
                self.isPickup(pickup);

                if (pickup) {
                    logger.info('In-store pickup selected, packaging not offered');

                    // No packaging is shipped with a pickup order — drop the
                    // line item so no deposit or fee stays on the quote.
                    if (self.selection()) {
                        self._rollbackSelection(null);
                        self._postSelection(null);
                    }
                    return;
                }

                // Back to a delivered method: re-evaluate the current address.
                self._lastCheckedKey = null;
                self._triggerCheck();
            }

            apply(quote.shippingMethod());
            quote.shippingMethod.subscribe(apply);
        },

        /**
         * Subscribe directly to the postcode and country_id form elements so we
         * react the moment the customer edits either field — before the address
         * is committed to quote.shippingAddress().
         * @private
         */
        _watchAddressFields: function () {
            var self = this;
            var fieldsetPath = 'checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset';

            registry.async(fieldsetPath + '.postcode')(function (element) {
                element.on('value', function () {
                    self._triggerCheck();
                });
            });

            registry.async(fieldsetPath + '.country_id')(function (element) {
                element.on('value', function () {
                    self._triggerCheck();
                });
            });

            // Initial pass for pre-filled forms (logged-in customer with saved address).
            registry.async('checkoutProvider')(function () {
                self._triggerCheck();
            });
        },

        /**
         * @private
         */
        _maybeCheckAvailability: function () {
            var self = this;

            if (this.isPickup() || !this.reusableAllowed) {
                return;
            }

            registry.async('checkoutProvider')(function (checkoutProvider) {
                var address = checkoutProvider.get('shippingAddress') || {};
                var postcode = (address.postcode || '').toString().trim();
                var country = (address.country_id || '').toString().toUpperCase();

                if (country && country !== SUPPORTED_COUNTRY) {
                    logger.info('Country ' + country + ' not eligible');
                    self._reset();
                    return;
                }

                // Incomplete or malformed postcode — the customer is still typing.
                // Skip the request instead of sending a partial value to the API.
                if (!POSTCODE_PATTERN.test(postcode)) {
                    self._reset();
                    return;
                }

                var key = country + '|' + postcode.toUpperCase().replace(/\s+/g, '');
                if (key === self._lastCheckedKey) {
                    return;
                }
                self._lastCheckedKey = key;

                self._checkAvailability(postcode, country);
            });
        },

        /**
         * @param {string} postcode
         * @param {string} country
         * @private
         */
        _checkAvailability: function (postcode, country) {
            var self = this;

            this.isChecking(true);
            this.errorMessage('');

            $.ajax({
                url: config.urls.checkAvailability,
                type: 'POST',
                dataType: 'json',
                data: {
                    postcode: postcode,
                    country: country,
                    form_key: $.mage.cookies.get('form_key')
                }
            }).done(function (response) {
                if (self.isPickup()) {
                    // Pickup was chosen while this probe was in flight.
                    return;
                }

                self.isAvailable(response && response.available === true);

                if (response && response.error === 'api_error') {
                    logger.error('Internal server error.');
                } else if (!self.isAvailable()) {
                    logger.info('Postal code not eligible');
                }

                if (self.isAvailable()) {
                    logger.info('Postal code eligible');
                    self._lastCheckedPostcode = postcode;
                    self._lastCheckedCountry = country;
                    // Set after the postcode fields above — persisting the
                    // pre-selection reads them.
                    self._applyDefaultSelection();
                } else {
                    // Postcode no longer supported — clear any stale selection so we
                    // don't leave a deposit/disposable fee on the quote.
                    if (self.selection()) {
                        self._rollbackSelection(null);
                        self._postSelection(null);
                    }
                }
            }).fail(function () {
                self.isAvailable(false);
                logger.error('Internal server error.');
                self.errorMessage($.mage.__('Could not check BOXO availability. Please try again.'));
            }).always(function () {
                self.isChecking(false);
            });
        },

        /**
         * Pre-select the packaging the retailer configured, once BOXO turns out
         * to be available here and the customer has no choice of their own.
         *
         * Assigned through the observable rather than silently, so the line item
         * is added straight away: a radio that looks selected while the order
         * summary shows no deposit would be misleading.
         * @private
         */
        _applyDefaultSelection: function () {
            if (!this.defaultSelection || this.selection() || this.isPickup() || !this.reusableAllowed) {
                return;
            }

            this.selection(this.defaultSelection);
        },

        /**
         * @private
         */
        _watchSelection: function () {
            var self = this;

            this.selection.subscribe(function (value) {
                if (self._suppressSelectionPost) {
                    return;
                }
                self._postSelection(value);
            });
        },

        /**
         * Restore the radio without re-firing the persist subscriber.
         * @param {?string} value
         * @private
         */
        _rollbackSelection: function (value) {
            this._suppressSelectionPost = true;
            this.selection(value);
            this._suppressSelectionPost = false;
        },

        /**
         * @param {?string} selection 'reusable' | 'disposable' | null
         * @private
         */
        _postSelection: function (selection) {
            var self = this;
            var previous = this._lastSelection || null;
            var token = ++this._selectionToken;
            var totalsDeferred = null;

            this._lastSelection = selection;

            // Switching options repeatedly used to leave several requests in
            // flight; whichever answered last won, so the summary could end up
            // showing the item count of a superseded choice. Abort the pending
            // one and ignore any response that is no longer the current pick.
            if (this._selectionRequest) {
                this._selectionRequest.abort();
                this._selectionRequest = null;
            }

            fullScreenLoader.startLoader();

            this._selectionRequest = $.ajax({
                url: config.urls.setSelection,
                type: 'POST',
                dataType: 'json',
                data: {
                    selection: selection || '',
                    postcode: this._lastCheckedPostcode,
                    country: this._lastCheckedCountry,
                    form_key: $.mage.cookies.get('form_key')
                }
            }).done(function (response) {
                if (token !== self._selectionToken) {
                    return;
                }

                if (!response || !response.success) {
                    logger.error(self._describeFailure(response));
                    self._lastSelection = previous;
                    self._rollbackSelection(previous);
                    return;
                }

                logger.info('Packaging selected: ' + (selection || 'none'));

                // Hold the loader until the refreshed totals have landed, so the
                // customer can't act on a summary that still lists the old line.
                totalsDeferred = $.Deferred();
                totalsDeferred.always(function () {
                    fullScreenLoader.stopLoader();
                });
                getTotalsAction([], totalsDeferred);
            }).fail(function (jqXhr, textStatus) {
                if (textStatus === 'abort' || token !== self._selectionToken) {
                    return;
                }
                logger.error('Internal server error.');
                self._lastSelection = previous;
                self._rollbackSelection(previous);
            }).always(function () {
                if (token === self._selectionToken) {
                    self._selectionRequest = null;
                }
                // The winning request releases the loader once totals are back.
                if (!totalsDeferred) {
                    fullScreenLoader.stopLoader();
                }
            });
        },

        /**
         * @private
         */
        _reset: function () {
            this._lastCheckedKey = null;
            this.errorMessage('');
            if (this.isAvailable()) {
                this.isAvailable(false);
            }
            if (this.selection()) {
                this._rollbackSelection(null);
                this._postSelection(null);
            }
        }
    });
});
