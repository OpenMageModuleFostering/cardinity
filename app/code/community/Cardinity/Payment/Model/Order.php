<?php

/**
 * Handle order state on success
 */
class Cardinity_Payment_Model_Order
{
    /**
     * @param $order order
     * @param $secured was 3-D secure step processed?
     */
    public function paid($order, $secured = false)
    {
        if ($secured) {
            $message = 'Payment 3-D security step succeeded. Finalized successfully.';
        } else {
            $message = 'Payment received without 3-D security step.';
        }

        // update order state
        $order->setState(
            Mage_Sales_Model_Order::STATE_PROCESSING,
            true,
            $message
        );
        $order->sendNewOrderEmail();
        $order->setEmailSent(true);        
        $order->save();
    }
}