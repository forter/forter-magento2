<?php
/**
 * @author Zach Vaknin
 * @copyright Copyright (c) 2021 Forter (https://www.forter.com/)
 */
namespace Forter\Forter\Logger\Handler;

use Magento\Framework\Logger\Handler\Base as BaseHandler;
use Monolog\Logger as MonologLogger;

/**
 * Class ErrorHandler
 */
class ErrorHandler extends BaseHandler
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
    protected $fileName = '/var/log/Forter/exception.log';
}
