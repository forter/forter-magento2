<?php
/**
 * @author Zach Vaknin
 * @copyright Copyright (c) 2021 Forter (https://www.forter.com/)
 */
namespace Forter\Forter\Logger\Handler;

use Magento\Framework\Logger\Handler\Base as BaseHandler;
use Monolog\Logger as MonologLogger;

/**
 * Class DebugHandler
 */
class DebugHandler extends BaseHandler
{
    /**
     * Logging level
     *
     * @var int
     */
    protected $loggerType = MonologLogger::DEBUG;

    /**
     * File name
     *
     * @var string
     */
    protected $fileName = '/var/log/Forter/debug.log';
}
