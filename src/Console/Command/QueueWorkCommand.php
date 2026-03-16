<?php

declare(strict_types=1);

namespace LPhenom\LPhenom\Console\Command;

use LPhenom\LPhenom\Application;
use LPhenom\LPhenom\Console\CommandInterface;
use LPhenom\Queue\Worker;

/**
 * Start the queue worker.
 *
 * Usage:
 *   lphenom queue:work                # run forever
 *   lphenom queue:work --once         # process one job and exit
 *   lphenom queue:work --max-jobs=10  # process 10 jobs and exit
 */
final class QueueWorkCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'queue:work';
    }

    public function getDescription(): string
    {
        return 'Start the queue worker';
    }

    public function execute(Application $app, array $args): int
    {
        $container = $app->getContainer();

        /** @var Worker $worker */
        $worker = $container->get(Worker::class);

        $once    = false;
        $maxJobs = 0;

        foreach ($args as $arg) {
            if ($arg === '--once') {
                $once = true;
            }
            if (substr($arg, 0, 11) === '--max-jobs=') {
                $maxJobs = (int) substr($arg, 11);
            }
        }

        if ($once) {
            echo 'Processing one job...' . PHP_EOL;
            $didProcess = $worker->runOnce(5);
            if ($didProcess) {
                echo 'Job processed.' . PHP_EOL;
            } else {
                echo 'No job available.' . PHP_EOL;
            }
            return 0;
        }

        echo 'Queue worker started.' . PHP_EOL;
        $worker->run(5, $maxJobs);
        echo 'Queue worker stopped.' . PHP_EOL;
        return 0;
    }
}
