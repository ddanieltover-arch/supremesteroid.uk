<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class CheckoutBundleBoundaryTest extends TestCase
{
    public function test_storefront_entry_does_not_eager_load_page_modules(): void
    {
        $source = (string) file_get_contents(base_path('resources/js/app.tsx'));

        $this->assertStringContainsString("import.meta.glob('./Pages/**/*.tsx')", $source);
        $this->assertStringNotContainsString('eager: true', $source);
        $this->assertStringNotContainsString('filament', strtolower($source));
    }
}
