<?php
/**
 * Bitcoin model
 *
 * @author Jonathan Gautheron <jgautheron@tenwa.pl>
 * @version 1.0.4
 * @access private
 * @copyright Mtgox
 * @package Mtgox
 * @module Bitcoin
 */
class Mtgox_Bitcoin_Model_Key extends Mage_Core_Model_Config_Data
{
    public function _afterSave()
    {
        $key    = $this->getValue();
        $secret = Mage::getStoreConfig('payment/mtgox/secret');

        if (!Mage::helper('mtgoxbitcoin')->isValidConnection($key, $secret)) {
            Mage::getConfig()->saveConfig('payment/mtgox/active', 0);
            Mage::getSingleton('adminhtml/session')->addError('The MtGox payment module has been disabled since the API settings are invalid.');
        }

        return parent::_afterSave();
    }
}