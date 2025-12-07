<?php

namespace SilverStripe\Forum\PageTypes;

use PageController;
use SilverStripe\Forum\Model\Post;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * ForumMemberProfile is the profile pages for a given ForumMember
 *
 * @package forum
 */
class ForumMemberProfileController extends PageController
{

    private static $allowed_actions = [
        'show',
        'register',
        'RegistrationForm',
        'edit',
        'EditProfileForm',
        'thanks',
    ];

    /**
     * Return a set of {@link Forum} objects that
     * this member is a moderator of.
     *
     * @return ComponentSet
     */
    function ModeratedForums()
    {
        $member = $this->Member();
        return $member ? $member->ModeratedForums() : null;
    }

    /**
     * Create breadcrumbs (just shows a forum holder link and name of user)
     * @return string HTML code to display breadcrumbs
     */
    public function Breadcrumbs()
    {
        $nonPageParts = array();
        $parts = array();

        $forumHolder = $this->getForumHolder();
        $member = $this->Member();

        $parts[] = '<a href="' . $forumHolder->Link() . '">' . $forumHolder->Title . '</a>';
        $nonPageParts[] = _t('ForumMemberProfile.USERPROFILE', 'User Profile');

        return implode(" &raquo; ", array_reverse(array_merge($nonPageParts, $parts)));
    }


    public function show($request)
    {
        $member = $this->Member();

        if (!$member) {
            return $this->httpError(404);
        }

        return $this->renderWith(['ForumMemberProfile_show', 'Page']);
    }

    /**
     * Get the latest 10 posts by this member
     */
    public function LatestPosts()
    {
        return Post::get()
            ->filter('AuthorID', (int)$this->urlParams['ID'])
            ->limit(10, 0)
            ->sort('Created', 'DESC')
            ->filterByCallback(function ($post) {
                return $post->canView();
            });
    }


    /**
     * Show the registration form
     */
    function register()
    {
        return array(
            "Title" => _t('ForumMemberProfile.FORUMREGTITLE', 'Forum Registration'),
            "Subtitle" => _t('ForumMemberProfile.REGISTER', 'Register'),
            "Abstract" => $this->getForumHolder()->ProfileAbstract,
        );
    }


    /**
     * Factory method for the registration form
     *
     * @return Form Returns the registration form
     */
    function RegistrationForm()
    {
        $data = Session::get("FormInfo.Form_RegistrationForm.data");

        $use_openid =
            ($this->getForumHolder()->OpenIDAvailable() == true) &&
            (isset($data['IdentityURL']) && !empty($data['IdentityURL'])) ||
            (isset($_POST['IdentityURL']) && !empty($_POST['IdentityURL']));

        $fields = singleton('Member')->getForumFields($use_openid, true);

        // If a BackURL is provided, make it hidden so the post-registration
        // can direct to it.
        if (isset($_REQUEST['BackURL'])) {
            $fields->push(new HiddenField('BackURL', 'BackURL', $_REQUEST['BackURL']));
        }

        $validator = singleton('Member')->getForumValidator(!$use_openid);
        $form = new Form(
            $this,
            'RegistrationForm',
            $fields,
            new FieldList(new FormAction("doregister", _t('ForumMemberProfile.REGISTER', 'Register'))),
            $validator
        );

        // Guard against automated spam registrations by optionally adding a field
        // that is supposed to stay blank (and is hidden from most humans).
        // The label and field name are intentionally common ("username"),
        // as most spam bots won't resist filling it out. The actual username field
        // on the forum is called "Nickname".
        if (ForumHolder::$use_honeypot_on_register) {
            $form->Fields()->push(
                new LiteralField(
                    'HoneyPot',
                    '<div style="position: absolute; left: -9999px;">' .
                        // We're super paranoid and don't mention "ignore" or "blank" in the label either
                        '<label for="RegistrationForm_username">' . _t('ForumMemberProfile.LeaveBlank', 'Don\'t enter anything here') . '</label>' .
                        '<input type="text" name="username" id="RegistrationForm_username" value="" />' .
                        '</div>'
                )
            );
        }

        $member = new Member();

        // we should also load the data stored in the session. if failed
        if (is_array($data)) {
            $form->loadDataFrom($data);
        }

        // Optional spam protection
        if (class_exists('SpamProtectorManager') && ForumHolder::$use_spamprotection_on_register) {
            $form->enableSpamProtection();
        }
        return $form;
    }


