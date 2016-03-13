<?php
define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR);
require BASE_PATH . 'include/loader.php';

//check for config exist
if (!file_exists(BASE_PATH . 'config.php')) {
    \Core\fatal('Please, create config.php file, you can use config.php.example to see how it should look.');
}
if (!file_exists(BASE_PATH . 'builds.txt')) {
    \Core\fatal('Please, create builds.txt file, you can use builds.txt.example to see how it should look.');
}

require BASE_PATH . 'config.php';

//check path to install and create directory

if ($argc < 2) {
    \Core\fatal('Please provide a path to install');
}
$pathToInstall = rtrim($argv[1], '/') . '/';
\IO\createDirectory($pathToInstall);

//download files

$buildNames = array_filter(explode("\n",file_get_contents(BASE_PATH . 'builds.txt')), function ($value) {
    return $value && strpos($value, '#') === false;
});
rsort($buildNames);

//only latest version for single install
if (isset($config['single_install']) && $config['single_install']) {
    $buildNames = [$buildNames[0]];
}

foreach ($buildNames as $_buildName) {
    $installer = new \Installer\Installer($config, $pathToInstall, $_buildName);

    //skip if installed
    if ($installer->isBuildInstalled()) {
        continue;
    }

    $installer->run();
}

\Core\printInfo('FINISHED');