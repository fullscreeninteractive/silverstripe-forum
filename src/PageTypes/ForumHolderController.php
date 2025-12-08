<?php

namespace FullscreenInteractive\SilverStripe\Forum\PageTypes;

use PageController;
use SilverStripe\Control\RSS\RSSFeed;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use FullscreenInteractive\SilverStripe\Forum\Search\ForumSearch;
use SilverStripe\Core\Convert;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\Requirements;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

class ForumHolderController extends PageController
{

    private static $allowed_actions = [
        'popularthreads',
        'login',
        'logout',
        'search',
        'rss'
    ];

    public function init()
    {
        parent::init();

        Requirements::javascript('silverstripe/framework:thirdparty/jquery/jquery.js');
        Requirements::javascript("forum/javascript/jquery.MultiFile.js");
        Requirements::javascript("forum/javascript/forum.js");

        Requirements::themedCSS('Forum', 'forum', 'all');

        RSSFeed::linkToFeed($this->Link('rss'), _t('ForumHolder.POSTSTOALLFORUMS', "Posts to all forums"));
    }

    /**
     * Show the 20 most popular threads across all {@link Forum} children.
     *
     * Two configuration options are available:
     * 1. "posts" - most popular threads by posts
     * 2. "views" - most popular threads by views
     *
     * e.g. mysite.com/forums/popularthreads?by=posts
     *
     * @return array
     */
    public function popularthreads()
    {
        $start = isset($_GET['start']) ? (int) $_GET['start'] : 0;
        $limit = 20;
        $method = isset($_GET['by']) ? $_GET['by'] : null;
        if (!$method) {
            $method = 'posts';
        }

        if ($method == 'posts') {
            $threadsQuery = ForumThread::get()->buildSQL(
                "\"SiteTree\".\"ParentID\" = '" . $this->ID . "'",
                "\"PostCount\" DESC",
                "$start,$limit",
                "LEFT JOIN \"Post\" ON \"Post\".\"ThreadID\" = \"ForumThread\".\"ID\" LEFT JOIN \"SiteTree\" ON \"SiteTree\".\"ID\" = \"ForumThread\".\"ForumID\""
            );
            $threadsQuery->select[] = "COUNT(\"Post\".\"ID\") AS 'PostCount'";
            $threadsQuery->groupby[] = "\"ForumThread\".\"ID\"";
            $threads = singleton('ForumThread')->buildDataObjectSet($threadsQuery->execute());
            if ($threads) {
                $threads->setPageLimits($start, $limit, $threadsQuery->unlimitedRowCount());
            }
        } elseif ($method == 'views') {
            $threads = ForumThread::get()->sort("NumViews", "DESC")->limit($limit, $start);
        }

        return array(
            'Title' => _t('ForumHolder.POPULARTHREADS', 'Most popular forum threads'),
            'Subtitle' => _t('ForumHolder.POPULARTHREADS', 'Most popular forum threads'),
            'Method' => $method,
            'Threads' => $threads
        );
    }

    /**
     * The login action
     *
     * It simple sets the return URL and forwards to the standard login form.
     */
    public function login()
    {
        $this->getRequest()->getSession()
            ->set('BackURL', $this->Link());

        $this->redirect('Security/login');
    }


    public function logout()
    {
        $url = Security::logout_url();
        return $this->redirect($url);
    }

    /**
     * The search action
     *
     * @return array Returns an array to render the search results.
     */
    public function search()
    {
        $keywords   = (isset($_REQUEST['Search'])) ? Convert::raw2xml($_REQUEST['Search']) : null;
        $order      = (isset($_REQUEST['order'])) ? Convert::raw2xml($_REQUEST['order']) : null;
        $start      = (isset($_REQUEST['start'])) ? (int) $_REQUEST['start'] : 0;

        $abstract = ($keywords) ? "<p>" . sprintf(_t('ForumHolder.SEARCHEDFOR', "You searched for '%s'."), $keywords) . "</p>" : null;

        // get the results of the query from the current search engine
        $search = ForumSearch::getSearchEngine();

        if ($search) {
            $engine = new $search();

            $results = $engine->getResults($this->ID, $keywords, $order, $start);
        } else {
            $results = false;
        }

        $results = PaginatedList::create(
            $results,
            $this->request->getVars()
        );


        // if the user has requested this search as an RSS feed then output the contents as xml
        // rather than passing it to the template
        if ($this->getRequest()->getVar('rss')) {
            $rss = RSSFeed::create(
                $results,
                $this->Link(),
                _t('ForumHolder.SEARCHRESULTS', 'Search results'),
                "",
                "Title",
                "RSSContent",
                "RSSAuthor"
            );

            return $rss->outputToBrowser();
        }

        $rssLink = sprintf(
            $this->Link() . "search/?Search=%s&amp;order=%s&amp;rss",
            urlencode($keywords),
            urlencode($order)
        );

        RSSFeed::linkToFeed($rssLink, _t('ForumHolder.SEARCHRESULTS', 'Search results'));

        return [
            "Subtitle" => DBField::create_field('Text', _t('ForumHolder.SEARCHRESULTS', 'Search results')),
            "Abstract" => DBField::create_field('HTMLText', $abstract),
            "Query" => DBField::create_field('Text', $this->getRequest()->getVar('Search')),
            "Order" => DBField::create_field('Text', ($order) ? $order : "relevance"),
            "RSSLink" => DBField::create_field('HTMLText', $rssLink),
            "SearchResults" => $results
        ];
    }

