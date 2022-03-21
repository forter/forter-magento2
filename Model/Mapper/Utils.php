<?php
namespace Forter\Forter\Model\Mapper;

use Forter\Forter\Model\Config;
use Forter\Forter\Model\ForterLogger;
use Forter\Forter\Model\ForterLoggerMessage;

class Utils {

    /**
     * local file name
     */
    const LOCAL_FILE_MAPPER = "mapping.json";
    /**
     *  mapper address location
     */
    const MAPPER_LOCATION = 'https://dev-file-dump.fra1.digitaloceanspaces.com/mapper.json';
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
        ForterLogger $forterLogger
    ) {
        $this->filesystem = $filesystem;
        $this->writeFactory = $writeFactory;
        $this->forterConfig = $config;
        $this->forterLogger = $forterLogger;
    }

    public function fetchMapping(bool $isDebugMode, $storeId = -1, $orderId = -1) {
        $client = new \GuzzleHttp\Client();
        $res = $client->request('GET', self::MAPPER_LOCATION);
        if ($res->getStatusCode() !== 200) {
            $this->log(true , sprintf('failed Sync Mapping -> %s', self::MAPPER_LOCATION));
            throw new \Exception(sprintf('failed fetch %s', self::MAPPER_LOCATION));
        }
        $responseBody = $res->getBody();
        $metaData = new \stdClass();
        $metaData->responseBody =  $responseBody;
        $this->log($isDebugMode , sprintf('response Sync Mapping -> %s', self::MAPPER_LOCATION), $storeId, $orderId , $metaData);
        return $responseBody;
    }

    public function locateLocalMapperOrFetch(bool $isDebugMode, $storeId = -1, $orderId = -1) {
        $varDir = $this->filesystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR);
        $forterDir = $this->writeFactory->create($varDir->getAbsolutePath('forter'));
        $filePath = sprintf('%s%s%s', $varDir->getAbsolutePath('forter') , PATH_SEPARATOR , Utils::LOCAL_FILE_MAPPER);
        if (!file_exists($filePath)) {
            $content =  file_get_contents($filePath);
            $metaData = new \stdClass();
            $metaData->mapping = $content;
            $this->log($isDebugMode, 'load mapping file', $storeId, $orderId,$content);
            return file_get_contents($filePath);
        }
        return $this->fetchMapping($isDebugMode);
    }

    public function log(bool $isDebugMode, string $message , $storeId = -1, $orderId = -1,$metaData = null) {
        if ($isDebugMode) {
            $this->forterConfig->log($message, "info");
            $message = new ForterLoggerMessage($storeId,  $orderId, $message);
            $message->metaData = ($metaData)?$metaData : new \stdClass();
            $this->forterLogger->SendLog($message);
        }
    }
}
