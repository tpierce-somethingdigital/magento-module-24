<?php
declare(strict_types=1);

namespace Ordergroove\Subscription\Model\RecurringOrder;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Cart;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Json\Helper\Data;
use Magento\Framework\Webapi\Exception;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\QuoteManagement;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order;
use Magento\Store\Model\Store;
use Ordergroove\Subscription\Helper\RecurringOrderHelper\BraintreeSearchHelper;
use Ordergroove\Subscription\Helper\RecurringOrderHelper\BundleRecurringOrderHelper;
use Ordergroove\Subscription\Helper\RecurringOrderHelper\CreateRecurringOrderHelper;
use Ordergroove\Subscription\Logger\RecurringOrder\Error\Logger as ErrorLogger;
use Ordergroove\Subscription\Logger\RecurringOrder\Info\Logger as InfoLogger;
use Magento\SalesRule\Model\DeltaPriceRound;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\InventorySales\Model\ResourceModel\GetAssignedStockIdForWebsite;
use Magento\Inventory\Model\Source\Command\GetSourcesAssignedToStockOrderedByPriority;
use Magento\InventorySalesApi\Api\IsProductSalableForRequestedQtyInterface;
use Ordergroove\Subscription\Exception\RecurringOrderException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;

/**
 * Class CreateRecurringOrder
 * @package Ordergroove\Subscription\Model\RecurringOrder
 */
class CreateRecurringOrder
{
    /**
     * @var Product
     */
    protected $product;

    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var QuoteManagement
     */
    protected $quoteManagement;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var Store
     */
    protected $store;

    /**
     * @var ErrorLogger
     */
    protected $errorLogger;

    /**
     * @var InfoLogger
     */
    protected $infoLogger;

    /**
     * @var CreateRecurringOrderHelper
     */
    protected $createRecurringOrderHelper;

    /**
     * @var QuoteRepository
     */
    protected $quoteRepository;

    /**
     * @var BundleRecurringOrderHelper
     */
    protected $bundleRecurringOrderHelper;

    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var BraintreeSearchHelper
     */
    protected $braintreeSearchHelper;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var DeltaPriceRound
     */
    protected $deltaPriceRound;

    /**
     * @var PriceCurrencyInterface
     */
    protected $priceCurrency;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepo;

    /**
     * @var GetAssignedStockIdForWebsite
     */
    protected $getAssignedStock;

    /**
     * @var GetSourcesAssignedToStockOrderedByPriority
     */
    protected $sourcesAssignedToStock;

    /**
     * @var IsProductSalableForRequestedQtyInterface
     */
    protected $isProductSalableForRequestedQtyInterface;

    /**
     * @var WebsiteRepositoryInterface
     */
    protected $websiteRepo;

    /**
     * CreateRecurringOrder constructor.
     * @param Product $product
     * @param QuoteFactory $quoteFactory
     * @param QuoteManagement $quoteManagement
     * @param CustomerRepositoryInterface $customerRepository
     * @param Store $store
     * @param ErrorLogger $errorLogger
     * @param InfoLogger $infoLogger
     * @param CreateRecurringOrderHelper $createRecurringOrderHelper
     * @param QuoteRepository $quoteRepository
     * @param BundleRecurringOrderHelper $bundleRecurringOrderHelper
     * @param Cart $cart
     * @param BraintreeSearchHelper $braintreeSearchHelper
     * @param ProductRepositoryInterface $productRepository
     * @param DeltaPriceRound $deltaPriceRound
     * @param PriceCurrencyInterface $priceCurrency
     * @param ScopeConfigInterface $scopeConfig
     * @param OrderRepositoryInterface $orderRepo
     * @param GetAssignedStockIdForWebsite $getAssignedStock
     * @param GetSourcesAssignedToStockOrderedByPriority $sourcesAssignedToStock
     * @param IsProductSalableForRequestedQtyInterface $isProductSalableForRequestedQtyInterface
     * @param WebsiteRepositoryInterface $websiteRepo
     */
    public function __construct(
        Product $product,
        QuoteFactory $quoteFactory,
        QuoteManagement $quoteManagement,
        CustomerRepositoryInterface $customerRepository,
        Store $store,
        ErrorLogger $errorLogger,
        InfoLogger $infoLogger,
        CreateRecurringOrderHelper $createRecurringOrderHelper,
        QuoteRepository $quoteRepository,
        BundleRecurringOrderHelper $bundleRecurringOrderHelper,
        Cart $cart,
        BraintreeSearchHelper $braintreeSearchHelper,
        ProductRepositoryInterface $productRepository,
        DeltaPriceRound $deltaPriceRound,
        PriceCurrencyInterface $priceCurrency,
        ScopeConfigInterface $scopeConfig,
        OrderRepositoryInterface $orderRepo,
        GetAssignedStockIdForWebsite $getAssignedStock,
        GetSourcesAssignedToStockOrderedByPriority $sourcesAssignedToStock,
        IsProductSalableForRequestedQtyInterface $isProductSalableForRequestedQtyInterface,
        WebsiteRepositoryInterface $websiteRepo
    ) {
        $this->product = $product;
        $this->quoteFactory = $quoteFactory;
        $this->quoteManagement = $quoteManagement;
        $this->customerRepository = $customerRepository;
        $this->store = $store;
        $this->errorLogger = $errorLogger;
        $this->infoLogger = $infoLogger;
        $this->createRecurringOrderHelper = $createRecurringOrderHelper;
        $this->quoteRepository = $quoteRepository;
        $this->bundleRecurringOrderHelper = $bundleRecurringOrderHelper;
        $this->cart = $cart;
        $this->braintreeSearchHelper = $braintreeSearchHelper;
        $this->productRepository = $productRepository;
        $this->deltaPriceRound = $deltaPriceRound;
        $this->priceCurrency = $priceCurrency;
        $this->scopeConfig = $scopeConfig;
        $this->orderRepo = $orderRepo;
        $this->getAssignedStock = $getAssignedStock;
        $this->sourcesAssignedToStock = $sourcesAssignedToStock;
        $this->isProductSalableForRequestedQtyInterface = $isProductSalableForRequestedQtyInterface;
        $this->websiteRepo = $websiteRepo;
    }

