<?php
/**
 * Bitcoin action controller
 *
 * @author Michał Adamiak <madamiak@tenwa.pl>
 * @version 1.0.5
 * @access private
 * @copyright Mtgox
 * @package Mtgox
 * @module Bitcoin
 */
class Mtgox_Bitcoin_PaymentController extends Mage_Core_Controller_Front_Action
{
    /**
     * Redirect user to MtGox payment page
     */
    public function redirectAction()
    {
        $model        = Mage::getModel('mtgoxbitcoin/bitcoin');
        $responseData = $model->getBitcoinCheckoutFormFields();

        $form = new Varien_Data_Form();
        $form->setAction($responseData['return']['payment_url'])
            ->setId('mtgox_bitcoin_checkout')
            ->setName('mtgox_bitcoin_checkout')
            ->setMethod('POST')
            ->setUseContainer(TRUE);

        $html = '<html><body>';
        $html.= $this->__('You will be redirected to the MtGox Payment Platform within 5 seconds.');
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">setTimeout(function() { document.getElementById("mtgox_bitcoin_checkout").submit(); }, 4000);</script>';
        $html.= '</body></html>';

        $this->getResponse()->setBody($html);
    }

    /**
     * Action for MtGox IPN response
     */
    public function notifyAction()
    {
        $bitcoinKey    = Mage::getStoreConfig('payment/mtgox/bitcoin_key');
        $bitcoinSecret = Mage::getStoreConfig('payment/mtgox/bitcoin_secret');
        $rawPostData   = file_get_contents("php://input");

        $goodSign = hash_hmac(
            'sha512', $rawPostData, base64_decode($bitcoinSecret), TRUE
        );

        $sign = base64_decode($_SERVER['HTTP_REST_SIGN']);
        if ($sign == $goodSign) {
            $status      = Mage::app()->getRequest()->getParam('status');
            $orderNumber = trim(stripslashes(Mage::app()->getRequest()->getParam('data')));
            $order       = Mage::getModel('sales/order')->loadByIncrementId($orderNumber);
            $paymentId   = Mage::app()->getRequest()->getParam('payment_id');

            switch ($status) {
                case 'paid':
                    $order->getPayment()->
                        registerCaptureNotification($order->getBaseGrandTotal());
                    $order->getPayment()->setTransactionId($paymentId);
                    $order->save();
                    break;
                case 'partial':
                    $order->getPayment()->
                        registerCaptureNotification($_POST['amount_valid']);
                    $order->getPayment()->setTransactionId($paymentId);
                    $order->save();
                    break;
                case 'cancelled':
                    $order->registerCancellation('Payment cancelled', TRUE)->save();
                    break;
                default:
                    $order->registerCancellation('Payment failed', TRUE)->save();
                    break;
            }
        }
        exit;
    }

    /**
     * Shows template on failure
     * and changes order status to cancelled
     */
    public function failAction()
    {
        $orderNumber = trim(stripslashes(Mage::app()->getRequest()->getParam('id')));
        $orderNumber = str_replace('/', '', $orderNumber);

        $session = Mage::getSingleton('core/session', array(
            'name' => 'frontend'
        ));
        $session->setData('order_number', $orderNumber);

        $this->loadLayout();
        $this->renderLayout();

        if (Mage::getSingleton('customer/session')->isLoggedIn()) {
            $order = Mage::getModel('sales/order')
                ->loadByIncrementId($orderNumber);

            $orderData  = $order->getData();
            $customerId = Mage::getSingleton('customer/session')->getId();

            if ($orderData['customer_id'] === $customerId) {
                $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, TRUE)
                    ->save();
            }
        }
    }
}