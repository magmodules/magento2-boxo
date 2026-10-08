/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 *
 * Place-order validator. Blocks submission when BOXO is offered but the
 * customer has not yet picked a packaging option.
 */
define([
    'mage/translate',
    'Magento_Ui/js/model/messageList',
    'Magmodules_Boxo/js/model/boxo-state'
], function ($t, messageList, boxoState) {
    'use strict';

    return {
        validate: function () {
            if (!boxoState.enabled() || !boxoState.available()) {
                return true;
            }
            var selection = boxoState.selection();
            if (selection === 'reusable' || selection === 'disposable') {
                return true;
            }
            messageList.addErrorMessage({
                message: $t('Please select a packaging option to continue.')
            });
            return false;
        }
    };
});
