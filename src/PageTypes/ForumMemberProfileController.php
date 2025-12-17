<?php

namespace FullscreenInteractive\SilverStripe\Forum\PageTypes;

use FullscreenInteractive\SilverStripe\Forum\Form\ForumProfileForm;
use FullscreenInteractive\SilverStripe\Forum\Form\ForumRegistrationForm;
use PageController;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use SilverStripe\Forms\TextareaField;
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
     * Return a set of {@link Forum} objects that this member is a moderator of.
     */
    public function ModeratedForums()
    {
        $member = $this->Member();
        return $member ? $member->ModeratedForums() : null;
    }


    public function show()
    {
        $member = $this->Member();

        if (!$member) {
            return $this->httpError(404);
        }

        return [
            "Title" => "Forum",
            "Subtitle" => $this->data()->dbObject('ProfileSubtitle'),
            "Abstract" => $this->data()->dbObject('ProfileAbstract'),
            "Form" => $this->EditProfileForm()
        ];
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
    public function register()
    {
        return [
            "Title" => _t('ForumMemberProfile.FORUMREGTITLE', 'Forum Registration'),
            "Subtitle" => _t('ForumMemberProfile.REGISTER', 'Register'),
            "Abstract" => $this->getForumHolder()->ProfileAbstract,
        ];
    }


    /**
     * Factory method for the registration form
     */
    public function RegistrationForm(): ForumRegistrationForm
    {
        return ForumRegistrationForm::create($this, 'RegistrationForm');
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
            return $this->redirect($this->Member()->getMemberProfileLink());
        }

        return [
            "Title" => "Forum",
            "Subtitle" => $holder->ProfileSubtitle,
            "Abstract" => $holder->ProfileAbstract,
            "Form" => $form
        ];
    }


    /**
     * Factory method for the edit profile form
     */
    public function EditProfileForm(): ForumProfileForm
    {
        return ForumProfileForm::create($this, 'EditProfileForm');
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
     */
    public function Member(): ?Member
    {
        $id = $this->request->param('ID');

        if (empty($id) || !is_numeric($id)) {
            return Security::getCurrentUser();
        }

        return Member::get()->byID($id);
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
