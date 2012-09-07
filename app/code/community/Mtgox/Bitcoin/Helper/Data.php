<?php
/**
 * Bitcoin helper
 *
 * @author Jonathan Gautheron <jgautheron@tenwa.pl>
 * @version 1.0.4
 * @access private
 * @copyright Mtgox
 * @package Mtgox
 * @module Bitcoin
 */
class Mtgox_Bitcoin_Helper_Data extends Mage_Core_Helper_Abstract
{

    const API_ORDER_CREATE = '1/generic/private/merchant/order/create',
          API_INFO         = '1/generic/private/info';

    /**
     * Ensures that the connection is valid with the given API key + secret
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
     * @param array  $req    date to be sent
     *
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
            $msg = 'Could not get reply: ' . curl_error($ch);
            Mage::log($msg, Zend_Log::ERR);
            Mage::getSingleton('core/session')->addError($msg);
        }
        $dec = json_decode($res, TRUE);
        if (!$dec) {
            $msg = 'Invalid data received, please make sure connection is working and requested API exists';
            Mage::log($msg, Zend_Log::ERR);
            Mage::getSingleton('core/session')->addError($msg);
        }
        return $dec;
    }
}