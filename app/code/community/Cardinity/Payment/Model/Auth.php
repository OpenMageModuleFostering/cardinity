<?php

class Cardinity_Payment_Model_Auth
{
    public function setData($data)
    {
        $this->_getSession()->setData('_auth_data', $data);
    }

    public function getData()
    {
        return $this->_getSession()->getData('_auth_data');
    }

    public function setUrl($url)
    {
        $this->_getSession()->setData('_auth_url', $url);
    }

    public function getUrl()
    {
        return $this->_getSession()->getData('_auth_url');
    } 

    public function setPaymentId($paymentId)
    {
        $this->_getSession()->setData('_payment_id', $paymentId);
    }

    public function getPaymentId()
    {
        return $this->_getSession()->getData('_payment_id');
    }

    public function setOrderId($orderId)
    {
        $this->_getSession()->setData('_order_id', $orderId);
    }

    public function getOrderId()
    {
        return $this->_getSession()->getData('_order_id');
    }

    public function setRealOrderId($orderId)
    {
        $this->_getSession()->setData('_real_order_id', $orderId);
    }

    public function getRealOrderId()
    {
        return $this->_getSession()->getData('_real_order_id');
    }

    /**
     * Cleanup data
     */
    public function cleanup()
    {
        $this->_getSession()->setData('_auth_data', null);
        $this->_getSession()->setData('_auth_url', null);
        $this->_getSession()->setData('_payment_id', null);
        $this->_getSession()->setData('_order_id', null);
        $this->_getSession()->setData('_real_order_id', null);
    }

    private function _getSession()
    {
        return Mage::getSingleton('checkout/session');
    } 
}