    /**
     * Get the RSS feed
     *
     * This method will output the RSS feed with the last 50 posts to the
     * browser.
     */
    public function rss()
    {
        $this->getResponse()->getHeaders()->set('Cache-Control', 'max-age=3600'); // cache for one hour

        $threadID = null;
        $forumID = null;

        // optionally allow filtering of the forum posts by the url in the format
        // rss/thread/$ID or rss/forum/$ID
        if (isset($this->urlParams['ID']) && ($action = $this->urlParams['ID'])) {
            if (isset($this->urlParams['OtherID']) && ($id = $this->urlParams['OtherID'])) {
                switch ($action) {
                    case 'forum':
                        $forumID = (int) $id;
                        break;
                    case 'thread':
                        $threadID = (int) $id;
                }
            } else {
                // fallback is that it is the ID of a forum like it was in
                // previous versions
                $forumID = (int) $action;
            }
        }

        $data = array('last_created' => null, 'last_id' => null);

        if (!isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && !isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            // just to get the version data..
            $available = ForumHolder::new_posts_available($this->ID, $data, null, null, $forumID, $threadID);

            // No information provided by the client, just return the last posts
            $rss = new RSSFeed(
                $this->getRecentPosts(50, $forumID, $threadID),
                $this->Link() . 'rss',
                sprintf(_t('Forum.RSSFORUMPOSTSTO'), $this->Title),
                "",
                "Title",
                "RSSContent",
                "RSSAuthor",
                $data['last_created'],
                $data['last_id']
            );
            return $rss->outputToBrowser();
        } else {
            // Return only new posts, check the request headers!
            $since = null;
            $etag = null;

            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
                // Split the If-Modified-Since (Netscape < v6 gets this wrong)
                $since = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
                // Turn the client request If-Modified-Since into a timestamp
                $since = @strtotime($since[0]);
                if (!$since) {
                    $since = null;
                }
            }

            if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && is_numeric($_SERVER['HTTP_IF_NONE_MATCH'])) {
                $etag = (int)$_SERVER['HTTP_IF_NONE_MATCH'];
            }
            if ($available = ForumHolder::new_posts_available($this->ID, $data, $since, $etag, $forumID, $threadID)) {
                HTTP::register_modification_timestamp($data['last_created']);
                $rss = new RSSFeed(
                    $this->getRecentPosts(50, $forumID, $threadID, $etag),
                    $this->Link() . 'rss',
                    sprintf(_t('Forum.RSSFORUMPOSTSTO'), $this->Title),
                    "",
                    "Title",
                    "RSSContent",
                    "RSSAuthor",
                    $data['last_created'],
                    $data['last_id']
                );
                return $rss->outputToBrowser();
            } else {
                if ($data['last_created']) {
                    HTTP::register_modification_timestamp($data['last_created']);
                }

                if ($data['last_id']) {
                    HTTP::register_etag($data['last_id']);
                }

                // There are no new posts, just output an "304 Not Modified" message
                HTTP::add_cache_headers();
                header('HTTP/1.1 304 Not Modified');
            }
        }
        exit;
    }

    /**
     * Return the GlobalAnnouncements from the individual forums
     *
     * @return DataObjectSet
     */
    public function GlobalAnnouncements()
    {
        // Get all the forums with global sticky threads
        return ForumThread::get()
            ->filter('IsGlobalSticky', 1)
            ->innerJoin(ForumHolder::baseForumTable(), '"ForumThread"."ForumID"="ForumPage"."ID"', "ForumPage")
            ->where('"ForumPage"."ParentID" = ' . $this->ID)
            ->filterByCallback(function ($thread) {
                if ($thread->canView()) {
                    $post = Post::get()->filter('ThreadID', $thread->ID)->sort('Post.Created DESC');
                    $thread->Post = $post;
                    return true;
                }
            });
    }
}
