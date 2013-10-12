<?php
require 'config.php';
require 'functions.php';

$url = 'http://www.magentocommerce.com/downloads/assets/%s/magento-%s.tar.bz2';
$fileName = 'magento-%s.tar.bz2';
$cacheBuilds = 'cache/builds/';

\Core\createDirectory($cacheBuilds);

//download files

$buildNames = explode("\n",file_get_contents('builds.txt'));
foreach ($buildNames as $_key => $_buildName) {
    //skip lines with #
    if (strpos($_buildName, '#') !== false) {
        unset($buildNames[$_key]);
        continue;
    }
    if (!file_exists($cacheBuilds . sprintf($fileName, $_buildName))) {
        \Core\printInfo('Download magento ' . $_buildName);
        \Core\printInfo('Download magento ' . sprintf($url, $_buildName, $_buildName));
        \Core\printInfo('Download magento ' . $cacheBuilds . sprintf($fileName, $_buildName));
        \Core\downloadFile(sprintf($url, $_buildName, $_buildName) , $cacheBuilds . sprintf($fileName, $_buildName));
    }
}
unset($_buildName);

//install magento
if ($argc < 2) {
    \Core\fatal('Please provide a path to install');
}

$pathToInstall = rtrim($argv[1], '/') . '/';
\Core\createDirectory($pathToInstall);
foreach ($buildNames as $_buildName) {
    if (!file_exists("{$pathToInstall}$_buildName")) {
        \Core\printInfo('Unarchive ' . $_buildName);
        $archive = realpath($cacheBuilds). '/' . sprintf($fileName, $_buildName);
        \Core\createDirectory("{$pathToInstall}temp/$_buildName");
        `cd {$pathToInstall}temp/$_buildName && tar xjf $archive`;
        if (file_exists("{$pathToInstall}temp/$_buildName")) {
            rename("{$pathToInstall}temp/$_buildName/magento", "{$pathToInstall}$_buildName");
        } else {
            \Core\fatal('Error while unarchive ' . $cacheBuilds . sprintf($fileName, $_buildName));
        }
    }
}

foreach ($buildNames as $_buildName) {
    $_buildPath = "{$pathToInstall}$_buildName";
    if (file_exists("{$pathToInstall}$_buildName/app/etc/local.xml")) {
        continue;
    }
    $intBuild = intval(str_replace('.', '', $_buildName));
    $dbName = $config['db_name'] . '_' . $intBuild;
    $dbPass = $config['db_pass'] ? ' -p' . $config['db_pass'] : '';
    `mysql -h {$config['db_host']} -u {$config['db_user']}$dbPass -e "CREATE DATABASE IF NOT EXISTS $dbName"`;

    `cd $_buildPath && chmod -R o+w media var && chmod o+w app/etc`;

    \Core\printInfo('Install ' . $_buildName);

    if ($intBuild < 1800) {
        if ($intBuild >= 1600) {
            `cp resources/Mysql4.php $_buildPath/app/code/core/Mage/Install/Model/Installer/Db/Mysql4.php`;
        } else {
            `cp resources/Db.php $_buildPath/app/code/core/Mage/Install/Model/Installer/Db.php`;
        }
    }

    \Core\printInfo(exec("cd $_buildPath && php -f install.php -- "
    . ' --license_agreement_accepted "yes"'
    . ' --locale "en_US"'
    . ' --timezone "America/Los_Angeles"'
    . ' --default_currency "USD"'
    . " --db_host \"{$config['db_host']}\""
    . " --db_name \"$dbName\""
    . " --db_user \"{$config['db_user']}\""
    . " --db_pass \"{$config['db_pass']}\""
    . " --url \"{$config['site_url']}$_buildName/\""
    . ' --use_rewrites "yes"'
    . ' --skip_url_validation "yes"'
    . ' --use_secure "no"'
    . ' --secure_base_url ""'
    . ' --use_secure_admin "no"'
    . ' --admin_firstname "Admin"'
    . ' --admin_lastname "Admin"'
    . " --admin_email \"{$config['admin_email']}\""
    . " --admin_username \"{$config['admin_username']}\""
    . " --admin_password \"{$config['admin_pass']}\""
    ));
}