<?php

namespace FullscreenInteractive\SilverStripe\Forum\PageTypes;

use Page;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\GridField\GridField;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumCategory;
use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use SilverStripe\Control\Controller;
use SilverStripe\ORM\DB;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;

class ForumHolder extends Page
{
    private static string $table_name = 'ForumHolder';

    private static string $avatars_folder = 'forum/avatars/';

    private static ?string $cms_icon_class = 'font-icon-p-alt-3';

    private static string $attachments_folder = 'forum/attachments/';

    private static array $db = [
        "HolderSubtitle" => "Varchar(200)",
        "ForumSubtitle" => "Varchar(200)",
        "HolderAbstract" => "HTMLText",
        "ForumAbstract" => "HTMLText",
        "DisplaySignatures" => "Boolean",
        "ShowInCategories" => "Boolean",
        "AllowGravatars" => "Boolean",
        "GravatarType" => "Varchar(10)",
        "ForbiddenWords" => "Text",
        'CanRegister' => 'Boolean',
        "CanPostType" => "Enum('Anyone, LoggedInUsers, OnlyTheseUsers, NoOne', 'LoggedInUsers')",
    ];

    private static array $has_many = [
        "Categories" => ForumCategory::class
    ];

    private static array $owns = [
        "Categories"
    ];

    private static array $cascade_deletes = [
        "Categories"
    ];

    private static array $allowed_children = [
        Forum::class
    ];

    private static $defaults = [
        "HolderSubtitle" => "Welcome to our forum!",
        "ForumSubtitle" => "Start a new topic",
        "ForumAbstract" => "<p>From here you can start a new topic.</p>",
        "CanRegister" => true,
    ];

    /**
     * If the user has spam protection enabled and setup then we can provide spam
     * prevention for the forum. This stores whether we actually want the registration
     * form to have such protection
     *
     * @var bool
     */
    private static bool $use_spam_protection_on_register = true;

    /**
     * If the user has spam protection enabled and setup then we can provide spam
     * prevention for the forum. This stores whether we actually want the posting
     * form (adding, replying) to have such protection
     *
     * @var bool
     */
    private static bool $use_spam_protection_on_posts = false;

    /**
     * Add a hidden field to the form which should remain empty
     * If its filled out, we can assume that a spam bot is auto-filling fields.
     *
     * @var bool
     */
    private static bool $use_honeypot_on_register = false;

    /**
     * @var bool If TRUE, each logged in Member who visits a Forum will write the LastViewed field
     * which is for the "Currently online" functionality.
     */
    private static $currently_online_enabled = false;

    public function getCMSFields()
    {
        $self = $this;

        $this->beforeUpdateCMSFields(function ($fields) use ($self) {

            $fields->addFieldsToTab("Root.Messages", [
                TextField::create("HolderSubtitle", "Subtitle"),
                HTMLEditorField::create("HolderAbstract", "Abstract"),
                TextField::create("ForumSubtitle", "Create topic Subtitle"),
                HTMLEditorField::create("ForumAbstract", "Create topic Abstract"),
            ]);
            $fields->addFieldsToTab("Root.Settings", [
                CheckboxField::create("DisplaySignatures", "Display Member Signatures?"),
                CheckboxField::create("ShowInCategories", "Show Forums In Categories?"),
                CheckboxField::create("CanRegister", "Allow users to register?")
                    ->setDescription("If disabled, users will need to be created in the CMS"),
                CheckboxField::create("AllowGravatars", "Allow Gravatars?")
            ]);

            $fields->addFieldsToTab("Root.Categories", [GridField::create(
                'Category',
                'Category',
                $self->Categories()
            )]);

            $fields->addFieldsToTab("Root.LanguageFilter", [
                TextField::create("ForbiddenWords", "Forbidden words (comma separated)"),
                LiteralField::create("FWLabel", "These words will be replaced by an asterisk")
            ]);

            $fields->addFieldsToTab("Root.Access", [
                HeaderField::create(_t('Forum.ACCESSPOST', 'Who can post to the forum?'), 2),
                OptionsetField::create("CanPostType", "", array(
                    "Anyone" => _t('Forum.READANYONE', 'Anyone'),
                    "LoggedInUsers" => _t('Forum.READLOGGEDIN', 'Logged-in users'),
                    "NoOne" => _t('Forum.READNOONE', 'Nobody. Make Forum Read Only')
                ))
            ]);
        });

        $fields = parent::getCMSFields();

        return $fields;
    }


