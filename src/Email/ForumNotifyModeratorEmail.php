<?php

namespace FullscreenInteractive\SilverStripe\Forum\Email;

use FullscreenInteractive\SilverStripe\Forum\Model\ForumThread;
use FullscreenInteractive\SilverStripe\Forum\Model\Post;
use SilverStripe\Control\Email\Email;
use SilverStripe\Security\Member;

class ForumNotifyModeratorEmail extends Email
{
    protected ?Post $post = null;

    protected ?ForumThread $thread = null;

    protected ?Member $moderator = null;

    protected bool $startingThread = false;

    public function setPost(Post $post): self
    {
        $this->post = $post;
        return $this;
    }

    public function setThread(ForumThread $thread): self
    {
        $this->thread = $thread;
        return $this;
    }

    public function setStartingThread(bool $startingThread): self
    {
        $this->startingThread = $startingThread;
        return $this;
    }


    public function setModerator(Member $moderator): self
    {
        $this->moderator = $moderator;
        return $this;
    }


    public function send(): void
    {
        if ($this->startingThread) {
            $this->setSubject(sprintf(
                'New thread "%s" in forum [%s]', $this->thread->Title, $this->thread->Forum->Title
            ));
        } else {
            $this->setSubject(sprintf(
                'New post "%s" in forum [%s]', $this->post->Title, $this->thread->Forum->Title
            ));
        }

        $this->setHTMLTemplate('email/ForumMember_NotifyModerator');
        $this->addData([
            'NewThread' => $this->startingThread,
            'Moderator' => $this->moderator,
            'Author' => $this->post->Author(),
            'Forum' => $this,
            'Post' => $this->post
        ]);

        parent::send();
    }
}
