<?php

require __DIR__ . '/../../vendor/autoload.php';

use Curl\Curl;

$curl = new Curl();

$myFile = curl_file_create('assets/php.png', 'image/png', 'Foo Bar');

$curl->post('https://httpbin.org/post', ['myFile' => $myFile]);

if ($curl->error) {
    $message = sprintf('Error: %s', $curl->errorMessage);
} else {
    $message = 'Success';
}
echo sprintf('%s%s', $message, PHP_EOL);
