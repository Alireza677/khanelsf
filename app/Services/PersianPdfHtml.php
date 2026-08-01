<?php

namespace App\Services;

use ArPHP\I18N\Arabic;
use DOMDocument;
use DOMNode;
use DOMXPath;

final class PersianPdfHtml
{
    public function __construct(private readonly Arabic $arabic) {}

    public function shape(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previousErrors = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<?xml encoding="UTF-8">'.$html,
                LIBXML_HTML_NODEFDTD,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        foreach (iterator_to_array($document->childNodes) as $node) {
            if ($node->nodeType === XML_PI_NODE) {
                $document->removeChild($node);
            }
        }

        $textNodes = (new DOMXPath($document))->query(
            '//text()[not(ancestor::style) and not(ancestor::script)]',
        );

        if ($textNodes !== false) {
            /** @var DOMNode $node */
            foreach ($textNodes as $node) {
                $node->nodeValue = $this->shapeText($node->nodeValue ?? '');
            }
        }

        return $document->saveHTML() ?: $html;
    }

    public function shapeText(string $text): string
    {
        if (preg_match('/\p{Arabic}/u', $text) !== 1) {
            return $text;
        }

        preg_match('/^(\s*)(.*?)(\s*)$/us', $text, $parts);

        $leading = $parts[1] ?? '';
        $content = $parts[2] ?? $text;
        $trailing = $parts[3] ?? '';

        return $leading.$this->arabic->utf8Glyphs(
            $content,
            max(1000, mb_strlen($content) + 1),
            false,
            true,
        ).$trailing;
    }
}
