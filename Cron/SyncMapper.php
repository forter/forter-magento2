<?php

namespace Forter\Forter\Cron;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\ForterLogger;
use Forter\Forter\Model\ForterLoggerMessage;
use Forter\Forter\Model\Mapper\Utils;
/**
 * Class SendQueue
 * @package Forter\Forter\Cron
 */
class SyncMapper
{
    private \Magento\Framework\Filesystem $filesystem;

    private \Magento\Framework\Filesystem\Directory\WriteFactory $writeFactory;
    /**
     * @var AbstractApi
     */
    private $abstractApi;
    /**
     * @var Config
     */
    private $config;
    /**
     * Mapper utils helper methods
     *
     * @var Utils
     */
    private $mapperUtils;

    public function __construct(
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Filesystem\Directory\WriteFactory $writeFactory,
        Config $config,
        AbstractApi $abstractApi,
        Utils $mapperUtils
    ) {
        $this->filesystem = $filesystem;
        $this->writeFactory = $writeFactory;
        $this->forterConfig = $config;
        $this->abstractApi = $abstractApi;
        $this->mapperUtils = $mapperUtils;
    }

    /**
     * Process items in Queue
     */
    public function execute()
    {
        try {
            $isDebugMode = $this->forterConfig->isDebugEnabled();
            $this->mapperUtils->log(true , 'start Sync Mapping');
            $res = $this->mapperUtils->fetchMapping($isDebugMode);
            $this->saveLocalFile($res, $isDebugMode);
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    private function saveLocalFile($res, bool $isDebugMode) {
        $varDir = $this->filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
        $forterDir = $this->writeFactory->create($varDir->getAbsolutePath('forter'));
        $filePath = sprintf('%s%s%s', $varDir->getAbsolutePath('forter') , PATH_SEPARATOR , Utils::LOCAL_FILE_MAPPER);
        try {
            $forterDir->getDriver()->deleteFile($filePath);
        } catch (\Exception $e) {}
        $file = fopen($filePath, 'w');
        $forterDir->getDriver()->fileWrite($file,$res);
        $metaData = new \stdClass();
        $metaData->folder =  $filePath;
        $metaData->context = $res;
        $this->mapperUtils->log($isDebugMode , sprintf('response Sync Mapping -> %s', Utils::MAPPER_LOCATION) ,-1 , -1, $metaData);
    }

}
