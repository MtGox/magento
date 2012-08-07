<?php
/**
 * Bitcoin action controller
 *
 * @author Michał Adamiak <madamiak@tenwa.pl>
 * @version 1.0.4
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
        $html.= $this->__('You will be redirected to the MtGox Payment Platform in a few seconds.');
        $html.= $form->toHtml();
        $html.= '<script type="text/javascript">document.getElementById("mtgox_bitcoin_checkout").submit();</script>';
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

        $good_sign = hash_hmac(
            'sha512', $rawPostData, base64_decode($bitcoinSecret), TRUE
        );

        $sign = base64_decode($_SERVER['HTTP_REST_SIGN']);
        if ($sign == $good_sign) {
            $orderNumber = trim(stripslashes($_POST['data']));
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderNumber);
            switch ($_POST['status']) {
                case 'paid':
                    $order->getPayment()->
                        registerCaptureNotification($order->getBaseGrandTotal());
                    $order->getPayment()->setTransactionId($_POST['payment_id']);
                    $order->save();
                    break;
                case 'partial':
                    $order->getPayment()->
                        registerCaptureNotification($_POST['amount_valid']);
                    $order->getPayment()->setTransactionId($_POST['payment_id']);
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
        $orderNumber = trim(stripslashes($_GET['id']));
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