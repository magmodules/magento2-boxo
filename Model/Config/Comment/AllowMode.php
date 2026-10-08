<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Model\Config\Comment;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Config\Model\Config\CommentInterface;
use Magmodules\Boxo\Model\Config\Source\AllowMode as Mode;
use Magmodules\Boxo\Model\Packaging\Attribute;

/**
 * Reports what the selected mode currently does, in numbers.
 *
 * The products are flagged in the catalog, far away from this dropdown, so
 * without a count it is easy to mark products and not notice the mode is still
 * "All products" — or to switch to "Only selected products" having flagged
 * none, which withdraws BOXO from the whole shop.
 */
class AllowMode implements CommentInterface
{
    public function __construct(private readonly CollectionFactory $productCollectionFactory)
    {
    }

    /**
     * @param string $elementValue
     * @return string
     */
    public function getCommentText($elementValue): string
    {
        $mode = $elementValue !== '' ? $elementValue : Mode::MODE_ALL;

        if ($mode === Mode::MODE_ALL) {
            return (string)__(
                'Every product qualifies. Flags set on products are ignored while this is selected.'
            );
        }

        $isExclude = $mode === Mode::MODE_EXCLUDE;
        $flag = $isExclude ? Attribute::EXCLUDE : Attribute::INCLUDE;
        $count = $this->countFlagged($flag);

        if ($count === 0) {
            return $isExclude
                ? (string)__(
                    'No products are flagged yet, so nothing is excluded. '
                    . 'Mark them in Catalog &gt; Products with "Exclude from BOXO reusable packaging".'
                )
                : (string)__(
                    'No products are flagged yet, so reusable packaging is offered to nobody. '
                    . 'Mark them in Catalog &gt; Products with "Allow in BOXO reusable packaging".'
                );
        }

        return $isExclude
            ? (string)__('%1 product(s) are currently excluded from reusable packaging.', $count)
            : (string)__('%1 product(s) currently qualify for reusable packaging.', $count);
    }

    /**
     * @param string $attributeCode
     * @return int
     */
    private function countFlagged(string $attributeCode): int
    {
        try {
            // Left join: a store-scoped value has no default-scope row to
            // inner join against, and the count must hold either way.
            return (int)$this->productCollectionFactory->create()
                ->addAttributeToFilter($attributeCode, 1, 'left')
                ->getSize();
        } catch (\Exception $e) {
            // A count is a convenience; never break the configuration screen.
            return 0;
        }
    }
}
