<?php
namespace Installer;

const BUILDS_CACHE_PATH = 'cache/builds/';
const BUILD_FILE_NAME = '%s.tar.gz';
const BUILD_DOWNLOAD_URL = 'https://github.com/%s/archive/1.9.2.4.tar.gz';
const SAMPLE_DATA_FILE_NAME = 'magento-sample-data-%s.tar.gz';
const SAMPLE_DATA_DOWNLOAD_URL = 'http://downloads.sourceforge.net/project/mageloads/assets/%s/magento-sample-data-%s.tar.gz';

class Installer
{
    protected $config;
    protected $pathToInstall;
    protected $build;

    public function __construct($config, $pathToInstall, $build)
    {
        $this->config = $config;
        $this->pathToInstall = $pathToInstall;
        $this->build = $build;
    }

    protected function getConfig($key)
    {
        $value = '';
        if (isset($this->config[$key])) {
            $value = $this->config[$key];
        }

        return $value;
    }

    protected function isSingleInstall()
    {
        return (bool)$this->getConfig('single_install');
    }

    protected function getBuildPath()
    {
        if ($this->isSingleInstall()) {
            return $this->pathToInstall;
        }

        return $this->pathToInstall . DIRECTORY_SEPARATOR . $this->build;
    }

    protected function buildToInt()
    {
        return intval(str_replace('.', '', $this->build));
    }

    public function isBuildInstalled()
    {
        return (file_exists($this->getBuildPath() . "/app/etc/local.xml"));
    }

    public function run()
    {
        $this->downloadBuild();
        $this->unpackBuild();
        $this->createDb();

        if ($this->getConfig('install_sample_data')) {
            $this->downloadSampleData();
            $this->unpackSampleData();
            $this->applySampleData();
        }

        $this->prepareInstall();
        $this->install();
        $this->reindex();

        $modules = $this->getConfig('modman_modules');
        if (is_array($modules) && !empty($modules)) {
            foreach ($modules as $alias => $url) {
                $this->installModmanModule($url, $alias);
            }
        }
    }

    public function downloadBuild()
    {
        $build = $this->build;
        $saveTo = BASE_PATH . BUILDS_CACHE_PATH . sprintf(BUILD_FILE_NAME, $build);
        if (!file_exists($saveTo)) {
            \Core\printInfo('Downloading magento ' . $build);
            \IO\downloadFile(sprintf(BUILD_DOWNLOAD_URL, $this->getConfig('github_repo'), $build) , $saveTo);
        }
    }

    public function unpackBuild()
    {
        $saveTo = $this->getBuildPath();
        if (!file_exists($saveTo . '/app/Mage.php')) {
            \Core\printInfo('Unpacking ' . $this->build);
            $archive = realpath(BASE_PATH . BUILDS_CACHE_PATH). '/' . sprintf(BUILD_FILE_NAME, $this->build);
            $tempPath = realpath(__DIR__ . '/..') . '/.temp/';
            \IO\createDirectory($tempPath);
            system("cd $tempPath && tar xzf $archive");
            $unpackedPath = $tempPath . explode('/', $this->getConfig('github_repo'))[1] . '-' . $this->build;
            if (file_exists($unpackedPath)) {
                $command = 'mv ' . $unpackedPath . '/* ' . $saveTo;
                system($command);
                if (!file_exists($saveTo . '/app/Mage.php')) {
                    \Core\fatal('Error while copy: ' . $command);
                }
            } else {
                \Core\fatal('Error while un archiving ' . $tempPath . '/' . $unpackedPath);
            }
        } else {
            \Core\printInfo('Skip unpack build. ' . $saveTo . ' already exist');
        }
    }

    public function getSampleDataBuild()
    {
        $sampleDataBuild = '1.9.1.0';
        if ($this->buildToInt($this->build) < $this->buildToInt($sampleDataBuild)) {
            $sampleDataBuild = '1.6.1.0';
        }
        if ($this->buildToInt($this->build) < $this->buildToInt($sampleDataBuild)) {
            $sampleDataBuild = '1.2.0';
        }

        return $sampleDataBuild;
    }

