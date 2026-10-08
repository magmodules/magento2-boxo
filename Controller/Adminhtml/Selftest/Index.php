<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Controller\Adminhtml\Selftest;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magmodules\Boxo\Api\Selftest\RepositoryInterface as SelftestRepository;

/**
 * AJAX controller returning the self-test results
 */
class Index extends Action
{

    public const ADMIN_RESOURCE = 'Magmodules_Boxo::config';

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;
    /**
     * @var SelftestRepository
     */
    private $selftestRepository;

    /**
     * @param Action\Context $context
     * @param JsonFactory $resultJsonFactory
     * @param SelftestRepository $selftestRepository
     */
    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        SelftestRepository $selftestRepository
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->selftestRepository = $selftestRepository;
        parent::__construct($context);
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->resultJsonFactory->create();

        return $resultJson->setData(['result' => $this->selftestRepository->test()]);
    }
}
