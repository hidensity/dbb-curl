<?php

require __DIR__ . '/../../vendor/autoload.php';

use Curl\Curl;

$curl = new Curl();

$oldProgress = -1;

$curl->progress(function ($client, $downloadSize, $downloaded, $uploadSize, $uploaded) use (&$oldProgress) {
    if ($downloadSize === 0) {
        return;
    }

    // Display progress bar like: xxx% [=====>      ]
    $progressSize = 40;
    $fractionDownloaded = $downloaded / $downloadSize;
    $dots = round($fractionDownloaded * $progressSize);
    if ($oldProgress == $dots &&
        $fractionDownloaded != 1) {
        return;
    }
    $oldProgress = $dots;

    printf('%3.0f%% [', $fractionDownloaded * 100);
    for ($i = 0; $i < $dots - 1; $i++) {
        echo '=';
    }
    echo '>';
    for (; $i < $progressSize - 1; $i++) {
        echo ' ';
    }
    echo ']' . "\r";
});

$curl->complete(function ($instance) {
    echo "\n" . 'Download complete.' . "\n";
});

$curl->download('http://dbberlin.xyz/public/php_manual_en.html.gz', '/tmp/php_manual_en.html.gz');
