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

$buildNames = explode("\n",file_get_contents(BASE_PATH . 'builds.txt'));
foreach ($buildNames as $_key => $_buildName) {
    //skip empty lines and lines with #
    if (!$_buildName || strpos($_buildName, '#') !== false) {
        continue;
    }

    //skip if installed
    if (\Installer\isBuildInstalled($pathToInstall, $_buildName)) {
        continue;
    }

    \Installer\downloadBuild($_buildName);
    \Installer\unpackBuild($pathToInstall, $_buildName);
    \Installer\createDb($_buildName);

    if ($config['install_sample_data']) {
        \Installer\downloadSampleData($_buildName);
        \Installer\unpackSampleData($pathToInstall, $_buildName);
        \Installer\applySampleData($pathToInstall, $_buildName);
    }

    \Installer\prepareInstall($pathToInstall, $_buildName);
    \Installer\install($pathToInstall, $_buildName);
    \Installer\reindex($pathToInstall, $_buildName);
}

\Core\printInfo('FINISHED');