<?php

namespace FullscreenInteractive\SilverStripe\Forum\Extensions;

use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use SilverStripe\Core\Extension;
use SilverStripe\Assets\Image;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumHolder;
use SilverStripe\Control\Email\Email;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

class ForumMemberExtension extends Extension
{
    private static $db =  [
        'ForumRank' => 'Varchar',
        'Occupation' => 'Varchar',
        'Company' => 'Varchar',
        'City' => 'Varchar',
        'Country' => 'Varchar',
        'Nickname' => 'Varchar',
        'FirstNamePublic' => 'Boolean',
        'SurnamePublic' => 'Boolean',
        'OccupationPublic' => 'Boolean',
        'CompanyPublic' => 'Boolean',
        'CityPublic' => 'Boolean',
        'CountryPublic' => 'Boolean',
        'EmailPublic' => 'Boolean',
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

    private static $searchable_fields = [
        'Nickname' => true
    ];

    private static $indexes = [
        'Nickname' => true
    ];

    private static $field_labels = [
        'SuspendedUntil' => "Suspend this member from writing on forums until the specified date"
    ];

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


    public function OccupationPublic()
    {
        return $this->owner->OccupationPublic || Permission::check('ADMIN');
    }


    public function CompanyPublic()
    {
        return $this->owner->CompanyPublic || Permission::check('ADMIN');
    }


    public function CityPublic()
    {
        return $this->owner->CityPublic || Permission::check('ADMIN');
    }


    public function CountryPublic()
    {
        return $this->owner->CountryPublic || Permission::check('ADMIN');
    }
    public function EmailPublic()
    {
        return $this->owner->EmailPublic || Permission::check('ADMIN');
    }

    /**
     * Run the Country code through a converter to get the proper Country Name
     */
    public function FullCountry()
    {
        $locale = new Locale();
        $locale->setLocale($this->owner->Country);
        return $locale->getRegion();
    }


    public function NumPosts()
    {
        if (is_numeric($this->owner->ID)) {
            return $this->owner->ForumPosts()->Count();
        } else {
            return 0;
        }
    }


    /**
     * Checks if the current user is a moderator of the
     * given forum by looking in the moderator ID list.
     *
     * @param Forum object to check
     * @return boolean
     */
    public function isModeratingForum($forum)
    {
        $moderatorIds = $forum->Moderators() ? $forum->Moderators()->getIdList() : array();
        return in_array($this->owner->ID, $moderatorIds);
    }

    public function Link()
    {
        return "ForumMemberProfile/show/" . $this->owner->ID;
    }


    /**
     * Get the fields needed by the forum module
     *
     * @param bool $showIdentityURL Should a field for an OpenID or an i-name
     *                              be shown (always read-only)?
     * @return FieldList Returns a FieldList containing all needed fields for
     *                  the registration of new users
     */
    public function getForumFields($showIdentityURL = false, $addOnlyMode = false)
    {
        $owner = $this->getOwner();
        $gravatarText = ForumHolder::get()->filter([
            "AllowGravatars" => 1
        ])->exists() ? '<small>' . _t('ForumRole.CANGRAVATAR', 'If you use Gravatars then leave this blank') . '</small>' : "";

        //Sets the upload folder to the Configurable one set via the ForumHolder or overridden via Config::inst()->update().
        $avatarField = FileField::create('Avatar', _t('ForumRole.AVATAR', 'Avatar Image') . ' ' . $gravatarText);
        $avatarField->setFolderName(ForumHolder::config()->get('avatars_folder'));
        $avatarField->getValidator()->setAllowedExtensions(array('jpg', 'jpeg', 'gif', 'png'));

        $personalDetailsFields = CompositeField::create([
            LiteralField::create("PersonalDetails", "<h2>" . _t('ForumRole.PERSONAL', 'Personal Details') . "</h2>"),
            LiteralField::create("Blurb", "<p id=\"helpful\">" . _t('ForumRole.TICK', 'Tick the fields to show in public profile') . "</p>"),
            TextField::create("Nickname", _t('ForumRole.NICKNAME', 'Nickname')),
            TextField::create("FirstName", _t('ForumRole.FIRSTNAME', 'First name')),
            TextField::create("Surname", _t('ForumRole.SURNAME', 'Surname')),
            TextField::create("Occupation", _t('ForumRole.OCCUPATION', 'Occupation')),
            TextField::create('Company', _t('ForumRole.COMPANY', 'Company')),
            TextField::create('City', _t('ForumRole.CITY', 'City')),
            DropdownField::create("Country", _t('ForumRole.COUNTRY', 'Country')),
            EmailField::create("Email", _t('ForumRole.EMAIL', 'Email')),
            PasswordField::create("Password", _t('ForumRole.PASSWORD', 'Password')),
            $avatarField
        ]);
        // Don't show 'forum rank' at registration
        if (!$addOnlyMode) {
            $personalDetailsFields->push(
                ReadonlyField::create("ForumRank", _t('ForumRole.RATING', 'User rating'))
            );
        }
        $personalDetailsFields->setID('PersonalDetailsFields');

        $fieldset = FieldList::create(
            $personalDetailsFields
        );

        if ($showIdentityURL) {
            $fieldset->insertBefore(
                ReadonlyField::create('IdentityURL', _t('ForumRole.OPENIDINAME', 'OpenID/i-name')),
                'Password'
            );
            $fieldset->insertAfter(
                LiteralField::create(
                    'PasswordOptionalMessage',
                    '<p>' . _t('ForumRole.PASSOPTMESSAGE', 'Since you provided an OpenID respectively an i-name the password is optional. If you enter one, you will be able to log in also with your e-mail address.') . '</p>'
                ),
                'IdentityURL'
            );
        }

        $isSuspended = $this->owner->IsSuspended();
        if ($isSuspended) {
            $fieldset->insertAfter(
                LiteralField::create(
                    'SuspensionNote',
                    '<p class="message warning suspensionWarning">' . $this->ForumSuspensionMessage() . '</p>'
                ),
                'Blurb'
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
            $validator = RequiredFields::create(["Nickname", "Email", "Password"]);
        } else {
            $validator = RequiredFields::create(["Nickname", "Email"]);
        }

        $this->getOwner()->extend('updateForumValidator', $validator);

        return $validator;
    }


    public function updateCMSFields(FieldList $fields)
    {
        $allForums = Forum::get();
        $fields->removeByName('ModeratedForums');
        $fields->addFieldToTab('Root.ModeratedForums', CheckboxSetField::create('ModeratedForums', _t('ForumRole.MODERATEDFORUMS', 'Moderated forums'), ($allForums->exists() ? $allForums->map('ID', 'Title') : array())));
        $suspend = $fields->dataFieldByName('SuspendedUntil');
        $suspend->setConfig('showcalendar', true);
        if (Permission::checkMember($this->owner->ID, "ACCESS_FORUM")) {
            $avatarField = new FileField('Avatar', _t('ForumRole.UPLOADAVATAR', 'Upload avatar'));
            $avatarField->getValidator()->setAllowedExtensions(array('jpg', 'jpeg', 'gif', 'png'));

            $fields->addFieldToTab('Root.Forum', $avatarField);
            $fields->addFieldToTab('Root.Forum', new DropdownField("ForumRank", _t('ForumRole.FORUMRANK', "User rating"), array(
                "Community Member" => _t('ForumRole.COMMEMBER'),
                "Administrator" => _t('ForumRole.ADMIN', 'Administrator'),
                "Moderator" => _t('ForumRole.MOD', 'Moderator')
            )));
            $fields->addFieldToTab('Root.Forum', $this->owner->dbObject('ForumStatus')->scaffoldFormField());
        }
    }

    public function IsSuspended(): bool
    {
        if ($this->owner->SuspendedUntil) {
            return strtotime(DBDatetime::now()->Format('Y-m-d')) < strtotime($this->owner->SuspendedUntil);
        } else {
            return false;
        }
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
        } elseif ($this->owner->FirstNamePublic && $this->owner->FirstName) {
            return $this->owner->FirstName;
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

            if (!$avatar) {
                return $default ?? "";
            }

            $resizedAvatar = $avatar->SetWidth(80);
            if (!$resizedAvatar) {
                return $default ?? "";
            }

            return $resizedAvatar->URL;
        }

        //If Gravatar is enabled, allow the selection of the type of default Gravatar.
        if ($holder = ForumHolder::get()->filter('AllowGravatars', 1)->first()) {
            // If the GravatarType is one of the special types, then set it otherwise use the
            //default image from above forummember_holder.gif
            if ($holder->GravatarType) {
                $default = $holder->GravatarType;
            } else {
                // we need to get the absolute path for the default forum image
                return $default ?? "";
            }
            // ok. no image but can we find a gravatar. Will return the default image as defined above if not.
            return "http://www.gravatar.com/avatar/" . md5($this->owner->Email) . "?amp;size=80";
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
