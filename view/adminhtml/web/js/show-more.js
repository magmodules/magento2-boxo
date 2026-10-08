/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
require([
    'jquery',
    'mage/translate',
    '!domReady'
], function ($, $t) {
    'use strict';

    let comment = $('.mm-ui-heading-comment'),
        heading = comment.parent().find('.mm-ui-heading-block'),
        showMoreLessBtnHtml = `
            <div class="mm-ui-show-more-actions hidden">
                <a href="javascript:void(0)" class="mm-ui-show-btn-more">
                    ${$t('More')}
                </a>
            </div>`;

    if (!comment.length) {
        return;
    }

    heading.append(showMoreLessBtnHtml);

    $(document).on('click', '.mm-ui-show-more-actions a', (e) => {
        let button = $(e.target),
            target = button.closest('.value').find('.mm-ui-heading-comment');

        if (target.hasClass('show')) {
            target.removeClass('show');
            button.text($t('More'));
        } else {
            target.addClass('show');
            button.text($t('Less'));
        }
    });

    /**
     * Only offer "More" when the comment is actually clamped.
     */
    function isShowMore() {
        Array.from(comment).forEach((item) => {
            const BTN = item.closest('td').querySelector('.mm-ui-show-more-actions');

            if (!BTN) {
                return;
            }

            item.scrollHeight <= 55 ? BTN.classList.add('hidden') : BTN.classList.remove('hidden');
        });
    }

    // Re-measure when a collapsed group is opened, the height is 0 while hidden.
    const form = document.querySelector('form[action*="magmodules_boxo"]');

    if (form) {
        new MutationObserver((mutations) => {
            for (let i = 0; i < mutations.length; i++) {
                if (mutations[i].target.classList.contains('section-config')) {
                    isShowMore();
                }
            }
        }).observe(form, {
            subtree: true,
            attributes: true,
            attributeFilter: ['class']
        });
    }

    $(document).ready(isShowMore);
    window.addEventListener('resize', isShowMore);
});
