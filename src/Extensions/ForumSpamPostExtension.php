<?php

namespace FullscreenInteractive\SilverStripe\Forum\Extensions;

use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\DataQuery;
use SilverStripe\Security\Security;

/**
 * Extension for the Post data object to add spam protection functionality.
 */
class ForumSpamPostExtension extends Extension
{

    /**
     * Augment the SQL query to add spam protection functionality.
     *
     */
    public function augmentSQL(DataQuery $query)
    {
        $enabled = Post::config()->allow_reading_spam;

        if (!$enabled) {
            return;
        }

        $member = Security::getCurrentUser();
        $forum = $this->getOwner()->ForumID;

        // Do Status filtering

        if ($member && is_numeric($forum->ID) && $member->ID == $forum->Moderator()->ID) {
            $filter = "\"Post\".\"Status\" IN ('Moderated', 'Awaiting')";
        } else {
            $filter = "\"Post\".\"Status\" = 'Moderated'";
        }

        $query->addWhere($filter);

        // Exclude Ghost member posts, but show Ghost members their own posts
        $authorStatusFilter = '"AuthorID" IN (SELECT "ID" FROM "Member" WHERE "ForumStatus" = \'Normal\')';
        if ($member && $member->ForumStatus == 'Ghost') {
            $authorStatusFilter .= ' OR "AuthorID" = ' . $member->ID;
        }

        $query->addWhere($authorStatusFilter);

        $query->setDistinct(false);
    }
}
