<?php

namespace FullscreenInteractive\SilverStripe\Forum\Dev\Tasks;

use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

/**
 * One-off or periodic maintenance: recompute LastPostDate on every thread from Post rows.
 */
class SyncForumThreadLastPostDatesTask extends BuildTask
{
    protected static string $commandName = 'SyncForumThreadLastPostDatesTask';

    protected string $title = 'Sync forum thread LastPostDate fields';

    protected static string $description = 'Recomputes ForumThread.LastPostDate from Post Created/LastEdited (e.g. after upgrading the forum module).';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $count = 0;
        foreach (ForumThread::get() as $thread) {
            $thread->syncLastPostDate();
            $count++;
            if ($count % 500 === 0) {
                $output->writeln("Synced {$count} threads…");
            }
        }
        $output->writeln("Synced {$count} forum threads.");

        return Command::SUCCESS;
    }
}
