<?php

/**
 * Forum Search.
 *
 * Wrapper for providing search functionality
 *
 * @package forum
 */

class ForumSearch
{
    
    /**
     * The search class engine to use for the forum. By default use the standard
     * Database Search but optionally allow other search engines. Must implement
     * the {@link ForumSearch} interface.
     *
     * @var String
     */
    private static $search_engine = 'ForumDatabaseSearch';
    
    /**
     * Set the search class to use for the Forum search. Must implement the
     * {@link ForumSearch} interface
     *
     * @param String
     *
     * @return The result of load() on the engine
     */
    public static function set_search_engine($engine)
    {
        if (!$engine) {
            $engine = 'ForumDatabaseSearch';
        }
        
        $search = new $engine();
        
        if ($search instanceof ForumSearchProvider) {
            self::$search_engine = $engine;
            
            return $search->load();
        } else {
            user_error("$engine must implement the ForumSearchProvider interface");
        }
    }
    
    /**
     * Return the search class for the forum search
     *
     * @return String
     */
    public static function get_search_engine()
    {
        return self::$search_engine;
    }
}
