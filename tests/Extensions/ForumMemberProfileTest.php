<?php

namespace FullscreenInteractive\SilverStripe\Forum\Tests\Extensions;

use FullscreenInteractive\SilverStripe\Forum\PageTypes\ForumHolder;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\ORM\FieldType\DBDatetime;

class ForumMemberProfileTest extends FunctionalTest
{

    protected static $fixture_file = [
        'ForumTest.yml',
    ];

    protected static $use_draft_site = true;

    public function testRegistrationWithHoneyPot()
    {
        $origHoneypot = ForumHolder::config()->get('use_honeypot_on_register');
        $origSpamprotection = ForumHolder::config()->get('use_spamprotection_on_register');

        ForumHolder::config()->set('use_spamprotection_on_register', false);

        ForumHolder::config()->set('use_honeypot_on_register', false);
        $response = $this->get('ForumMemberProfile/register');
        $this->assertNotContains('RegistrationForm_username', $response->getBody(), 'Honeypot is disabled by default');

        ForumHolder::config()->set('use_honeypot_on_register', true);
        $response = $this->get('ForumMemberProfile/register');
        $this->assertContains('RegistrationForm_username', $response->getBody(), 'Honeypot can be enabled');

        // TODO Will fail if Member is decorated with further *required* fields,
        // through updateForumFields() or updateForumValidator()
        $baseData = array(
            'Password' => array(
                '_Password' => 'text',
                '_ConfirmPassword' => 'text'
            ),
            "Nickname" => 'test',
            "Email" => 'test@test.com',
        );

        $invalidData = array_merge($baseData, array('action_doregister' => 1, 'username' => 'spamtastic'));
        $response = $this->post('ForumMemberProfile/RegistrationForm', $invalidData);
        $this->assertEquals(403, $response->getStatusCode());

        $validData = array_merge($baseData, array('action_doregister' => 1));
        $response = $this->post('ForumMemberProfile/RegistrationForm', $validData);
        // Weak check (registration might still fail), but good enough to know if the honeypot is working
        $this->assertEquals(200, $response->getStatusCode());

        ForumHolder::config()->set('use_honeypot_on_register', $origHoneypot);
        ForumHolder::config()->set('use_spamprotection_on_register', $origSpamprotection);
    }

    public function testMemberProfileSuspensionNote()
    {
        DBDatetime::set_mock_now('2011-10-10');

        $normalMember = $this->objFromFixture('Member', 'test1');
        $this->loginAs($normalMember);
        $response = $this->get('ForumMemberProfile/edit/' . $normalMember->ID);

        $this->assertNotContains(
            _t('ForumRole.SUSPENSIONNOTE'),
            $response->getBody(),
            'Normal profiles don\'t show suspension note'
        );

        $suspendedMember = $this->objFromFixture('Member', 'suspended');
        $this->loginAs($suspendedMember);
        $response = $this->get('ForumMemberProfile/edit/' . $suspendedMember->ID);
        $this->assertContains(
            _t('ForumRole.SUSPENSIONNOTE'),
            $response->getBody(),
            'Suspended profiles show suspension note'
        );

        DBDatetime::clear_mock_now();
    }
}
