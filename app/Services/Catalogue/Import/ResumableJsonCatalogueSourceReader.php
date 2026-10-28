<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

use App\Contracts\Catalogue\ResumableCatalogueSourceReader;
use InvalidArgumentException;
use RuntimeException;

/**
 * Authorized local JSON catalogue feed with resumable offset pagination.
 */
class ResumableJsonCatalogueSourceReader implements ResumableCatalogueSourceReader
{
    /** @var list<array<string, mixed>> */
    protected array $items = [];

    protected int $offset = 0;

    protected int $retrieved = 0;

    protected bool $loaded = false;

    public function __construct(
        protected string $path,
        protected string $sourceName = 'json-feed',
        protected int $pageSize = 25,
    ) {
        $this->pageSize = max(1, min(500, $pageSize));
    }

    public function sourceType(): string
    {
        return 'json-feed';
    }

    public function sourceName(): string
    {
        return $this->sourceName;
    }

    public function records(): iterable
    {
        $this->load();
        while ($this->offset < count($this->items)) {
            yield $this->pull();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function pull(): ?array
    {
        $this->load();
        if ($this->offset >= count($this->items)) {
            return null;
        }

        $item = $this->items[$this->offset];
        $this->offset++;
        $this->retrieved++;

        return $item;
    }

    public function seek(int $offset): void
    {
        $this->load();
        $this->offset = max(0, min($offset, count($this->items)));
    }

    protected function load(): void
    {
        if ($this->loaded) {
            return;
        }
        if (! is_file($this->path)) {
            throw new InvalidArgumentException('JSON catalogue feed not found: '.$this->path);
        }
        $raw = file_get_contents($this->path);
        if ($raw === false) {
            throw new RuntimeException('Unable to read JSON catalogue feed: '.$this->path);
        }
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('JSON catalogue feed must decode to an array.');
        }
        $items = array_is_list($decoded) ? $decoded : ($decoded['products'] ?? $decoded['records'] ?? null);
        if (! is_array($items) || ! array_is_list($items)) {
            throw new InvalidArgumentException('JSON catalogue feed must contain a list of products.');
        }
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('JSON catalogue record #'.($index + 1).' must be an object.');
            }
            $this->items[] = $item;
        }
        $this->loaded = true;
    }

    public function checkpoint(): array
    {
        return [
            'offset' => $this->offset,
            'page_size' => $this->pageSize,
            'retrieved' => $this->retrieved,
            'discovered' => count($this->items),
            'exhausted' => $this->loaded && $this->offset >= count($this->items),
        ];
    }

    public function restore(array $checkpoint): void
    {
        $this->load();
        $this->offset = max(0, (int) ($checkpoint['offset'] ?? 0));
        $this->retrieved = (int) ($checkpoint['retrieved'] ?? 0);
    }

    public function retrievalStats(): array
    {
        $this->load();

        return [
            'discovered' => count($this->items),
            'retrieved' => $this->retrieved,
            'retrieval_failures' => 0,
            'exhausted' => $this->offset >= count($this->items),
            'next_offset' => $this->offset,
            'authorized' => true,
            'path' => $this->path,
        ];
    }
}
