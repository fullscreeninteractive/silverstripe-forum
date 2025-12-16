<?php

namespace FullscreenInteractive\SilverStripe\Forum\Form;

use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumHolder;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;

class ForumRegistrationForm extends Form
{
    public function __construct($controller, $name)
    {
        $data = $controller->request->getSession()->get("FormInfo.Form_RegistrationForm.data");

        $useOpenID =
            ($controller->getForumHolder()->OpenIDAvailable() == true) &&
            ($data['IdentityURL'] ?? false) ||
            ($controller->request->postVar('IdentityURL') ?? false);

        $fields = Member::singleton()->getForumFields($useOpenID);

        // If a BackURL is provided, make it hidden so the post-registration
        // can direct to it.
        if ($controller->request->getVar('BackURL')) {
            $fields->push(HiddenField::create('BackURL', 'BackURL', $controller->request->getVar('BackURL')));
        }

        $validator = Member::singleton()->getForumValidator(!$useOpenID);

        $actions = FieldList::create([
            FormAction::create("doRegister", _t('ForumMemberProfile.REGISTER', 'Register'))
        ]);


        // Guard against automated spam registrations by optionally adding a field
        // that is supposed to stay blank (and is hidden from most humans).
        // The label and field name are intentionally common ("username"),
        // as most spam bots won't resist filling it out. The actual username field
        // on the forum is called "Nickname".
        if (ForumHolder::config()->get('use_honeypot_on_register')) {
            $fields->push(
                LiteralField::create(
                    'HoneyPot',
                    '<div style="position: absolute; left: -9999px;">' .
                    '<label for="RegistrationForm_username">' . _t('ForumMemberProfile.LeaveBlank', 'Don\'t enter anything here') . '</label>' .
                    '<input type="text" name="username" id="RegistrationForm_username" value="" />' .
                    '</div>'
                )
            );
        }

        parent::__construct($controller, $name, $fields, $actions, $validator);

        // Optional spam protection
        if (ForumHolder::config()->get('use_spamprotection_on_register')) {
            $this->enableSpamProtection();
        }

    }


    /**
     * Register a new member
     *
     * @param array $data User submitted data
     * @param Form $form The used form
     */
    public function doRegister($data, $form)
    {
        $forumGroup = Group::get()->filter('Code', 'forum-members')->first();

        if (!$forumGroup) {
            $group = Group::create();
            $group->Code = 'forum-members';
            $group->Title = 'Forum Members';
            $group->write();
        }

        if ($member = Member::get()->filter('Email', $data['Email'])->first()) {
            if ($member) {
                $form->addErrorMessage(
                    "Blurb",
                    _t('ForumMemberProfile.EMAILEXISTS', 'Sorry, that email address already exists. Please choose another.'),
                    "bad"
                );

                // Load errors into session and post back
                $this->request->getSession()->set("FormInfo.Form_RegistrationForm.data", $data);
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
            $this->request->getSession()->set("FormInfo.Form_RegistrationForm.data", $data);
            return $this->redirectBack();
        } elseif ($member = Member::get()->filter('Nickname', $data['Nickname'])->first()) {
            $errorMessage = _t('ForumMemberProfile.NICKNAMEEXISTS', 'Sorry, that nickname already exists. Please choose another.');
            $form->addErrorMessage("Blurb", $errorMessage, "bad");

            // Load errors into session and post back
            $this->request->getSession()->set("FormInfo.Form_RegistrationForm.data", $data);
            return $this->redirectBack();
        }

        // create the new member
        $member = Member::create();
        $form->saveInto($member);

        $member->write();
        $member->login();

        $member->Groups()->add($forumGroup);
        $member->extend('onForumRegister', $this->request);

        if (isset($data['BackURL']) && $data['BackURL']) {
            return $this->controller->redirect($data['BackURL']);
        }

        return $this->controller->redirect(ForumHolder::get()->first()->Link());
    }
}
