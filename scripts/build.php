<?php

$phar = new Phar(filename : 'build/server.phar',alias : 'server.phar');

// add all files in the project
$files = $phar->buildFromDirectory(
  '/app/server',
  '/^\/.*(?<!Test\.php)$/' // exclude all files ending with "Test.php"
);

var_dump($files);

$phar->setStub($phar->createDefaultStub('public_html/index.php', 'public_html/index.php'));