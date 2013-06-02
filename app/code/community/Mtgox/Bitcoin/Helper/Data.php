<?php
/**
 * Bitcoin helper
 *
 * @author Jonathan Gautheron <jgautheron@tenwa.pl>
 * @package Mtgox
 * @module Bitcoin
 */
class Mtgox_Bitcoin_Helper_Data extends Mage_Core_Helper_Abstract
{
    const LOG_FILENAME = 'mtgox.log';

    const CACHE_DURATION = 60;

    const API_ORDER_CREATE = '1/generic/private/merchant/order/create',
          API_INFO         = '1/generic/private/info',
          API_TICKER       = '1/BTC%s/ticker';

    protected $_supportedCurrencies = array(
        'EUR', 'PLN', 'JPY', 'USD', 'AUD', 'CAD', 'GBP', 'CHF',
        'RUB', 'SEK', 'DKK', 'HKD', 'CNY', 'SGD', 'THB', 'NZD',
        'NOK',
    );

    /**
     * Ensure that the connection is valid with the given API key + secret
     *
     * @param string $key    mtgox key
     * @param string $secret mtgox secret key
     *
     * @return boolean
     */
    public function isValidConnection($key, $secret)
    {
        $response = Mage::helper('mtgoxbitcoin')->mtgoxQuery(self::API_INFO, $key, $secret);
        return $response['result'] === 'success';
    }

    /**
     * Send data to specific mtgox api url
     *
     * @staticvar null $ch
     *
     * @param string $path   mtgox api path
     * @param string $key    mtgox key
     * @param string $secret mtgox secret key
     * @param array  $req    data to be sent
     * @see https://en.bitcoin.it/wiki/MtGox/API/HTTP/v1
     *
     * @return array
     */
    protected function mtgoxQuery($path, $key, $secret, array $req = array())
    {
        $mt = explode(' ', microtime());
        $req['nonce'] = $mt[1] . substr($mt[0], 2, 6);

        if ($key && $secret) {
            $postData = http_build_query($req, '', '&');
            $headers = array(
                'Rest-Key: ' . $key,
                'Rest-Sign: ' . base64_encode(
                    hash_hmac('sha512', $postData, base64_decode($secret), TRUE)
                 ),
            );
        }

        static $ch = NULL;
        if (is_null($ch)) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt(
                $ch, CURLOPT_USERAGENT,
                'Mozilla/4.0 (compatible; MtGox PHP client; ' . php_uname('s') . '; PHP/' . phpversion() . ')'
            );
        }
        curl_setopt($ch, CURLOPT_URL, 'https://data.mtgox.com/api/' . $path);

        if ($key && $secret) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $res = curl_exec($ch);
        if ($res === FALSE) {
            $msg = 'Could not get reply: ' . curl_error($ch);
            Mage::log($msg, Zend_Log::ERR, self::LOG_FILENAME);
            Mage::getSingleton('core/session')->addError($msg);
            return false;
        }

        $dec = json_decode($res, TRUE);
        if (!$dec) {
            $msg = 'Invalid data received, please make sure connection is working and requested API exists';
            Mage::log($msg, Zend_Log::ERR, self::LOG_FILENAME);
            Mage::getSingleton('core/session')->addError($msg);
            return false;
        }

        return $dec;
    }

    public function sendQuery($path, array $req = array(), $auth = true)
    {
        $_bitcoinKey    = null;
        $_bitcoinSecret = null;

        if ($auth) {
            $_bitcoinKey    = Mage::getStoreConfig('payment/mtgox/key');
            $_bitcoinSecret = Mage::getStoreConfig('payment/mtgox/secret');
        }

        return $this->mtgoxQuery($path, $_bitcoinKey, $_bitcoinSecret, $req);
    }

    /**
     * Check if the currency is supported by MtGox
     *
     * @param string $currency currency code (EUR, USD...)
     *
     * @return boolean
     */
    protected function isSupportedCurrency($currency)
    {
        return in_array($currency, $this->_supportedCurrencies);
    }

    /**
     * Get the BTC rate for the current currency
     *
     * @return float
     */
    public function getCurrencyRate()
    {
        $currencyCode = Mage::app()->getStore()->getCurrentCurrencyCode();

        // do not convert bitcoins obviously
        if ($currencyCode === 'BTC') {
            return false;
        }

        if (!$this->isSupportedCurrency($currencyCode)) {
            // fail silently, log the error
            Mage::log(
                'Currency not supported: ' . $currencyCode . '(supported currencies: ' . implode(',', $this->_supportedCurrencies). ')',
                Zend_Log::ERR, self::LOG_FILENAME);
            return false;
        }

        $cacheTag     = 'BTC_rate_' . $currencyCode;
        $currencyRate = Mage::app()->loadCache($cacheTag);
        if (!$currencyRate) {
            $response = $this->sendQuery(sprintf(self::API_TICKER, $currencyCode), array(), false);

            // something's wrong
            if (!$response OR !isset($response['result'])) {
                return false;
            }

            if ($response['result'] !== 'success') {
                Mage::log('Could not retrieve the currency rate', Zend_Log::ERR, self::LOG_FILENAME);
                return false;
            }

            $currencyRate = $response['return']['avg']['value'];
            Mage::app()->saveCache($currencyRate, $cacheTag, array($cacheTag), self::CACHE_DURATION);
        }

        return $currencyRate;
    }
}