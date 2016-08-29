<?php

namespace Curl;

class Curl implements CurlInterface
{
    const VERSION = '1.0.0';
    const DEFAULT_TIMEOUT = 30;

    public $curl;

    public $error = false;
    public $errorCode = 0;
    public $errorMessage = null;

    public $curlError = false;
    public $curlErrorCode = 0;
    public $curlErrorMessage = null;

    public $httpError = false;
    public $httpStatusCode = 0;
    public $httpErrorMessage = null;

    public $baseUrl = null;
    public $url = null;
    public $requestHeaders = null;
    public $responseHeaders = null;
    public $rawResponseHeaders = '';
    public $response = null;
    public $rawResponse = null;

    protected $beforeSendFunction = null;
    protected $downloadCompleteFunction = null;
    protected $successFunction = null;
    protected $errorFunction = null;
    protected $completeFunction = null;

    protected $cookies = [];
    protected $responseCookies = [];
    protected $headers = [];
    protected $options = [];

    protected $jsonDecoder = null;
    protected $jsonPattern = self::PATTERN_JSON;
    protected $xmlDecoder = null;
    protected $xmlPattern = self::PATTERN_XML;
    protected $defaultDecoder = null;

    protected $rfc2616 = [];
    protected $rfc6265 = [];

    protected static $deferredProperties = [
        self::PROPERTY_EFFECTIVE_URL,
        self::PROPERTY_TOTAL_TIME
    ];

    /**
     * Curl constructor.
     * @param null $baseUrl
     * @throws \ErrorException
     */
    public function __construct($baseUrl = null)
    {
        if (!extension_loaded('curl')) {
            throw new \ErrorException('cURL extension not loaded.');
        }

        $this->curl = curl_init();
        $this->setDefaultUserAgent();
        $this->setDefaultXmlDecoder();
        $this->setDefaultTimeout();
        $this->setOpt(CURLINFO_HEADER_OUT, true);
        $this->setOpt(CURLOPT_HEADERFUNCTION, [$this, 'headerCallback']);
        $this->setOpt(CURLOPT_RETURNTRANSFER, true);
        $this->headers = new CaseInsensitiveArray();
        $this->setUrl($baseUrl);
        $this->rfc2616 = array_fill_keys(CurlRfc::getRfc2616(), true);
        $this->rfc6265 = array_fill_keys(CurlRfc::getRfc6265(), true);
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        $return = null;

        if (in_array($name, self::$deferredProperties) && (is_callable([$this, $getter = sprintf('__get%s', $name)]))) {
            $return = $this->$name = $this->$getter;
        }

        return $return;
    }

    /**
     * @return void
     */
    public function close()
    {
        if (is_resource($this->curl)) {
            curl_close($this->curl);
        }
        $this->options = null;
        $this->jsonDecoder = null;
        $this->xmlDecoder = null;
    }

    /**
     * @return callable|null
     */
    public function getBeforeSendFunction()
    {
        return $this->beforeSendFunction;
    }

    /**
     * @param $callback
     * @return $this
     */
    public function beforeSend($callback)
    {
        $this->beforeSendFunction = $callback;

        return $this;
    }

    /**
     * @return callable|null
     */
    public function getCompleteFunction()
    {
        return $this->completeFunction;
    }

    /**
     * @param $callback
     * @return $this
     */
    public function complete($callback)
    {
        $this->completeFunction = $callback;

        return $this;
    }

    /**
     * @return callable|null
     */
    public function getErrorFunction()
    {
        return $this->errorFunction;
    }

    /**
     * @param $callback
     * @return $this
     */
    public function error($callback)
    {
        $this->errorFunction = $callback;

        return $this;
    }

    /**
     * @param $callback
     * @return $this
     */
    public function progress($callback)
    {
        $this->setOpt(CURLOPT_PROGRESSFUNCTION, $callback);
        $this->setOpt(CURLOPT_NOPROGRESS, false);

        return $this;
    }

    /**
     * @return void
     */
    public function call()
    {
        $args = func_get_args();
        $function = array_shift($args);
        if (is_callable($function)) {
            array_unshift($args, $this);
            call_user_func_array($function, $args);
        }
    }

