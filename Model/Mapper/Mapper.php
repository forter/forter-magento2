<?

namespace Forter\Forter\Model\Mapper;

use Forter\Forter\Model\AbstractApi;
use Forter\Forter\Model\Config;
use Forter\Forter\Model\ForterLogger;
use Forter\Forter\Model\ForterLoggerMessage;

class Mapper
{
    private \Magento\Framework\Filesystem $filesystem;

    private \Magento\Framework\Filesystem\Directory\WriteFactory $writeFactory;

    /**
     * local file name
     */
    const LOCAL_FILE_MAPPER = "mapping.json";
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
    /**
     * @var bool
     */
    private $isDebugMode;
    /**
     * Mapper utils helper methods
     *
     * @var Utils
     */
    private $mapperUtils;
    private $mapping;
    public function __construct(
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Filesystem\Directory\WriteFactory $writeFactory,
        Config $config,
        AbstractApi $abstractApi,
        ForterLogger $forterLogger,
        Utils $mapperUtils
    ) {
        $this->filesystem = $filesystem;
        $this->writeFactory = $writeFactory;
        $this->forterConfig = $config;
        $this->abstractApi = $abstractApi;
        $this->forterLogger = $forterLogger;
        $this->isDebugMode = $this->forterConfig->isDebugEnabled();
        $this->mapperUtils = $mapperUtils;
        $this->loadLocalMap();
    }

    private function loadLocalMap()
    {
        try {
            $this->mapping =  json_decode($this->mapperUtils->locateLocalMapperOrFetch($this->isDebugMode));
        } catch (\Exception $e) {
            $this->abstractApi->reportToForterOnCatch($e);
        }
    }

    public function runMapping($order) {
        $billingAddress = $order->getBillingAddress();
        $payment = $order->getPayment();

        if (!$payment) {
            return [];
        }

        $paymentData = [];
        $payment_method = $payment->getMethod();

    }
}
