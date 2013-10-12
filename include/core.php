<?php
namespace Core;

function fatal($message)
{
    die("FATAL: $message\n");
}

function printInfo($info)
{
    print "==> $info\n";
}