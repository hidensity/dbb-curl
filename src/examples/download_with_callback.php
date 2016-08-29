<?php

require __DIR__ . '/../../vendor/autoload.php';

use Curl\Curl;

$curl = new Curl();

$curl->download('https://secure.php.net/images/logos/php-med-trans.png', function ($instance, $tmpFile) {
    $saveToPath = sprintf('/tmp/%s', basename($instance->url));
    $fh = fopen($saveToPath, 'wb');
    stream_copy_to_stream($tmpFile, $fh);
    fclose($fh);
});
