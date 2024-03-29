<?php

namespace Stock2shop\Parcelninja\Model\Carrier;

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use phpDocumentor\Reflection\DocBlock\Tags\Param;

/**
 * Parcelninja shipping model
 */
class Parcelninja extends AbstractCarrier implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'parcelninja';

    /**
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    private $rateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    private $rateMethodFactory;

    protected $_sessionManager;

    private $_pn_carrier_title = '';

    protected $pn_base_url = '';
    protected $pn_api_usr = '';
    protected $pn_api_pwd = '';

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $rateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Framework\Session\SessionManagerInterface $sessionManager,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);

        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;

        $this->_sessionManager = $sessionManager;

        $this->pn_base_url = $scopeConfig->getValue('carriers/parcelninja/api_url', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, true, false);
        $this->pn_api_usr = $scopeConfig->getValue('carriers/parcelninja/api_username', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, true, false);;
        $this->pn_api_pwd = $scopeConfig->getValue('carriers/parcelninja/api_password', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, true, false);;
    }

    /**
     * Parcelninja Shipping Rates Collector
     *
     * @param RateRequest $request
     * @return \Magento\Shipping\Model\Rate\Result|bool
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active') && strtolower($request->getDestCountryId()) != 'za') {
            return false;
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->rateResultFactory->create();

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->rateMethodFactory->create();

        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod($this->_code);
        $method->setMethodTitle($this->getConfigData('name'));

        $shippingCost = $this->_getCheapestQuote($request);
        if ($this->_pn_carrier_title) {
            $method->setCarrierTitle($this->_pn_carrier_title);
        }

        $method->setPrice($shippingCost);
        $method->setCost($shippingCost);

        $result->append($method);

        return $result;
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->_code => $this->getConfigData('name')];
    }

    private function _getCheapestQuote($request) {
        // Create payload
        $postcode = $request->getDestPostcode();
        $city = $request->getDestCity();
        $items = $request->getAllItems();
        $payload = array(
            "deliveryInformation" => array(
                "postalCode" => $postcode,
                'suburb' => $city
            )
        );
        $payload_items = array();
        foreach ($items as $item) {
            array_push(
                $payload_items,
                array(
                    "sku" => $item->getSku(),
                    "quantity" => $item->getQty(),
                    "fromReserve" => false
                )
            );
        }
        $payload['items'] = $payload_items;

        // Fetch quote from parcelninja
        $resource = 'delivery/quote/cheapest';
        $response = $this->_post($resource, $payload);
        if (!$response) {
            // Use default shipping cost if parcelninja request fails
            $shippingCost = (float)$this->getConfigData('shipping_cost');
        } else {
            foreach ($response as $quote) {
                $shippingCost = (float)$quote->cost;
                $this->_pn_carrier_title = $quote->service->description;
                // Temporarily store PN quote id to later save with our order
                $this->_sessionManager->setParcelninjaQuoteId($quote->quoteId);
            }
        }

        return $shippingCost;
    }

    /**
     * Makes POST request
     *
     * @param $resource
     * @param $payload
     */
    private function _post($resource, $payload)
    {
        try {
            $client = new Client([
                'base_uri' => $this->pn_base_url,
                'auth' => [
                    $this->pn_api_usr,
                    $this->pn_api_pwd
                ]
            ]);

            $response = $client->request('POST', $resource, ['json' => $payload]);

            return json_decode($response->getBody());
        } catch (\Exception $e) {
            return false;
        }
    }
}
