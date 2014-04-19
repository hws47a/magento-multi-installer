<?php
namespace Installer;

const BUILDS_CACHE_PATH = 'cache/builds/';
const BUILD_FILE_NAME = 'magento-%s.tar.bz2';
const BUILD_DOWNLOAD_URL = 'http://www.magentocommerce.com/downloads/assets/%s/magento-%s.tar.bz2';
const SAMPLE_DATA_FILE_NAME = 'magento-sample-data-%s.tar.bz2';
const SAMPLE_DATA_DOWNLOAD_URL = 'http://www.magentocommerce.com/downloads/assets/%s/magento-sample-data-%s.tar.bz2';

function downloadBuild($build)
{
    $saveTo = BASE_PATH . BUILDS_CACHE_PATH . sprintf(BUILD_FILE_NAME, $build);
    if (!file_exists($saveTo)) {
        \Core\printInfo('Download magento ' . $build);
        \IO\downloadFile(sprintf(BUILD_DOWNLOAD_URL, $build, $build) , $saveTo);
    }
}

function downloadSampleData($build)
{
    $sampleDataBuild = '1.6.1.0';
    if (buildToInt($build) < 1610) {
        $sampleDataBuild = '1.2.0';
    }

    $saveTo = BASE_PATH . BUILDS_CACHE_PATH . sprintf(SAMPLE_DATA_FILE_NAME, $sampleDataBuild);
    if (!file_exists($saveTo)) {
        \Core\printInfo('Download sample data ' . $sampleDataBuild);
        \IO\downloadFile(sprintf(SAMPLE_DATA_DOWNLOAD_URL, $sampleDataBuild, $sampleDataBuild), $saveTo);
    }
}

function unpackBuild($pathToInstall, $build)
{
    $saveTo = $pathToInstall . $build;
    $tempPath = $pathToInstall . '.temp/' . $build;
    if (!file_exists($saveTo)) {
        \Core\printInfo('Unpack ' . $build);
        $archive = realpath(BASE_PATH . BUILDS_CACHE_PATH). '/' . sprintf(BUILD_FILE_NAME, $build);
        \IO\createDirectory($tempPath);
        system("cd $tempPath && tar xjf $archive");
        if (file_exists($tempPath . '/magento')) {
            rename($tempPath . '/magento', $saveTo);
        } else {
            \Core\fatal('Error while unarchive ' . $archive);
        }
    }
}

function unpackSampleData($pathToInstall, $build)
{
    $sampleDataBuild = getSampleDataBuild($build);
    if (!file_exists($pathToInstall . '.temp/magento-sample-data-' . $sampleDataBuild)) {
        \Core\printInfo('Unpack magento sample data ' . $sampleDataBuild);
        $archive = realpath(BASE_PATH . BUILDS_CACHE_PATH). '/' . sprintf(SAMPLE_DATA_FILE_NAME, $sampleDataBuild);
        \IO\createDirectory("{$pathToInstall}.temp/");
        system("cd {$pathToInstall}.temp/ && tar xjf $archive");
    }

}

function isBuildInstalled($pathToInstall, $build)
{
    return (file_exists("{$pathToInstall}$build/app/etc/local.xml"));
}

function buildToInt($build)
{
    return intval(str_replace('.', '', $build));
}

function getSampleDataBuild($build)
{
    $sampleDataBuild = '1.6.1.0';
    if (buildToInt($build) < buildToInt($sampleDataBuild)) {
        $sampleDataBuild = '1.2.0';
    }

    return $sampleDataBuild;
}

function getDbName($build)
{
    global $config;

    return $config['db_name'] . '_' . buildToInt($build);
}

function getSiteUrl($url, $build)
{
    return $url . $build . '/';
}

function createDb($build)
{
    global $config;

    $dbHost = $config['db_host'];
    $dbName = getDbName($build);
    $dbUser = $config['db_user'];
    $dbPass = $config['db_pass'] ? ' -p' . $config['db_pass'] : '';
    system("mysql -h $dbHost -u $dbUser $dbPass -e \"CREATE DATABASE IF NOT EXISTS $dbName\"");
}

