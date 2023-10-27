<?php

namespace Forter\Forter\Console\Command;

use Forter\Forter\Cron\SendQueue as Cron;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Decline extends Command
{

    /**
     * @var Cron
     */
    protected $cron;

    /**
     * @param Cron $cron
     * @param string|null $name
     */
    public function __construct(
        Cron $cron,
        string $name = null
    ) {
        $this->cron = $cron;
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
        $this->cron->execute();
        return 0;
    }
}
