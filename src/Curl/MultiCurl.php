<?php

namespace Curl;

class MultiCurl
{
    public $baseUrl = null;
    public $multiCurl;

    protected $curls = [];
    protected $isStarted = false;

    protected $beforeSendFunction = null;
    protected $successFunction = null;
    protected $errorFunction = null;
    protected $completeFunction = null;

    protected $cookies = [];
    protected $headers = [];
    protected $options = [];

    protected $jsonDecoder = null;
    protected $xmlDecoder = null;

    /**
     * MultiCurl constructor.
     * @param null $baseUrl
     */
    public function __construct($baseUrl = null)
    {
        $this->multiCurl = curl_multi_init();
        $this->headers = new CaseInsensitiveArray();
        $this->baseUrl = $baseUrl;
    }

    /**
     * @param Curl $curl
     * @throws \ErrorException
     */
    protected function addHandle(Curl $curl)
    {
        $mCurlErrorCode = curl_multi_add_handle($this->multiCurl, $curl->curl);
        if (!($mCurlErrorCode === CURLM_OK)) {
            throw new \ErrorException(sprintf('cURL multi add handle error: %s', curl_multi_strerror($mCurlErrorCode)));
        }
        $this->curls[] = $curl;

        if ($this->isStarted) {
            $this->initHandle($curl);
        }
    }

    /**
     * @param Curl $curl
     */
    protected function initHandle(Curl $curl)
    {
        // Set call backs, if not already individually set.
        if ($curl->getBeforeSendFunction() === null) {
            $curl->beforeSend($this->beforeSendFunction);
        }
        if ($curl->getSuccessFcuntion() === null) {
            $curl->success($this->successFunction);
        }
        if ($curl->getCompleteFunction() === null) {
            $curl->complete($this->completeFunction);
        }
        if ($curl->getErrorFunction() === null) {
            $curl->error($this->errorFunction);
        }

        foreach ($this->options as $option => $value) {
            $curl->setOpt($option, $value);
        }
        foreach ($this->headers as $key => $value) {
            $curl->setHeader($key, $value);
        }

        $curl->setJsonDecoder($this->jsonDecoder);
        $curl->setXmlDecoder($this->xmlDecoder);
        $curl->call($curl->getBeforeSendFunction());
    }
}
