<?php

namespace SilverStripe\Forum\Controllers;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forum\Models\Post;
use SilverStripe\Forum\Pages\ForumHolder;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\ORM\ManyManyList;
use SilverStripe\View\Requirements;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Control\Session;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Security\Member;
use SilverStripe\Logging\Log;
use SilverStripe\Security\Group;
use SilverStripe\Core\Object;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Control\Director;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Security\Security;
use PageController;

/**
 * ForumMemberProfile is the profile pages for a given ForumMember
 *
 * @package forum
 */
class ForumMemberProfile extends PageController
{
    /** @var array */
    private static $allowed_actions = array(
        'show',
        'register',
        'RegistrationForm',
        'registerwithopenid',
        'RegistrationWithOpenIDForm',
        'edit',
        'EditProfileForm',
        'thanks',
    );

    /** @var string */
    public $URLSegment = "ForumMemberProfile";

    /**
     * Return a set of {@link Forum} objects that
     * this member is a moderator of.
     *
     * @return ManyManyList
     */
    public function ModeratedForums()
    {
        $member = $this->Member();

        return ($member) ? $member->ModeratedForums() : null;
    }

    /**
     * Create breadcrumbs (just shows a forum holder link and name of user)
     *
     * @return string HTML code to display breadcrumbs
     */
    public function Breadcrumbs()
    {
        $nonPageParts = array();
        $parts        = array();

        $forumHolder = $this->getForumHolder();

        $parts[]        = '<a href="' . $forumHolder->Link() . '">' . $forumHolder->Title . '</a>';
        $nonPageParts[] = _t('ForumMemberProfile.USERPROFILE', 'User Profile');

        return implode(" &raquo; ", array_reverse(array_merge($nonPageParts, $parts)));
    }

    /**
     * Initialise the controller
     */
    public function init()
    {
        Requirements::themedCSS('Forum', 'all');
        $member       = $this->Member() ? $this->Member() : null;
        $nicknameText = ($member) ? ($member->Nickname . '\'s ') : '';

        $this->Title = DBField::create_field('HTMLText', Convert::raw2xml($nicknameText) . _t('ForumMemberProfile.USERPROFILE', 'User Profile'));

        parent::init();
    }

    /**
     * @param HTTPRequest $request
     *
     * @return DBHTMLText|void
     * @throws HTTPResponse_Exception
     */
    public function show($request)
    {
        $member = $this->Member();
        if (!$member) {
            return $this->httpError(404);
        }

        return $this->renderWith(array('ForumMemberProfile_show', 'Page'));
    }

    /**
     * Get the latest 10 posts by this member
     *
     * @return ArrayList
     */
    public function LatestPosts()
    {
        return Post::get()
            ->filter('AuthorID', (int)$this->urlParams['ID'])
            ->limit(10, 0)
            ->sort('Created', 'DESC')
            ->filterByCallback(function ($post) {
                /** @var Post $post */
                return $post->canView();
            });
    }

    /**
     * Show the registration form
     */
    public function register()
    {
        return array(
            "Title"    => _t('ForumMemberProfile.FORUMREGTITLE', 'Forum Registration'),
            "Subtitle" => _t('ForumMemberProfile.REGISTER', 'Register'),
            "Abstract" => $this->getForumHolder()->ProfileAbstract,
        );
    }

