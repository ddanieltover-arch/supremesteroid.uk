<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\ProductClassification;
use App\Services\Catalogue\OriginalProductCopyService;
use PHPUnit\Framework\TestCase;

class OriginalProductCopyServiceTest extends TestCase
{
    public function test_compose_returns_guidance_fields(): void
    {
        $service = new OriginalProductCopyService();

        $copy = $service->compose([
            'name' => 'Magnesium Complex',
            'brand' => 'Apex Labs',
            'category' => 'Recovery',
            'format' => 'tablets',
            'pack_size' => '60 caps',
            'classification' => ProductClassification::OTC_CONSUMER->value,
        ]);

        $this->assertNotEmpty($copy['why_choose']);
        $this->assertNotEmpty($copy['how_supplied']);
        $this->assertNotEmpty($copy['where_it_fits']);
        $this->assertNotEmpty($copy['who_its_for']);
        $this->assertNotEmpty($copy['what_you_get']);
        $this->assertNotEmpty($copy['answer_capsule']);
        $this->assertIsArray($copy['key_differences']);
        $this->assertNotEmpty($copy['key_differences']);
        $this->assertLessThanOrEqual(4, count($copy['key_differences']));
        $this->assertSame([], $copy['related_reading']);
        $this->assertStringContainsString('Recovery', $copy['where_it_fits']);
        $this->assertStringContainsString('60 caps', $copy['what_you_get']);
        $this->assertStringNotContainsString('dosage', mb_strtolower($copy['why_choose']));
        $this->assertStringNotContainsString('cycle', mb_strtolower($copy['how_supplied']));
    }

    public function test_research_classification_shapes_who_its_for(): void
    {
        $service = new OriginalProductCopyService();

        $copy = $service->compose([
            'name' => 'Research Fragment',
            'brand' => 'Lab Brand',
            'category' => 'Peptides',
            'classification' => ProductClassification::RESEARCH_USE->value,
            'lab_verification_reference' => 'LAB-1',
        ]);

        $this->assertStringContainsString('research-use', mb_strtolower($copy['who_its_for']));
        $this->assertStringContainsString('Not medical advice', $copy['who_its_for']);
    }
}
