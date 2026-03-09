<?php

namespace FullscreenInteractive\SilverStripe\Forum\Extensions;

use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use SilverStripe\Core\Extension;
use SilverStripe\Assets\Image;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumHolder;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumMemberProfile;
use SilverStripe\Control\Email\Email;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\EmailField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordViewer;
use SilverStripe\Forms\PasswordField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Member;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

class ForumMemberExtension extends Extension
{
    private static $db =  [
        'UUID' => 'Varchar(36)',
        'FirstNamePublic' => 'Boolean',
        'SurnamePublic' => 'Boolean',
        'EmailPublic' => 'Boolean',
        'ForumRank' => 'Varchar',
        'Nickname' => 'Varchar(200)',
        'LastViewed' => 'Datetime',
        'Signature' => 'Text',
        'ForumStatus' => 'Enum("Normal, Banned, Ghost", "Normal")',
        'SuspendedUntil' => 'Date'
    ];

    private static $has_one = [
        'Avatar' => Image::class
    ];

    private static $has_many = [
        'ForumPosts' => Post::class
    ];

    private static $owns = [
        'Avatar'
    ];

    private static $cascade_deletes = [
        'Avatar'
    ];

    private static $belongs_many_many = [
        'ModeratedForums' => Forum::class
    ];

    private static $defaults = [
        'ForumRank' => 'Community Member'
    ];

    private static $indexes = [
        'Nickname' => true,
        'UUID' => true
    ];

    private static $field_labels = [
        'SuspendedUntil' => "Suspend this member from writing on forums until the specified date"
    ];


    public function onBeforeWrite()
    {
        if (!$this->owner->UUID) {
            $uuid = uniqid();
            $check = Member::get()->filter('UUID', $uuid)->exists();

            while ($check) {
                $uuid = uniqid();
                $check = Member::get()->filter('UUID', $uuid)->exists();
            }

            $this->owner->UUID = $uuid;
        }
    }


    public function ForumRank()
    {
        $moderatedForums = $this->owner->ModeratedForums();

        if ($moderatedForums && $moderatedForums->Count() > 0) {
            return _t('MODERATOR', 'Forum Moderator');
        } else {
            return $this->owner->getField('ForumRank');
        }
    }


    public function FirstNamePublic()
    {
        return $this->owner->FirstNamePublic || Permission::check('ADMIN');
    }


    public function SurnamePublic()
    {
        return $this->owner->SurnamePublic || Permission::check('ADMIN');
    }


    public function EmailPublic()
    {
        return $this->owner->EmailPublic || Permission::check('ADMIN');
    }



    public function getMemberProfileLink(): string
    {
        $page = ForumMemberProfile::get()->first();

        if (!$page) {
            return '';
        }

        if ($this->owner->IsSuspended() || $this->owner->IsBanned() || $this->owner->IsGhost()) {
            return '';
        }

        return $page->Link('show/' . $this->owner->UUID);
    }


    public function NumPosts()
    {
        return $this->owner->ForumPosts()->Count();
    }


    /**
     * Checks if the current user is a moderator of the given forum by looking
     * in the moderator ID list.
     */
    public function isModeratingForum(Forum $forum): bool
    {
        $moderatorIds = $forum->Moderators() ? $forum->Moderators()->getIdList() : [];
        return in_array($this->owner->ID, $moderatorIds);
    }


    /**
     * Get the fields needed by the forum module.
     */
    public function getForumFields(): FieldList
    {

        $gravatarText = ForumHolder::get()->filter([
            "AllowGravatars" => 1
        ])->exists() ? '<small>' . _t('ForumRole.CANGRAVATAR', 'If you use Gravatars then leave this blank') . '</small>' : "";

        $avatarField = FileField::create('Avatar', _t('ForumRole.AVATAR', 'Avatar Image') . ' ' . $gravatarText);
        $avatarField->setFolderName(ForumHolder::config()->get('avatars_folder'));
        $avatarField->getValidator()->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);

        $personalDetailsFields = CompositeField::create([
            LiteralField::create("PersonalDetails", "<h2>" . _t('ForumRole.PERSONAL', 'Personal Details') . "</h2>"),
            TextField::create("Nickname", _t('ForumRole.NICKNAME', 'Nickname')),
            FieldGroup::create([
                TextField::create("FirstName", _t('ForumRole.FIRSTNAME', 'First name')),
                CheckboxField::create("FirstNamePublic", _t('ForumRole.FIRSTNAMEPUBLIC', 'Public?'), 1),
            ]),
            FieldGroup::create([
                TextField::create("Surname", _t('ForumRole.SURNAME', 'Surname')),
                CheckboxField::create("SurnamePublic", _t('ForumRole.SURNAMEPUBLIC', 'Public?'), 1),
            ]),
            FieldGroup::create([
                EmailField::create("Email", _t('ForumRole.EMAIL', 'Email')),
                CheckboxField::create("EmailPublic", _t('ForumRole.EMAILPUBLIC', 'Public?'), 1),
            ]),
            PasswordField::create("Password", _t('ForumRole.PASSWORD', 'Password')),
            $avatarField,
            TextareaField::create("Signature", _t('ForumRole.SIGNATURE', 'Signature')),
        ]);

        $fieldset = FieldList::create(
            $personalDetailsFields
        );

        $isSuspended = $this->owner->IsSuspended();

        if ($isSuspended) {
            $fieldset->insertAfter(
                'Blurb',
                LiteralField::create(
                    'SuspensionNote',
                    '<p class="message warning suspensionWarning">' . $this->ForumSuspensionMessage() . '</p>'
                ),
            );
        }

        $this->owner->extend('updateForumFields', $fieldset);

