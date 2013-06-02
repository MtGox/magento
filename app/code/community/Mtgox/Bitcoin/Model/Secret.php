<?php
/**
 * Bitcoin model
 *
 * @copyright Mtgox
 * @package Mtgox
 * @module Bitcoin
 */
class Mtgox_Bitcoin_Model_Secret extends Mage_Core_Model_Config_Data
{
    public function _afterSave()
    {
        $key    = Mage::getStoreConfig('payment/mtgox/key');
        $secret = $this->getValue();

        if (!Mage::helper('mtgoxbitcoin')->isValidConnection($key, $secret)) {
            Mage::getConfig()->saveConfig('payment/mtgox/active', 0);
        }

        return parent::_afterSave();
    }
}