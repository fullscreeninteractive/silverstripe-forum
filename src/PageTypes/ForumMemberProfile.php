<?php

namespace FullscreenInteractive\SilverStripe\Forum\PageTypes;

use Page;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\TextField;

class ForumMemberProfile extends Page
{
    private static string $table_name = 'ForumMemberProfile';

    private static ?string $cms_icon_class = 'font-icon-p-profile';

    private static array $db = [
        "ProfileSubtitle" => "Varchar(200)",
        "ProfileAbstract" => "HTMLText",
        "ProfileModify" => "HTMLText",
        "ProfileAdd" => "HTMLText",
    ];

    private static array $defaults = [
        "ProfileSubtitle" => "Edit Your Profile",
        "ProfileAbstract" => "<p>Please fill out the fields below. You can choose whether some are publically visible by using the checkbox for each one.</p>",
        "ProfileModify" => "<p>Thanks, your member profile has been modified.</p>",
        "ProfileAdd" => "<p>Thanks, you are now signed up to the forum.</p>",
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldsToTab('Root.Main', [
            TextField::create('ProfileSubtitle', 'Profile Subtitle'),
            HTMLEditorField::create('ProfileAbstract', 'Profile Abstract'),
            HTMLEditorField::create('ProfileModify', 'Profile Modify'),
            HTMLEditorField::create('ProfileAdd', 'Profile Add'),
        ]);

        return $fields;
    }
}