function applySampleData($pathToInstall, $build)
{
    $sampleDataBuild = getSampleDataBuild($build);

    $sampleDataPath = $pathToInstall . '.temp/magento-sample-data-' . $sampleDataBuild;
    if (file_exists($sampleDataPath)) {
        \Core\printInfo("Apply sample data v$sampleDataBuild to magento $build");
        system("cp -R $sampleDataPath/media/* {$pathToInstall}$build/media/");
        $dumpName = "$sampleDataPath/magento_sample_data_for_{$sampleDataBuild}.sql";

        global $config;
        $dbHost = $config['db_host'];
        $dbName = getDbName($build);
        $dbUser = $config['db_user'];
        $dbPass = $config['db_pass'] ? ' -p' . $config['db_pass'] : '';
        system("mysql -h $dbHost -u $dbUser $dbPass $dbName < $dumpName");
    } else {
        \Core\fatal('Error while unpack ' . BASE_PATH . BUILDS_CACHE_PATH . sprintf(SAMPLE_DATA_FILE_NAME, $build));
    }
}

function prepareInstall($pathToInstall, $build)
{
    $buildPath = "{$pathToInstall}$build";

    system("cd $buildPath && chmod -R o+w media var && chmod o+w app/etc");

    if (buildToInt($build) < 1800) {
        //apply fix for mysql 5.6+
        if (buildToInt($build) >= 1600) {
            system("cp " . BASE_PATH . "resources/Mysql4.php $buildPath/app/code/core/Mage/Install/Model/Installer/Db/Mysql4.php");
        } else {
            system("cp " . BASE_PATH . "resources/Db.php $buildPath/app/code/core/Mage/Install/Model/Installer/Db.php");
        }
        \Core\printInfo('Fixed installer for mysql 5.6+');

        //fix php installer for 5.4+ (PHP Extensions "0" must be loaded)
        $installerConfigPath = $buildPath . '/app/code/core/Mage/Install/etc/config.xml';
        $data = file_get_contents($installerConfigPath);
        $data = str_replace('<pdo_mysql/>', '<pdo_mysql>1</pdo_mysql>', $data);
        file_put_contents($installerConfigPath, $data);
        \Core\printInfo('Fixed installer config for php 5.4+');
    }
}

function install($pathToInstall, $build)
{
    global $config;

    \Core\printInfo('Install ' . $build);
    $buildPath = "{$pathToInstall}$build";
    $dbName = getDbName($build);
    $siteUrl = getSiteUrl($config['site_url'], $build);
    $secureUrl = isset($config['secure_url']) && $config['secure_url'] ? getSiteUrl($config['secure_url'], $build) : '';

    $shell = "cd $buildPath && php -f install.php -- "
        . ' --license_agreement_accepted "yes"'
        . ' --locale "en_US"'
        . ' --timezone "America/Los_Angeles"'
        . ' --default_currency "USD"'
        . " --db_host \"{$config['db_host']}\""
        . " --db_name \"$dbName\""
        . " --db_user \"{$config['db_user']}\""
        . " --db_pass \"{$config['db_pass']}\""
        . " --url \"$siteUrl\""
        . ' --use_rewrites "yes"'
        . ' --skip_url_validation "yes"'
        . ' --admin_firstname "Admin"'
        . ' --admin_lastname "Admin"'
        . " --admin_email \"{$config['admin_email']}\""
        . " --admin_username \"{$config['admin_username']}\""
        . " --admin_password \"{$config['admin_pass']}\"";

    if ($secureUrl) {
        $shell .= ' --use_secure "yes"'
            . ' --secure_base_url "' . $secureUrl . '"';
        if (isset($config['admin_secure']) && $config['admin_secure']) {
            $shell .= ' --use_secure_admin "yes"';
        } else {
            $shell .= ' --use_secure_admin "no"';
        }
    } else {
        $shell .= ' --use_secure "no"'
            . ' --secure_base_url ""'
            . ' --use_secure_admin "no"';
    }

    system($shell);
}

function reindex($pathToInstall, $build)
{
    \Core\printInfo('Start reindex');
    $buildPath = "{$pathToInstall}$build";
    system("cd $buildPath && php shell/indexer.php reindexall");
}