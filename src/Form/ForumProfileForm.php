<?php

namespace FullscreenInteractive\SilverStripe\Forum\Form;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Security\Member;

class ForumProfileForm extends Form
{
    public function __construct($controller, $name)
    {
        $member = $this->Member();
        $fields = Member::singleton()->getForumFields();

        $validator = $member ? $member->getForumValidator() : Member::singleton()->getForumValidator();

        $actions = FieldList::create([
            FormAction::create("doEditProfile", _t('ForumMemberProfile.SAVECHANGES', 'Save changes'))
        ]);

        parent::__construct($controller, $name, $fields, $actions, $validator);

        if ($member && $member->hasMethod('canEdit') && $member->canEdit()) {
            $member->Password = '';
            $this->loadDataFrom($member);
        }
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

        $existingMember = Member::get()->filter('Email', $data['Email'])->first();
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

        return $this->controller->redirect($this->controller->Link('thanks'));
    }
}
