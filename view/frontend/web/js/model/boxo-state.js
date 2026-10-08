/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 *
 * Singleton observable shared between the BOXO checkout component and the
 * place-order validator so the validator can read whether a packaging choice
 * is required and whether one has been made.
 */
define(['ko'], function (ko) {
    'use strict';

    return {
        enabled: ko.observable(false),
        available: ko.observable(false),
        selection: ko.observable(null)
    };
});
