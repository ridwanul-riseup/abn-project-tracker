<?php

namespace App\Support\Docs;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Safe Markdown → HTML conversion.
 *
 * - Raw HTML in Markdown is escaped (never executed).
 * - Unsafe link protocols (javascript:, vbscript:, file:, data:) are removed.
 * - Relative links to other .md files are rewritten to the in-app resolver,
 *   which enforces document permissions.
 * - External links open in a new tab with rel="noopener noreferrer".
 */
class MarkdownRenderer
{
    public function render(string $markdown, ?string $currentPath = null): string
    {
        $config = [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
            'max_delimiters_per_line' => 1000,
            'external_link' => [
                'internal_hosts' => [parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost'],
                'open_in_new_window' => true,
                'nofollow' => '',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
            'heading_permalink' => [
                'html_class' => 'heading-permalink',
                'id_prefix' => '',
                'fragment_prefix' => '',
                'insert' => 'after',
                'symbol' => '#',
                'aria_hidden' => true,
            ],
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new ExternalLinkExtension);
        $environment->addExtension(new HeadingPermalinkExtension);

        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event) use ($currentPath) {
            foreach ($event->getDocument()->iterator() as $node) {
                if ($node instanceof Link) {
                    $node->setUrl($this->rewriteLink($node->getUrl(), $currentPath));
                } elseif ($node instanceof Image && ! preg_match('#\Ahttps://#i', $node->getUrl())) {
                    // Only remote HTTPS images; local files are not served by the hub.
                    $node->setUrl('');
                }
            }
        });

        return (string) (new MarkdownConverter($environment))->convert($markdown);
    }

    private function rewriteLink(string $url, ?string $currentPath): string
    {
        if ($url === '' || str_starts_with($url, '#') || preg_match('#\A[a-z][a-z0-9+.-]*:#i', $url) || str_starts_with($url, '//')) {
            return $url;
        }

        $fragment = '';
        if (($pos = strpos($url, '#')) !== false) {
            $fragment = substr($url, $pos);
            $url = substr($url, 0, $pos);
        }
        $url = (string) strtok($url, '?');

        if (! str_ends_with(strtolower($url), '.md')) {
            return $url.$fragment;
        }

        $resolved = DocumentPath::resolveRelative($currentPath ?? '', rawurldecode($url));

        return $resolved === null
            ? '#'
            : route('docs.resolve', ['path' => $resolved]).$fragment;
    }
}