    public function downloadSampleData()
    {
        $sampleDataBuild = $this->getSampleDataBuild();

        $saveTo = BASE_PATH . BUILDS_CACHE_PATH . sprintf(SAMPLE_DATA_FILE_NAME, $sampleDataBuild);
        if (!file_exists($saveTo)) {
            \Core\printInfo('Downloading sample data ' . $sampleDataBuild);
            \IO\downloadFile(sprintf(SAMPLE_DATA_DOWNLOAD_URL, $sampleDataBuild, $sampleDataBuild), $saveTo);
        }
    }

    public function unpackSampleData()
    {
        $sampleDataBuild = $this->getSampleDataBuild();
        if (!file_exists($this->pathToInstall . '.temp/magento-sample-data-' . $sampleDataBuild)) {
            \Core\printInfo('Unpacking magento sample data ' . $sampleDataBuild);
            $archive = realpath(BASE_PATH . BUILDS_CACHE_PATH). '/' . sprintf(SAMPLE_DATA_FILE_NAME, $sampleDataBuild);
            \IO\createDirectory("{$this->pathToInstall}.temp/");
            system("cd {$this->pathToInstall}.temp/ && tar xzf $archive");
        }

    }

    protected function getMysqlConnectLine()
    {
        $dbHost = $this->getConfig('db_host');
        $dbUser = $this->getConfig('db_user');
        $dbPass = $this->getConfig('db_pass') ? ' -p' . $this->getConfig('db_pass') : '';

        return "mysql -h $dbHost -u $dbUser $dbPass";
    }

    protected function getDbName()
    {
        $name = $this->getConfig('db_name');
        if (!$this->isSingleInstall()) {
            $name .= '_' . $this->buildToInt();
        }

        return $name;
    }

    protected function getSiteUrl()
    {
        $url = $this->getConfig('site_url');

        if (!$this->isSingleInstall()) {
            $url .= $this->build . '/';
        }

        return $url;
    }

    protected function getMySQLVersion() {
        $output = shell_exec('mysql -V');
        preg_match('@[0-9]+\.[0-9]+\.[0-9]+@', $output, $version);
        return $version[0];
    }

    protected function createDb()
    {
        $dbName = $this->getDbName();
        system($this->getMysqlConnectLine() . " -e \"CREATE DATABASE IF NOT EXISTS $dbName\"");
    }

    function applySampleData()
    {
        $sampleDataBuild = $this->getSampleDataBuild();

        $sampleDataPath = $this->pathToInstall . '/.temp/magento-sample-data-' . $sampleDataBuild;
        if (file_exists($sampleDataPath)) {
            \Core\printInfo("Applying sample data $sampleDataBuild to magento {$this->build}");
            $buildPath = $this->getBuildPath();
            system("cp -R $sampleDataPath/media/* {$buildPath}/media");
            $dumpName = "$sampleDataPath/magento_sample_data_for_{$sampleDataBuild}.sql";

            $dbName = $this->getDbName();
            system($this->getMysqlConnectLine() . " $dbName < $dumpName");
            system('rm -rf ' . $sampleDataPath);
        } else {
            \Core\fatal('Error while unpacking ' . BASE_PATH . BUILDS_CACHE_PATH . sprintf(SAMPLE_DATA_FILE_NAME, $sampleDataBuild));
        }
    }

    function prepareInstall()
    {
        $buildPath = $this->getBuildPath();
        if (!file_exists($buildPath . '/var')) {
            mkdir($buildPath . '/var');
        }

        system("cd $buildPath && chmod -R o+w media var && chmod o+w app/etc");

        if ($this->buildToInt() < 1800) {
            $version = $this->getMySQLVersion();
            \Core\printInfo('Mysql version: ' . $version);
            $version = explode('.', $version);
            $minVersion = explode('.', '5.6');
            if (count($version) < 2
                || ($version[0] > $minVersion[0] || ($version[0] == $minVersion[0] && $version[1] >= $minVersion[1]))
            ) {
                //apply fix for mysql 5.6+
                if ($this->buildToInt() >= 1600) {
                    system("cp " . BASE_PATH . "resources/Mysql4.php $buildPath/app/code/core/Mage/Install/Model/Installer/Db/Mysql4.php");
                } else {
                    system("cp " . BASE_PATH . "resources/Db.php $buildPath/app/code/core/Mage/Install/Model/Installer/Db.php");
                }
                \Core\printInfo('Fixed installer for mysql 5.6+');
            }

            $phpVersion = phpversion();
            \Core\printInfo('PHP version: ' . $phpVersion);
            $phpVersion = explode('.', $phpVersion);
            $phpMinVersion = explode('.', '5.4');
            if (count($phpVersion) < 2
                || ($phpVersion[0] > $phpMinVersion[0]
                    || ($phpVersion[0] == $phpMinVersion[0] && $phpVersion[1] >= $phpMinVersion[1]))
            ) {
                //fix php installer for 5.4+ (PHP Extensions "0" must be loaded)
                $installerConfigPath = $buildPath . '/app/code/core/Mage/Install/etc/config.xml';
                $data = file_get_contents($installerConfigPath);
                $data = str_replace('<pdo_mysql/>', '<pdo_mysql>1</pdo_mysql>', $data);
                file_put_contents($installerConfigPath, $data);
                \Core\printInfo('Fixed installer config for php 5.4+');
            }
        }
    }

