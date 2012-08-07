<?php
/**
 * Bitcoin model
 *
 * @author Michał Adamiak <madamiak@tenwa.pl>
 * @version 1.0.4
 * @access private
 * @copyright Mtgox
 * @package Mtgox
 * @module Bitcoin
 */
class Mtgox_Bitcoin_Model_Bitcoin extends Mage_Payment_Model_Method_Abstract
{
    protected $_code                   = 'mtgox';
    protected $_isInitializeNeeded     = TRUE;
    protected $_canUseCheckout         = TRUE;
    protected $_canUseInternal         = FALSE;
    protected $_canUseForMultishipping = FALSE;

    /**
     * Config instance
     * @var Mage_Bitcoin_Model_Config
     */
    protected $_config = NULL;
    protected $_order;

    /**
     * Runs Bitcoin module
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('mtgoxbitcoin/bitcoin');
    }

    /**
     * Whether method is available for specified currency
     * @param string $currencyCode
     * @return boolean
     */
    public function canUseForCurrency($currencyCode)
    {
        return TRUE;
    }

    /**
     * Return Order place redirect url
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        return Mage::getUrl('mtgoxbitcoin/payment/redirect', array('_secure' => TRUE));
    }

    /**
     * Gets data from bitcoin module and prepre it to sed on mtgox api
     * @return array
     */
    public function getBitcoinCheckoutFormFields()
    {
        $bitcoinKey    = Mage::getStoreConfig('payment/mtgox/key');
        $bitcoinSecret = Mage::getStoreConfig('payment/mtgox/secret');
        $description   = Mage::getStoreConfig('payment/mtgox/description');

        $checkout         = Mage::getSingleton('checkout/session');
        $orderIncrementId = $checkout->getLastRealOrderId();
        $order            = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);

        $referenceNumber = $order->getIncrementId();
        $paymentAmount   = $order->getBaseGrandTotal();
        $currencyCode    = $order->getBaseCurrencyCode();

        $returnSuccess = Mage::getUrl('checkout/onepage/success');
        $returnFailure = Mage::getUrl('mtgoxbitcoin/payment/fail?id=' . $referenceNumber);

        $ipnUrl      = Mage::getUrl('mtgoxbitcoin/payment/notify');
        $requestData = array(
            'amount'         => $paymentAmount,
            'currency'       => $currencyCode,
            'description'    => $description,
            'data'           => $orderIncrementId,
            'return_success' => $returnSuccess,
            'return_failure' => $returnFailure,
            'ipn'            => $ipnUrl
        );

        // autosell: Automatically sell received bitcoins
        $orderAutoSell = !!Mage::getStoreConfig('payment/mtgox/order_autosell');
        if ($orderAutoSell) {
            $requestData['autosell'] = 1;
        }

        // email: Receive an email on completed transaction
        $orderEmail = !!Mage::getStoreConfig('payment/mtgox/order_email');
        if ($orderEmail) {
            $requestData['email'] = 1;
        }

        // instant_only: Only allow transactions that will settle instantly
        $orderInstantOnly = !!Mage::getStoreConfig('payment/mtgox/order_instant_only');
        if ($orderInstantOnly) {
            $requestData['instant_only'] = 1;
        }

        $responseData = Mage::helper('mtgoxbitcoin')->mtgoxQuery(Mtgox_Bitcoin_Helper_Data::API_ORDER_CREATE, $bitcoinKey, $bitcoinSecret, $requestData);

        return $responseData;
    }

    /**
     * Instantiate state and set it to state object
     * @param $paymentAction
     * @param object $stateObject
     */
    public function initialize($paymentAction, $stateObject)
    {
        $state = Mage_Sales_Model_Order::STATE_PENDING_PAYMENT;
        $stateObject->setState($state);
        $stateObject->setStatus('pending_payment');
        $stateObject->setIsNotified(FALSE);
    }

    /**
     * Check whether payment method can be used
     * @param $quote
     * @return boolean
     */
    public function isAvailable($quote = NULL)
    {
        return Mage::getStoreConfig('payment/mtgox/active');
    }

    /**
     * Get order model
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (!$this->_order) {
            $this->_order = $this->getInfoInstance()->getOrder();
        }
        return $this->_order;
    }
}
