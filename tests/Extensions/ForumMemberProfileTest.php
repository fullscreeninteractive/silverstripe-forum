<?php

namespace FullscreenInteractive\SilverStripe\Forum\Tests\Extensions;

use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumHolder;
use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumMemberProfile;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Member;

class ForumMemberProfileTest extends FunctionalTest
{

    protected static $fixture_file = [
        './tests/fixtures.yml',
    ];

    protected static $use_draft_site = true;

    public function testRegistrationWithHoneyPot()
    {
        $profilePage = $this->objFromFixture(ForumMemberProfile::class, 'profile');
        $response = $this->get($profilePage->RelativeLink('register'));

        $origHoneypot = ForumHolder::config()->get('use_honeypot_on_register');
        $origSpamprotection = ForumHolder::config()->get('use_spamprotection_on_register');

        ForumHolder::config()->set('use_spamprotection_on_register', false);

        ForumHolder::config()->set('use_honeypot_on_register', false);
        $response = $this->get($profilePage->RelativeLink('register'));
        $this->assertStringNotContainsString('RegistrationForm_username', $response->getBody(), 'Honeypot is disabled by default');

        ForumHolder::config()->set('use_honeypot_on_register', true);
        $response = $this->get($profilePage->RelativeLink('register'));
        $this->assertStringContainsString('RegistrationForm_username', $response->getBody(), 'Honeypot can be enabled');

        // TODO Will fail if Member is decorated with further *required* fields,
        // through updateForumFields() or updateForumValidator()
        $baseData = [
            'Password' => [
                '_Password' => 'text',
                '_ConfirmPassword' => 'text'
            ],
            "Nickname" => 'test',
            "Email" => 'test@test.com',
        ];

        $invalidData = array_merge($baseData, ['action_doregister' => 1, 'username' => 'spamtastic']);
        $response = $this->post($profilePage->RelativeLink('RegistrationForm'), $invalidData);
        $this->assertEquals(403, $response->getStatusCode());

        $validData = array_merge($baseData, ['action_doregister' => 1]);
        $response = $this->post($profilePage->RelativeLink('RegistrationForm'), $validData);
        // Weak check (registration might still fail), but good enough to know if the honeypot is working
        $this->assertEquals(200, $response->getStatusCode());

        ForumHolder::config()->set('use_honeypot_on_register', $origHoneypot);
        ForumHolder::config()->set('use_spamprotection_on_register', $origSpamprotection);
    }

    public function testMemberProfileSuspensionNote()
    {
        $profilePage = $this->objFromFixture(ForumMemberProfile::class, 'profile');
        $response = $this->get($profilePage->RelativeLink('edit/1'));

        DBDatetime::set_mock_now('2011-10-10');

        $normalMember = $this->objFromFixture(Member::class, 'test1');
        $this->loginAs($normalMember);

        $response = $this->get($profilePage->RelativeLink('edit/' . $normalMember->ID));

        $this->assertStringNotContainsString(
            _t('ForumRole.SUSPENSIONNOTE', 'Suspended'),
            $response->getBody(),
            'Normal profiles don\'t show suspension note'
        );

        $suspendedMember = $this->objFromFixture(Member::class, 'suspended');
        $this->loginAs($suspendedMember);

        $response = $this->get($profilePage->RelativeLink('edit/' . $suspendedMember->ID));
        $this->assertStringContainsString(
            _t('ForumRole.SUSPENSIONNOTE', 'Suspended'),
            $response->getBody(),
            'Suspended profiles show suspension note'
        );

        DBDatetime::clear_mock_now();
    }
}
