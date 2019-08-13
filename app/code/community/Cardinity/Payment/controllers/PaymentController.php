<?php


class Cardinity_Payment_PaymentController extends Mage_Core_Controller_Front_Action
{
    /**
     * Redirect to 3-D secure page
     */
    public function authAction() 
    {
        $this->loadLayout();

        $block = $this->getLayout()->createBlock('Cardinity_Payment_Block_Auth');
        $this->getLayout()->getBlock('content')->append($block);

        $this->renderLayout();
    }
    
    /**
     * The response action is triggered when your gateway sends back a response 
     * after processing the customer's payment
     */
    public function callbackAction() 
    {
        if(!$this->getRequest()->isPost()) {
            return $this->_cancel();
        }

        $auth = Mage::getModel('Cardinity_Payment_Model_Auth');

        $pares = $_POST['PaRes'];
        $orderId =  $_POST['MD'];

        // Payment was successful, so update the order's state, send order email and move to the success page
        $order = Mage::getModel('sales/order')->load($auth->getOrderId());
        if (!$order->getId() 
            || $order->getId() !== $auth->getOrderId() 
            || $order->getRealOrderId() !== $orderId
        ) {
            return $this->_cancel();
        }

        // finalize payment
        $model = Mage::getModel('Cardinity_Payment_Model_Cardinity');
        $finalize = $model->finalize($auth->getPaymentId(), $pares);
        if (!$finalize) {
            return $this->_cancel();
        }
        
        Mage::getModel('Cardinity_Payment_Model_Order')->paid($order, true);
    
        Mage::getSingleton('checkout/session')->unsQuoteId();
        $auth->cleanup();
        
        Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure'=>true));
    }
    
    /**
     * The cancel action is triggered when an order is to be cancelled
     */
    public function cancelAction() 
    {
        Mage::getModel('Cardinity_Payment_Model_Auth')->cleanup();
        
        if (Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
            if($order->getId()) {
                // Flag the order as 'cancelled' and save it
                $order->cancel()->setState(
                    Mage_Sales_Model_Order::STATE_CANCELED,
                    true,
                    'Gateway has declined the payment.'
                )->save();
            }
        }
    }

    private function _cancel()
    {
        $this->cancelAction();
        Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure', array('_secure'=>true));
    }
}
