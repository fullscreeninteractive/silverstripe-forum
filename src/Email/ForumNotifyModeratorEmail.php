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
            $this->setSubject('New thread "' . $this->thread->Title . '" in forum [' . $this->thread->Forum->Title . ']');
        } else {
            $this->setSubject('New post "' . $this->post->Title . '" in forum [' . $this->thread->Forum->Title . ']');
        }
        $this->setTemplate('ForumMember_NotifyModerator');
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
