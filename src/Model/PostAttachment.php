<?php

namespace FullscreenInteractive\SilverStripe\Forum\Model;

use SilverStripe\Assets\File;
use SilverStripe\Security\Security;
use SilverStripe\Core\Convert;
use SilverStripe\Control\HTTPRequest;

/**
 * Attachments for posts (one post can have many attachments)
 */
class PostAttachment extends File
{
    private static string $table_name = 'Forum_PostAttachment';

    private static $has_one = [
        "Post" => Post::class
    ];

    private static $defaults = [
        'ShowInSearch' => 0
    ];

    /**
     * Can a user delete this attachment
     *
     * @return bool
     */
    public function canDelete($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        return ($this->Post()) ? $this->Post()->canDelete($member) : true;
    }

    /**
     * Can a user edit this attachment
     *
     * @return bool
     */
    public function canEdit($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        return ($this->Post()) ? $this->Post()->canEdit($member) : true;
    }

    /**
     * Allows the user to download a file without right-clicking
     */
    public function download()
    {
        if (isset($this->urlParams['ID'])) {
            $SQL_ID = Convert::raw2sql($this->urlParams['ID']);

            if (is_numeric($SQL_ID)) {
                $file = PostAttachment::get()->byID($SQL_ID);
                $response = HTTPRequest::send_file(file_get_contents($file->getFullPath()), $file->Name);
                $response->output();
            }
        }

        return $this->redirectBack();
    }
}
