<?php

namespace FullscreenInteractive\SilverStripe\Forum\Form;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Security\Group;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

class ForumProfileForm extends Form
{
    public function __construct($controller, $name)
    {
        $fields = Member::singleton()->getForumFields();
        $validator = Member::singleton()->getForumValidator();

        $actions = FieldList::create([
            FormAction::create("doEditProfile", _t('ForumMemberProfile.SAVECHANGES', 'Save changes'))
        ]);

        parent::__construct($controller, $name, $fields, $actions, $validator);
    }


    /**
     * Save member profile action
     *
     * @param array $data
     * @param $form
     */
    public function doEditProfile($data, $form)
    {
        $member = Security::getCurrentUser();
        $forumGroup = Group::get()->filter('Code', 'forum-members')->first();
        $existingMember = Member::get()->filter([
            'Email:nocase' => $data['Email'],
            'ID:not' => $member->ID
        ])->first();

        if ($existingMember) {
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

        $nicknameCheck = Member::get()->filter([
            'Nickname:nocase' => $data['Nickname'],
            'ID:not' => $member->ID
        ])->first();

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

        return $this->controller->redirect($this->controller->Link('thanks'));
    }
}
