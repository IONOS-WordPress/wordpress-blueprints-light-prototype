<?php

$phar = new Phar(filename : 'build/server.phar',alias : 'server.phar');

// add all files in the project
$files = $phar->buildFromDirectory(
  '/app/server',
  '/^\/.*(?<!Test\.php)$/' // exclude all files ending with "Test.php"
);

$phar->setStub($phar->createDefaultStub('public_html/index.php', 'public_html/index.php'));

$phar->compress(Phar::GZ);

echo json_encode(array_keys($files), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;