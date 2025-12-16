<?php

namespace FullscreenInteractive\SilverStripe\Forum\Form;

use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\Forum;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Security\Security;

class AdminActionsForm extends Form
{
    public function __construct($controller, $name)
    {

        $fields = FieldList::create([
            CheckboxField::create('IsSticky', _t('Forum.ISSTICKYTHREAD', 'Is this a Sticky Thread?')),
            CheckboxField::create('IsGlobalSticky', _t('Forum.ISGLOBALSTICKY', 'Is this a Global Sticky (shown on all forums)')),
            CheckboxField::create('IsReadOnly', _t('Forum.ISREADONLYTHREAD', 'Is this a Read only Thread?')),
            HiddenField::create('ID', 'Thread')
        ]);

        if (($forums = Forum::get()) && $forums->exists()) {
            $fields->push(DropdownField::create(
                'ForumID',
                _t('Forum.CHANGETHREADFORUM', "Change Thread Forum"),
                $forums->map('ID', 'Title', 'Select New Category:'),
                '',
                null,
                'Select New Location:'
            ));
        }

        $actions = FieldList::create([
            FormAction::create('doAdminFormFeatures', _t('Forum.SAVE', 'Save'))
        ]);

        parent::__construct($controller, $name, $fields, $actions);
    }


    /**
     * Process's the moving of a given topic. Has to check for admin privileges,
     * passed an old topic id (post id in URL) and a new topic id
     */
    public function doAdminFormFeatures($data, $form)
    {
        if (isset($data['ID'])) {
            $thread = ForumThread::get()->byID($data['ID']);

            if ($thread) {
                if (!$thread->canModerate()) {
                    return Security::permissionFailure($this);
                }

                $form->saveInto($thread);
                $thread->write();
            }
        }

        return $this->redirect($this->Link());
    }
}
