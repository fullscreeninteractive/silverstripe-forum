<?php

namespace FullscreenInteractive\SilverStripe\Forum\PageTypes;

use Page;

class ForumMemberProfile extends Page
{
    private static string $table_name = 'ForumMemberProfile';

    private static ?string $cms_icon_class = 'font-icon-p-profile';
}
