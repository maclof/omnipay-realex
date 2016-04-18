<?php

namespace Omnipay\Realex\Message;

/**
 * Realex Remote Purchase Request
 */
class RemotePurchaseRequest extends RemoteAuthorizeRequest
{
    public function getData()
    {
        $this->validateData();

        return $this->getRequestXML($this->getCard(), false);
    }
}
