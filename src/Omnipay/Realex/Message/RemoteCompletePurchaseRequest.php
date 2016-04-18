<?php

namespace Omnipay\Realex\Message;

use Omnipay\Common\Exception\OmnipayException;
use Omnipay\Common\Exception\InvalidResponseException;
use Omnipay\Common\Exception\InvalidCreditCardException;

/**
 * Realex Remote Complete Authorize Request
 */
class RemoteCompletePurchaseRequest extends AbstractRequest
{
	protected $liveCheckoutEndpoint = 'https://epage.payandshop.com/epage-remote.cgi';
    protected $testCheckoutEndpoint = 'https://epage.payandshop.com/epage-remote.cgi';
    protected $responseData;

    // Get the XML data
    public function getData() 
    {
        $this->validateData();

        return $this->getRequestXML($this->getCard(), true, [], false);
    }

    // Send data
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
            'card'          => $this->getCard()
        ];

        // Return the 3d secure response
        $return = $this->createResponse($response, true);
        $return->successful = true;
        return $return;
    }

    // get request XML
    public function getRequestXML($card, $autoSettle = true, $extraData = array(), $addressData = true, $cardData = true)
    {
        $data    = $this->getBaseData($autoSettle, $card);
        $brand   = (strcasecmp($card->getBrand(), "mastercard") == 0) ? "mc" : $card->getBrand();
        $request = new \SimpleXMLElement('<request />');

        $request['timestamp']        = $data['TIMESTAMP'];
        $request['type']             = $this->getType();

        $request->merchantid         = $this->getMerchantId();
        $request->account            = $this->getAccount();
        $request->orderid            = $data['ORDER_ID'];
        $request->custipaddress      = $this->getClientIp();

        $request->amount             = $data['AMOUNT'];
        $request->amount['currency'] = $data['CURRENCY'];

        $request->autosettle['flag'] = (int)$data['AUTO_SETTLE_FLAG'];

        $request->card->number       = $card->getNumber();
        $request->card->expdate      = $card->getExpiryDate('my');
        $request->card->type         = $brand;
        $request->card->chname       = $card->getName();

        // Not all request want this data
        if($cardData) {
            $request->card->issueno      = $card->getIssueNumber();
            $request->card->cvn->number  = $card->getCvv();
            $request->card->cvn->presind = '1';
        }

        // not all requests want this data
        if($addressData) {
            $request->address['type']    = 'billing';
            $request->address->code      = $card->getBillingPostcode();
            $request->address->country   = strtoupper($card->getBillingCountry());
        }

        // MPI data specific for the auth request
        $request->mpi->cavv               = $this->getCavv();
        $request->mpi->xid               = $this->getXid();
        $request->mpi->eci               = $this->getEci();
        $request->sha1hash               = $data['SHA1HASH'];

        return $request->asXML();
    }

    // Return a created response
    protected function createResponse($data, $threeD = false)
    {
        return $this->response = $threeD ? new ThreeDSecureResponse($this, $data) : new RemoteAuthorizeResponse($this, $data);
    }

    // Validate some card data
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

    public function getResponseData()
    {
        return $this->responseData;
    }

    protected function getType()
    {
        return 'auth';
    }

    protected function validateResponse()
    {
        // If we get a 500 error, we should send back an issue
        if((string)$this->responseData->result >= 500)
            return (string)$this->responseData->message;

        // This checks for aay 100 codes which usually result in declined, lost, stolen, or bad cards
        if(((string)$this->responseData->result > 100) && ((string)$this->responseData->result < 200))
           return "This card has been declined: " . (string)$this->responseData->message;

        // This checks for 200 errors which are usually bank issues
        if(((string)$this->responseData->result > 199) && ((string)$this->responseData->result < 300))
           return "There has been an error contacting your bank: " . (string)$this->responseData->message;

        // This checks for any 300 errors which are specific to realex
        if(((string)$this->responseData->result > 299) && ((string)$this->responseData->result < 400))
           return "There is some difficulty contacting the payment gateway: " . (string)$this->responseData->message;

        return true;
    }

    protected function getCheckoutEndpoint()
    {
        return $this->getTestMode() ? $this->testCheckoutEndpoint : $this->liveCheckoutEndpoint;
    }
}