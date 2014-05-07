<?php
namespace IO;

function createDirectory($name)
{
    if (!file_exists($name)) {
        mkdir($name, 0777, true);
    }

    if (!is_dir($name)) {
        \Core\fatal($name . ' is not a directory');
    }

    if (!is_writable($name)) {
        \Core\fatal($name . ' is not a writable');
    }
}

function downloadFile($from, $to)
{
    createDirectory(BASE_PATH .'temp/cache/builds/');
    createDirectory(BASE_PATH .'cache/builds/');
    $tmpPath = BASE_PATH . 'temp/' . basename($to);
    if (file_exists($tmpPath)) {
        unlink($tmpPath);
    }

    $fp = fopen($tmpPath, 'w');
    $ch = curl_init($from);
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_exec($ch);
    curl_close($ch);
    rename($tmpPath, $to);
}