<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavAttribute;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magmodules\Boxo\Model\Packaging\Attribute;

/**
 * Creates the product flags that drive the reusable-packaging rules.
 *
 * Kept on the product rather than in a list of SKUs in the configuration: a
 * shop can then set them straight from the product grid — filter, select all,
 * Update Attributes — or through a CSV import, and newly added products carry
 * their own answer instead of needing the configuration revisited.
 */
class CreatePackagingAttributes implements DataPatchInterface
{
    /**
     * Definitions shared by both flags.
     */
    private const DEFINITION = [
        'type' => 'int',
        'input' => 'boolean',
        'source' => \Magento\Eav\Model\Entity\Attribute\Source\Boolean::class,
        'backend' => '',
        'required' => false,
        'user_defined' => false,
        'default' => 0,
        // Whether a product fits a reusable container is a property of the
        // product, not something that varies per site.
        'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
        'visible' => true,
        'searchable' => false,
        'filterable' => false,
        'comparable' => false,
        'visible_on_front' => false,
        'used_in_product_listing' => false,
        'unique' => false,
        // Surfaces the flag as a column and a filter in the product grid, which
        // is what makes selecting products in bulk workable.
        'is_used_in_grid' => true,
        'is_visible_in_grid' => true,
        'is_filterable_in_grid' => true,
        'group' => 'BOXO Reusable Packaging',
    ];

    private const LABELS = [
        Attribute::EXCLUDE => 'Exclude from BOXO reusable packaging',
        Attribute::INCLUDE => 'Allow in BOXO reusable packaging',
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $sortOrder = 10;
        foreach (self::LABELS as $code => $label) {
            if ($eavSetup->getAttributeId(Product::ENTITY, $code)) {
                continue;
            }

            $eavSetup->addAttribute(
                Product::ENTITY,
                $code,
                self::DEFINITION + [
                    'label' => $label,
                    'sort_order' => $sortOrder,
                ]
            );

            $sortOrder += 10;
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
