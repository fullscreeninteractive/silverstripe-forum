<?php

namespace FullscreenInteractive\SilverStripe\Forum\PageTypes;

use Page;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDeleteAction;
use SilverStripe\Forms\GridField\GridFieldPageCount;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TreeMultiselectField;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumCategory;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumHolder;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Model\List\PaginatedList;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\View\Requirements;

class Forum extends Page
{
    private static $table_name = 'Forum';

    private static $allowed_children = 'none';

    /**
     * Enable this to automatically notify moderators when a message is posted
     * or edited on his forums.
     */
    private static $notify_moderators = false;

    /**
     * The default avatar URL to use if no avatar is set for a user.
     *
     * @var string
     */
    private static $default_avatar_url = "https://via.placeholder.com/150";

    private static $db = [
        "Abstract" => "Text",
        "CanPostType" => "Enum('Inherit, Anyone, LoggedInUsers, OnlyTheseUsers, NoOne', 'Inherit')",
        "CanAttachFiles" => "Boolean",
    ];

    private static $has_one = [
        "Moderator" => Member::class,
        "Category" => ForumCategory::class
    ];

    private static $many_many = [
        'Moderators' => Member::class,
        'PosterGroups' => Group::class
    ];

    private static $defaults = [
        "ForumPosters" => "LoggedInUsers"
    ];

    /**
     * Number of posts to include in the thread view before pagination takes effect.
     *
     * @var int
     */
    private static $posts_per_page = 8;

    /**
     * When migrating from older versions of the forum it used post ID as the url token
     * as of forum 1.0 we now use ThreadID. If you want to enable 301 redirects from post to thread ID
     * set this to true
     *
     * @var bool
     */
    private static $redirect_post_urls_to_thread = false;

    /**
     * Check if the user can view the forum.
     */
    public function canView($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        return (parent::canView($member) || $this->canModerate($member));
    }

