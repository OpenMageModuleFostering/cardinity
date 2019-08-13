<?php
require __DIR__ . '/../vendor/autoload.php';

use Cardinity\Client;
use Cardinity\Exception;
use Cardinity\Method\MethodInterface;
use Cardinity\Method\Payment;
use Cardinity\Method\Refund;

class Cardinity_Payment_Model_Cardinity extends Mage_Payment_Model_Method_Cc
{
    const LOG = 'cardinity.log';

    /**
     * unique internal payment method identifier
     *
     * @var string [a-z0-9_]
     */
    protected $_code = 'cardinity';

    /**
     * Is this payment method a gateway (online auth/charge) ?
     */
    protected $_isGateway = true;

    /**
     * Can authorize online?
     */
    protected $_canAuthorize = false;

    /**
     * Can capture funds online?
     */
    protected $_canCapture = true;

    /**
     * Can capture partial amounts online?
     */
    protected $_canCapturePartial = false;

    /**
     * Can refund online?
     */
    protected $_canRefund = true;

    /**
     * Can void transactions online?
     */
    protected $_canVoid = false;

    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal = false;

    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout = true;

    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping = false;

    /**
     * Can save credit card information for future processing?
     */
    protected $_canSaveCc = false;

    /** @var Client */
    private $_client;

    /** @var string */
    private $_redirectUrl;

    public function __construct()
    {
        $logger = Client::LOG_NONE;
        // To enable request/response debugging uncomment following lines: 
        // $logger = function($data) {
        //     Mage::log($data, Zend_Log::ERR, self::LOG);
        // };

        $this->_client = Client::create([
            'consumerKey' => $this->getConfigData('cardinity_key'),
            'consumerSecret' => $this->getConfigData('cardinity_secret'),
        ], $logger);
    }

    /**
     * Capture payment abstract method
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function capture(Varien_Object $payment, $amount)
    {
        if (!$this->canCapture()) {
            Mage::throwException(Mage::helper('payment')->__('Capture action is not available.'));
        }

        if ($amount <= 0) {
            Mage::throwException(Mage::helper('payment')->__('Invalid amount for capture.'));
        }

        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $holder = sprintf(
            '%s %s',
            $quote->getBillingAddress()->getData('firstname'),
            $quote->getBillingAddress()->getData('lastname')
        );
        
        $params = [
            'amount' => floatval($amount),
            'currency' => Mage::app()->getStore()->getCurrentCurrencyCode(),
            'settle' => true,
            // 'description' => '',
            'order_id' => $this->getInfoInstance()->getOrder()->getRealOrderId(),
            'country' => $quote->getBillingAddress()->getData('country_id'),
            'payment_method' => Payment\Create::CARD,
            'payment_instrument' => [
                'pan' => $payment->cc_number,
                'exp_year' => (int) $payment->cc_exp_year,
                'exp_month' => (int) $payment->cc_exp_month,
                'cvc' => $payment->cc_cid,
                'holder' => $holder
            ]
        ];

        $result = $this->call(new Payment\Create($params));
        if ($result) {
            // ok, set transaction id
            $payment->customer_payment_id = $result->getId();
            $payment->save();

            if ($result->isApproved()) {
                $order = $this->getInfoInstance()->getOrder();
                Mage::getModel('Cardinity_Payment_Model_Order')->paid($order);

                return $this;
            } elseif ($result->isPending()) {
                $session = Mage::getSingleton('checkout/session');
                $auth = $result->getAuthorizationInformation();

                $model = Mage::getModel('Cardinity_Payment_Model_Auth');
                $model->setUrl($auth->getUrl());
                $model->setData($auth->getData());
                $model->setPaymentId($result->getId());
                $model->setOrderId($this->getInfoInstance()->getOrder()->getId());
                $model->setRealOrderId($this->getInfoInstance()->getOrder()->getRealOrderId());

                return $this;
            }
        }

        Mage::throwException($this->_getHelper()->__('Order could not be processed.'));

        return $this;
    }

    /**
     * Redirect to authorization step if necessary
     */
    public function getOrderPlaceRedirectUrl()
    {
        $auth = Mage::getModel('Cardinity_Payment_Model_Auth');
        if ($auth->getUrl()) {
            return Mage::getBaseUrl() . 'cardinity/payment/auth';
        }
        return null;
    }

    /**
     * Finalize payment after 3-D security verification
     * 
     * @param string $paymentId payment id received from Cardinity
     * @param string $pares payer authentication response received from ACS
     * @return boolean
     */
    public function finalize($paymentId, $pares)
    {
        $method = new Payment\Finalize($paymentId, $pares);
        $result = $this->call($method, false);

        return $result && $result->isApproved();
    }

    /**
     * Perform Cardinity call
     * @param MethodInterface $method 
     * @param boolean $throwException 
     * @return mixed
     */
    private function call(MethodInterface $method, $throwException = true)
    {
        try {
            return $this->_client->call($method);
        } catch (Exception\Declined $e) {
            $this->log($e);

            $error = $this->_getHelper()->__('Your request was valid but it was declined.');
            $this->error($error, $throwException);

            return $e->getResult();
        } catch (Exception\Request $e) {
            $this->log($e);

            $error = $this->_getHelper()->__('Request failed.');
            $this->error($error, $throwException);
        } catch (Exception\Runtime $e) {
            $this->log($e);
            
            $error = $this->_getHelper()->__('Runtime error.');
            $this->error($error, $throwException);
        }
    }


    /**
     * @param string $error 
     * @param boolean $throwException 
     */
    private function error($error, $throwException)
    {
        if ($throwException) {
            Mage::throwException($error);
        } else {
            Mage::getSingleton('core/session')->addError($error);
        }
    }

    private function log(\Exception $e, $level = Zend_Log::ERR)
    {
        Mage::log(
            sprintf(
                "%s: %s.",
                get_class($e),
                $e->getMessage()
            ),
            $level,
            self::LOG
        );

        if (method_exists($e, 'getErrorsAsString')) {
             Mage::log(
                sprintf(
                    "Errors: %s",
                    $e->getErrorsAsString()
                ),
                $level,
                self::LOG
            );
        }

        if ($e->getPrevious()) {
            Mage::log(
                sprintf(
                    "Previous %s: %s",
                    get_class($e->getPrevious()),
                    $e->getPrevious()->getMessage()
                ),
                $level,
                self::LOG
            );
        }
    }
}