        return $fieldset;
    }

    /**
     * Get the fields needed by the forum module
     *
     * @param bool $needPassword Should a password be required?
     * @return Validator Returns a Validator for the fields required for the
     *                              registration of new users
     */
    public function getForumValidator($needPassword = true)
    {
        if ($needPassword) {
            $validator = RequiredFieldsValidator::create(["Nickname", "Email", "Password"]);
        } else {
            $validator = RequiredFieldsValidator::create(["Nickname", "Email"]);
        }

        $this->getOwner()->extend('updateForumValidator', $validator);

        return $validator;
    }


    public function updateCMSFields(FieldList $fields)
    {
        $allForums = Forum::get();
        $fields->removeByName([
            'UUID',
            'FirstNamePublic',
            'SurnamePublic',
            'EmailPublic',
            'CountryPublic',
            'ForumRank',
            'ForumStatus',
            'LastViewed',
            'SuspendedUntil',
            'Avatar',
            'Nickname',
            'ModeratedForums',
            'Signature',
            'ForumPosts'
        ]);


        $avatarField = FileField::create('Avatar', _t('ForumRole.UPLOADAVATAR', 'Upload avatar'));
        $avatarField->getValidator()->setAllowedExtensions(['jpg', 'jpeg', 'gif', 'png']);

        $fields->addFieldsToTab('Root.Forum', [
            TextField::create('Nickname', _t('ForumRole.NICKNAME', 'Nickname')),
            DropdownField::create("ForumRank", _t('ForumRole.FORUMRANK', "User rating"), [
                "Community Member" => _t('ForumRole.COMMEMBER', 'Community Member'),
                "Administrator" => _t('ForumRole.ADMIN', 'Administrator'),
                "Moderator" => _t('ForumRole.MOD', 'Moderator')
            ]),
            $avatarField,
            DropdownField::create("ForumStatus", _t('ForumRole.FORUMSTATUS', "Forum status"), [
                "Normal" => _t('ForumRole.NORMAL', 'Normal'),
                "Banned" => _t('ForumRole.BANNED', 'Banned'),
                "Ghost" => _t('ForumRole.GHOST', 'Ghost')
            ]),
            TextareaField::create("Signature", _t('ForumRole.SIGNATURE', 'Signature')),
        ]);

        $forums = $allForums->map('ID', 'Title');

        $fields->addFieldsToTab('Root.Forum', [
            CheckboxSetField::create('ModeratedForums', _t('ForumRole.MODERATEDFORUMS', 'Moderated forums'), $forums)
        ]);

        $fields->addFieldsToTab('Root.Forum', [
            GridField::create(
                "ForumPosts",
                _t('ForumRole.FORUMPOSTS', 'Forum posts'),
                $this->owner->ForumPosts(),
                GridFieldConfig_RecordViewer::create()
            )
        ]);
    }

    public function IsSuspended(): bool
    {
        $suspendedUntil = $this->owner->dbObject('SuspendedUntil');
        if ($suspendedUntil && $suspendedUntil->exists()) {
            return $suspendedUntil->isInThePast();
        }

        return false;
    }


    public function IsBanned(): bool
    {
        return $this->owner->ForumStatus == 'Banned';
    }


    public function IsGhost(): bool
    {
        return $this->owner->ForumStatus == 'Ghost' && $this->owner->ID !== Security::getCurrentUser()->ID;
    }


    /**
     * Can the current user edit the given member?
     *
     * @return true if this member can be edited, false otherwise
     */
    public function canEdit($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }

        if ($this->owner->ID == Security::getCurrentUser()->ID) {
            return true;
        }

        if ($member) {
            return $member->can('AdminCMS');
        }

        return false;
    }


    /**
     * Used in preference to the Nickname field on templates
     *
     * Provides a default for the nickname field (first name, or "Anonymous
     * User" if that's not set)
     */
    public function Nickname()
    {
        if ($this->owner->Nickname) {
            return $this->owner->Nickname;
        } elseif ($this->owner->FirstName) {
            return $this->owner->FirstName;
        } elseif ($this->owner->Surname) {
            return $this->owner->Surname;
        } else {
            return _t('ForumRole.ANONYMOUS', 'Anonymous user');
        }
    }

    /**
     * Return the url of the avatar or gravatar of the selected user.
     * Checks to see if the current user has an avatar, if they do use it
     * otherwise query gravatar.com
     */
    public function getFormattedAvatar(): string
    {
        $default = Forum::config()->get('default_avatar_url');

        // if they have uploaded an image
        if ($this->owner->AvatarID) {
            $avatar = Image::get()->byID($this->owner->AvatarID);

            if ($avatar) {
                return $avatar->SetWidth(240)->URL;
            }
        }

        // If Gravatar is enabled, allow the selection of the type of default Gravatar.
        if ($holder = ForumHolder::get()->filter('AllowGravatars', 1)->first()) {
            // If the GravatarType is one of the special types, then set it otherwise use the default image from above forummember_holder.gif
            if ($holder->GravatarType) {
                $default = $holder->GravatarType;
            }

            return "http://www.gravatar.com/avatar/" . md5($this->owner->Email) . "?amp;size=240";
        }

        return $default ?? "";
    }

    /**
     * Conditionally includes admin email address (hence we can't simply generate this
     * message in templates). We don't need to spam protect the email address as
     * the note only shows to logged-in users.
     */
    public function ForumSuspensionMessage(): string
    {
        $msg = _t('ForumRole.SUSPENSIONNOTE', 'This forum account has been suspended.');
        $adminEmail = Email::config()->get('admin_email');

        if ($adminEmail) {
            $msg .= ' ' . sprintf(
                _t('ForumRole.SUSPENSIONEMAILNOTE', 'Please contact %s to resolve this issue.'),
                $adminEmail
            );
        }
        return $msg;
    }
}