    public function delete($url, $queryParameters = [], $data = [])
    {
        if (is_array($url)) {
            $data = $queryParameters;
            $queryParameters = $url;
            $url = $this->baseUrl;
        }

        $this->setUrl($url, $queryParameters);
        $this->setOpt(CURLOPT_CUSTOMREQUEST, self::REQUEST_DELETE);
        $this->setOpt(CURLOPT_POSTFIELDS, $this->buildPostData($data));
    }

    /**
     * @param $url
     * @param $mixedFilename
     * @return bool
     */
    public function download($url, $mixedFilename)
    {
        if (is_callable($mixedFilename)) {
            $this->downloadCompleteFunction = $mixedFilename;
            $fh = tmpfile();
        } else {
            $fh = fopen($mixedFilename, 'wb');
        }

        $this->setOpt(CURLOPT_FILE, $fh);
        $this->get($url);
        $this->downloadComplete($fh);

        return $this->error;
    }

    /**
     * @param $url
     * @param array $data
     * @return mixed|null
     */
    public function get($url, $data = [])
    {
        if (is_array($url)) {
            $data = $url;
            $url = $this->baseUrl;
        }
        $this->setUrl($url, $data);
        $this->setOpt(CURLOPT_CUSTOMREQUEST, self::REQUEST_GET);
        $this->setOpt(CURLOPT_HTTPGET, true);

        return $this->exec();
    }

    /**
     * @param $url
     * @param $data
     * @param bool $follow303WithPost
     * @return mixed|null
     */
    public function post($url, $data, $follow303WithPost = false)
    {
        if (is_array($url)) {
            $data = $url;
            $url = $this->baseUrl;
        }
        $this->setUrl($url);

        if ($follow303WithPost) {
            $this->setOpt(CURLOPT_CUSTOMREQUEST, self::REQUEST_POST);
        } else {
            if (isset($this->options[CURLOPT_CUSTOMREQUEST])) {
                if (version_compare(PHP_VERSION, '5.5.11') < 1 || defined('HHVM_VERSION')) {
                    trigger_error('Due to technical limitations of PHP <= 5.5.11 it is not possible to perform a '
                        . 'post-redirect-get request using Curl object that has already been used to perform other '
                        . 'types of requests. Either use a new Curl object or update your PHP version.');
                } else {
                    $this->setOpt(CURLOPT_CUSTOMREQUEST, null);
                }
            }
        }

        $this->setOpt(CURLOPT_POST, true);
        $this->setOpt(CURLOPT_POSTFIELDS, $this->buildPostData($data));

        return $this->exec();
    }

    /**
     * @param $url
     * @param array $data
     * @return mixed|null
     */
    public function put($url, $data = [])
    {
        if (is_array($url)) {
            $data = $url;
            $url = $this->baseUrl;
        }
        $this->setUrl($url);

        $this->setOpt(CURLOPT_CUSTOMREQUEST, self::REQUEST_PUT);

        $data = $this->buildPostData($data);
        if (empty($this->options[CURLOPT_INFILE]) && empty($this->options[CURLOPT_INFILESIZE])) {
            $this->setHeader(self::HEADER_CONTENT_LENGTH, strlen($data));
        }
        if (!empty($data)) {
            $this->setOpt(CURLOPT_POSTFIELDS, $data);
        }

        return $this->exec();
    }

