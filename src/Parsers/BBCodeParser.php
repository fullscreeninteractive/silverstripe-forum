<?php

namespace FullscreenInteractive\SilverStripe\Forum\Parsers;

use ChrisKonnertz\BBCode\BBCode;
use SilverStripe\ORM\FieldType\DBField;

class BBCodeParser
{
    public function parse(string $content): DBField
    {
        $bbcode = new BBCode();
        $rendered = $bbcode->render($content);

        return DBField::create_field('HTMLText', $rendered);
    }
}