    /**
     * Factory method for the registration form
     *
     * @return Form Returns the registration form
     */
    public function RegistrationForm()
    {
        $data = Session::get("FormInfo.Form_RegistrationForm.data");

        $use_openid =
            ($this->getForumHolder()->OpenIDAvailable() == true) &&
            (isset($data['IdentityURL']) && !empty($data['IdentityURL'])) ||
            (isset($_POST['IdentityURL']) && !empty($_POST['IdentityURL']));

        /** @var FieldList $fields */
        $fields = Member::singleton()->getForumFields($use_openid, true);

        // If a BackURL is provided, make it hidden so the post-registration
        // can direct to it.
        if (isset($_REQUEST['BackURL'])) {
            $fields->push(new HiddenField('BackURL', 'BackURL', $_REQUEST['BackURL']));
        }

        $validator = singleton(Member::class)->getForumValidator(!$use_openid);
        $form      = new Form(
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
        if (ForumHolder::$useHoneypotOnRegister) {
            $form->Fields()->push(
                LiteralField::create(
                    'HoneyPot',
                    '<div style="position: absolute; left: -9999px;">' .
                    // We're super paranoid and don't mention "ignore" or "blank" in the label either
                    '<label for="RegistrationForm_username">' . _t('ForumMemberProfile.LeaveBlank',
                        'Don\'t enter anything here') . '</label>' .
                    '<input type="text" name="username" id="RegistrationForm_username" value="" />' .
                    '</div>'
                )
            );
        }

        // we should also load the data stored in the session. if failed
        if (is_array($data)) {
            $form->loadDataFrom($data);
        }

        // Optional spam protection
        if (class_exists('SpamProtectorManager') && ForumHolder::$useSpamProtectionOnRegister) {
            $form->enableSpamProtection();
        }

        return $form;
    }

    /**
     * Register a new member
     *
     * @param array $data User submitted data
     * @param Form  $form The used form
     *
     * @return array|bool|HTTPResponse
     */
    public function doregister($data, $form)
    {
        // Check if the honeypot has been filled out
        if (ForumHolder::$useHoneypotOnRegister) {
            if (isset($data['username'])) {
                Injector::inst()->get('Logger')->log(sprintf(
                    'Forum honeypot triggered (data: %s)',
                    http_build_query($data)
                ), Log::NOTICE);

                return $this->httpError(403);
            }
        }

        $forumGroup = Group::get()->filter('Code', 'forum-members')->first();

        if ($member = Member::get()->filter('Email', $data['Email'])->first()) {
            if ($member) {
                $form->setFieldMessage(
                    "Blurb",
                    _t(
                        'ForumMemberProfile.EMAILEXISTS',
                        'Sorry, that email address already exists. Please choose another.'
                    ),
                    "bad"
                );

                // Load errors into session and post back
                Session::set("FormInfo.Form_RegistrationForm.data", $data);

                return $this->redirectBack();
            }
        } elseif ($this->getForumHolder()->OpenIDAvailable() && isset($data['IdentityURL']) && ($member = Member::get()->filter('IdentityURL', $data['IdentityURL'])->first())) {
            $errorMessage = _t(
                'ForumMemberProfile.OPENIDEXISTS',
                'Sorry, that OpenID is already registered. Please choose another or register without OpenID.'
            );

            $form->setFieldMessage("Blurb", $errorMessage, "bad");

            // Load errors into session and post back
            Session::set("FormInfo.Form_RegistrationForm.data", $data);

            return $this->redirectBack();

        } elseif ($member = Member::get()->filter('Nickname', $data['Nickname'])->first()) {
            $errorMessage = _t(
                'ForumMemberProfile.NICKNAMEEXISTS',
                'Sorry, that nickname already exists. Please choose another.'
            );

            $form->setFieldMessage("Blurb", $errorMessage, "bad");

            // Load errors into session and post back
            Session::set("FormInfo.Form_RegistrationForm.data", $data);

            return $this->redirectBack();
        }

        // create the new member
        $member = Member::create();
        $form->saveInto($member);

        $member->write();
        $member->logIn();

        $member->Groups()->add($forumGroup);

        $member->extend('onForumRegister', $this->request);

        if (isset($data['BackURL']) && $data['BackURL']) {
            return $this->redirect($data['BackURL']);
        }

        return ["Form" => ForumHolder::get()->first()->ProfileAdd];
    }

    /**
     * Start registration with OpenID
     *
     * @param array $data    Data passed by the director
     * @param array $message Message and message type to output
     *
     * @return array Returns the needed data to render the registration form.
     */
    public function registerwithopenid($data, $message = null)
    {
        if ($message) {
            $message = '<p class="' . $message['type'] . '">' . Convert::raw2xml($message['message']) . '</p>';
        } else {
            $message = "<p>" . _t('ForumMemberProfile.ENTEROPENID', 'Please enter your OpenID to continue the registration') . "</p>";
        }

        return array(
            "Title"    => _t('ForumMemberProfile.SSFORUM'),
            "Subtitle" => _t('ForumMemberProfile.REGISTEROPENID', 'Register with OpenID'),
            "Abstract" => $message,
            "Form"     => $this->RegistrationWithOpenIDForm(),
        );
    }

    /**
     * Factory method for the OpenID registration form
     *
     * @return Form Returns the OpenID registration form
     */
    public function RegistrationWithOpenIDForm()
    {
        $form = Form::create(
            $this,
            'RegistrationWithOpenIDForm',
            FieldList::create(TextField::create("OpenIDURL", "OpenID URL", "", null)),
            FieldList::create(FormAction::create("doregisterwithopenid", _t('ForumMemberProfile.REGISTER', 'Register'))),
            RequiredFields::create("OpenIDURL")
        );

        return $form;
    }


    /**
     * Register a new member
     *
     * @param                          $data
     * @param Form                     $form
     *
     * @return HTTPResponse
     */
    public function doregisterwithopenid($data, Form $form)
    {
        $openid = trim($data['OpenIDURL']);
        Session::set("FormInfo.Form_RegistrationWithOpenIDForm.data", $data);

        if (strlen($openid) == 0) {
            if (!is_null($form)) {
                $form->setFieldMessage(
                    "Blurb",
                    "Please enter your OpenID or your i-name.",
                    "bad"
                );
            }

            return $this->redirectBack();
        }

        $trust_root    = Director::absoluteBaseURL();
        $return_to_url = $trust_root . $this->Link('processopenidresponse');

        $consumer = new \Auth_OpenID_Consumer(new \OpenIDStorage(), new \SessionWrapper());

        // No auth request means we can't begin OpenID
        $auth_request = $consumer->begin($openid);
        if (!$auth_request) {
            if (!is_null($form)) {
                $form->setFieldMessage(
                    "Blurb",
                    "That doesn't seem to be a valid OpenID or i-name identifier. " .
                    "Please try again.",
                    "bad"
                );
            }

            return $this->redirectBack();
        }

        $identity = Convert::raw2sql($auth_request->endpoint->claimed_id);
        if ($member = Member::get()->filter('IdentityURL', $identity)->first()) {
            if (!is_null($form)) {
                $form->setFieldMessage(
                    "Blurb",
                    "That OpenID or i-name is already registered. Use another one.",
                    "bad"
                );
            }

            return $this->redirectBack();
        }

        // Add the fields for which we wish to get the profile data
        $sreg_request = \Auth_OpenID_SRegRequest::build(
            null,
            ['nickname', 'fullname', 'email', 'country']
        );

        if ($sreg_request) {
            $auth_request->addExtension($sreg_request);
        }


        if ($auth_request->shouldSendRedirect()) {
            // For OpenID 1, send a redirect.
            $redirect_url = $auth_request->redirectURL($trust_root, $return_to_url);

            if (\Auth_OpenID::isFailure($redirect_url)) {
                displayError("Could not redirect to server: " .
                    $redirect_url->message);
            } else {
                return $this->redirect($redirect_url);
            }
        } else {
            // For OpenID 2, use a javascript form to send a POST request to the
            // server.
            $form_id   = 'openid_message';
            $form_html = $auth_request->formMarkup($trust_root, $return_to_url, false, array('id' => $form_id));

            if (\Auth_OpenID::isFailure($form_html)) {
                displayError("Could not redirect to server: " . $form_html->message);
            } else {
                $page_contents = array(
                    "<html><head><title>",
                    "OpenID transaction in progress",
                    "</title></head>",
                    "<body onload='document.getElementById(\"" . $form_id .
                    "\").submit()'>",
                    $form_html,
                    "<p>Click &quot;Continue&quot; to login. You are only seeing " .
                    "this because you appear to have JavaScript disabled.</p>",
                    "</body></html>"
                );

                print implode("\n", $page_contents);
            }
        }
    }

    /**
     * Function to process the response of the OpenID server
     */
    public function processopenidresponse()
    {
        $consumer = new \Auth_OpenID_Consumer(new \OpenIDStorage(), new \SessionWrapper());

        $trust_root    = Director::absoluteBaseURL();
        $return_to_url = $trust_root . $this->Link('ProcessOpenIDResponse');

        // Complete the authentication process using the server's response.
        $response = $consumer->complete($return_to_url);

        if ($response->status == Auth_OpenID_SUCCESS) {
            Session::clear("FormInfo.Form_RegistrationWithOpenIDForm.data");
            $openid = $response->identity_url;

            if ($response->endpoint->canonicalID) {
                $openid = $response->endpoint->canonicalID;
            }

            $sreg_resp = \Auth_OpenID_SRegResponse::fromSuccessResponse($response);
            $sreg      = $sreg_resp->contents();

            // Convert the simple registration data to the needed format
            // try to split fullname to get firstname and surname
            $data = array('IdentityURL' => $openid);
            if (isset($sreg['nickname'])) {
                $data['Nickname'] = $sreg['nickname'];
            }
            if (isset($sreg['fullname'])) {
                $fullname = explode(' ', $sreg['fullname'], 2);
                if (count($fullname) == 2) {
                    $data['FirstName'] = $fullname[0];
                    $data['Surname']   = $fullname[1];
                } else {
                    $data['Surname'] = $fullname[0];
                }
            }
            if (isset($sreg['country'])) {
                $data['Country'] = $sreg['country'];
            }
            if (isset($sreg['email'])) {
                $data['Email'] = $sreg['email'];
            }

            Session::set("FormInfo.Form_RegistrationForm.data", $data);

            return $this->redirect($this->Link('register'));
        }

        // The server returned an error message, handle it!
        if ($response->status == Auth_OpenID_CANCEL) {
            $error_message = _t('ForumMemberProfile.CANCELLEDVERIFICATION',
                'The verification was cancelled. Please try again.');
        } elseif ($response->status == Auth_OpenID_FAILURE) {
            $error_message = _t('ForumMemberProfile.AUTHENTICATIONFAILED', 'The OpenID/i-name authentication failed.');
        } else {
            $error_message = _t('ForumMemberProfile.UNEXPECTEDERROR',
                'An unexpected error occured. Please try again or register without OpenID');
        }

        $this->RegistrationWithOpenIDForm()->setFieldMessage(
            "Blurb",
            $error_message,
            'bad'
        );

        return $this->redirect($this->Link('registerwithopenid'));
    }

    /**
     * Edit profile
     *
     * @return array Returns an array to render the edit profile page.
     */
    public function edit()
    {
        $holder = DataObject::get_one("ForumHolder");
        $form   = $this->EditProfileForm();

        if (!$form && Member::currentUser()) {
            $form = "<p class=\"error message\">" . _t('ForumMemberProfile.WRONGPERMISSION',
                    'You don\'t have the permission to edit that member.') . "</p>";
        } elseif (!$form) {
            return $this->redirect('ForumMemberProfile/show/' . $this->Member()->ID);
        }

        return array(
            "Title"    => "Forum",
            "Subtitle" => $holder->ProfileSubtitle,
            "Abstract" => $holder->ProfileAbstract,
            "Form"     => $form,
        );
    }

    /**
     * Factory method for the edit profile form
     *
     * @return Form Returns the edit profile form.
     */
    public function EditProfileForm()
    {
        $member      = $this->Member();
        $show_openid = (isset($member->IdentityURL) && !empty($member->IdentityURL));

        /** @var FieldList $fields */
        $fields    = $member ? $member->getForumFields($show_openid) : Member::singleton()->getForumFields($show_openid);
        $validator = $member ? $member->getForumValidator(false) : Member::singleton()->getForumValidator(false);
        if ($holder = ForumHolder::get()->filter(["DisplaySignatures" => '1'])) {
            $fields->push(TextareaField::create('Signature', 'Forum Signature'));
        }

        $form = new Form(
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
     * @param Form  $form
     *
     * @return bool|HTTPResponse
     */
    public function dosave($data, Form $form)
    {
        $member = Member::currentUser();

        $email      = Convert::raw2sql($data['Email']);
        $forumGroup = Group::get()->filter(['Code' => 'forum-members']);

        // An existing member may have the requested email that doesn't belong to the
        // person who is editing their profile - if so, throw an error
        /** @var Member $existingMember */
        $existingMember = Member::get()->filter(['Email' => $email]);
        if ($existingMember) {
            if ($existingMember->ID != $member->ID) {
                $form->setFieldMessage(
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

        $nicknameCheck = Member::get()->filter(
            [
                'Nickname' => Convert::raw2sql($data['Nickname']),
                'ID:not' => $member->ID
            ]
        );

        if ($nicknameCheck) {
            $form->setFieldMessage(
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
        return [
            "Form" => ForumHolder::get()->first()->ProfileModify
        ];
    }

    /**
     * Create a link
     *
     * @param string $action Name of the action to link to
     *
     * @return string Returns the link to the passed action.
     */
    public function Link($action = null)
    {
        return Controller::join_links($this->class, $action);
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
            $member = Member::currentUser();
        }

        return $member;
    }

    /**
     * Get the forum holder controller. Sadly we can't work out which forum holder
     *
     * @return ForumHolder Returns the forum holder controller.
     */
    public function getForumHolder()
    {
        $holders = ForumHolder::get();
        if ($holders) {
            foreach ($holders as $holder) {
                if ($holder->canView()) {
                    return $holder;
                }
            }
        }

        // no usable forums
        $messageSet = array(
            'default'         => _t('Forum.LOGINTOPOST', "You'll need to login before you can post to that forum. Please do so below."),
            'alreadyLoggedIn' => _t('Forum.NOPOSTPERMISSION', "I'm sorry, but you do not have permission to this edit this profile."),
            'logInAgain'      => _t('Forum.LOGINTOPOSTAGAIN', 'You have been logged out of the forums.  If you would like to log in again to post, enter a username and password below.'),
        );

        return Security::permissionFailure($this, $messageSet);
    }

    /**
     * Get a subtitle
     *
     * @return string
     */
    public function getHolderSubtitle()
    {
        return _t('ForumMemberProfile.USERPROFILE', 'User profile');
    }


    /**
     * Get the URL segment of the forum holder
     *
     * @return string
     */
    public function URLSegment()
    {
        return $this->getForumHolder()->URLSegment;
    }


    /**
     * This needs MetaTags because it doesn't extend SiteTree at any point
     *
     * @return string
     */
    public function MetaTags($includeTitle = true)
    {
        $tags  = "";
        $title = _t('ForumMemberProfile.FORUMUSERPROFILE', 'Forum User Profile');

        if (isset($this->urlParams['Action'])) {
            if ($this->urlParams['Action'] == "register") {
                $title = _t('ForumMemberProfile.FORUMUSERREGISTER', 'Forum Registration');
            }
        }
        if ($includeTitle == true) {
            $tags .= sprintf("<title>%s</title>\n", $title);
        }

        return $tags;
    }
}
