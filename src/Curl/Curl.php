<?php

namespace Curl;

class Curl
{
    const VERSION = '1.0.0';
    const DEFAULT_TIMEOUT = 30;

    /**
     * @var array
     */
    public static $RFC2616 = [
        // RFC2616: "any CHAR except CTLs or separators".
        // CHAR           = <any US-ASCII character (octets 0 - 127)>
        // CTL            = <any US-ASCII control character
        //                  (octets 0 - 31) and DEL (127)>
        // separators     = "(" | ")" | "<" | ">" | "@"
        //                | "," | ";" | ":" | "\" | <">
        //                | "/" | "[" | "]" | "?" | "="
        //                | "{" | "}" | SP | HT
        // SP             = <US-ASCII SP, space (32)>
        // HT             = <US-ASCII HT, horizontal-tab (9)>
        // <">            = <US-ASCII double-quote mark (34)>
        '!', '#', '$', '%', '&', "'", '*', '+', '-', '.', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'B',
        'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
        'Y', 'Z', '^', '_', '`', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q',
        'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '|', '~',
    ];

    /**
     * @var array
     */
    public static $RFC6265 = [
        // RFC6265: "US-ASCII characters excluding CTLs, whitespace, DQUOTE, comma, semicolon, and backslash".
        // 0x21
        '!',
        // 0x23-2B
        '#', '$', '%', '&', "'", '(', ')', '*', '+',
        // 0x2D-3A
        '-', '.', '/', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ':',
        // 0x3C-5B
        '<', '=', '>', '?', '@', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q',
        'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '[',
        // 0x5D-7E
        ']', '^', '_', '`', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r',
        's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '{', '|', '}', '~',
    ];

    public $curl;
    public $id = null;

    public $error = false;
    public $errorCode = 0;
    public $errorMessage = null;

    public $curlError = false;
    public $curlErrorCode = 0;
    public $curlErrorMessage = null;

    public $httpError = false;
    public $httpErrorCode = 0;
    public $httpErrorMessage = null;

    public $baseUrl = null;
    public $url = null;
    public $requestHeaders = null;
    public $responseHeaders = null;
    public $rawResponseHeaders = '';
    public $response = null;
    public $rawResponse = null;

    public $beforeSendFunction = null;
    public $downloadCompleteFunction = null;
    public $successFunction = null;
    public $errorFunction = null;
    public $completeFunction = null;

    protected $cookies = [];
    protected $responseCookies = [];
    protected $headers = [];
    protected $options = [];

    protected $jsonDecoder = null;
    protected $jsonPattern = '/^(?:application|text)\/(?:[a-z]+(?:[\.-][0-9a-z]+){0,}[\+\.]|x-)?json(?:-[a-z]+)?/i';
    protected $xmlDecoder = null;
    protected $xmlPattern = '~^(?:text/|application/(?:atom\+|rss\+)?)xml~i';
    protected $defaultDecoder = null;

    protected static $deferredProperties = [
        'effectiveUrl',
        'totalTime'
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
    }
}
