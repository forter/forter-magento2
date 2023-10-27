<?php

namespace Forter\Forter\Console\Command;

use Magento\Framework\App\Area;
use Forter\Forter\Cron\SendQueue as Cron;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\App\State;

class Decline extends Command
{
    /**
     * @var State
     */
    protected $state;

    /**
     * @var Cron
     */
    protected $cron;

    /**
     * @param State $state
     * @param Cron $cron
     * @param string|null $name
     */
    public function __construct(
        State $state,
        Cron $cron,
        string $name = null
    ) {
        $this->cron = $cron;
        $this->state = $state;
        parent::__construct($name);
    }


    protected function configure()
    {
        $options = [

        ];

        $this->setName('forter:decline')
            ->setDescription('')
            ->setDefinition($options);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode(Area::AREA_ADMINHTML);
        $this->cron->execute();

        return 0;
    }
}