    /**
     * Check if the user can post to the forum and edit his own posts.
     */
    public function canPost($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if ($this->CanPostType == "Inherit") {
            $holder = $this->getForumHolder();

            if ($holder) {
                return $holder->canPost($member);
            }

            return false;
        }

        if ($this->CanPostType == "NoOne") {
            return false;
        }

        if ($this->CanPostType == "Anyone" || $this->canEdit($member)) {
            return true;
        }

        if ($member) {
            if ($member->IsSuspended()) {
                return false;
            }
            if ($member->IsBanned()) {
                return false;
            }

            if ($this->CanPostType == "LoggedInUsers") {
                return true;
            }

            if ($groups = $this->PosterGroups()) {
                foreach ($groups as $group) {
                    if ($member->inGroup($group)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if user has access to moderator panel and can delete posts and threads.
     */
    public function canModerate($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if (!$member) {
            return false;
        }

        // Admins
        if (Permission::checkMember($member, 'ADMIN')) {
            return true;
        }

        // Moderators
        if ($member->isModeratingForum($this)) {
            return true;
        }

        return false;
    }

    /**
     * Can we attach files to topics/posts inside this forum?
     *
     * @return bool Set to TRUE if the user is allowed to, to FALSE if they're
     *              not
     */
    public function canAttach($member = null)
    {
        return $this->CanAttachFiles ? true : false;
    }


    /**
     * Check if we can and should show forums in categories
     */
    public function getShowInCategories()
    {
        $holder = $this->getForumHolder();

        if ($holder) {
            return $holder->getShowInCategories();
        }
    }

    /**
     * Returns a FieldList with which to create the CMS editing form
     *
     * @return FieldList The fields to be displayed in the CMS.
     */
    public function getCMSFields()
    {
        $self = $this;

        $this->beforeUpdateCMSFields(function ($fields) use ($self) {
            Requirements::javascript("silverstripe/forum:client/javascript/ForumAccess.js");
            Requirements::css("silverstripe/forum:client/css/Forum_CMS.css");

            $fields->addFieldToTab("Root.Access", HeaderField::create(_t('Forum.ACCESSPOST', 'Who can post to the forum?'), 2));
            $fields->addFieldToTab("Root.Access", $optionSetField = OptionsetField::create("CanPostType", "", [
                "Inherit" => "Inherit",
                "Anyone" => _t('Forum.READANYONE', 'Anyone'),
                "LoggedInUsers" => _t('Forum.READLOGGEDIN', 'Logged-in users'),
                "OnlyTheseUsers" => _t('Forum.READLIST', 'Only these people (choose from list)'),
                "NoOne" => _t('Forum.READNOONE', 'Nobody. Make Forum Read Only')
            ]));

            $optionSetField->addExtraClass('ForumCanPostTypeSelector');

            $fields->addFieldsToTab("Root.Access", [
                TreeMultiselectField::create("PosterGroups", _t('Forum.GROUPS', "Groups")),
                OptionsetField::create("CanAttachFiles", _t('Forum.ACCESSATTACH', 'Can users attach files?'), [
                    "1" => _t('Forum.YES', 'Yes'),
                    "0" => _t('Forum.NO', 'No')
                ])
            ]);


            //Dropdown of forum category selection.
            $categories = ForumCategory::get()->map();

            $fields->addFieldsToTab(
                "Root.Main",
                DropdownField::create('CategoryID', _t('Forum.FORUMCATEGORY', 'Forum Category'), $categories),
                'Content'
            );

            //GridField Config - only need to attach or detach Moderators with existing Member accounts.
            $moderatorsConfig = GridFieldConfig::create()
                ->addComponent(GridFieldButtonRow::create('before'))
                ->addComponent(GridFieldAddExistingAutocompleter::create('buttons-before-right'))
                ->addComponent(GridFieldToolbarHeader::create())
                ->addComponent($sort = GridFieldSortableHeader::create())
                ->addComponent($columns = GridFieldDataColumns::create())
                ->addComponent(GridFieldDeleteAction::create(true))
                ->addComponent(GridFieldPageCount::create('toolbar-header-right'))
                ->addComponent($pagination = GridFieldPaginator::create());

            // Use GridField for Moderator management
            $moderators = GridField::create(
                'Moderators',
                _t('MODERATORS', 'Moderators for this forum'),
                $self->Moderators(),
                $moderatorsConfig
            );

            $columns->setDisplayFields([
                'Nickname' => 'Nickname',
                'FirstName' => 'First name',
                'Surname' => 'Surname',
                'Email' => 'Email',
                'LastVisited.Long' => 'Last Visit'
            ]);

            $fields->addFieldToTab('Root.Moderators', $moderators);
        });

        $fields = parent::getCMSFields();

        return $fields;
    }

    /**
     * Helper Method from the template includes. Uses $ForumHolder so in order for it work
     * it needs to be included on this page.
     */
    public function getForumHolder(): ?ForumHolder
    {
        $holder = $this->Parent();
        if ($holder instanceof ForumHolder) {
            return $holder;
        }
        return null;
    }

    /**
     * Get the latest posting of the forum. For performance the forum ID is stored on the
     * {@link Post} object as well as the {@link Forum} object
     *
     * @return Post
     */
    public function getLatestPost()
    {
        return Post::get()->filter([
            'ForumID' => $this->ID
        ])->sort([
            'ID' => 'DESC'
        ])->first();
    }

    /**
     * Get the number of total topics (threads) in this Forum
     *
     * @return int Returns the number of topics (threads)
     */
    public function getNumTopics()
    {
        $sqlQuery = new SQLQuery();
        $sqlQuery->setFrom('"Post"');
        $sqlQuery->setSelect('COUNT(DISTINCT("ThreadID"))');
        $sqlQuery->addInnerJoin('Member', '"Post"."AuthorID" = "Member"."ID"');
        $sqlQuery->addWhere('"Member"."ForumStatus" = \'Normal\'');
        $sqlQuery->addWhere('"ForumID" = ' . $this->ID);
        return $sqlQuery->execute()->value();
    }

    /**
     * Get the number of total posts
     *
     * @return int Returns the number of posts
     */
    public function getNumPosts()
    {
        return DB::query('SELECT COUNT("Post"."ID") FROM "Post" INNER JOIN "Member" ON "Post"."AuthorID" = "Member"."ID" WHERE "Member"."ForumStatus" = \'Normal\' AND "ForumID" = ' . $this->ID)->value();
    }


    /**
     * Get the number of distinct Authors
     *
     * @return int
     */
    public function getNumAuthors()
    {
        return DB::query('SELECT COUNT(DISTINCT("AuthorID")) FROM "Post" INNER JOIN "Member" ON "Post"."AuthorID" = "Member"."ID" WHERE "Member"."ForumStatus" = \'Normal\' AND "ForumID" = ' . $this->ID)->value();
    }

    /**
     * Returns the Topics (the first Post of each Thread) for this Forum
     * @return DataList
     */
    public function getTopics()
    {
        // Get a list of Posts
        $posts = Post::get();

        // Get the underlying query and change it to return the ThreadID and Max(Created) and Max(ID) for each thread
        // of those posts
        $postQuery = $posts->dataQuery()->query();

        $postQuery
            ->setSelect(array())
            ->selectField('MAX("Post"."Created")', 'PostCreatedMax')
            ->selectField('MAX("Post"."ID")', 'PostIDMax')
            ->selectField('"ThreadID"')
            ->setGroupBy('"ThreadID"')
            ->addWhere(sprintf('"ForumID" = \'%s\'', $this->ID))
            ->setDistinct(false);

        // Get a list of forum threads inside this forum that aren't sticky
        $threads = ForumThread::get()->filter(array(
            'ForumID' => $this->ID,
            'IsGlobalSticky' => 0,
            'IsSticky' => 0
        ));

        // Get the underlying query and change it to inner join on the posts list to just show threads that
        // have approved (and maybe awaiting) posts, and sort the threads by the most recent post
        $threadQuery = $threads->dataQuery()->query();
        $threadQuery
            ->addSelect(array('"PostMax"."PostCreatedMax", "PostMax"."PostIDMax"'))
            ->addFrom('INNER JOIN (' . $postQuery->sql() . ') AS "PostMax" ON ("PostMax"."ThreadID" = "ForumThread"."ID")')
            ->addOrderBy(array('"PostMax"."PostCreatedMax" DESC', '"PostMax"."PostIDMax" DESC'))
            ->setDistinct(false);

        // And return the results
        return $threads->exists() ? PaginatedList::create($threads, $this->request->getVar('page') ?? 0) : null;
    }



    /*
     * Returns the Sticky Threads
     * @param boolean $include_global Include Global Sticky Threads in the results (default: true)
     * @return DataList
     */
    public function getStickyTopics($include_global = true)
    {
        // Get Threads that are sticky & in this forum
        $where = '("ForumThread"."ForumID" = ' . $this->ID . ' AND "ForumThread"."IsSticky" = 1)';
        // Get Threads that are globally sticky
        if ($include_global) {
            $where .= ' OR ("ForumThread"."IsGlobalSticky" = 1)';
        }

        // Get the underlying query
        $query = ForumThread::get()->where($where)->dataQuery()->query();

        // Sort by the latest Post in each thread's Created date
        $query
            ->addSelect('"PostMax"."PostMax"')
            // TODO: Confirm this works in non-MySQL DBs
            ->addFrom(sprintf(
                'LEFT JOIN (SELECT MAX("Created") AS "PostMax", "ThreadID" FROM "Post" WHERE "ForumID" = \'%s\' GROUP BY "ThreadID") AS "PostMax" ON ("PostMax"."ThreadID" = "ForumThread"."ID")',
                $this->ID
            ))
            ->addOrderBy('"PostMax"."PostMax" DESC')
            ->setDistinct(false);

        // Build result as ArrayList
        $res = new ArrayList();
        $rows = $query->execute();
        if ($rows) {
            foreach ($rows as $row) {
                $res->push(new ForumThread($row));
            }
        }

        return $res;
    }
}
