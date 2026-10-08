/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
require([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'loader'
], function ($, modal) {
    'use strict';

    const moduleName = 'boxo';

    /**
     * @param{String} type - debug || error || test || changelog.
     * @param{Object} options - modal options.
     */
    function initModal(type, options) {
        const modalId = `#mm-ui-result_${type}-modal`;
        if (!$(modalId).length) return;

        let defaultOptions = {
            modalClass: `mm-ui-modal ${moduleName}`,
            type: 'popup',
            responsive: true,
            innerScroll: true,
            title: options.title || '',
            buttons: [
                {
                    text: $.mage.__('Close window'),
                    class: 'mm-ui-modal-close',
                    click: function () {
                        this.closeModal();
                    }
                }
            ]
        };

        if (options.buttons) {
            let additionalButtons = {
                text: $.mage.__('download as .txt file'),
                class: 'mm-ui-button__download mm-ui-icon__download-alt',
                click: () => {
                    let elText = document.getElementById(`mm-ui-result_${options.buttons}`).innerText || '',
                        link = document.createElement('a');

                    link.setAttribute('download', `${options.buttons}-log.txt`);
                    link.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(elText));
                    link.click();
                }
            };

            defaultOptions.buttons.unshift(additionalButtons);
        }

        modal(defaultOptions, $(modalId));
        $(modalId).loader({texts: ''});
    }

    const successHandlers = {
        /**
         * Log records and self-test results, both rendered as a list.
         *
         * @param{Object} response - Ajax request response data.
         * @param{Object} $container - jQuery container element.
         * @param{String} type - debug || error || test.
         */
        logs(response, $container, type) {
            if (Array.isArray(response.result)) {
                if (type === 'test') {
                    response = response.result.map((data) => this.tmpTest(type, data)).join('');
                } else {
                    response = `<ul>${response.result.map((data) => this.tmpLogs(type, data)).join('')}</ul>`;
                }
            }

            $container.find('.result').empty().append(response);
        },

        tmpLogs(type, data) {
            return `<li class="mm-ui-result-item mm-ui-result_${type}-item">
                        <strong>${data.date}</strong>
                        <p>${data.msg}</p>
                    </li>`;
        },

        tmpTest(type, data) {
            let supportLinkHtml = '',
                resultMsg = data.result_code === 'failed' ? data.result_msg : '',
                resultText = data.result_code === 'success'
                    ? $.mage.__('Passed')
                    : $.mage.__('Failed'),

                svg = data.result_code === 'success'
                    ? `<svg xmlns="http://www.w3.org/2000/svg" width="27.5" height="27.5" viewBox="0 0 27.5 27.5">
                           <path d="M28.063,14.313A13.75,13.75,0,1,1,14.313.563,13.75,13.75,0,0,1,28.063,14.313Zm-15.34,7.281,10.2-10.2a.887.887,0,0,0,0-1.255L21.669,8.882a.887.887,0,0,0-1.255,0l-8.32,8.32L8.21,13.318a.887.887,0,0,0-1.255,0L5.7,14.572a.887.887,0,0,0,0,1.255l5.766,5.766a.887.887,0,0,0,1.255,0Z" transform="translate(-0.563 -0.563)" fill="#f5e077"/>
                       </svg>`
                    : `<svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 30 30">
                           <path d="M18,3A15,15,0,1,0,33,18,15.005,15.005,0,0,0,18,3Zm1.5,22.5h-3v-3h3Zm0-6h-3v-9h3Z" transform="translate(-3 -3)" fill="#b41f1f"/>
                       </svg>`;

            if (data.support_link) {
                supportLinkHtml = `<a target="_blank" href="${data.support_link}" class="mm-ui-icon__help-rounded">
                                       ${$.mage.__('More information')}
                                       <svg xmlns="http://www.w3.org/2000/svg" width="20.019" height="20.019" viewBox="0 0 20.019 20.019">
                                           <path d="M20.019,4.395V20.124a2.145,2.145,0,0,1-2.145,2.145H2.145A2.145,2.145,0,0,1,0,20.124V4.395A2.145,2.145,0,0,1,2.145,2.25H17.874A2.145,2.145,0,0,1,20.019,4.395Zm-3.932.715h-5a1.073,1.073,0,0,0-.758,1.831L11.754,8.37,3.017,17.107a.536.536,0,0,0,0,.758L4.4,19.252a.536.536,0,0,0,.758,0L13.9,10.515l1.429,1.429a1.073,1.073,0,0,0,1.831-.758v-5A1.072,1.072,0,0,0,16.086,5.11Z" transform="translate(0 -2.25)" fill="#55534f"/>
                                       </svg>
                                   </a>`;
            }

            return `<li class="mm-ui-result_${type}-item ${data.result_code}">
                        ${svg}
                        <div>
                            <strong>${resultText}</strong>
                            <div>
                                <p>${data.test}</p>
                                <p><em>${resultMsg}</em></p>
                            </div>
                            ${supportLinkHtml}
                        </div>
                    </li>`;
        },

        /**
         * @param{Object} response - Ajax request response data.
         * @param{Object} $container - jQuery container element.
         */
        version(response, $container) {
            let resultText = $.mage.__('Great, you are using the latest version.'),
                resultClass = 'up',
                currentVersion = response.result.current_version.replace(/v|version/gi, ''),
                latestVersion = response.result.last_version.replace(/v|version/gi, '');

            if (currentVersion !== latestVersion) {
                resultClass = 'down';
                resultText = $.mage.__('There is a new version available <span>(%1)</span> see <button type="button" id="mm-ui-button_changelog">changelog</button>.')
                    .replace('%1', latestVersion);
            }

            $container.html(`<strong class="mm-ui-version mm-ui-icon__thumbs-${resultClass}">${resultText}</strong>`);
        },

        /**
         * Outcome of verifying the API key against BOXO.
         *
         * @param{Object} response - Ajax request response data.
         * @param{Object} $container - jQuery container element.
         */
        apikey(response, $container) {
            const result = response.result || {},
                environment = result.environment === 'sandbox'
                    ? $.mage.__('sandbox')
                    : $.mage.__('production');

            let resultText,
                resultClass;

            if (result.success) {
                resultClass = 'up';
                resultText = $.mage.__('Connection to the BOXO %1 API established').replace('%1', environment);
            } else {
                resultClass = 'down';

                switch (result.error) {
                    case 'missing_key':
                        resultText = $.mage.__('No %1 API key configured, add the key from your BOXO account!')
                            .replace('%1', environment);
                        break;
                    case 'rejected':
                        resultText = $.mage.__('The BOXO %1 API rejected this key.').replace('%1', environment);
                        break;
                    case 'unreachable':
                        resultText = $.mage.__('Could not reach the BOXO %1 API, see the error log for details.')
                            .replace('%1', environment);
                        break;
                    default:
                        resultText = $.mage.__('Unexpected response from the BOXO %1 API (status %2).')
                            .replace('%1', environment)
                            .replace('%2', result.status || 0);
                }
            }

            $container.html(`<strong class="mm-ui-version mm-ui-icon__thumbs-${resultClass}">${resultText}</strong>`);
        },

        /**
         * @param{Object} response - Ajax request response data.
         * @param{Object} $container - jQuery container element.
         */
        changelog(response, $container) {
            const listHTML = Object.keys(response).map((version) => {
                return `<li class="mm-ui-result_changelog-item">
                            <b>${version}</b>
                            <span class="mm-ui-divider">|</span>
                            <b>${response[version].date}</b>
                            <div>${response[version].changelog}</div>
                        </li>`;
            });

            $container.find('.result').empty().append(listHTML.join(''));
        }
    };

    $(() => {
        initModal('debug', {title: $.mage.__('Last 100 debug log records'), buttons: 'debug'});
        initModal('error', {title: $.mage.__('Last 100 error log records'), buttons: 'error'});
        initModal('test', {title: $.mage.__('Self-test')});
        initModal('changelog', {title: $.mage.__('Changelog')});
    });

    // These blocks replace themselves with the result instead of opening a modal.
    $('.mm-ui-result_version-wrapper, .mm-ui-result_apikey-wrapper').loader({texts: ''});

    /**
     * Credentials as they currently stand in the form, so a key can be verified
     * before it is saved. An untouched obscured field posts '******', which the
     * controller reads as "test the stored key".
     *
     * Sandbox mode has no field in the admin — it is a developer-only setting
     * applied through config — so the environment is left to the controller to
     * read from the stored configuration.
     *
     * @returns {FormData}
     */
    function apiKeyPayload() {
        const data = new FormData();

        data.append('form_key', window.FORM_KEY);
        data.append('api_key', $('#magmodules_boxo_general_api_key').val() || '');

        return data;
    }

    $(document).on('click', '[id^=mm-ui-button]', function () {
        let action = this.id.split('_')[1],
            $modal = $(`#mm-ui-result_${action}-modal`),
            $result = $(`#mm-ui-result_${action}`);

        // The support link is a plain anchor, it has no endpoint to call.
        if (action === 'help') {
            return;
        }

        const inlineResult = action === 'version' || action === 'apikey';

        if (inlineResult) {
            // The version check is a one-shot; the key test stays clickable so a
            // corrected key can be retried without saving in between.
            if (action === 'version') {
                $(this).fadeOut(300).addClass('mm-ui-disabled');
            }
            $modal = $(`.mm-ui-result_${action}-wrapper`);
            $modal.loader('show');
        } else {
            $modal.modal('openModal').loader('show');
        }

        $result.hide();

        const options = action === 'apikey'
            ? {method: 'POST', body: apiKeyPayload()}
            : {};

        fetch($modal.attr('data-mm-ui-endpoind-url'), options)
            .then((res) => res.clone().json().catch(() => res.text()))
            .then((data) => {
                const func = successHandlers[action] ? action : 'logs';

                successHandlers[func](data, $result, action);
                $result.fadeIn();
                $modal.loader('hide');
            });
    });
});
