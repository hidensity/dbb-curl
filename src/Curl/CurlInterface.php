<?php

namespace Curl;

interface CurlInterface
{
    const PATTERN_JSON = '/^(?:application|text)\/(?:[a-z]+(?:[\.-][0-9a-z]+){0,}[\+\.]|x-)?json(?:-[a-z]+)?/i';
    const PATTERN_XML = '~^(?:text/|application/(?:atom\+|rss\+)?)xml~i';
    const PATTERN_SET_COOKIE = '/^Set-Cookie:\s*([^=]+)=([^;]+)/mi';
    
    const PROPERTY_EFFECTIVE_URL = 'effectiveUrl';
    const PROPERTY_TOTAL_TIME = 'totalTime';

    const HEADER_CONTENT_TYPE = 'Content-Type';
    const HEADER_REQUEST_LINE = 'Request-Line';
    const HEADER_STATUS_LINE = 'Status-Line';
    const HEADER_CONTENT_LENGTH = 'Content-Length';

    const OPT_RETURN_TRANSFER = 'CURLOPT_RETURNTRANSFER';

    const REQUEST_DELETE = 'DELETE';
    const REQUEST_GET = 'GET';
    const REQUEST_HEAD = 'HEAD';
    const REQUEST_OPTIONS = 'OPTIONS';
    const REQUEST_PATCH = 'PATCH';
}
