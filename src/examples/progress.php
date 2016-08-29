<?php

require __DIR__ . '/../../vendor/autoload.php';

use Curl\Curl;

$curl = new Curl();

$curl->progress(function ($client, $downloadSize, $downloaded, $uploadSize, $uploaded) {
    if ($downloadSize === 0) {
        return;
    }

    $percentage = floor($downloaded * 100 / $downloadSize);
    echo ' ' . $percentage . '%' . "\r";
});

$curl->download('http://dbberlin.xyz/public/php_manual_en.html.gz', '/tmp/php_manual_en.html.gz');
