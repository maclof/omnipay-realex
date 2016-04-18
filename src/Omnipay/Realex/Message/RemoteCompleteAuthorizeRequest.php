<?php

namespace Omnipay\Realex\Message;

use Omnipay\Common\Exception\OmnipayException;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Exception\InvalidCreditCardException;

/**
 * Realex Remote Complete Authorize Request
 * 2nd step, verifies the signature from a request
 */
class RemoteCompleteAuthorizeRequest extends AbstractRequest
{
    protected $liveCheckoutEndpoint = 'https://epage.payandshop.com/epage-remote.cgi';
    protected $testCheckoutEndpoint = 'https://epage.payandshop.com/epage-remote.cgi';
    protected $responseData;
    
    public function getData() 
    {
        $this->validateData();

        return $this->getRequestXML($this->getCard(), false, ["pares" => $this->getPares()], false, false);
    }

    public function sendData($data)
    {
        $httpResponse = $this->httpClient->post($this->getCheckoutEndpoint(), null, $data)->send();
        $this->responseData = $httpResponse->xml();

        // Verify the request via some error codes
        $validationMessage = $this->validateResponse();
        if($validationMessage !== true)
            throw new InvalidCreditCardException($validationMessage);

        // Port some variables into a neat array
        $storeArray = $this->getStore();
        $response['data'] = [
            'transactionId' => (string)$this->responseData->orderid,
            'result'        => (string)$this->responseData->result,
            'status'        => (string)$this->responseData->threedsecure->status,
            'eci'           => (string)$this->responseData->threedsecure->eci,
            'cavv'          => (string)$this->responseData->threedsecure->cavv, 
            'xid'           => (string)$this->responseData->threedsecure->xid,
            'merchantId'    => $storeArray['merchantId'],
            'amount'        => $storeArray['amount'],
            'currency'      => $storeArray['currency'],
            'store'         => $storeArray['store'],
            'account'       => $storeArray['account'],
            'card'          => $this->getCard(),
            'secret'        => $storeArray['secret'],
            'url'           => "/beta/checkout/complete_purchase"
        ];

        // if the card is not enrolled (11), enrolled result (N)
        if(($response['data']['result'] == 110) && ($response['data']['status'] == "N")) {
            // Get the card
            $card = $this->getCard();
            
            // If card type is Visa, we set to 6, all other cards are set to 1
            if($card->getBrand() == "visa") {
                $response['data']['eci'] = 6;
            }else{
                $response['data']['eci'] = 1;
            }

            // We then unset the ECI and CAVV fields, ECI is the only field sent
            $response['data']['cavv'] = "";
            $response['data']['xid'] = "";
        }

        // Return the 3d secure response
        return $this->createResponse($response, true);
    }

    protected function createResponse($data, $threeD = false)
    {
        return $this->response = $threeD ? new ThreeDSecureResponse($this, $data) : new RemoteAuthorizeResponse($this, $data);
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
        return '3ds-verifysig';
    }

    protected function validateResponse()
    {
        // If we get a 500 error, we should send back an issue
        if((string)$this->responseData->status == "N")
            return "The cardholder did not authenticate successfully.";

        if((string)$this->responseData->status == "U")
            return "Cardholder authentication is temporarily unavailable.";
        
        if((string)$this->responseData->status == "A")
            return "The cardholder is enrolled and the bank has acknowleged the attempted authentication.";

        // If we get a 500 error, we should send back an issue
        if((string)$this->responseData->result >= 501)
            return (string)$this->responseData->message;

        return true;
    }

    protected function getCheckoutEndpoint()
    {
        return $this->getTestMode() ? $this->testCheckoutEndpoint : $this->liveCheckoutEndpoint;
    }
}
