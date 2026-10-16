<?php

namespace App\Support;

/**
 * Turns the admin's plain policy text into safe HTML: "## " lines become headings, "- " lines become
 * bullet lists, and blank lines separate paragraphs. Everything else is escaped.
 */
class PolicyText
{
    public static function toHtml(string $text): string
    {
        $html = [];
        $paragraph = [];
        $list = [];
        $flushParagraph = function () use (&$paragraph, &$html) {
            if ($paragraph) {
                $html[] = '<p>'.implode('<br>', $paragraph).'</p>';
                $paragraph = [];
            }
        };
        $flushList = function () use (&$list, &$html) {
            if ($list) {
                $html[] = '<ul>'.implode('', array_map(fn ($item) => "<li>{$item}</li>", $list)).'</ul>';
                $list = [];
            }
        };

        foreach (preg_split('/\R/', trim($text)) as $line) {
            $line = rtrim($line);
            if (str_starts_with($line, '## ')) {
                $flushParagraph();
                $flushList();
                $html[] = '<h2>'.e(trim(substr($line, 3))).'</h2>';
            } elseif (preg_match('/^\s*[-*] (.+)$/', $line, $match)) {
                $flushParagraph();
                $list[] = e(trim($match[1]));
            } elseif (trim($line) === '') {
                $flushParagraph();
                $flushList();
            } else {
                $flushList();
                $paragraph[] = e($line);
            }
        }
        $flushParagraph();
        $flushList();

        return implode("\n", $html);
    }
}
