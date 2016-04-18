<?php

namespace Omnipay\Realex\Message;

/**
 * Realex Remote Authorize Response
 */
class RemoteAuthorizeResponse extends Response
{
    public function __construct($request, $data = array())
    {
        $this->request = $request;
        $this->data    = $this->decode($data);
    }

    protected function decode($data)
    {
        $data = (array)simplexml_load_string($data);
        $data['TIMESTAMP'] = $data['@attributes']['timestamp'];
        unset($data['@attributes']);

        foreach ( $data as $key => $value ) {
            $data[strtoupper($key)] = ! is_string($value) ? (array)$value : $value ;
            unset($data[$key]);
        }

        return $data;
    }
}
