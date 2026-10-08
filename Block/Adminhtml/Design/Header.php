<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Block\Adminhtml\Design;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;

/**
 * Branding header rendered on top of the section
 */
class Header extends Field
{

    /**
     * @var string
     */
    protected $_template = 'Magmodules_Boxo::system/config/header.phtml';

    public function __construct(
        Context $context,
        private readonly ConfigRepository $configRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritDoc
     */
    public function render(AbstractElement $element): string
    {
        $element->addClass('boxo');

        return $this->toHtml();
    }

    /**
     * Support link for extension
     */
    public function getSupportLink(): string
    {
        return $this->configRepository->getSupportLink();
    }

    /**
     * Installed extension version
     */
    public function getVersion(): string
    {
        return $this->configRepository->getExtensionVersion();
    }
}