    /**
     * Register a new member
     *
     * @param array $data User submitted data
     * @param Form $form The used form
     */
    function doregister($data, $form)
    {

        // Check if the honeypot has been filled out
        if (ForumHolder::$use_honeypot_on_register) {
            if (@$data['username']) {
                SS_Log::log(sprintf(
                    'Forum honeypot triggered (data: %s)',
                    http_build_query($data)
                ), SS_Log::NOTICE);
                return $this->httpError(403);
            }
        }

        $forumGroup = Group::get()->filter('Code', 'forum-members')->first();

        if ($member = Member::get()->filter('Email', $data['Email'])->first()) {
            if ($member) {
                $form->addErrorMessage(
                    "Blurb",
                    _t('ForumMemberProfile.EMAILEXISTS', 'Sorry, that email address already exists. Please choose another.'),
                    "bad"
                );

                // Load errors into session and post back
                Session::set("FormInfo.Form_RegistrationForm.data", $data);
                return $this->redirectBack();
            }
        } elseif (
            $this->getForumHolder()->OpenIDAvailable()
            && isset($data['IdentityURL'])
            && ($member = Member::get()->filter('IdentityURL', $data['IdentityURL'])->first())
        ) {
            $errorMessage = _t('ForumMemberProfile.OPENIDEXISTS', 'Sorry, that OpenID is already registered. Please choose another or register without OpenID.');
            $form->addErrorMessage("Blurb", $errorMessage, "bad");

            // Load errors into session and post back
            Session::set("FormInfo.Form_RegistrationForm.data", $data);
            return $this->redirectBack();
        } elseif ($member = Member::get()->filter('Nickname', $data['Nickname'])->first()) {
            $errorMessage = _t('ForumMemberProfile.NICKNAMEEXISTS', 'Sorry, that nickname already exists. Please choose another.');
            $form->addErrorMessage("Blurb", $errorMessage, "bad");

            // Load errors into session and post back
            Session::set("FormInfo.Form_RegistrationForm.data", $data);
            return $this->redirectBack();
        }

        // create the new member
        $member = Object::create('Member');
        $form->saveInto($member);

        $member->write();
        $member->login();

        $member->Groups()->add($forumGroup);

        $member->extend('onForumRegister', $this->request);

        if (isset($data['BackURL']) && $data['BackURL']) {
            return $this->redirect($data['BackURL']);
        }

        return array("Form" => ForumHolder::get()->first()->ProfileAdd);
    }


    /**
     * Edit profile
     *
     * @return array Returns an array to render the edit profile page.
     */
    public function edit()
    {
        $holder = ForumHolder::get()->first();
        $form = $this->EditProfileForm();

        if (!$form && Security::getCurrentUser()) {
            $form = "<p class=\"error message\">" . _t('ForumMemberProfile.WRONGPERMISSION', 'You don\'t have the permission to edit that member.') . "</p>";
        } elseif (!$form) {
            return $this->redirect('ForumMemberProfile/show/' . $this->Member()->ID);
        }

        return array(
            "Title" => "Forum",
            "Subtitle" => $holder->ProfileSubtitle,
            "Abstract" => $holder->ProfileAbstract,
            "Form" => $form,
        );
    }


