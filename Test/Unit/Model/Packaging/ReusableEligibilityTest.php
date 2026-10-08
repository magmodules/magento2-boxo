<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Test\Unit\Model\Packaging;

use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Boxo\Api\Log\RepositoryInterface as LogRepository;
use Magmodules\Boxo\Model\Config\Source\AllowMode;
use Magmodules\Boxo\Model\Packaging;
use Magmodules\Boxo\Model\Packaging\Attribute;
use Magmodules\Boxo\Model\Packaging\ReusableEligibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Whether a cart may ship in reusable BOXO packaging.
 *
 * The densest decision in the module and the one a merchant notices first: withhold the reusable
 * option wrongly and the feature looks broken, offer it wrongly and BOXO gets a parcel it cannot
 * take back. Two independent rules feed it — a quantity cap and a per-product allow list — and
 * both have edges that are easy to get backwards.
 */
#[CoversClass(ReusableEligibility::class)]
class ReusableEligibilityTest extends TestCase
{
    public function testNullQuoteIsAllowed(): void
    {
        $this->assertTrue($this->eligibility()->isAllowed(null));
        $this->assertNull($this->eligibility()->getBlockReason(null));
    }

    /**
     * max_qty = 0 is the shipped default, and it has to mean "no cap" rather than "nothing fits".
     */
    public function testMaxQtyOfZeroDisablesTheCap(): void
    {
        $quote = $this->quote([$this->item('sku-a', 99.0)]);

        $this->assertNull($this->eligibility(0)->getBlockReason($quote));
    }

    /**
     * What matters is how many physical items go into the box, so 5× one product fills it the same
     * as 5 different ones. Counting cart lines instead would let a single line of 99 through.
     */
    public function testQtyIsSummedAcrossQuantitiesNotLines(): void
    {
        $quote = $this->quote([$this->item('sku-a', 5.0)]);

        $this->assertSame(
            ['code' => 'cart_limit_exceeded', 'qty' => 5.0, 'max' => 4],
            $this->eligibility(4)->getBlockReason($quote)
        );
    }

    /**
     * The cap is inclusive: a cart of exactly max fits.
     */
    public function testACartOfExactlyTheMaximumIsAllowed(): void
    {
        $quote = $this->quote([
            $this->item('sku-a', 2.0),
            $this->item('sku-b', 2.0),
        ]);

        $this->assertNull($this->eligibility(4)->getBlockReason($quote));
    }

    /**
     * The packaging line is not one of the products being shipped — counting it would make the
     * cap one item tighter than configured, and only once an option had been chosen.
     */
    public function testThePackagingLineDoesNotCountTowardsTheCap(): void
    {
        $quote = $this->quote([
            $this->item('sku-a', 1.0),
            $this->item('sku-b', 1.0),
            $this->item(Packaging::SKU_REUSABLE, 1.0),
        ]);

        $this->assertNull($this->eligibility(2)->getBlockReason($quote));
    }

    /**
     * A configurable ships as one item but appears as two rows. Counting both doubles it.
     */
    public function testChildRowsAreCountedThroughTheirParent(): void
    {
        $quote = $this->quote([
            $this->item('parent-sku', 1.0),
            $this->item('child-sku', 1.0, parentItemId: '17'),
        ]);

        $this->assertNull($this->eligibility(1)->getBlockReason($quote));
    }

    public function testModeAllNeverConsultsTheAttributes(): void
    {
        $quote = $this->quote([$this->item('sku-a', 1.0, flags: [Attribute::EXCLUDE => true])]);

        $this->assertNull($this->eligibility(0, AllowMode::MODE_ALL)->getBlockReason($quote));
    }

    public function testExcludeModeBlocksAFlaggedProduct(): void
    {
        $quote = $this->quote([
            $this->item('sku-a', 1.0, flags: [Attribute::EXCLUDE => false]),
            $this->item('sku-b', 1.0, flags: [Attribute::EXCLUDE => true], productId: '42'),
        ]);

        $this->assertSame(
            ['code' => 'product_not_eligible', 'product_id' => '42', 'sku' => 'sku-b'],
            $this->eligibility(0, AllowMode::MODE_EXCLUDE)->getBlockReason($quote)
        );
    }

    public function testExcludeModeAllowsAnUnflaggedCart(): void
    {
        $quote = $this->quote([$this->item('sku-a', 1.0, flags: [Attribute::EXCLUDE => false])]);

        $this->assertNull($this->eligibility(0, AllowMode::MODE_EXCLUDE)->getBlockReason($quote));
    }

