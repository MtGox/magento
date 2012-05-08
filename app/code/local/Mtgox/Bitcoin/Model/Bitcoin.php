<?php
/**
 * Bitcoin model
 *
 * @author Michał Adamiak <madamiak@tenwa.pl>
 * @version 1.1.0
 * @access private
 * @copyright Mtgox
 * @package Mtgox
 * @module Bitcoin
 */
class Mtgox_Bitcoin_Model_Bitcoin extends Mage_Payment_Model_Method_Abstract
{
    protected $_code                   = 'bitcoin';
    protected $_isInitializeNeeded     = TRUE;
    protected $_canUseCheckout         = TRUE;
    protected $_canUseInternal         = FALSE;
    protected $_canUseForMultishipping = FALSE;

    /**
     * Config instance
     * @var Mage_Bitcoin_Model_Config
     */
    protected $_config = null;
    protected $_order;

    /**
     * Runs Bitcoin module
     */
    public function _construct()
    {
        parent::_construct();
        $this->_init('bitcoin/bitcoin');
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
        return Mage::getUrl('bitcoin/payment/redirect', array('_secure' => true));
    }

    /**
     * Gets data from bitcoin module and prepre it to sed on mtgox api
     * @return array
     */
    public function getBitcoinCheckoutFormFields()
    {
    	$bitcoinKey = Mage::getStoreConfig( 'payment/bitcoin/bitcoin_key' );
    	$bitcoinSecret = Mage::getStoreConfig( 'payment/bitcoin/bitcoin_secret' );
        $description = Mage::getStoreConfig( 'payment/bitcoin/bitcoin_description' );
    	$bitcoinPath = '1/generic/private/merchant/order/create';
        $checkout = Mage::getSingleton('checkout/session');
        $orderIncrementId = $checkout->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderIncrementId);
        $referenceNumber = $order->getIncrementId();
        $paymentAmount = $order->getBaseGrandTotal();
        $currencyCode = $order->getBaseCurrencyCode();
        $returnSuccess = Mage::getUrl('checkout/onepage/success');
        $returnFailure = Mage::getUrl('checkout/cart');
        $returnFailure = Mage::getUrl('bitcoin/payment/fail?id=' . $referenceNumber);
        $ipnUrl = Mage::getUrl('bitcoin/payment/notify');
        $requestData = array(
            'amount'            => $paymentAmount,
            'currency'          => $currencyCode,
            'description'       => $description,
            'data'              => $orderIncrementId,
            'return_success'    => $returnSuccess,
            'return_failure'    => $returnFailure,
            'ipn'               => $ipnUrl,
            'autosell'          => 0
        );
        $autoSell = Mage::getStoreConfig( 'payment/bitcoin/bitcoin_autosale' );
        if ($autoSell) {
            $requestData['autosell'] = 1;
        }
        $responseData = $this->
            mtgoxQuery($bitcoinPath, $bitcoinKey, $bitcoinSecret, $requestData);
        return $responseData;
    }

    /**
     * Send data to specific mtgox api url
     * @staticvar null $ch
     * @param string $path mtgox api path
     * @param string $key mtgox key
     * @param string $secret mtgox secret key
     * @param array $req date to be sent
     * @return array
     * @throws Exception
     */
    public function mtgoxQuery($path, $key, $secret, array $req = array())
    {
		$mt = explode(' ', microtime());
		$req['nonce'] = $mt[1] . substr($mt[0], 2, 6);
		$postData = http_build_query($req, '', '&');
		$headers = array(
			'Rest-Key: ' . $key,
			'Rest-Sign: ' . base64_encode(
                hash_hmac('sha512', $postData, base64_decode($secret), TRUE)
             ),
		);
		static $ch = NULL;
		if (is_null($ch)) {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt(
                $ch, CURLOPT_USERAGENT,
                'Mozilla/4.0 (compatible; MtGox PHP client; ' . php_uname('s') . '; PHP/' . phpversion() . ')'
            );
		}
		curl_setopt($ch, CURLOPT_URL, 'https://mtgox.com/api/' . $path);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        $res = curl_exec($ch);
		if ($res === FALSE) {
            throw new Exception('Could not get reply: ' . curl_error($ch));
        }
		$dec = json_decode($res, TRUE);
		if (!$dec) {
            throw new Exception('
                Invalid data received,
                please make sure connection is working and requested API exists
            ');
        }
		return $dec;
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
        $stateObject->setIsNotified(false);
    }

    /**
     * Check whether payment method can be used
     * @param $quote
     * @return boolean
     */
    public function isAvailable($quote)
    {
        return TRUE;
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
