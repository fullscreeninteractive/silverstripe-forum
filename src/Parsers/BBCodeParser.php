<?php

namespace FullscreenInteractive\SilverStripe\Forum\Parsers;

use ChrisKonnertz\BBCode\BBCode;
use FullscreenInteractive\SilverStripe\Forum\Interfaces\PostContentParserInterface;
use SilverStripe\ORM\FieldType\DBField;

class BBCodeParser implements PostContentParserInterface
{
    public function parse(string $content): DBField
    {
        $bbcode = new BBCode();
        $rendered = $bbcode->render($content);

        return DBField::create_field('HTMLText', $rendered);
    }


    public function getSupportingHelpText(): DBField
    {
        $sampleText = '
        [b]Bold[/b]
        [i]Italic[/i]
        [u]Underline[/u]
        [s]Strikethrough[/s]
        [color=red]Red[/color]
        [size=12]Size 12[/size]
        [url=https://www.google.com]Google[/url]
        ';
        return DBField::create_field('HTMLText', '<p>' . $sampleText . '</p>');
    }
}
