<?php

namespace FullscreenInteractive\SilverStripe\Forum\Search;

use SilverStripe\ORM\DataList;

/**
 * Interface for the Search classes
 */

interface ForumSearchProvider
{
    public function getResults(int $forumHolderID, string $query, string $order, int $offset = 0, int $limit = 10): ?DataList;

    public function load(): bool;
}
