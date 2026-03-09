<?php

namespace FullscreenInteractive\SilverStripe\Forum\Tests\Parsers;

use FullscreenInteractive\SilverStripe\Forum\Interfaces\PostContentParserInterface;
use FullscreenInteractive\SilverStripe\Forum\Parsers\MarkdownParser;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBField;

class MarkdownParserTest extends SapphireTest
{
    protected $usesDatabase = false;

    private MarkdownParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new MarkdownParser();
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(PostContentParserInterface::class, $this->parser);
    }

    public function testParseBold(): void
    {
        $result = $this->parser->parse('**Bold text**');

        $this->assertInstanceOf(DBField::class, $result);
        $this->assertStringContainsString('<strong>Bold text</strong>', $result->getValue());
    }

    public function testParseItalic(): void
    {
        $result = $this->parser->parse('*Italic text*');

        $this->assertStringContainsString('<em>Italic text</em>', $result->getValue());
    }

    public function testParseLink(): void
    {
        $result = $this->parser->parse('[Google](https://www.google.com)');

        $this->assertStringContainsString('href="https://www.google.com"', $result->getValue());
        $this->assertStringContainsString('Google', $result->getValue());
    }

    public function testParseInlineCode(): void
    {
        $result = $this->parser->parse('`code here`');

        $this->assertStringContainsString('<code>code here</code>', $result->getValue());
    }

    public function testParseBlockquote(): void
    {
        $result = $this->parser->parse('> This is a quote');

        $this->assertStringContainsString('<blockquote>', $result->getValue());
        $this->assertStringContainsString('This is a quote', $result->getValue());
    }

    public function testParsePlainText(): void
    {
        $result = $this->parser->parse('Just plain text');

        $this->assertStringContainsString('Just plain text', $result->getValue());
    }

    public function testParseEmptyString(): void
    {
        $result = $this->parser->parse('');

        $this->assertInstanceOf(DBField::class, $result);
        $this->assertEmpty(trim($result->getValue()));
    }

    public function testParseEscapesHtmlInput(): void
    {
        $result = $this->parser->parse('<script>alert("xss")</script>');

        $this->assertStringNotContainsString('<script>', $result->getValue());
    }

    public function testParseDisallowsUnsafeLinks(): void
    {
        $result = $this->parser->parse('[click](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $result->getValue());
    }

    public function testGetSupportingHelpTextReturnsDBField(): void
    {
        $result = $this->parser->getSupportingHelpText();

        $this->assertInstanceOf(DBField::class, $result);
    }

    public function testGetSupportingHelpTextContainsMarkdownExamples(): void
    {
        $result = $this->parser->getSupportingHelpText();
        $value = $result->getValue();

        $this->assertStringContainsString('**Bold**', $value);
        $this->assertStringContainsString('*Italic*', $value);
        $this->assertStringContainsString('~~Strikethrough~~', $value);
        $this->assertStringContainsString('`Inline Code`', $value);
    }
}
