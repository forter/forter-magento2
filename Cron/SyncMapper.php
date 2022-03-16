<?php

namespace Forter\Forter\Cron;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\ActionsHandler\Approve;
use Forter\Forter\Model\ActionsHandler\Decline;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\ForterLogger;
use Forter\Forter\Model\ForterLoggerMessage;
use Forter\Forter\Model\QueueFactory;
use Forter\Forter\Model\RequestBuilder\Order;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class SendQueue
 * @package Forter\Forter\Cron
 */
class SyncMapper
{
    private \Magento\Framework\Filesystem $filesystem;

    private \Magento\Framework\Filesystem\Directory\WriteFactory $writeFactory;

    /**
     * local file name
     */
    const LOCAL_FILE_MAPPER = "mapping.json";
    /**
     *  mapper address location
     */
    const MAPPER_LOCATION = 'https://dev-file-dump.fra1.digitaloceanspaces.com/mapper.json';
    /**
     * @var AbstractApi
     */
    private $abstractApi;
    /**
     * @var Config
     */
    private $config;
    /**
     * @var ForterLogger
     */
    private $forterLogger;

    public function __construct(
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Filesystem\Directory\WriteFactory $writeFactory,
        Config $config,
        AbstractApi $abstractApi,
        ForterLogger $forterLogger
    ) {
        $this->filesystem = $filesystem;
        $this->writeFactory = $writeFactory;
        $this->forterConfig = $config;
        $this->abstractApi = $abstractApi;
        $this->forterLogger = $forterLogger;
    }

    /**
     * Process items in Queue
     */
    public function execute()
    {
        try {
            $isDebugMode = $this->forterConfig->isDebugEnabled();
            $this->log(true , 'start Sync Mapping');
            $res = $this->fetchMapping($isDebugMode);
            $this->saveLocalFile($res, $isDebugMode);
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    private function saveLocalFile($res, bool $isDebugMode) {
        $varDir = $this->filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
        $forterDir = $this->writeFactory->create($varDir->getAbsolutePath('forter'));
        $forterDir->getDriver()->fileWrite($varDir->getAbsolutePath('forter'),$res);
        $metaData = new \stdClass();
        $metaData->folder =  $varDir->getAbsolutePath('forter');
        $metaData->context = $res;
        $this->log($isDebugMode , sprintf('response Sync Mapping -> %s', self::MAPPER_LOCATION) , $metaData);
    }

    private function fetchMapping(bool $isDebugMode) {
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', self::MAPPER_LOCATION);
        if ($res->getStatusCode() !== 200) {
            $this->log(true , sprintf('failed Sync Mapping -> %s', self::MAPPER_LOCATION));
            throw new \Exception(sprintf('failed fetch %s', self::MAPPER_LOCATION));
        }
        $responseBody = $res->getBody();
        $metaData = new \stdClass();
        $metaData->responseBody =  $responseBody;
        $this->log($isDebugMode , sprintf('response Sync Mapping -> %s', self::MAPPER_LOCATION) , $metaData);
        return $responseBody;
    }

    private function log(bool $isDebugMode, string $message ,\stdClass $metaData = new \stdClass()) {
        if ($isDebugMode) {
            $this->forterConfig->log($message, "info");
            $message = new ForterLoggerMessage($this->config->getStoreId(),  -1, $message);
            $this->forterLogger->SendLog($message);
        }
    }
}
