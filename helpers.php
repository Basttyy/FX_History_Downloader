<?php

$gh_repo = 'FX-Data';

$pairs = [
    'EURUSD' => ['2022', '2021', '2019'],
    'XAUUSD' => ['2022', '2021', '2020', '2019', '2018'],
];

$timframes = [
    '1',                //1 min
    '2',                //2 min
    '3',                //3 min
    '4',                //4 min
    '5',                //5 min
    '6',                //6 min
    '10',               //10 min
    '12',               //12 min
    '15',               //15 min
    '20',               //20 min
    '30',               //30 min
    '60',               //1 hr
    '120',              //2 hr
    '180',              //3 hr
    '240',              //4 hr
    '360',              //6 hr
    '480',              //8 hr
    '720',              //12 hr
    '1440',             //1 D
    '10080',            //1 W
    '43200',            //1 M
];

$exclude_timeframes = [
    '2',                //2 min
    '3',                //3 min
    '4',                //4 min
    '6',                //6 min
    '10',               //10 min
    '12',               //12 min
    '20',               //20 min
    '120',              //2 hr
    '180',              //3 hr
    '360',              //6 hr
    '480',              //8 hr
    '720',              //12 hr
];

//show upload and download progress
$on_progress = function ($download_total, $download_bytes, $upload_total, $upload_bytes) use (&$prev_down_bytes, &$prev_up_bytes) {
    if ($prev_up_bytes === 0 && $upload_total === 0) {
        $prev_up_bytes = 1;
        echo ("expected upload: $upload_total,  uploaded: $upload_bytes --- 0%").PHP_EOL;
    } else {
        if ($prev_up_bytes < $upload_bytes && $upload_bytes != 0) {
            $percent = ((float)$download_total/(float)$download_bytes)*100;
            echo ("expected upload: $upload_total,  uploaded: $upload_bytes --- $percent%").PHP_EOL;
        }
    }
    if ($prev_down_bytes === 0 && $download_total === 0) {
        $prev_down_bytes = 1;
        echo ("expected download: $download_total,  downloaded: $download_bytes --- 0%").PHP_EOL;
    } else {
        if ($prev_down_bytes < $download_bytes) {
            $percent = ((float)$download_bytes/(float)$download_total)*100;
            echo ("expected download: $download_total,  downloaded: $download_bytes --- $percent%").PHP_EOL;
        }
    }
};