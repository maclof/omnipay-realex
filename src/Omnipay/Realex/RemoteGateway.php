<?php

namespace Omnipay\Realex;

/**
 * Realex Remote Class
 */
class RemoteGateway extends RedirectGateway
{
    public function getName()
    {
        return 'Realex Remote';
    }

    public function authorize(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Realex\Message\RemoteAuthorizeRequest', $parameters);
    }

    public function completeAuthorize(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Realex\Message\RemoteCompleteAuthorizeRequest', $parameters);
    }

    public function purchase(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Realex\Message\RemotePurchaseRequest', $parameters);
    }

    public function completePurchase(array $parameters = array())
    {
        return $this->createRequest('\Omnipay\Realex\Message\RemoteCompletePurchaseRequest', $parameters);
    }
}