    function install()
    {
        \Core\printInfo('Install ' . $this->build);
        $buildPath = $this->getBuildPath();
        $dbName = $this->getDbName();
        $siteUrl = $this->getSiteUrl();
        $secureUrl = $this->getConfig('secure_url') ? $this->getSiteUrl() : '';

        $shell = "cd $buildPath && php -f install.php -- "
            . ' --license_agreement_accepted "yes"'
            . ' --locale "en_US"'
            . ' --timezone "America/Los_Angeles"'
            . ' --default_currency "USD"'
            . " --db_host \"{$this->getConfig('db_host')}\""
            . " --db_name \"$dbName\""
            . " --db_user \"{$this->getConfig('db_user')}\""
            . " --db_pass \"{$this->getConfig('db_pass')}\""
            . " --url \"$siteUrl\""
            . ' --use_rewrites "yes"'
            . ' --skip_url_validation "yes"'
            . ' --admin_firstname "Admin"'
            . ' --admin_lastname "Admin"'
            . " --admin_email \"{$this->getConfig('admin_email')}\""
            . " --admin_username \"{$this->getConfig('admin_username')}\""
            . " --admin_password \"{$this->getConfig('admin_pass')}\"";

        if ($secureUrl) {
            $shell .= ' --use_secure "yes"'
                . ' --secure_base_url "' . $secureUrl . '"';
            $shell .= ' --use_secure_admin ' . ($this->getConfig('admin_secure') ? '"yes"' : '"no"');
        } else {
            $shell .= ' --use_secure "no"'
                . ' --secure_base_url ""'
                . ' --use_secure_admin "no"';
        }

        system($shell);
    }

    function reindex()
    {
        \Core\printInfo('Start reindex');
        $buildPath = $this->getBuildPath();
        system("cd $buildPath && php shell/indexer.php reindexall");
    }

    function installModmanModule($url, $alias)
    {
        if (!file_exists($this->pathToInstall . '/.modman/')) {
            mkdir($this->pathToInstall . '/.modman/');
        }
        if (!file_exists($this->pathToInstall . '/.modman/' . $alias)) {
            \Core\printInfo(sprintf('Installing %s from %s', $alias, $url));
            system("cd {$this->pathToInstall}/.modman/ && git clone $url $alias");
        }

        \Core\printInfo('Deploy ' . $alias . ' to ' . $this->build);
        $buildPath = $this->getBuildPath();
        if (!file_exists($buildPath . '/.modman/')) {
            mkdir($buildPath . '/.modman/');
        }
        if (!$this->isSingleInstall()) {
            system(sprintf('ln -s %s %s', realpath($this->pathToInstall . '/.modman/' . $alias), $buildPath . '/.modman/' . $alias));
        }
        system(sprintf('cd %s && modman %s deploy', $buildPath, $alias));

        //allow template symlinks
        \Core\printInfo('Allowing template symlinks');
        $sql = "INSERT INTO core_config_data (scope, scope_id, path, value) VALUES ('default', 0, 'dev/template/allow_symlink', '1') ON DUPLICATE KEY UPDATE value = 1;";
        system(sprintf('%s %s -e "%s"',$this->getMysqlConnectLine(), $this->getDbName(), $sql));
    }
}
