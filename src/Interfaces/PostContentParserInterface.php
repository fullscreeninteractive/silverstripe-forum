<?php

namespace FullscreenInteractive\SilverStripe\Forum\Interfaces;

use SilverStripe\ORM\FieldType\DBField;

interface PostContentParserInterface
{
    public function parse(string $content): DBField;


    public function getSupportingHelpText(): DBField;
}