    /**
     * The inversion that is easy to ship backwards: in "only selected products" the *absence* of
     * the flag is what blocks. Reversed, a shop that flagged nothing would offer reusable on
     * everything — the exact opposite of what it configured.
     */
    public function testIncludeModeBlocksAnUnflaggedProduct(): void
    {
        $quote = $this->quote([
            $this->item('sku-a', 1.0, flags: [Attribute::INCLUDE => true]),
            $this->item('sku-b', 1.0, flags: [Attribute::INCLUDE => false], productId: '7'),
        ]);

        $this->assertSame(
            ['code' => 'product_not_eligible', 'product_id' => '7', 'sku' => 'sku-b'],
            $this->eligibility(0, AllowMode::MODE_INCLUDE)->getBlockReason($quote)
        );
    }

    public function testIncludeModeAllowsAFullyFlaggedCart(): void
    {
        $quote = $this->quote([$this->item('sku-a', 1.0, flags: [Attribute::INCLUDE => true])]);

        $this->assertNull($this->eligibility(0, AllowMode::MODE_INCLUDE)->getBlockReason($quote));
    }

    /**
     * The packaging product carries neither flag, so in include mode it would block the very
     * option it represents — the cart would qualify until the customer chose it.
     */
    public function testThePackagingLineIsNeverSubjectToTheAllowRules(): void
    {
        $quote = $this->quote([
            $this->item('sku-a', 1.0, flags: [Attribute::INCLUDE => true]),
            $this->item(Packaging::SKU_REUSABLE, 1.0, flags: [Attribute::INCLUDE => false]),
        ]);

        $this->assertNull($this->eligibility(0, AllowMode::MODE_INCLUDE)->getBlockReason($quote));
    }

    /**
     * Both rules fail at once. The cap is reported, because it is the one the customer can act on
     * by removing something.
     */
    public function testTheQuantityCapIsReportedBeforeProductIneligibility(): void
    {
        $quote = $this->quote([
            $this->item('sku-a', 5.0, flags: [Attribute::EXCLUDE => true]),
        ]);

        $reason = $this->eligibility(4, AllowMode::MODE_EXCLUDE)->getBlockReason($quote);

        $this->assertSame('cart_limit_exceeded', $reason['code'] ?? null);
    }

    public function testIsAllowedMirrorsTheBlockReason(): void
    {
        $blocked = $this->quote([$this->item('sku-a', 5.0)]);

        $this->assertFalse($this->eligibility(4)->isAllowed($blocked));
        $this->assertTrue($this->eligibility(4)->isAllowed($this->quote([$this->item('sku-a', 1.0)])));
    }

    private function eligibility(int $maxQty = 0, string $allowMode = AllowMode::MODE_ALL): ReusableEligibility
    {
        $config = $this->createMock(ConfigRepository::class);
        $config->method('getMaxReusableQty')->willReturn($maxQty);
        $config->method('getAllowMode')->willReturn($allowMode);

        return new ReusableEligibility($config, $this->createMock(LogRepository::class));
    }

    /**
     * @param Item[] $items
     */
    private function quote(array $items): Quote
    {
        $quote = $this->createMock(Quote::class);
        $quote->method('getAllItems')->willReturn($items);
        $quote->method('getStoreId')->willReturn(1);
        $quote->method('getId')->willReturn(99);

        return $quote;
    }

    /**
     * @param array<string, bool>|null $flags
     */
    private function item(
        string $sku,
        float $qty,
        ?string $parentItemId = null,
        ?array $flags = null,
        string $productId = '1'
    ): Item {
        // getParentItemId() and getProductId() are DataObject magic getters rather than declared
        // methods, so they cannot be stubbed — they are fed through the real setData() instead.
        $item = $this->createPartialMock(Item::class, ['getSku', 'getQty', 'getProduct']);
        $item->method('getSku')->willReturn($sku);
        $item->method('getQty')->willReturn($qty);
        $item->setData('parent_item_id', $parentItemId);
        $item->setData('product_id', $productId);

        if ($flags === null) {
            $item->method('getProduct')->willReturn(null);

            return $item;
        }

        $product = $this->createMock(Product::class);
        $product->method('getData')->willReturnCallback(
            static fn(string $key = '') => $flags[$key] ?? null
        );
        $item->method('getProduct')->willReturn($product);

        return $item;
    }
}
