<?php

namespace Ordergroove\Subscription\Model\Carrier;

class Flatrate{

    protected $_checkoutSession;
    protected $_scopeConfig;
    protected $_customerSession;

    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->_storeManager = $storeManager;
        $this->_checkoutSession = $checkoutSession;
        $this->_scopeConfig = $scopeConfig;
        $this->_customerSession = $customerSession;
    }

    public function afterCollectRates(\Magento\OfflineShipping\Model\Carrier\Flatrate $Flatrate, $result)
    {   
        $url = $this->_storeManager->getStore()->getCurrentUrl(false);
        $path = parse_url($url)['path'];
        if ($path == '/ordergroove/subscription/placeorder') {
            return $result;
        }
        return false;
    }  

}