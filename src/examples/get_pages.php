<?php

require __DIR__ . '/../../vendor/autoload.php';

use Curl\Curl;

$curl = new Curl();

for ($i = 1; $i <= 10; $i++) {
    $curl->get('https://httpbin.org/get', ['page' => $i]);
    echo sprintf('Page: %d%s', $i, PHP_EOL);
    var_dump($curl->response);
}