    /**
     * @param array $data
     * @return array
     * @throws Exception
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws CommandException
     * @throws \Exception
     */
    public function placeRecurringOrder(array $data)
    {
        try {
            // check if product exists
            $orderOgId = $data['head']['orderOgId'];
            $orderTokenId = $data['head']['orderTokenId'];
            $parseTokenData = $this->createRecurringOrderHelper->parseTokenData($orderTokenId);
            $customerEmail = $data['customer']['customerEmail'];
            $customerInfo = $this->createRecurringOrderHelper->checkCustomerData($customerEmail, $parseTokenData['website_id']);

            $ccData = [
                'orderCcType' => $data['head']['orderCcType'],
                'expirationDate' => $data['head']['orderCcExpire'],
                'orderTokenId' => $parseTokenData['token'],
                'customerEmail' => $data['customer']['customerEmail'],
                'websiteId' => $parseTokenData['website_id']
            ];

            $validateTokenData = $this->createRecurringOrderHelper->validateToken($parseTokenData, $customerInfo['customerId']);
            
            // Credit card validity checks are only currently required for non-PayPal transactions
            if($validateTokenData['method'] == 'braintree') {
                $billingAddress = $this->braintreeSearchHelper->checkCreditCardValidity($ccData);
            }

            $customer = $this->customerRepository->getById($customerInfo['customerId']);

            // Build order data
            $orderData = [
                'email' => $data['customer']['customerEmail'],
                'shipping_address' => [
                    'firstname' => $data['customer']['customerFirstName'],
                    'lastname' => $data['customer']['customerLastName'],
                    'street' => $data['customer']['customerShippingAddress'],
                    'city' => $data['customer']['customerShippingCity'],
                    'country_id' => $data['customer']['customerShippingCountry'],
                    'region' => $data['customer']['customerShippingState'],
                    'postcode' => $data['customer']['customerShippingZip'],
                    'telephone' => $data['customer']['customerShippingPhone']
                ],
                'shipping' => $data['head']['orderShipping'],
                'orderSubtotalDiscount' => $data['head']['orderSubtotalDiscount']
            ];
            
            // Non-PayPal vaulted credit cards require a billing address
            if($validateTokenData['method'] == 'braintree') {
                $orderData['billing_address'] = [
                    'firstname' => $billingAddress['firstName'],
                    'lastname' => $billingAddress['lastName'],
                    'street' => $billingAddress['streetAddress'],
                    'city' => $billingAddress['locality'],
                    'country_id' => $billingAddress['countryCodeAlpha2'],
                    'region' => $data['customer']['customerShippingState'],
                    'postcode' => $billingAddress['postalCode'],
                    'telephone' => $data['customer']['customerShippingPhone']
                ];
            }

            $quote = $this->quoteFactory->create();
            $quote->assignCustomer($customer);


            if (!isset($data['items']['item'][0])) {
                $data['items'][0] = $data['items']['item'];
                unset($data['items']['item']);
                $data['items']['item'][0] = $data['items'][0];
                unset($data['items'][0]);
            }

            $items = $data['items']['item'];
            $websiteId = null;
            $magentoOrder = null;
            foreach ($items as $item) {
                $magentoOrder = $this->orderRepo->get($item['subscription']['originalOrderId']);
                $websiteId = $magentoOrder->getStore()->getWebsiteId();
                break;
            }
            $quote->setStoreId($magentoOrder->getStoreId());
            $websiteCode = $this->websiteRepo->getById($websiteId)->getCode();
            if (!$this->scopeConfig->getValue('ordergroove_subscription/general/enable_split_shipments', ScopeInterface::SCOPE_WEBSITES, $websiteId) && $this->scopeConfig->getValue('ordergroove_subscription/general/enable_msi', ScopeInterface::SCOPE_WEBSITES, $websiteId)) {
                $stockId = $this->getAssignedStock->execute($websiteCode);
                $sourceCodesArray = $this->getSourceCodesAssignedToStock($stockId);
                $skuQtyList = $this->bundleRecurringOrderHelper->getSkuQtyList($items);
                $this->createRecurringOrderHelper->noSplitOrdersStockCheck($sourceCodesArray, $skuQtyList);

                foreach ($items as $item) {
                    $productId = $item['product_id'];
                    if (isset($item['components'])) {
                        $paramsObject = $this->addBundleProductToCart($productId, $item, $stockId, $websiteId);
                    } else {
                        $paramsObject = [
                            'qty' => $item['qty'],
                            'custom_price' => $item['finalPrice'] / intval($item['qty'])
                        ];
                    }
                    $product = $this->productRepository->getById($productId, false, null, true);
                    $response = $quote->addProduct($product, new DataObject($paramsObject));

                    if (is_string($response)) {
                        throw new RecurringOrderException(__("Unexpected response when trying to add product to the cart: ".$response), null, "020");
                    }
                }
            } else {
                $stockId = null;
                if ($this->scopeConfig->getValue('ordergroove_subscription/general/enable_msi', ScopeInterface::SCOPE_WEBSITES, $websiteId)) {
                    $stockId = $this->getAssignedStock->execute($websiteCode);
                }
                foreach ($items as $item) {
                    $productId = $item['product_id'];
                    if (isset($item['components'])) {
                        $paramsObject = $this->addBundleProductToCart($productId, $item, $stockId, $websiteId);
                    } else {
                        $paramsObject = $this->addSimpleAndConfigurableToCart($productId, $item, $stockId, $websiteId);
                    }
                    $product = $this->productRepository->getById($productId, false, null, true);
                    $response = $quote->addProduct($product, new DataObject($paramsObject));

                    if (is_string($response)) {
                        throw new RecurringOrderException(__("Unexpected response when trying to add product to the cart: ".$response), null, "020");
                    }
                }
            }

            $this->cart->save();
            
            // Right now PayPal orders don't require a billing address so it might not be set
            if(isset($orderData->billing_address)) {
                $quote->getBillingAddress()->addData($orderData['billing_address']);
            }
            
            // Set Addresses to quote
            $quote->getShippingAddress()->addData($orderData['shipping_address']);

            // Collect shipping rates, set Shipping & Payment Method
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->setIsOrdergrooveShipping(true);
            $shippingAddress->setOrdergrooveShippingAmount(floatval($orderData['shipping']));

            // Add Ordergroove custom discount amount
            $shippingAddress->setIsOrdergrooveDiscount(true);
            $shippingAddress->setOrdergrooveCustomOrderDiscount(floatval($orderData['orderSubtotalDiscount']));

            $shippingAddress->setCollectShippingRates(true)
                ->collectShippingRates()
                ->setShippingMethod('flatrate_flatrate');
            
            $quote->setPaymentMethod($validateTokenData['method']);
            $quote->setInventoryProcessed(false);
            $quote->setIsMultiShipping(0);
            $this->quoteRepository->save($quote);

            // Distribute orderSubtotalDiscount amongst line items
            $orderSubtotalDiscount = $orderData['orderSubtotalDiscount'];
            $quoteItems = $quote->getAllItems();
            $countOfItems = count($quoteItems);
            foreach ($quoteItems as $quoteItem) {
                if ($countOfItems > 1) {
                    $ratio = $quoteItem->getPrice() / $quote->getBaseSubtotal();
                    $quoteItemDiscount = $orderData['orderSubtotalDiscount'] * $ratio;
                    $quoteItemDiscount = $this->priceCurrency->convert($quoteItemDiscount);
                    $quoteItemFormattedDiscount = $this->deltaPriceRound->round($quoteItemDiscount, 'regular');
                    $quoteItem->setDiscountAmount(($quoteItem->getDiscountAmount() + $quoteItemFormattedDiscount) * $quoteItem->getQty());
                    $quoteItem->setBaseDiscountAmount(($quoteItem->getBaseDiscountAmount() + $quoteItemFormattedDiscount) * $quoteItem->getQty())->save();
                } else {
                    $quoteItem->setDiscountAmount(($quoteItem->getDiscountAmount() + $orderSubtotalDiscount) * $quoteItem->getQty());
                    $quoteItem->setBaseDiscountAmount(($quoteItem->getBaseDiscountAmount() + $orderSubtotalDiscount) * $quoteItem->getQty())->save();
                }
            }

            $extraPaymentData = $this->createRecurringOrderHelper->createPaymentMethodNonce($validateTokenData, $customerInfo['customerId']);
            $quote->getPayment()->importData($extraPaymentData);
            $quote->setTotalsCollectedFlag(false);
            $quote->collectTotals();
            $this->quoteRepository->save($quote);
            // Create Order From Quote
            $order = $this->quoteManagement->submit($quote);
            return $this->createRecurringOrderHelper->afterPlaceOrder($order, $data);
        } catch (\Exception $e) {
            $this->errorLogger->error("Error on OG order " . $orderOgId . " " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param $productId
     * @param $item
     * @param $stockId
     * @param $websiteId
     * @return array
     */
    public function addBundleProductToCart($productId, $item, $stockId, $websiteId)
    {
        $bundleProductIds = $this->bundleRecurringOrderHelper->bundleProductsInRecurringOrder($item);
        $countOfEachBundleProduct = array_count_values($bundleProductIds);
        if ($this->scopeConfig->getValue('ordergroove_subscription/general/enable_msi', ScopeInterface::SCOPE_WEBSITES, $websiteId) && $this->scopeConfig->getValue('ordergroove_subscription/general/enable_split_shipments', ScopeInterface::SCOPE_WEBSITES, $websiteId)) {
            foreach ($countOfEachBundleProduct as $key => $value) {
                if (!$this->isProductSalableForRequestedQtyInterface->execute($this->productRepository->getById($key)->getSku(), $stockId, floatval($value))) {
                    $this->errorLogger->error("Requested product ID $key is currently not available");
                    throw new RecurringOrderException(__("Requested product ID $key is currently not available"), null, "999");
                }
            }
        }
        $getBundleOptions = $this->bundleRecurringOrderHelper->getBundleOptions($productId, $bundleProductIds, $websiteId);
        $getBundleOptionsQty = $this->bundleRecurringOrderHelper->getBundleOptionsQtyFromOG($productId, $countOfEachBundleProduct, $websiteId);

        return [
            'bundle_option' => $getBundleOptions,
            'bundle_option_qty' => $getBundleOptionsQty,
            'qty' => intval($item['qty']),
            'custom_price' => $item['finalPrice'] / intval($item['qty'])
        ];
    }

    /**
     * @param $productId
     * @param $item
     * @param $stockId
     * @param $websiteId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function addSimpleAndConfigurableToCart($productId, $item, $stockId, $websiteId)
    {
        $quantity = $item['qty'];
        if ($this->scopeConfig->getValue('ordergroove_subscription/general/enable_msi', ScopeInterface::SCOPE_WEBSITES, $websiteId)) {
            if (!$this->isProductSalableForRequestedQtyInterface->execute($item['sku'], $stockId, floatval($quantity))) {
                $this->errorLogger->error("Requested product ID $productId is currently not available");
                throw new RecurringOrderException(__("Requested product ID $productId is currently not available"), null, "999");
            }
        } else {
            $this->createRecurringOrderHelper->getStockStatus($productId);
            $this->createRecurringOrderHelper->getStockQty($productId, $quantity);
        }
        return [
            'qty' => $item['qty'],
            'custom_price' => $item['finalPrice'] / intval($item['qty'])
        ];
    }

    /**
     * @param $stockId
     * @return array
     */
    public function getSourceCodesAssignedToStock($stockId)
    {
        $sourceCodesArray = [];
        foreach ($this->sourcesAssignedToStock->execute($stockId) as $source) {
            array_push($sourceCodesArray, $source->getData()['source_code']);
        }
        return $sourceCodesArray;
    }
}
