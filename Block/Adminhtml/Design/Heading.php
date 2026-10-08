<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Block\Adminhtml\Design;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Sub-heading with optional comment, used to split groups into blocks
 */
class Heading extends Field
{

    /**
     * @inheritDoc
     */
    public function render(AbstractElement $element): string
    {
        $html = '<tr id="row_' . $element->getHtmlId() . '">';
        $html .= '  <td class="label"></td>';
        $html .= '  <td class="value">';
        $html .= '     <div class="mm-ui-heading-block">' . $element->getData('label') . '</div>';
        $html .= '     <div class="mm-ui-heading-comment">' . $element->getData('comment') . '</div>';
        $html .= '  </td>';
        $html .= '  <td></td>';
        $html .= '</tr>';

        return $html;
    }
}
