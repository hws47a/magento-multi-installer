<?php
namespace Core;

function fatal($message)
{
    die("FATAL: $message\n");
}

function createDirectory($name)
{
    if (!file_exists($name)) {
        mkdir($name, 0777, true);
    }

    if (!is_dir($name)) {
        fatal($name . ' is not a directory');
    }

    if (!is_writable($name)) {
        fatal($name . ' is not a writable');
    }
}

function printInfo($info)
{
    print "==> $info\n";
}

function downloadFile($from, $to)
{
    createDirectory('temp/cache/builds/');
    $tmpPath = 'temp/' . $to;
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