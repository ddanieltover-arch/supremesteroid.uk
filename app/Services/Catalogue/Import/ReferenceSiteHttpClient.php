<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Contracts\Catalogue\ReferencePageFetcher;
use RuntimeException;

/**
 * Conservative public-page fetcher. One host, in-run cache, paced requests.
 * Does not bypass authentication, CAPTCHA, or anti-bot controls.
 */
class ReferenceSiteHttpClient implements ReferencePageFetcher
{
    /** @var array<string, string> */
    protected array $cache = [];

    protected int $requests = 0;

    public function __construct(
        protected string $allowedHost,
        protected int $delayMs = 2000,
        protected int $timeoutSeconds = 20,
        protected ?\Closure $sleeper = null,
    ) {}

    public function get(string $url): string
    {
        if (isset($this->cache[$url])) {
            return $this->cache[$url];
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === '' || $host !== strtolower($this->allowedHost)) {
            throw new RuntimeException('Reference fetch refused for host: '.$host);
        }

        if (! str_starts_with(strtolower($url), 'https://')) {
            throw new RuntimeException('Reference fetch requires HTTPS.');
        }

        if ($this->requests > 0) {
            $sleep = $this->sleeper ?? static function (int $ms): void {
                usleep($ms * 1000);
            };
            $sleep($this->delayMs);
        }

        $this->requests++;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeoutSeconds,
                'follow_location' => 1,
                'max_redirects' => 2,
                'header' => implode("\r\n", [
                    'User-Agent: SupremeSteroidsCatalogueReview/1.0 (local catalogue review)',
                    'Accept: text/html,application/xml,text/xml;q=0.9,*/*;q=0.8',
                ]),
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = $this->statusFromHeaders($http_response_header ?? []);
        if ($body === false || $status >= 400 || $status === 0) {
            throw new RuntimeException('Reference page request failed (HTTP '.$status.'): '.$url);
        }

        $this->cache[$url] = $body;

        return $body;
    }

    /**
     * @param  list<string>  $headers
     */
    protected function statusFromHeaders(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('#HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return 0;
    }
}
