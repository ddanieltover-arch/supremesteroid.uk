<?php

declare(strict_types=1);

namespace App\Services\Content;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class BlogPostContentFormatter
{
    /**
     * Extract article body HTML and normalize markup for the storefront template.
     */
    public function format(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"><div id="blog-root">'.$html.'</div>';
        $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $root = $document->getElementById('blog-root');
        if (! $root instanceof DOMElement) {
            return $this->fallbackStrip($html);
        }

        $bodyNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " blog-content ")]', $root)->item(0);
        if (! $bodyNode instanceof DOMElement) {
            $bodyNode = $root;
        }

        $this->removeUnwantedNodes($xpath, $bodyNode);
        $this->normalizeElement($bodyNode);

        $output = '';
        foreach (iterator_to_array($bodyNode->childNodes) as $child) {
            $output .= $document->saveHTML($child) ?: '';
        }

        $output = trim(html_entity_decode($output, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $output !== '' ? $output : $this->fallbackStrip($html);
    }

    private function removeUnwantedNodes(DOMXPath $xpath, DOMElement $body): void
    {
        $queries = [
            './/*[contains(concat(" ", normalize-space(@class), " "), " table-of-contents ")]',
            './/h2[contains(translate(normalize-space(.), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "table of contents")]',
            './/button',
            './/script',
            './/style',
            './/nav',
            './/aside',
            './/form',
        ];

        foreach ($queries as $query) {
            $nodes = $xpath->query($query, $body);
            if ($nodes === false) {
                continue;
            }

            foreach (iterator_to_array($nodes) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    private function normalizeElement(DOMElement $element): void
    {
        $allowed = [
            'p', 'br', 'strong', 'em', 'b', 'i', 'u', 'ul', 'ol', 'li', 'a',
            'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'code', 'pre', 'hr',
            'figure', 'figcaption', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
            'div', 'span',
        ];

        /** @var list<DOMNode> $children */
        $children = iterator_to_array($element->childNodes);

        foreach ($children as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (! in_array($tag, $allowed, true)) {
                // Keep children, drop wrapper.
                while ($child->firstChild) {
                    $element->insertBefore($child->firstChild, $child);
                }
                $element->removeChild($child);

                continue;
            }

            $this->normalizeElement($child);
            $this->sanitizeAttributes($child);

            if ($tag === 'li') {
                $this->unwrapNestedParagraphs($child);
            }

            if ($tag === 'div' || $tag === 'span') {
                $hasBlockChildren = false;
                foreach ($child->childNodes as $grandChild) {
                    if ($grandChild instanceof DOMElement && in_array(strtolower($grandChild->tagName), ['p', 'ul', 'ol', 'h2', 'h3', 'h4', 'table', 'blockquote', 'figure'], true)) {
                        $hasBlockChildren = true;
                        break;
                    }
                }

                if (! $hasBlockChildren && trim($child->textContent) === '' && $child->getElementsByTagName('img')->length === 0) {
                    $child->parentNode?->removeChild($child);
                }
            }
        }
    }

    /**
     * Scraped posts often wrap list item text in <p>; unwrap so bullets render cleanly.
     */
    private function unwrapNestedParagraphs(DOMElement $listItem): void
    {
        /** @var list<DOMNode> $children */
        $children = iterator_to_array($listItem->childNodes);

        foreach ($children as $child) {
            if (! $child instanceof DOMElement || strtolower($child->tagName) !== 'p') {
                continue;
            }

            while ($child->firstChild) {
                $listItem->insertBefore($child->firstChild, $child);
            }

            $listItem->removeChild($child);
        }
    }

    private function sanitizeAttributes(DOMElement $element): void
    {
        $tag = strtolower($element->tagName);
        $keep = match ($tag) {
            'a' => ['href', 'title', 'rel', 'target'],
            'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
            'h2', 'h3', 'h4', 'h5', 'h6' => ['id'],
            'td', 'th' => ['colspan', 'rowspan'],
            default => [],
        };

        $remove = [];
        foreach ($element->attributes ?? [] as $attribute) {
            if (! in_array($attribute->name, $keep, true)) {
                $remove[] = $attribute->name;
            }
        }

        foreach ($remove as $name) {
            $element->removeAttribute($name);
        }

        if ($tag === 'a') {
            $href = trim((string) $element->getAttribute('href'));
            if ($href === '' || str_starts_with(strtolower($href), 'javascript:')) {
                $element->removeAttribute('href');
            } else {
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }

        if ($tag === 'img') {
            $src = trim((string) $element->getAttribute('src'));
            if ($src === '' || str_starts_with(strtolower($src), 'data:')) {
                $element->parentNode?->removeChild($element);

                return;
            }

            if (! $element->hasAttribute('loading')) {
                $element->setAttribute('loading', 'lazy');
            }
            if (! $element->hasAttribute('alt')) {
                $element->setAttribute('alt', '');
            }
        }
    }

    private function fallbackStrip(string $html): string
    {
        return trim(strip_tags(
            $html,
            '<p><br><strong><em><b><i><u><ul><ol><li><a><h2><h3><h4><h5><h6><blockquote><code><pre><hr><figure><figcaption><img><table><thead><tbody><tr><th><td>'
        ));
    }
}