    /**
     * Factory method for the edit profile form
     *
     * @return Form Returns the edit profile form.
     */
    public function EditProfileForm()
    {
        $member = $this->Member();
        $show_openid = (isset($member->IdentityURL) && !empty($member->IdentityURL));

        $fields = $member ? $member->getForumFields($show_openid) : Member::singleton()->getForumFields($show_openid);
        $validator = $member ? $member->getForumValidator(false) : Member::singleton()->getForumValidator(false);
        if ($holder = ForumHolder::get()->filter('DisplaySignatures', 1)->first()) {
            $fields->push(TextareaField::create('Signature', 'Forum Signature'));
        }

        $form = Form::create(
            $this,
            'EditProfileForm',
            $fields,
            FieldList::create(FormAction::create("dosave", _t('ForumMemberProfile.SAVECHANGES', 'Save changes'))),
            $validator
        );

        if ($member && $member->hasMethod('canEdit') && $member->canEdit()) {
            $member->Password = '';
            $form->loadDataFrom($member);
            return $form;
        }

        return null;
    }


    /**
     * Save member profile action
     *
     * @param array $data
     * @param $form
     */
    function dosave($data, $form)
    {
        $member = Member::currentUser();

        $SQL_email = Convert::raw2sql($data['Email']);
        $forumGroup = DataObject::get_one('Group', "\"Code\" = 'forum-members'");

        // An existing member may have the requested email that doesn't belong to the
        // person who is editing their profile - if so, throw an error
        $existingMember = DataObject::get_one('Member', "\"Email\" = '$SQL_email'");
        if ($existingMember) {
            if ($existingMember->ID != $member->ID) {
                $form->addErrorMessage(
                    'Blurb',
                    _t(
                        'ForumMemberProfile.EMAILEXISTS',
                        'Sorry, that email address already exists. Please choose another.'
                    ),
                    'bad'
                );

                return $this->redirectBack();
            }
        }

        $nicknameCheck = Member::get()->filter('Nickname', $data['Nickname'])->exclude('ID', $member->ID)->first();

        if ($nicknameCheck) {
            $form->addErrorMessage(
                "Blurb",
                _t('ForumMemberProfile.NICKNAMEEXISTS', 'Sorry, that nickname already exists. Please choose another.'),
                "bad"
            );
            return $this->redirectBack();
        }

        $form->saveInto($member);
        $member->write();

        if (!$member->inGroup($forumGroup)) {
            $forumGroup->Members()->add($member);
        }

        $member->extend('onForumUpdateProfile', $this->request);

        return $this->redirect('thanks');
    }


    /**
     * Print the "thank you" page
     *
     * Used after saving changes to a member profile.
     *
     * @return array Returns the needed data to render the page.
     */
    public function thanks()
    {
        $holder = ForumHolder::get()->first();

        return [
            "Form" => $holder?->ProfileModify
        ];
    }


    /**
     * Return the with the passed ID (via URL parameters) or the current user
     *
     * @return null|Member Returns the member object or NULL if the member
     *                     was not found
     */
    public function Member()
    {
        $member = null;
        if (!empty($this->urlParams['ID']) && is_numeric($this->urlParams['ID'])) {
            $member = Member::get()->byID($this->urlParams['ID']);
        } else {
            $member = Security::getCurrentUser();
        }

        return $member;
    }

    /**
     * Get a subtitle
     */
    public function getHolderSubtitle()
    {
        return _t('ForumMemberProfile.USERPROFILE', 'User profile');
    }


    /**
     * This needs MetaTags because it doesn't extend SiteTree at any point
     */
    public function MetaTags($includeTitle = true)
    {
        $tags = "";
        $title = _t('ForumMemberProfile.FORUMUSERPROFILE', 'Forum User Profile');

        if (isset($this->urlParams['Action'])) {
            if ($this->urlParams['Action'] == "register") {
                $title = _t('ForumMemberProfile.FORUMUSERREGISTER', 'Forum Registration');
            }
        }
        if ($includeTitle == true) {
            $tags .= "<title>" . $title . "</title>\n";
        }

        return $tags;
    }
}
