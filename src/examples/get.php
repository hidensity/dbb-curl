<?php

require __DIR__ . '/../../vendor/autoload.php';

use Curl\Curl;

$curl = new Curl();
$curl->get('https://www.google.de');

if ($curl->error) {
    echo sprintf('Error [%s]: %s%s', $curl->errorCode, $curl->errorMessage, PHP_EOL);
} else {
    echo sprintf('Response: %s', PHP_EOL);
    var_dump($curl->response);
}