    /**
     * @param $username
     * @param string $password
     */
    public function setBasicAuthentication($username, $password = '')
    {
        $this->setOpt(CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $this->setOpt(CURLOPT_USERPWD, sprintf('%s:%s', $username, $password));
    }

    /**
     * @param $username
     * @param string $password
     */
    public function setDigestAuthentication($username, $password = '')
    {
        $this->setOpt(CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        $this->setOpt(CURLOPT_USERPWD, sprintf('%s:%s', $username, $password));
    }

    /**
     * @param $url
     * @param array $data
     * @return $this
     */
    public function setUrl($url, $data = [])
    {
        $this->baseUrl = $url;
        $this->url = $this->buildUrl($url, $data);
        $this->setOpt(CURLOPT_URL, $this->url);

        return $this;
    }

    /**
     * @param $key
     * @param $value
     */
    public function setCookie($key, $value)
    {
        $nameChars = [];
        foreach (str_split($key) as $nameChar) {
            if (!isset($this->rfc2616[$nameChar])) {
                $nameChars[] = rawurlencode($nameChar);
            } else {
                $nameChars[] = $nameChar;
            }
        }

        $valueChars = [];
        foreach (str_split($value) as $valueChar) {
            if (!isset($this->rfc6265[$valueChar])) {
                $valueChars[] = rawurlencode($valueChar);
            } else {
                $valueChars[] = $valueChar;
            }
        }

        $this->cookies[implode('', $nameChars)] = implode('', $valueChars);
        $this->setOpt(CURLOPT_COOKIE, implode(';', array_map(function ($k, $v) {
            return sprintf('%s = %s', $k, $v);
        }, array_keys($this->cookies), array_values($this->cookies))));
    }

    /**
     * @return array
     */
    public function getCookies()
    {
        return $this->cookies;
    }

    /**
     * @param $key
     * @return array|null
     */
    public function getCookie($key)
    {
        return isset($this->cookies[$key]) ? $this->cookies[$key] : null;
    }

    /**
     * @return array
     */
    public function getResponseCookies()
    {
        return $this->responseCookies;
    }

    /**
     * @param $key
     * @return array|null
     */
    public function getResponseCookie($key)
    {
        return isset($this->responseCookies[$key]) ? $this->responseCookies[$key] : null;
    }

    /**
     * @param $port
     * @return $this
     */
    public function setPort($port)
    {
        $this->setOpt(CURLOPT_PORT, intval($port));

        return $this;
    }

    /**
     * @param $seconds
     * @return $this
     */
    public function setConnectTimeout($seconds)
    {
        $this->setOpt(CURLOPT_CONNECTTIMEOUT, intval($seconds));

        return $this;
    }

    /**
     * @param $string
     * @return $this
     */
    public function setCookieString($string)
    {
        $this->setOpt(CURLOPT_COOKIE, $string);

        return $this;
    }

    /**
     * @param $file
     * @return $this
     */
    public function setCookieFile($file)
    {
        $this->setOpt(CURLOPT_COOKIEFILE, $file);

        return $this;
    }

    /**
     * @param $jar
     * @return $this
     */
    public function setCookieJar($jar)
    {
        $this->setOpt(CURLOPT_COOKIEJAR, $jar);

        return $this;
    }

    /**
     * @return $this
     */
    public function setDefaultJsonDecoder()
    {
        $args = func_get_args();
        $this->jsonDecoder = function ($response) use ($args) {
            array_unshift($args, $response);
            if (version_compare(PHP_VERSION, '5.4.0', '<')) {
                $args = array_slice($args, 0, 3);
            }


            $jsonObj = call_user_func_array('json_decode', $args);
            if (!($jsonObj === null)) {
                $response = $jsonObj;
            }

            return $response;
        };

        return $this;
    }

    /**
     * return $this
     */
    public function setDefaultXmlDecoder()
    {
        $this->xmlDecoder = function ($response) {
            $xmlObj = @simplexml_load_string($response);
            if (!($xmlObj === false)) {
                $response = $xmlObj;
            }

            return $response;
        };

        return $this;
    }

    /**
     * @param string $decoder
     * @return $this
     */
    public function setDefaultDecoder($decoder = 'json')
    {
        if (is_callable($decoder)) {
            $this->defaultDecoder = $decoder;
        } elseif ($decoder === self::DECODER_JSON) {
            $this->defaultDecoder = $this->jsonDecoder;
        } elseif ($decoder === self::DECODER_XML) {
            $this->defaultDecoder = $this->xmlDecoder;
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function setDefaultTimeout()
    {
        $this->setTimeout(self::DEFAULT_TIMEOUT);

        return $this;
    }

    /**
     * @param $seconds
     * @return $this
     */
    public function setTimeout($seconds)
    {
        $this->setOpt(CURLOPT_TIMEOUT, intval($seconds));

        return $this;
    }

    /**
     * @return $this
     */
    public function setDefaultUserAgent()
    {
        $userAgent = sprintf(
            'phpCurl %s (+https://nu3.de) PHP/%s cURL/%s',
            self::VERSION,
            PHP_VERSION,
            curl_version()['version']
        );
        $this->setUserAgent($userAgent);

        return $this;
    }

    /**
     * @param $userAgent
     * @return $this
     */
    public function setUserAgent($userAgent)
    {
        $this->setOpt(CURLOPT_USERAGENT, $userAgent);

        return $this;
    }

    /**
     * @param $decoder
     * @return $this
     */
    public function setJsonDecoder($decoder)
    {
        if (is_callable($decoder)) {
            $this->jsonDecoder = $decoder;
        }

        return $this;
    }

    /**
     * @param $decoder
     * @return $this
     */
    public function setXmlDecoder($decoder)
    {
        if (is_callable($decoder)) {
            $this->xmlDecoder = $decoder;
        }

        return $this;
    }

    /**
     * @param $referrer
     * @return $this
     */
    public function setReferrer($referrer)
    {
        $this->setOpt(CURLOPT_REFERER, $referrer);

        return $this;
    }

    /**
     * @return callable|null
     */
    public function getSuccessFcuntion()
    {
        return $this->successFunction;
    }

    /**
     * @param $callback
     * @return $this
     */
    public function success($callback)
    {
        $this->successFunction = $callback;
        
        return $this;
    }

    /**
     * @param $url
     * @param array $data
     * @return mixed|null
     */
    public function head($url, $data = [])
    {
        if (is_array($url)) {
            $data = $url;
            $url = $this->baseUrl;
        }
        $this->setUrl($url, $data);
        $this->setOpt(CURLOPT_CUSTOMREQUEST, self::REQUEST_HEAD);
        $this->setOpt(CURLOPT_NOBODY, true);

        return $this->exec();
    }

    /**
     * @param $url
     * @param array $data
     * @return mixed|null
     */
    public function options($url, $data = [])
    {
        if (is_array($url)) {
            $data = $url;
            $url = $this->baseUrl;
        }
        $this->setUrl($url, $data);
        $this->unsetHeader(self::HEADER_CONTENT_LENGTH);
        $this->setOpt(CURLOPT_CUSTOMREQUEST, self::REQUEST_OPTIONS);

        return $this->exec();
    }

    /**
     * @param $url
     * @param array $data
     * @return mixed|null
     */
    public function patch($url, $data = [])
    {
        if (is_array($url)) {
            $data = $url;
            $url = $this->baseUrl;
        }

        if (is_array($data) && empty($data)) {
            $this->unsetHeader(self::HEADER_CONTENT_LENGTH);
        }

        $this->setUrl($url, $data);
        $this->setOpt(CURLOPT_CUSTOMREQUEST, self::REQUEST_PATCH);
        $this->setOpt(CURLOPT_POSTFIELDS, $this->buildPostData($data));

        return $this->exec();
    }

    /**
     * @param bool $on
     * @param $output
     */
    public function verbose($on = true, $output = STDERR)
    {
        if ($on) {
            $this->setOpt(CURLINFO_HEADER_OUT, false);
        }
        $this->setOpt(CURLOPT_VERBOSE, $on);
        $this->setOpt(CURLOPT_STDERR, $output);
    }

    /**
     * @param $option
     * @param $value
     * @return bool
     */
    public function setOpt($option, $value)
    {
        $requiredOptions = [CURLOPT_RETURNTRANSFER => self::OPT_RETURN_TRANSFER];

        if (in_array($option, array_keys($requiredOptions), true) && !($value === true)) {
            trigger_error(sprintf('%s is a required option', $requiredOptions[$option]));
        }

        $this->options[$option] = $value;

        return curl_setopt($this->curl, $option, $value);
    }

    /**
     * @param $key
     * @param $value
     */
    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value;
        $headers = [];
        foreach ($this->headers as $key => $header) {
            $headers[] = sprintf('%s:%s', $key, $header);
        }
        $this->setOpt(CURLOPT_HTTPHEADER, $headers);
    }

    /**
     * @param $key
     * @return $this
     */
    protected function unsetHeader($key)
    {
        $this->setHeader($key, '');
        unset($this->headers[$key]);

        return $this;
    }

    /**
     * @param $ch
     * @param $header
     * @return int
     */
    protected function headerCallback($ch, $header)
    {
        if (preg_match(self::PATTERN_SET_COOKIE, $header, $cookie) === 1) {
            $this->responseCookies[$cookie[1]] = trim($cookie[2], " \n\r\t\x0B");
        }
        $this->rawResponseHeaders .= $header;

        return strlen($header);
    }

    /**
     * @param $data
     * @return array|string
     */
    protected function buildPostData($data)
    {
        if (is_array($data)) {
            if (isset($this->headers[self::HEADER_CONTENT_TYPE]) &&
                preg_match($this->jsonPattern, $this->headers[self::HEADER_CONTENT_TYPE])) {
                $jsonStr = json_encode($data);
                if (!($jsonStr === false)) {
                    $data = $jsonStr;
                }
            } elseif ($this->isMultiDimensionalArray($data)) {
                $data = $this->httpBuildMultiQuery($data);
            } else {
                $binaryData = false;
                foreach ($data as $key => $value) {
                    if (is_array($value) && empty($value)) {
                        // Fix "Notice: Array to string conversion" when $value in curl_setopt($ch, CURLOPT_POSTFIELDS,
                        // $value) is an array that contains an empty array.
                        $data[$key] = '';
                    } elseif (is_string($value) && strpos($value, '@') === 0 && is_file(substr($value, 1))) {
                        // Fix "curl_setopt(): The usage of the @filename API for file uploading is deprecated.
                        // Please use the CURLFile class instead". Ignore non-file values prefixed with the @ character.
                        $binaryData = true;
                        if (class_exists('CURLFile')) {
                            $data[$key] = new \CURLFile(substr($value, 1));
                        }
                    } elseif ($value instanceof \CURLFile) {
                        $binaryData = true;
                    }
                }

                if (!$binaryData) {
                    $data = http_build_query($data, '', '&');
                }
            }
        }

        return $data;
    }

    /**
     * @param $handle
     */
    protected function downloadComplete($handle)
    {
        if (!$this->error && $this->downloadCompleteFunction) {
            rewind($handle);
            $this->call($this->downloadCompleteFunction, $handle);
            $this->downloadCompleteFunction = null;
        }

        if (is_resource($handle)) {
            fclose($handle);
        }

        if (!defined('STDOUT')) {
            define('STDOUT', fopen('php://stdout', 'w'));
        }

        $this->setOpt(CURLOPT_FILE, STDOUT);
        $this->setOpt(CURLOPT_RETURNTRANSFER, true);
    }

    /**
     * @param null $ch
     * @return mixed|null
     */
    protected function exec($ch = null)
    {
        $this->responseCookies = [];

        if (!($ch === null)) {
            $this->rawResponse = curl_multi_getcontent($ch);
        } else {
            $this->call($this->beforeSendFunction);
            $this->rawResponse = curl_exec($this->curl);
            $this->curlErrorCode = curl_errno($this->curl);
        }

        $this->curlErrorMessage = curl_error($this->curl);
        $this->curlError = !($this->curlErrorCode === 0);
        $this->httpStatusCode = $this->getInfo(CURLINFO_HTTP_CODE);
        $this->httpError = in_array(floor($this->httpStatusCode / 100), [4, 5]);
        $this->error = $this->curlError || $this->httpError;
        $this->errorCode = $this->error ? ($this->curlError ? $this->curlErrorCode : $this->httpStatusCode) : 0;

        if ($this->getOpt(CURLINFO_HEADER_OUT) === true) {
            $this->requestHeaders = $this->parseRequestHeaders($this->getInfo(CURLINFO_HEADER_OUT));
        }
        $this->responseHeaders = $this->parseResponseHeaders($this->rawResponseHeaders);
        $this->response = $this->parseResponse($this->responseHeaders, $this->rawResponse);

        $this->httpErrorMessage = '';
        if ($this->error) {
            if (isset($this->responseHeaders[self::HEADER_STATUS_LINE])) {
                $this->httpErrorMessage = $this->responseHeaders[self::HEADER_STATUS_LINE];
            }
        }
        $this->errorMessage = $this->curlError ? $this->curlErrorMessage : $this->httpErrorMessage;

        if (!$this->error) {
            $this->call($this->successFunction);
        } else {
            $this->call($this->errorFunction);
        }

        $this->call($this->completeFunction);

        return $this->response;
    }

    /**
     * @param $rawHeaders
     * @return array
     */
    protected function parseHeaders($rawHeaders)
    {
        $rawHeaders = preg_split('/\r\n/', $rawHeaders, PREG_SPLIT_NO_EMPTY);
        $httpHeaders = new CaseInsensitiveArray();

        $rawHeadersCount = count($rawHeaders);
        for ($i = 1; $i < $rawHeadersCount; $i++) {
            list($key, $value) = explode(':', $rawHeaders[$i], 2);
            $key = trim($key);
            $value = trim($value);
            if (isset($httpHeaders[$key])) {
                $httpHeaders[$key] .= sprintf(',%s', $value);
            } else {
                $httpHeaders[$key] = $value;
            }
        }

        return [
            isset($rawHeaders[0]) ? $rawHeaders[0] : '',
            $httpHeaders
        ];
    }

    /**
     * @param $rawHeaders
     * @return CaseInsensitiveArray
     */
    protected function parseRequestHeaders($rawHeaders)
    {
        $requestHeaders = new CaseInsensitiveArray();
        list($firstLine, $headers) = $this->parseHeaders($rawHeaders);
        $requestHeaders[self::HEADER_REQUEST_LINE] = $firstLine;
        foreach ($headers as $key => $header) {
            $requestHeaders[$key] = $header;
        }

        return $requestHeaders;
    }

    /**
     * @param $responseHeaders
     * @param $rawResponse
     * @return mixed
     */
    protected function parseResponse($responseHeaders, $rawResponse)
    {
        $response = $rawResponse;
        if (isset($responseHeaders[self::HEADER_CONTENT_TYPE])) {
            if (preg_match($this->jsonPattern, $responseHeaders[self::HEADER_CONTENT_TYPE])) {
                $jsonDecoder = $this->jsonDecoder;
                if (is_callable($jsonDecoder)) {
                    $response = $jsonDecoder($response);
                }
            } elseif (preg_match($this->xmlPattern, $responseHeaders[self::HEADER_CONTENT_TYPE])) {
                $xmlDecoder = $this->xmlDecoder;
                if (is_callable($xmlDecoder)) {
                    $response = $xmlDecoder($response);
                }
            } else {
                $decoder = $this->defaultDecoder;
                if (is_callable($decoder)) {
                    $response = $decoder($response);
                }
            }
        }

        return $response;
    }

    /**
     * @param $rawResponseHeaders
     * @return string
     */
    protected function parseResponseHeaders($rawResponseHeaders)
    {
        $responsHeaderArray = explode("\r\n\r\n", $rawResponseHeaders);
        $responseHeader = '';
        for ($i = count($responsHeaderArray) - 1; $i >= 0; $i--) {
            if (stripos($responsHeaderArray[$i], 'HTTP/') === 0) {
                $responseHeader = $responsHeaderArray[$i];
                break;
            }
        }

        return $responseHeader;
    }

    /**
     * @param $option
     * @return array
     */
    protected function getOpt($option)
    {
        return $this->options[$option];
    }

    /**
     * @param $opt
     * @return mixed
     */
    protected function getInfo($opt)
    {
        return curl_getinfo($this->curl, $opt);
    }

    /**
     * @param $url
     * @param array $data
     * @return string
     */
    protected function buildUrl($url, $data = [])
    {
        return sprintf('%s%s', $url, empty($data) ? '' : sprintf('?%s', http_build_query($data)));
    }

    /**
     * @param $data
     * @param null $key
     * @return string
     */
    protected function httpBuildMultiQuery($data, $key = null)
    {
        $query = [];

        if (empty($data)) {
            return sprintf('%s=', $key);
        }

        foreach ($data as $k => $value) {
            if (is_string($value) || is_numeric($value)) {
                $brackets = $this->isArrayAssoc($data) ? sprintf('[%s]', $k) : '[]';
                $query[] = sprintf(
                    '%s=%s',
                    urlencode($key === null ? $k : sprintf('%s%s', $key, $brackets)),
                    rawurlencode($value)
                );
            } elseif (is_array($value)) {
                $nested = $key === null ? $k : sprintf('%s[%s]', $key, $k);
                $query[] = $this->httpBuildMultiQuery($value, $nested);
            }
        }

        return implode('&', $query);
    }

    /**
     * @return mixed
     */
    protected function getEffectiveUrl()
    {
        return $this->getInfo(CURLINFO_EFFECTIVE_URL);
    }

    /**
     * @return mixed
     */
    protected function getTotalTime()
    {
        return $this->getInfo(CURLINFO_TOTAL_TIME);
    }

    /**
     * @param $array
     * @return bool
     */
    protected function isMultiDimensionalArray($array)
    {
        if (!is_array($array)) {
            return false;
        }

        return (bool)count(array_filter($array, 'is_array'));
    }

    /**
     * @param $array
     * @return bool
     */
    protected function isArrayAssoc($array)
    {
        return (bool)count(array_filter(array_keys($array), 'is_string'));
    }
}
