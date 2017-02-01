<?php
namespace SilverStripe\Forum\Models;

use SilverStripe\Assets\File;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;

/**
 * Attachments for posts (one post can have many attachments)
 *
 * @package forum
 * @method Post Post
 */
class PostAttachment extends File
{

    private static $has_one = array(
        "Post" => "Post"
    );

    private static $defaults = array(
        'ShowInSearch' => 0
    );

    /**
     * Can a user delete this attachment
     *
     * @return bool
     */
    public function canDelete($member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
        }

        return ($this->Post()) ? $this->Post()->canDelete($member) : true;
    }

    /**
     * Can a user edit this attachement
     *
     * @return bool
     */
    public function canEdit($member = null)
    {
        if (!$member) {
            $member = Member::currentUser();
        }

        return ($this->Post()) ? $this->Post()->canEdit($member) : true;
    }

    /**
     * Allows the user to download a file without right-clicking
     */
    public function download()
    {
        if (isset($this->urlParams['ID'])) {
            $id = Convert::raw2sql($this->urlParams['ID']);

            if (is_numeric($id)) {
                /** @var File $file */
                $file     = File::get()->byID($id);
                $response = HTTPRequest::send_file(file_get_contents($file->getFilename()), $file->Name);
                $response->output();
            }
        }
        
        // todo
        return $this->redirectBack();
    }
}
