<?php

namespace Ordergroove\Subscription\Helper\RecurringOrderHelper;

use Magento\Bundle\Model\OptionRepository;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Ordergroove\Subscription\Exception\RecurringOrderException;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Ordergroove\Subscription\Logger\RecurringOrder\Error\Logger as ErrorLogger;
use Magento\Store\Model\ScopeInterface;

/**
 * Class BundleRecurringOrderHelper
 * @package Ordergroove\Subscription\Helper\RecurringOrderHelper
 */
class BundleRecurringOrderHelper
{
    /**
     * @var OptionRepository
     */
    protected $optionRepository;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var CreateRecurringOrderHelper
     */
    protected $createRecurringOrderHelper;

    /**
     * @var ScopeConfigInterface;
     */
    protected $scopeConfig;

    /**
     * @var ErrorLogger
     */
    protected $errorLogger;

    /**
     * BundleRecurringOrderHelper constructor.
     * @param OptionRepository $optionRepository
     * @param ProductFactory $productFactory
     * @param CreateRecurringOrderHelper $createRecurringOrderHelper
     * @param ErrorLogger $errorLogger
     */
    public function __construct(
        OptionRepository $optionRepository,
        ProductFactory $productFactory,
        CreateRecurringOrderHelper $createRecurringOrderHelper,
        ScopeConfigInterface $scopeConfig,
        ErrorLogger $errorLogger
    )
    {
        $this->optionRepository = $optionRepository;
        $this->productFactory = $productFactory;
        $this->createRecurringOrderHelper = $createRecurringOrderHelper;
        $this->scopeConfig = $scopeConfig;
        $this->errorLogger = $errorLogger;
    }

    /**
     * @param array $data
     * @return array
     */
    public function bundleProductsInRecurringOrder(array $data)
    {
        $bundleProductIds = [];
        foreach ($data['components'] as $component) {
            foreach ($component as $productItem) {
                $bundleProductIds[] = $productItem['product_id'];
            }
        }
        return $bundleProductIds;
    }

    /**
     * @param $productId
     * @param array $bundleProductIds
     * @param $websiteId
     * @return array|false
     * @throws NoSuchEntityException
     * @throws RecurringOrderException
     */
    public function getBundleOptions($productId, array $bundleProductIds, $websiteId)
    {
        $product = $this->productFactory->create()->load($productId);
        $bundleProductIdSet = [];
        if ($product->getTypeId() !== 'bundle') {
            return false;
        }

        // only check here if not using msi
        if (!$this->scopeConfig->getValue('ordergroove_subscription/general/enable_msi', ScopeInterface::SCOPE_WEBSITES, $websiteId)) {
            foreach ($bundleProductIds as $bundleProductId) {
                $this->createRecurringOrderHelper->getStockStatus($bundleProductId);
            }
        }

        $selectionCollection = $product->getTypeInstance()
            ->getSelectionsCollection(
                $product->getTypeInstance()->getOptionsIds($product),
                $product
            );
        foreach ($selectionCollection as $selection) {
            foreach ($bundleProductIds as $bundleProductId) {
                if ($selection->getEntityId() === $bundleProductId) {
                    $bundleProductIdSet[$selection->getOptionId()][] = $selection->getSelectionId();
                }
            }
        }
        $bundleProductIdReturnSet = [];
        foreach ($bundleProductIdSet as $optionId => $optionIdSelections) {
            $bundleProductIdReturnSet[$optionId] = array_unique($optionIdSelections);
        }

        $finalBundleProductIdReturnSet = [];
        foreach ($bundleProductIdReturnSet as $key => $value) {
            foreach ($value as $optionItem) {
                $finalBundleProductIdReturnSet[$key][] = $optionItem;
            }
        }

        return $finalBundleProductIdReturnSet;
    }

    /**
     * @param $productId
     * @param array $countOfEachBundleProduct
     * @param $websiteId
     * @return array|false
     * @throws NoSuchEntityException
     * @throws RecurringOrderException
     */
    public function getBundleOptionsQtyFromOG($productId, array $countOfEachBundleProduct, $websiteId)
    {
        $product = $this->productFactory->create()->load($productId);
        $bundleProductQtySet = [];
        if (!($product->getTypeId() === 'bundle')) {
            return false;
        }
        $selectionCollection = $product->getTypeInstance()
            ->getSelectionsCollection(
                $product->getTypeInstance()->getOptionsIds($product),
                $product
            );

        foreach ($selectionCollection as $selection) {
            foreach ($countOfEachBundleProduct as $key => $value) {
                // $key = Product ID
                // $value = Requested quantity
                // only check stock here if not using msi
                if (!$this->scopeConfig->getValue('ordergroove_subscription/general/enable_msi', ScopeInterface::SCOPE_WEBSITES, $websiteId)) {
                    $this->createRecurringOrderHelper->getStockQty($key, $value);
                }
                if ((int)$selection->getEntityId() === $key) {
                    $bundleProductQtySet[$selection->getOptionId()][] = $value;
                }
            }
        }

        $finalBundleProductQtyReturnSet = [];
        foreach ($bundleProductQtySet as $key => $value) {
            if (count($value) > 1) {
                $finalBundleProductQtyReturnSet[$key][] = $value;
            } else {
                $finalBundleProductQtyReturnSet[$key] = $value[0];
            }
        }

        return $finalBundleProductQtyReturnSet;
    }

    /**
     * @param array $item
     * @param array $skuQtyList
     * @return array
     * @throws RecurringOrderException
     */
    public function getBundleSkuQty($item, $skuQtyList)
    {
        $bundleProductSkus = [];
        foreach ($item['components'] as $component) {
            foreach ($component as $productItem) {
                $bundleProductSkus[] = $productItem['sku'];
            }
        }
        $countOfEachBundleProductBySku = array_count_values($bundleProductSkus);

        $product = $this->productFactory->create()->load($item['product_id']);
        if (!($product->getTypeId() === 'bundle')) {
            $this->errorLogger->error("Incorrect product, product with ID $productId is not a bundle product");
            throw new RecurringOrderException(__("Incorrect product, product with ID $productId is not a bundle product"), null, "999");
        }

        foreach ($countOfEachBundleProductBySku as $key => $value) {
            // $key = Product Sku
            // $value = Requested quantity (may have to multiply this by $item['qty'])
            $skuQtyList[$key] = $value;
        }
        return $skuQtyList;
    }

    /**
     * @param array $items
     * @return array
     */
    public function getSkuQtyList($items)
    {
        $skuQtyList = [];
        foreach ($items as $item) {
            if (isset($item['components'])) {
                $skuQtyList = $this->getBundleSkuQty($item, $skuQtyList);
            } else {
                $skuQtyList[$item['sku']] = $item['qty'];
            }
        }
        return $skuQtyList;
    }
}
