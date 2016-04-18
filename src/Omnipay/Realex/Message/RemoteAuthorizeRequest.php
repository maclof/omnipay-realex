<?php

namespace Omnipay\Realex\Message;

use Guzzle\Http\ClientInterface;
use Omnipay\Common\Exception\OmnipayException;
use Omnipay\Common\Exception\InvalidCreditCardException;

/**
 * Realex Remote Authorize Request
 * First request, verifies 3ds enrollment
 */
class RemoteAuthorizeRequest extends AbstractRequest
{
    protected $liveCheckoutEndpoint = 'https://epage.payandshop.com/epage-remote.cgi';
    protected $testCheckoutEndpoint = 'https://epage.payandshop.com/epage-remote.cgi';
    protected $responseData;

    public function getData()
    {
        $this->validateData();

        return $this->getRequestXML($this->getCard(), false);
    }

    public function sendData($data)
    {
        // Make a request and get some datas back
        $response = [];
        $httpResponse = $this->httpClient->post($this->getCheckoutEndpoint(), null, $data)->send();
        $this->responseData = $httpResponse->xml();

        // Verify the request via some error codes
        $validationMessage = $this->validateResponse();
        if($validationMessage !== true)
            throw new InvalidCreditCardException($validationMessage);

        // Get card and order details
        $card = $this->getCard();
        $mdData = array(
            "number"            => $card->getNumber(),
            "billingPostcode"   => $card->getBillingPostcode(),
            "billingCountry"    => $card->getBillingCountry(),
            "expiryMonth"       => $card->getExpiryMonth(),
            "expiryYear"        => $card->getExpiryYear(),
            "billingFirstName"  => $card->getBillingFirstName(),
            "billingLastName"   => $card->getBillingLastName(),
            "shippingFirstName" => $card->getShippingFirstName(),
            "shippingLastName"  => $card->getShippingLastName(),
            "cvv"               => $card->getCvv(),
            "transactionid"     => (string)$this->responseData->orderid,
            "store"             => $this->getStore()
        );

        // If the card is not enrolled, and no attempt URL is provided, we can go straight to auth
        if(((string)$this->responseData->result == 110) && ((string)$this->responseData->enrolled == "N")) {
            
            // get some card details
            $card = $this->getCard();

            // Set the ECI type based on the card
            $eci = ($card->getBrand() == "visa" ? 6 : 1);

            // MD data
            $reference = http_build_query($mdData);
            $response['data']['PaReq'] = (string)$this->responseData->pareq;
            $response['data']['MD'] = base64_encode($reference);
            $response['data']['transactionid'] = (string)$this->responseData->orderid;
            $response['data']['xid'] = (string)$this->responseData->xid;
            $response['data']['eci'] = $eci;
            $response['url'] = "https://api.molt.in/beta/checkout/payment/complete_purchase";
            $response['enrolled'] = false;

            // Return for the redirect
            return $this->createResponse($response, true);

        } elseif(((string)$this->responseData->result == 110) && ((string)$this->responseData->enrolled == "U")) {
            
            // get some card details
            $card = $this->getCard();

            // Set the ECI type based on the card
            $eci = ($card->getBrand() == "visa" ? 6 : 1);

            // MD data
            $reference = http_build_query($mdData);
            $response['data']['PaReq'] = (string)$this->responseData->pareq;
            $response['data']['MD'] = base64_encode($reference);
            $response['data']['transactionid'] = (string)$this->responseData->orderid;
            $response['data']['xid'] = (string)$this->responseData->xid;
            $response['data']['eci'] = $eci;
            $response['url'] = "https://api.molt.in/beta/checkout/payment/complete_purchase";
            $response['enrolled'] = false;

            // Return for the redirect
            return $this->createResponse($response, true);
        }

        // Build a query from the array data
        $reference = http_build_query($mdData);
        $response['url'] = (string)$this->responseData->url;
        $response['data']['PaReq'] = (string)$this->responseData->pareq;
        $response['data']['MD'] = base64_encode($reference);
        $response['data']['TermUrl'] = $this->getNotifyUrl();
        $response['data']['transactionid'] = (string)$this->responseData->orderid;

        // Return the 3d secure response
        return $this->createResponse($response, true);
    }

    protected function createResponse($data, $threeD = false)
    {
        return $this->response = $threeD ? new ThreeDSecureResponse($this, $data) : new RemoteAuthorizeResponse($this, $data);
    }

    protected function getCheckoutEndpoint()
    {
        return $this->getTestMode() ? $this->testCheckoutEndpoint : $this->liveCheckoutEndpoint;
    }

    protected function validateData()
    {
        $this->validate('amount', 'card');

        $card = $this->getCard();
        $card->validate();

        foreach (array('name', 'cvv', 'billingPostcode', 'billingCountry') as $parameter) {
            $method = 'get'.ucfirst($parameter);
            if ( ! $card->$method()) {
                throw new InvalidCreditCardException("The $parameter parameter is required");
            }
        }
    }

    protected function getType()
    {
        return '3ds-verifyenrolled';
    }

    protected function validateResponse()
    {
        // If we get a 500 error, we should send back an issue
        if((string)$this->responseData->result >= 501)
            return (string)$this->responseData->message;

        return true;
    }
}