    public function canPost($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
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
     * Get the number of total posts
     *
     * @return int Returns the number of posts
     */
    public function getNumPosts(): int
    {
        $forums = Forum::get()->filter('ParentID', $this->ID);

        if (!$forums->exists()) {
            return 0;
        }

        return Post::get()->filter('ForumID', $forums->column('ID'))->count();
    }


    /**
     * Get the number of total topics (threads)
     *
     * @return int Returns the number of topics (threads)
     */
    public function getNumTopics()
    {
        $forums = Forum::get()->filter('ParentID', $this->ID);

        if (!$forums->exists()) {
            return 0;
        }

        return ForumThread::get()->filter([
            'ForumID' => $forums->column('ID'),
            'Author.ForumStatus' => 'Normal'
        ])->count();
    }


    /**
     * Get the number of distinct authors
     *
     * @return int Returns the number of distinct authors
     */
    public function getNumAuthors()
    {
        $forums = Forum::get()->filter('ParentID', $this->ID);

        if (!$forums->exists()) {
            return 0;
        }

        return Post::get()->filter([
            'ForumID' => $forums->column('ID'),
            'Author.ForumStatus' => 'Normal'
        ])->distinct('AuthorID')->count();
    }

    /**
     * Is the "Currently Online" functionality enabled?
     * @return bool
     */
    public function CurrentlyOnlineEnabled()
    {
        return $this->config()->currently_online_enabled;
    }

    /**
     * Get a list of currently online users (last 15 minutes)
     * that belong to the "forum-members" code {@link Group}.
     */
    public function CurrentlyOnline()
    {
        if (!$this->CurrentlyOnlineEnabled()) {
            return false;
        }

        $groupIDs = array();

        if ($forumGroup = Group::get()->filter('Code', 'forum-members')->first()) {
            $groupIDs[] = $forumGroup->ID;
        }

        if ($adminGroup = Group::get()->filter('Code', ['administrators', 'Administrators'])->first()) {
            $groupIDs[] = $adminGroup->ID;
        }

        return Member::get()
            ->leftJoin('Group_Members', '"Member"."ID" = "Group_Members"."MemberID"')
            ->filter('GroupID', $groupIDs)
            ->where('"Member"."LastViewed" > ' . DB::getConn()->datetimeIntervalClause('NOW', '-15 MINUTE'))
            ->sort('"Member"."FirstName", "Member"."Surname"');
    }


    /**
     * Get the latest members from the forum group.
     *
     * @return ArrayList
     */
    public function getLatestMembers()
    {
        $groupID = DB::query('SELECT "ID" FROM "Group" WHERE "Code" = \'forum-members\'')->value();

            $latestMembers = Member::get()
                ->leftJoin('Group_Members', '"Member"."ID" = "Group_Members"."MemberID"')
                ->filter('GroupID', $groupID)
                ->sort(['Created' => 'DESC'])
                ->limit(20);

        return $latestMembers;
    }

    /**
     * Get a list of Forum Categories
     * @return DataList
     */
    public function getShowInCategories()
    {
        $forumCategories = ForumCategory::get()->filter('ParentID', $this->ID);
        $showInCategories = $this->getField('ShowInCategories');

        return $forumCategories->exists() && $showInCategories;
    }

    /**
     * Get the forums. Actually its a bit more complex than that
     * we need to group by the Forum Categories.
     *
     * @return ArrayList
     */
    public function Forums()
    {
        $holder = $this;

        if ($this->getShowInCategories()) {
            return ForumCategory::get()
                ->filter('ParentID', $this->ID)
                ->filterByCallback(function ($category) use ($holder) {
                    // Get a list of forums that live under this holder & category
                    $category->CategoryForums = Forum::get()
                        ->filter([
                            'CategoryID' => $category->ID,
                            'ParentID' => $holder->ID,
                            'ShowInMenus' => 1
                        ])
                        ->filterByCallback(function ($forum) {
                            return $forum->canView();
                        });

                    return $category->CategoryForums->exists();
                });
        }

        return Forum::get()
            ->filter([
                'ParentID' => $this->ID,
                'ShowInMenus' => 1
            ])
            ->filterByCallback(function ($forum) {
                return $forum->canView();
            });
    }

    /**
     * A function that returns the correct base table to use for custom forum queries. It uses the getVar stage to determine
     * what stage we are looking at, and determines whether to use SiteTree or SiteTree_Live (the general case). If the stage is
     * not specified, live is assumed (general case). It is a static function so it can be used for both ForumHolder and Forum.
     */
    public static function baseForumTable(): string
    {
        $stage = (Controller::curr()->getRequest()) ? Controller::curr()->getRequest()->getVar('stage') : false;

        if (!$stage) {
            $stage = Versioned::get_stage();
        }

        if ($stage == "Stage") {
            return "SiteTree";
        } else {
            return "SiteTree_Live";
        }
    }


    /**
     * Get the latest posts
     *
     * @param int $limit Number of posts to return
     * @param int $forumID - Forum ID to limit it to
     * @param int $threadID - Thread ID to limit it to
     * @param int $lastVisit Optional: Unix timestamp of the last visit (GMT)
     * @param int $lastPostID Optional: ID of the last read post
     */
    public function getRecentPosts($limit = 50, $forumID = null, $threadID = null, $lastVisit = null, $lastPostID = null)
    {
        if ($lastVisit) {
            $lastVisit = @date('Y-m-d H:i:s', $lastVisit);
        }

        $lastPostID = (int) $lastPostID;
        $posts = Post::get();

        // last post viewed
        if ($lastPostID > 0) {
            $posts = $posts->filter(["ID:GreaterThan" => $lastPostID]);
        }

        // last time visited
        if ($lastVisit) {
            $posts = $posts->filter(["Created:GreaterThan" => $lastVisit]);
        }

        // limit to a forum
        if ($forumID) {
            $posts = $posts->filter(["ForumID" => $forumID]);
        }

        // limit to a thread
        if ($threadID) {
            $posts = $posts->filter(["ThreadID" => $threadID]);
        }

        // limit to just this forum install
        $posts = $posts->filter(["ParentID" => $this->ID]);

        return $posts->sort("ID", "DESC")->limit($limit);
    }


    /**
     * Are new posts available?
     *
     * @param int $id
     * @param array $data Optional: If an array is passed, the timestamp of
     *                    the last created post and it's ID will be stored in
     *                    it (keys: 'last_id', 'last_created')
     * @param int $lastVisit Unix timestamp of the last visit (GMT)
     * @param int $lastPostID ID of the last read post
     * @param int $thread ID of the relevant topic (set to NULL for all
     *                     topics)
     * @return bool Returns TRUE if there are new posts available, otherwise
     *              FALSE.
     */
    public function hasNewPosts($id, &$data = [], $lastVisit = null, $lastPostID = null, $forumID = null, $threadID = null)
    {
        $forums = Forum::get()->filter(["ParentID" => $id]);

        if (!$forums->exists()) {
            return false;
        }

        $posts = Post::get()->filter(["ForumID" => $forums->column("ID")]);

        // last post viewed
        if ($lastPostID) {
            $posts = $posts->filter(["ID:GreaterThan" => $lastPostID]);
        }
        if ($lastVisit) {
            $posts = $posts->filter(["Created:GreaterThan" => $lastVisit]);
        }
        if ($forumID) {
            $posts = $posts->filter(["ForumID" => $forumID]);
        }
        if ($threadID) {
            $posts = $posts->filter(["ThreadID" => $threadID]);
        }

        $lastPost = $posts->sort("ID", "DESC")->limit(1)->first();

        if ($data) {
            $data['last_id'] = (int)$lastPost->ID;
            $data['last_created'] = strtotime($lastPost->Created);
        }

        $lastVisit = (int) $lastVisit;

        if ($lastVisit <= 0) {
            $lastVisit = false;
        }

        $lastPostID = (int)$lastPostID;
        if ($lastPostID <= 0) {
            $lastPostID = false;
        }

        if (!$lastVisit && !$lastPostID) {
            return true;
        }
        if ($lastVisit && (strtotime($lastPost->Created) > $lastVisit)) {
            return true;
        }

        if ($lastPostID && ((int)$lastPost->ID > $lastPostID)) {
            return true;
        }

        return false;
    }
}
