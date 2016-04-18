<?php

namespace Omnipay\Realex\Message;

use Omnipay\Common\Message\RedirectResponseInterface;

/**
 * Realex Redirect Authorize Response
 */
class RedirectAuthorizeResponse extends Response implements RedirectResponseInterface
{
    protected $liveCheckoutEndpoint = 'https://hpp.realexpayments.com/pay';
    protected $testCheckoutEndpoint = 'https://hpp.sandbox.realexpayments.com/pay';

    public function isSuccessful()
    {
        return false;
    }

    public function isRedirect()
    {
        return true;
    }

    public function getRedirectUrl()
    {
        return $this->getCheckoutEndpoint();
    }

    public function getTransactionReference()
    {
        return $this->getRequest()->getTransactionId();
    }

    public function getRedirectMethod()
    {
        return 'POST';
    }

    public function getRedirectData()
    {
        return $this->getRequest()->getBaseData(false);
    }

    protected function getCheckoutEndpoint()
    {
        return $this->getRequest()->getTestMode() ? $this->testCheckoutEndpoint : $this->liveCheckoutEndpoint;
    }
}
