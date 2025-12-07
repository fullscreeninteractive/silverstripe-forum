<?php

/**
 * Interface for the Search classes
 *
 * @package forum
 */

interface ForumSearchProvider
{
    
    /**
     * Results function
     */
    public function getResults($forumHolderID, $query, $order, $offset = 0, $limit = 10);
    
    /**
     * A callback when this forum search provider is loaded
     */
    public function load();
}
