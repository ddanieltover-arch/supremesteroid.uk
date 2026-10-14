<?php

declare(strict_types=1);

namespace App\Services\Catalogue\Import;

/**
 * Heuristic flags for records that need legal/compliance review before any retail path.
 * Detection does not assert controlled-substance status — it only routes to COMPLIANCE_REVIEW.
 */
class RestrictedProductDetector
{
    /**
     * Terms that require additional lawful-status review before any purchasable path.
     *
     * @var list<string>
     */
    protected array $reviewTerms = [
        'anabolic',
        'androgenic',
        'trenbolone',
        'nandrolone',
        'oxandrolone',
        'stanozolol',
        'methandrostenolone',
        'testosterone enanthate',
        'testosterone cypionate',
        'boldenone',
        'drostanolone',
        'mesterolone',
        'clenbuterol',
        'hgh injectable',
        'sarm cycle',
        'steroid cycle',
        'injection vials for bodybuilding',
    ];

    /**
     * @param  array<string, mixed>  $normalized
     * @return array{requires_review: bool, reasons: list<string>}
     */
    public function evaluate(array $normalized): array
    {
        $haystack = strtolower(implode(' ', array_filter([
            (string) ($normalized['name'] ?? ''),
            (string) ($normalized['normalized_name'] ?? ''),
            (string) ($normalized['brand'] ?? ''),
            (string) ($normalized['category'] ?? ''),
            (string) ($normalized['product_type'] ?? ''),
            (string) ($normalized['format'] ?? ''),
            json_encode($normalized['attributes'] ?? []),
        ])));

        $reasons = [];
        foreach ($this->reviewTerms as $term) {
            if (str_contains($haystack, $term)) {
                $reasons[] = 'Source text matched compliance review term: '.$term;
            }
        }

        if (($normalized['requires_compliance_review'] ?? false) === true) {
            $reasons[] = 'Source explicitly marked requires_compliance_review.';
        }

        return [
            'requires_review' => $reasons !== [],
            'reasons' => $reasons,
        ];
    }

    /**
     * Hard exclusion for reference-site onboarding.
     * Excluded records must not be staged as products, prices, variants, or selling copy.
     *
     * @param  array<string, mixed>  $normalized
     * @return array{excluded: bool, reasons: list<string>}
     */
    public function excludedFromCommerceImport(array $normalized): array
    {
        $review = $this->evaluate($normalized);
        $haystack = strtolower(implode(' ', array_filter([
            (string) ($normalized['name'] ?? ''),
            (string) ($normalized['category'] ?? ''),
            (string) ($normalized['product_type'] ?? ''),
            (string) ($normalized['format'] ?? ''),
            (string) ($normalized['source_url'] ?? ''),
        ])));

        $reasons = $review['reasons'];
        foreach ($this->commerceExclusionTerms() as $term) {
            if (str_contains($haystack, $term)) {
                $reasons[] = 'Excluded from commerce onboarding: '.$term;
            }
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'excluded' => $reasons !== [],
            'reasons' => $reasons,
        ];
    }

    /**
     * @return list<string>
     */
    protected function commerceExclusionTerms(): array
    {
        return [
            'testosterone',
            'anavar',
            'dianabol',
            'winstrol',
            'trenbolone',
            'trenabolone',
            'sustanon',
            'masteron',
            'primobolan',
            'boldenone',
            'nandrolone',
            'turinabol',
            'anadrol',
            'oxymetholone',
            'stanozolol',
            'oxandrolone',
            'methandrostenolone',
            'clenbuterol',
            'clomid',
            'clomiphene',
            'tamoxifen',
            'nolvadex',
            'arimidex',
            'anastrozole',
            'letrozole',
            'exemestane',
            'semaglutide',
            'tirzepatide',
            'sildenafil',
            'tadalafil',
            'kamagra',
            'viagra',
            'cialis',
            'salbutamol',
            'ibutamoren',
            'mk677',
            'mk-677',
            'cardarine',
            'ostarine',
            'ligandrol',
            'stenabolic',
            'sarm',
            'peptide',
            'bpc-157',
            'bpc157',
            'tb-500',
            'growth hormone',
            'somatropin',
            'hcg',
            'gonadotropin',
            'proviron',
            'mesterolone',
            'superdrol',
            'methasterone',
            'halotestin',
            'fluoxymesterone',
            'cabergoline',
            'cabermed',
            'liothyronine',
            'cytomel',
            'levothyroxine',
            'glp-1',
            'belzempic',
            'retatrutide',
            'modafinil',
            'modvigil',
            'armodafinil',
            'igf',
            'igf-1',
            'igf1',
            'mgf',
            'mt2',
            'melanotan',
            'andiwin',
            'xlean',
            'prefilled pen',
            'insulin',
            'syringe',
            'needle',
        ];
    }
}
