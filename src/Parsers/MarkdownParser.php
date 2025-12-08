<?php

namespace FullscreenInteractive\SilverStripe\Forum\Parsers;

use League\CommonMark\CommonMarkConverter;
use SilverStripe\ORM\FieldType\DBField;

class MarkdownParser
{
    public function parse(string $content): DBField
    {
        $converter = new CommonMarkConverter(['html_input' => 'escape', 'allow_unsafe_links' => false]);
        return DBField::create_field('HTMLText', $converter->convert($content));
    }
}
