<?php
namespace Stock2shop\Parcelninja\Observer;

use Magento\Framework\Event\ObserverInterface;

class OrderSaveBefore implements ObserverInterface
{
    protected $_sessionManager;

    public function __construct(
        \Magento\Framework\Session\SessionManagerInterface $sessionManager
    ) {
        // Observer initialization code...
        // You can use dependency injection to get any class this observer may need.
        $this->_sessionManager = $sessionManager;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer['order'];
        $pn_quote_id = ($this->_sessionManager->getParcelninjaQuoteId()) ? "" . $this->_sessionManager->getParcelninjaQuoteId() . "" : false;
        if ($pn_quote_id && $order->getShippingMethod() == 'parcelninja_parcelninja') {
            $order->setShippingDescription($pn_quote_id);
        } elseif ($order->getShippingMethod() == 'parcelninja_parcelninja') {
            // throw exception as pn quote id is required for parcelninja shipping method
        }
    }
}
