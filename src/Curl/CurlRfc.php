<?php

namespace Curl;

class CurlRfc
{
    /**
     * @return array
     */
    public static function getRfc2616()
    {
        return [
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
            '!', '#', '$', '%', '&', "'", '*', '+', '-', '.', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'A',
            'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V',
            'W', 'X', 'Y', 'Z', '^', '_', '`', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n',
            'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '|', '~',
        ];
    }

    /**
     * @return array
     */
    public static function getRfc6265()
    {
        return [
            // RFC6265: "US-ASCII characters excluding CTLs, whitespace, DQUOTE, comma, semicolon, and backslash".
            // 0x21
            '!',
            // 0x23-2B
            '#', '$', '%', '&', "'", '(', ')', '*', '+',
            // 0x2D-3A
            '-', '.', '/', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', ':',
            // 0x3C-5B
            '<', '=', '>', '?', '@', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
            'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '[',
            // 0x5D-7E
            ']', '^', '_', '`', 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q',
            'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '{', '|', '}', '~',
        ];
    }
}
