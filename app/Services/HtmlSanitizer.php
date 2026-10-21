<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Cleans HTML from the rich text editor before it is stored or shown to admins.
 * Only basic formatting is kept - every other tag is removed (its text is kept), all attributes are
 * dropped except http/https/mailto link addresses, and script/style content is removed entirely.
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'a', 'span', 'div'];

    // Removed along with everything inside them
    private const DROPPED_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript', 'form', 'textarea', 'select'];

    public function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="oz-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('oz-root');
        if ($root === null) {
            return e(strip_tags($html));
        }

        $this->cleanChildren($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $doc->saveHTML($child);
        }

        return trim($output);
    }

    private function cleanChildren(DOMNode $node): void
    {
        // Copy the list first - it changes as nodes are removed/unwrapped
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROPPED_TAGS, true)) {
                $node->removeChild($child);

                continue;
            }

            $this->cleanChildren($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unknown tag - keep its (already cleaned) contents, lose the tag
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            $this->cleanAttributes($child, $tag);
        }
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $href = $tag === 'a' ? trim($element->getAttribute('href')) : '';

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->name);
        }

        if ($tag === 'a' && preg_match('#^(https?://|mailto:)#i', $href)) {
            $element->setAttribute('href', $href);
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }
}
