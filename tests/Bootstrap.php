<?php
date_default_timezone_set('UTC');
include 'init_autoloader.php';
if(!file_exists(__DIR__.'/tmp')) {
    mkdir(__DIR__.'/tmp');
}
if(!class_exists('PHPUnit\Framework\TestCase')) {
    include __DIR__.'/travis/patch55.php';
}
