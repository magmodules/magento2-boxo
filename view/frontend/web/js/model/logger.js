/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 *
 * Console channel for the checkout packaging block.
 *
 * Written for support rather than for the customer: the lines describe what the
 * extension decided and why, so an agent can read them off a shopper's screen
 * instead of reproducing the cart. They are therefore deliberately in English
 * and not translated — an agent should recognise them whatever the shop's
 * language — and kept to state changes so the console stays readable.
 */
define([], function () {
    'use strict';

    var PREFIX = '[BOXO Return]';

    /**
     * @param {String} method - console method to use.
     * @param {String} message
     */
    function write(method, message) {
        var console = window.console;

        if (!console || typeof console[method] !== 'function') {
            return;
        }

        console[method](PREFIX + ' ' + message);
    }

    return {
        /**
         * @param {String} message
         */
        info: function (message) {
            write('log', message);
        },

        /**
         * @param {String} message
         */
        warn: function (message) {
            write('warn', message);
        },

        /**
         * @param {String} message
         */
        error: function (message) {
            write('error', message);
        }
    };
});
