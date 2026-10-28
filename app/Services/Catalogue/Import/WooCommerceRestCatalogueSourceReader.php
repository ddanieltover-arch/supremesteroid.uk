<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Contracts\Catalogue\ResumableCatalogueSourceReader;
use RuntimeException;

/**
 * Authorized WooCommerce REST API product source (wc/v3).
 *
 * Requires consumer key/secret. Does not scrape storefront HTML and does not
 * use unauthenticated private endpoints.
 */
class WooCommerceRestCatalogueSourceReader implements ResumableCatalogueSourceReader
{
    protected int $page;

    protected int $discovered = 0;

    protected int $retrieved = 0;

    protected int $retrievalFailures = 0;

    protected bool $exhausted = false;

    protected ?string $lastError = null;

    public function __construct(
        protected string $baseUrl,
        protected string $consumerKey,
        protected string $consumerSecret,
        protected int $pageSize = 25,
        protected int $timeoutSeconds = 20,
        protected ?\Closure $httpGet = null,
        int $startPage = 1,
    ) {
        $this->page = max(1, $startPage);
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->pageSize = max(1, min(100, $pageSize));

        if (trim($this->consumerKey) === '' || trim($this->consumerSecret) === '') {
            throw new RuntimeException(
                'WooCommerce bulk ingest requires authorized CATALOGUE_WC_CONSUMER_KEY and CATALOGUE_WC_CONSUMER_SECRET credentials.'
            );
        }
    }

    public function sourceType(): string
    {
        return 'woocommerce-rest';
    }

    public function sourceName(): string
    {
        return 'woocommerce-rest';
    }

