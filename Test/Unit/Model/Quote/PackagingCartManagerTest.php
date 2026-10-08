<?php
/**
 * Copyright © Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magmodules\Boxo\Test\Unit\Model\Quote;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magmodules\Boxo\Api\Config\RepositoryInterface as ConfigRepository;
use Magmodules\Boxo\Api\Log\RepositoryInterface as LogRepository;
use Magmodules\Boxo\Exception\PackagingUnavailableException;
use Magmodules\Boxo\Model\Packaging;
use Magmodules\Boxo\Model\Quote\PackagingCartManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Keeping the quote's BOXO line in step with what the customer picked.
 *
 * Two invariants carry the money: at most one BOXO line is ever in the cart, and the choice
 * survives a reload even when it is free and therefore leaves no line behind. Both have already
 * shaped the schema — see the comment on etc/db_schema.xml.
 */
#[CoversClass(PackagingCartManager::class)]
class PackagingCartManagerTest extends TestCase
{
    private ProductRepositoryInterface&MockObject $productRepository;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
    }

    /**
     * The choice is recorded on the quote regardless of price, because a free option produces no
     * line item to read it back from — without this the customer silently falls back to the
     * configured default on the next page load.
     */
    public function testTheSelectionIsRecordedOnTheQuoteEvenWhenItIsFree(): void
    {
        $quote = $this->quote();
        $this->productRepository->expects($this->never())->method('get');

        $this->manager(surcharge: 0.0)->applySelection($quote, Packaging::SELECTION_DISPOSABLE);

        $this->assertSame(
            Packaging::SELECTION_DISPOSABLE,
            $quote->getData(PackagingCartManager::SELECTION_FIELD)
        );
    }

    public function testADisposableSurchargeAddsALineItem(): void
    {
        $quote = $this->quote();
        $this->productRepository->expects($this->once())
            ->method('get')
            ->with(Packaging::SKU_DISPOSABLE)
            ->willReturn($this->createMock(Product::class));
        $quote->expects($this->once())->method('addProduct')->willReturn($this->createMock(Item::class));

        $this->manager(surcharge: 0.25)->applySelection($quote, Packaging::SELECTION_DISPOSABLE);
    }

    /**
     * The reusable deposit is set by BOXO, not by the merchant, so it is charged whatever the
     * disposable surcharge is configured to.
     */
    public function testTheReusableDepositIsAlwaysCharged(): void
    {
        $quote = $this->quote();
        $this->productRepository->expects($this->once())
            ->method('get')
            ->with(Packaging::SKU_REUSABLE)
            ->willReturn($this->createMock(Product::class));
        $quote->expects($this->once())->method('addProduct')->willReturn($this->createMock(Item::class));

        $this->manager(surcharge: 0.0)->applySelection($quote, Packaging::SELECTION_REUSABLE);
    }

    /**
     * Switching options must never leave both lines in the cart — the customer would be charged
     * for a deposit and a disposable bag at once.
     */
    public function testSwitchingOptionsDropsThePreviousLine(): void
    {
        $existing = $this->item(Packaging::SKU_DISPOSABLE);
        $existing->expects($this->once())->method('isDeleted')->with(true);

        $quote = $this->quote([$existing]);
        $this->productRepository->method('get')->willReturn($this->createMock(Product::class));
        $quote->expects($this->once())->method('addProduct')->willReturn($this->createMock(Item::class));

        $this->manager(surcharge: 0.25)->applySelection($quote, Packaging::SELECTION_REUSABLE);
    }

    public function testOrdinaryCartLinesAreLeftAlone(): void
    {
        $product = $this->item('sku-a');
        $product->expects($this->never())->method('isDeleted');

        $this->manager()->applySelection($this->quote([$product]), null);
    }

    public function testReapplyingTheSameSelectionAddsNothing(): void
    {
        $existing = $this->item(Packaging::SKU_REUSABLE);
        $existing->expects($this->never())->method('isDeleted');

        $quote = $this->quote([$existing]);
        $this->productRepository->expects($this->never())->method('get');
        $quote->expects($this->never())->method('addProduct');

        $this->manager()->applySelection($quote, Packaging::SELECTION_REUSABLE);
    }

    public function testAnEmptySelectionRemovesTheBoxoLine(): void
    {
        $existing = $this->item(Packaging::SKU_REUSABLE);
        $existing->expects($this->once())->method('isDeleted')->with(true);

        $quote = $this->quote([$existing]);
        $quote->expects($this->never())->method('addProduct');

        $this->manager()->applySelection($quote, null);

        $this->assertNull($quote->getData(PackagingCartManager::SELECTION_FIELD));
    }

    /**
     * Turning the surcharge off with a disposable line already in the cart has to remove it, or
     * the customer keeps paying a fee the merchant has since withdrawn.
     */
    public function testAnExistingLineIsRemovedWhenTheOptionBecomesFree(): void
    {
        $existing = $this->item(Packaging::SKU_DISPOSABLE);
        $existing->expects($this->once())->method('isDeleted')->with(true);

        $quote = $this->quote([$existing]);
        $quote->expects($this->never())->method('addProduct');

        $this->manager(surcharge: 0.0)->applySelection($quote, Packaging::SELECTION_DISPOSABLE);
    }

    /**
     * The three ways adding the product can fail. Each has to surface as the module's own
     * exception, so the checkout can say the option is unavailable rather than 500.
     */
    public function testAMissingPackagingProductIsReportedAsUnavailable(): void
    {
        $this->productRepository->method('get')->willThrowException(new NoSuchEntityException());

        $this->expectException(PackagingUnavailableException::class);
        $this->manager()->applySelection($this->quote(), Packaging::SELECTION_REUSABLE);
    }

    public function testAnUnsalablePackagingProductIsReportedAsUnavailable(): void
    {
        $quote = $this->quote();
        $this->productRepository->method('get')->willReturn($this->createMock(Product::class));
        $quote->method('addProduct')->willThrowException(new LocalizedException(__('Out of stock')));

        $this->expectException(PackagingUnavailableException::class);
        $this->manager()->applySelection($quote, Packaging::SELECTION_REUSABLE);
    }

    /**
     * Quote::addProduct() reports some failures by returning an error string instead of throwing.
     * Treating that as success leaves a checkout that claims to have added packaging it did not.
     */
    public function testAnErrorStringFromAddProductIsReportedAsUnavailable(): void
    {
        $quote = $this->quote();
        $this->productRepository->method('get')->willReturn($this->createMock(Product::class));
        $quote->method('addProduct')->willReturn('This product is out of stock.');

        $this->expectException(PackagingUnavailableException::class);
        $this->manager()->applySelection($quote, Packaging::SELECTION_REUSABLE);
    }

    public function testTheRecordedSelectionIsReadBackFromTheQuote(): void
    {
        $quote = $this->quote();
        $quote->setData(PackagingCartManager::SELECTION_FIELD, Packaging::SELECTION_DISPOSABLE);

        $this->assertSame(Packaging::SELECTION_DISPOSABLE, $this->manager()->getCurrentSelection($quote));
    }

    /**
     * Carts created before the choice was stored on the quote still carry their line item, and
     * must not lose the selection on the upgrade.
     */
    public function testAPreUpgradeCartFallsBackToItsLineItem(): void
    {
        $quote = $this->quote([$this->item('sku-a'), $this->item(Packaging::SKU_REUSABLE)]);

        $this->assertSame(Packaging::SELECTION_REUSABLE, $this->manager()->getCurrentSelection($quote));
    }

    public function testAnUnrecognisedStoredValueFallsBackToTheLineItem(): void
    {
        $quote = $this->quote([$this->item(Packaging::SKU_DISPOSABLE)]);
        $quote->setData(PackagingCartManager::SELECTION_FIELD, 'nonsense');

        $this->assertSame(Packaging::SELECTION_DISPOSABLE, $this->manager()->getCurrentSelection($quote));
    }

    public function testNoSelectionAndNoLineItemMeansNoChoiceWasMade(): void
    {
        $this->assertNull($this->manager()->getCurrentSelection($this->quote([$this->item('sku-a')])));
    }

    private function manager(float $surcharge = 0.25): PackagingCartManager
    {
        $config = $this->createMock(ConfigRepository::class);
        $config->method('getDisposableSurcharge')->willReturn($surcharge);

        return new PackagingCartManager(
            $this->productRepository,
            $this->createMock(LogRepository::class),
            $config
        );
    }

    /**
     * getData()/setData() are left real so the recorded selection can be read back the way the
     * rest of Magento reads it.
     *
     * @param Item[] $items
     * @return Quote&MockObject
     */
    private function quote(array $items = []): Quote
    {
        $quote = $this->createPartialMock(Quote::class, ['getAllItems', 'getStoreId', 'addProduct']);
        $quote->method('getAllItems')->willReturn($items);
        $quote->method('getStoreId')->willReturn(1);

        return $quote;
    }

    /**
     * @return Item&MockObject
     */
    private function item(string $sku): Item
    {
        $item = $this->createPartialMock(Item::class, ['getSku', 'isDeleted']);
        $item->method('getSku')->willReturn($sku);

        return $item;
    }
}
