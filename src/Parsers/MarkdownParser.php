<?php

namespace FullscreenInteractive\SilverStripe\Forum\Parsers;

use FullscreenInteractive\SilverStripe\Forum\Interfaces\PostContentParserInterface;
use League\CommonMark\CommonMarkConverter;
use SilverStripe\ORM\FieldType\DBField;

class MarkdownParser implements PostContentParserInterface
{
    public function parse(string $content): DBField
    {
        $converter = new CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false
        ]);

        $rendered = $converter->convert($content);

        return DBField::create_field('HTMLText', $rendered);
    }


    public function getSupportingHelpText(): DBField
    {
        $sampleText = '
        **Bold**
        *Italic*
        ~~Strikethrough~~
        [Link](https://www.example.com)
        `Inline Code`
        > Blockquote
        ';

        return DBField::create_field('HTMLText', '<p>' . $sampleText . '</p>');
    }
}
