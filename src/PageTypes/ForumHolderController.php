<?php

namespace FullscreenInteractive\SilverStripe\Forum\PageTypes;

use PageController;
use SilverStripe\Control\RSS\RSSFeed;
use SilverStripe\Model\List\ArrayList;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use FullscreenInteractive\SilverStripe\Forum\Search\ForumSearch;
use SilverStripe\Core\Convert;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\Requirements;
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
        Requirements::javascript("fullscreeninteractive/silverstripe-forum:client/javascript/Forum.js");
        Requirements::css("fullscreeninteractive/silverstripe-forum:client/css/Forum.css");

        parent::init();

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
        $start = (int) ($this->request->getVar('start') ?: 0);
        $limit = 20;
        $method = $this->request->getVar('by') ?: 'posts';

        if ($method === 'posts') {
            $threads = ForumThread::get()
                ->filter('Forum.ParentID', $this->ID)
                ->leftJoin('Post', '"Post"."ThreadID" = "ForumThread"."ID"')
                ->alterDataQuery(function ($query) {
                    $query->query()
                        ->addSelect(['COUNT("Post"."ID") AS "PostCount"'])
                        ->addGroupBy('"ForumThread"."ID"');
                })
                ->sort('"PostCount" DESC')
                ->limit($limit, $start);
        } else {
            $threads = ForumThread::get()
                ->filter('Forum.ParentID', $this->ID)
                ->sort('NumViews', 'DESC')
                ->limit($limit, $start);
        }

        return [
            'Title' => _t('ForumHolder.POPULARTHREADS', 'Most popular forum threads'),
            'Subtitle' => _t('ForumHolder.POPULARTHREADS', 'Most popular forum threads'),
            'Method' => $method,
            'Threads' => $threads,
        ];
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
        $keywords   = $this->request->getVar('Search') ? Convert::raw2xml($this->request->getVar('Search')) : null;
        $order      = $this->request->getVar('order') ? Convert::raw2xml($this->request->getVar('order')) : null;
        $start      = $this->request->getVar('start') ? (int) $this->request->getVar('start') : 0;

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
        $response = $this->getResponse();
        $response->addHeader('Cache-Control', 'max-age=3600');

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

        $data = ['last_created' => null, 'last_id' => null];

        if (!isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && !isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            $rss = new RSSFeed(
                $this->getRecentPosts(50, $forumID, $threadID) ?? ArrayList::create(),
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
            if ($available = $this->data()->hasNewPosts($this->ID, $data, $since, $etag, $forumID, $threadID)) {
                $rss = new RSSFeed(
                    $available,
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
                    $this->getResponse()->addHeader('Last-Modified', (string) $data['last_created']);
                }

                if ($data['last_id']) {
                    $this->getResponse()->addHeader('ETag', (string) $data['last_id']);
                }

                // There are no new posts, just output an "304 Not Modified" message
                $this->getResponse()->addHeader('Cache-Control', 'max-age=3600, public');
                $this->getResponse()->setStatusCode(304);
                return $this->getResponse();
            }
        }
        return $this->getResponse();
    }

    /**
     * Return the GlobalAnnouncements from the individual forum,
     */
    public function GlobalAnnouncements()
    {
        return ForumThread::get()
            ->filter([
                'IsGlobalSticky' => 1,
                'Forum.ParentID' => $this->ID,
            ])
            ->filterByCallback(function ($thread) {
                if ($thread->canView()) {
                    $thread->Post = Post::get()->filter('ThreadID', $thread->ID)->sort('Created', 'DESC');
                    return true;
                }
                return false;
            });
    }
}