    public function records(): iterable
    {
        while (! $this->exhausted) {
            $pageRecords = $this->fetchPage($this->page);
            if ($pageRecords === []) {
                $this->exhausted = true;
                break;
            }

            foreach ($pageRecords as $record) {
                $this->retrieved++;
                yield $record;
            }

            if (count($pageRecords) < $this->pageSize) {
                $this->exhausted = true;
                break;
            }

            $this->page++;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function fetchPage(int $page): array
    {
        $endpoint = $this->baseUrl.'/wp-json/wc/v3/products?'.http_build_query([
            'page' => $page,
            'per_page' => $this->pageSize,
            'status' => 'any',
            'consumer_key' => $this->consumerKey,
            'consumer_secret' => $this->consumerSecret,
        ]);

        try {
            $body = ($this->httpGet ?? [$this, 'defaultHttpGet'])($endpoint);
            $decoded = json_decode($body, true);
            if (! is_array($decoded)) {
                throw new RuntimeException('WooCommerce API returned a non-array payload.');
            }
            if (isset($decoded['code']) || isset($decoded['message'])) {
                throw new RuntimeException('WooCommerce API error: '.((string) ($decoded['message'] ?? $decoded['code'])));
            }
            if (! array_is_list($decoded)) {
                throw new RuntimeException('WooCommerce API products payload must be a list.');
            }
        } catch (RuntimeException $exception) {
            $this->retrievalFailures++;
            $this->lastError = $exception->getMessage();
            $this->exhausted = true;

            return [];
        }

        $this->discovered += count($decoded);

        $mapped = [];
        foreach ($decoded as $row) {
            if (! is_array($row)) {
                continue;
            }
            $mapped[] = $this->mapProduct($row);
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function mapProduct(array $row): array
    {
        $id = (string) ($row['id'] ?? '');
        $slug = (string) ($row['slug'] ?? '');
        $permalink = (string) ($row['permalink'] ?? '');
        $categories = [];
        foreach ((array) ($row['categories'] ?? []) as $category) {
            if (is_array($category) && filled($category['name'] ?? null)) {
                $categories[] = (string) $category['name'];
            }
        }
        $brands = [];
        foreach ((array) ($row['brands'] ?? []) as $brand) {
            if (is_array($brand) && filled($brand['name'] ?? null)) {
                $brands[] = (string) $brand['name'];
            }
        }
        // Some stores expose brand via attributes.
        foreach ((array) ($row['attributes'] ?? []) as $attribute) {
            if (! is_array($attribute)) {
                continue;
            }
            $name = strtolower((string) ($attribute['name'] ?? ''));
            if (in_array($name, ['brand', 'pa_brand'], true)) {
                foreach ((array) ($attribute['options'] ?? []) as $option) {
                    if (is_scalar($option) && trim((string) $option) !== '') {
                        $brands[] = (string) $option;
                    }
                }
            }
        }

        $images = [];
        foreach ((array) ($row['images'] ?? []) as $index => $image) {
            if (! is_array($image) || ! filled($image['src'] ?? null)) {
                continue;
            }
            $images[] = [
                'url' => (string) $image['src'],
                'alt' => $image['alt'] ?? null,
                'is_primary' => $index === 0,
            ];
        }

        $variants = [];
        // Variable products expose variation IDs only in list endpoints; keep pack/attribute options when present.
        foreach ((array) ($row['attributes'] ?? []) as $attribute) {
            if (! is_array($attribute)) {
                continue;
            }
            $attrName = (string) ($attribute['name'] ?? 'option');
            foreach ((array) ($attribute['options'] ?? []) as $option) {
                if (! is_scalar($option)) {
                    continue;
                }
                $label = trim((string) $option);
                if ($label === '') {
                    continue;
                }
                $variants[] = [
                    'name' => $label,
                    'attributes' => [$attrName => $label],
                ];
            }
        }

        $sku = trim((string) ($row['sku'] ?? ''));

        return [
            'name' => html_entity_decode(trim((string) ($row['name'] ?? '')), ENT_QUOTES),
            'sku' => $sku !== '' ? $sku : null,
            'source_product_reference' => $id !== '' ? 'wc-'.$id : ($slug !== '' ? $slug : sha1($permalink ?: json_encode($row))),
            'source_url' => $permalink !== '' ? $permalink : null,
            'brand' => $brands[0] ?? null,
            'category' => $categories[0] ?? null,
            'price' => isset($row['price']) && is_numeric($row['price']) ? $row['price'] : null,
            'compare_at_price' => isset($row['regular_price']) && is_numeric($row['regular_price']) ? $row['regular_price'] : null,
            'currency' => 'GBP',
            'availability' => (($row['stock_status'] ?? null) === 'instock') ? 'In stock' : ($row['stock_status'] ?? null),
            'images' => $images,
            'variants' => $variants,
            'attributes' => [
                'source_status' => (string) ($row['status'] ?? ''),
                'source_type' => (string) ($row['type'] ?? ''),
            ],
            'description_source_reference' => $permalink !== '' ? $permalink : null,
            // Preserve raw authorized source payload separately from normalized fields.
            'original_payload_reference' => 'wc-product-'.($id !== '' ? $id : sha1(json_encode($row) ?: uniqid())),
            'source_retrieved_at' => gmdate('c'),
        ];
    }

    protected function defaultHttpGet(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeoutSeconds,
                'follow_location' => 1,
                'max_redirects' => 2,
                'header' => implode("\r\n", [
                    'User-Agent: SupremeSteroidsCatalogueBulkIngest/1.0 (authorized WooCommerce REST)',
                    'Accept: application/json',
                ]),
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('#HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }
        if ($body === false || $status >= 400 || $status === 0) {
            throw new RuntimeException('WooCommerce API request failed (HTTP '.$status.'): '.$this->redact($url));
        }

        return $body;
    }

    protected function redact(string $url): string
    {
        return (string) preg_replace('/(consumer_key|consumer_secret)=[^&]+/i', '$1=REDACTED', $url);
    }

    public function checkpoint(): array
    {
        return [
            'page' => $this->page,
            'page_size' => $this->pageSize,
            'exhausted' => $this->exhausted,
            'discovered' => $this->discovered,
            'retrieved' => $this->retrieved,
            'retrieval_failures' => $this->retrievalFailures,
            'last_error' => $this->lastError,
        ];
    }

    public function restore(array $checkpoint): void
    {
        $this->page = max(1, (int) ($checkpoint['page'] ?? 1));
        $this->exhausted = (bool) ($checkpoint['exhausted'] ?? false);
        $this->discovered = (int) ($checkpoint['discovered'] ?? 0);
        $this->retrieved = (int) ($checkpoint['retrieved'] ?? 0);
        $this->retrievalFailures = (int) ($checkpoint['retrieval_failures'] ?? 0);
        $this->lastError = isset($checkpoint['last_error']) ? (string) $checkpoint['last_error'] : null;
    }

    public function retrievalStats(): array
    {
        return [
            'discovered' => $this->discovered,
            'retrieved' => $this->retrieved,
            'retrieval_failures' => $this->retrievalFailures,
            'exhausted' => $this->exhausted,
            'next_page' => $this->page,
            'last_error' => $this->lastError,
            'authorized' => true,
        ];
    }
}
