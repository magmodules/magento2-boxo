<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Block\Adminhtml\System\Config\Button;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Block\Widget\Button;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;

/**
 * Version check button class
 */
class VersionCheck extends Field
{

    /**
     * @var string
     */
    protected $_template = 'Magmodules_Boxo::system/config/button/version.phtml';

    public function __construct(
        Context $context,
        private readonly ConfigRepository $configRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @param AbstractElement $element
     *
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * @param AbstractElement $element
     *
     * @return string
     */
    public function _getElementHtml(AbstractElement $element): string
    {
        return $this->_toHtml();
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->configRepository->getExtensionVersion();
    }

    /**
     * @return string
     */
    public function getVersionCheckUrl(): string
    {
        return $this->getUrl('boxo/versionCheck/index');
    }

    /**
     * @return string
     */
    public function getChangeLogUrl(): string
    {
        return $this->getUrl('boxo/versionCheck/changelog');
    }

    /**
     * @return string
     */
    public function getButtonHtml(): string
    {
        try {
            return $this->getLayout()
                ->createBlock(Button::class)
                ->setData([
                    'id' => 'mm-ui-button_version',
                    'label' => __('Check for latest versions')
                ])->toHtml();
        } catch (Exception $e) {
            return '';
        }
    }
}
