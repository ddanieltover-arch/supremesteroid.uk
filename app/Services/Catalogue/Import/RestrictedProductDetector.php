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

        $reasons = [];
        foreach ($review['reasons'] as $reason) {
            // Soft holds (explicit compliance markers) are not hard retail exclusions.
            if (str_contains($reason, 'explicitly marked requires_compliance_review')) {
                continue;
            }
            $reasons[] = $reason;
        }
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
            'trestolone',
            'methyltrienolone',
            'yk11',
            'yk-11',
            'pitavastatin',
            'liothyronine',
            'levothyroxine',
            'thyromed',
            'qomatropin',
            'ghk-cu',
            'mots-c',
            'thymosin',
            'thymalin',
            'mod grf',
            'mod-grf',
            'pnc-27',
            'pnc27',
            'methenolone',
            'hemi-pharma-hgh',
            '-hgh',
            'hgh-',
            '/hgh',
            'tri-tren',
            'tren-ace',
            'clenomed',
            'test-mix',
            'tmp-mix',
            'testex',
            'testo-cyp',
            'testosterone',
            'test-enanthate',
            'test-cyp',
            'testoprop',
            'testolone',
            'tren-mix',
            'tren ',
            'tren-',
            '-tren',
            'rad-140',
            'rad140',
            'lgd-403',
            'lgd403',
            'lgd-4033',
            'cabaser',
            'cabergolin',
            'bacteriostatic',
            'mtren',
            'm1t-',
            'm1t ',
            'equitest',
            'nandrodec',
            'npp-',
            'npp ',
            'tiromel',
            't3-',
            't3 ',
            'winvar',
            'superbolan',
            'supersus',
            'power-erection',
            'power erection',
            'fat-burn',
            'fat burn',
            'fat-burning',
            'bulking-stack',
            'bulking stack',
            'bulking-cycle',
            'bulking cycle',
            'cutting-stack',
            'cutting stack',
            'weight-loss',
            'weight loss',
            'lean-muscle',
            'lean muscle',
            'post-cycle',
            'post cycle',
            'pct-tabs',
            'pct tabs',
            'pct-stack',
            'pct stack',
            '/pct-',
            '-pct-',
            'pct-100',
            'beginner-stack',
            'beginners-bulking',
            'beginners-tablets',
            'injection-cycle',
            'injectable-tablets',
            'hard-big-muscle',
            'get-big-stack',
            'oxydrol',
            'fermawin',
            'fastrip',
            'tri-ester',
            'tpm-400',
            't400',
            'tm-400',
            'rip-200',
            'rip-400',
            'intex-set',
            'oral-only',
            'oral steroid',
            'injectable steroid',
            'anabolic',
            'androgenic',
            'steroid cycle',
            'sarm cycle',
            'methandienone',
            'metandienone',
            'metenolone',
            'aromasin',
            'exemastane',
            'caber',
            'andarine',
            ' s4',
            '-s4-',
            's4-',
            'epistan',
            'halo-',
            'halo ',
            'dapoxetine',
            'finasteride',
            'proscalpin',
            'raloxifene',
            'aicar',
            'sr-9009',
            'sr9009',
            'cjc-1295',
            'cjc1295',
            'epithalon',
            'epithalamine',
            'follistatin',
            'pt-141',
            'pt141',
            'bremelanotide',
            'water-for-injection',
            'water for injection',
            'pre-injection',
            'anastrazol',
            'semaglutyde',
            'clomifene',
            'fertogard',
            'testomed',
            'dianamed',
            'deca-durabolin',
            'durabolin',
            'deca ',
            'trt-formula',
            'trt formula',
            'nebivolol',
            'nebimed',
            'ezetimibe',
            'ezetimed',
            'telmisartan',
            'telmimed',
            'ment-ace',
            'ment-50',
            'ment ',
            'slupp',
            'androbolan',
            'apcalis',
            'apoxet',
            'fermavar',
            'fermaprovi',
            'primobol',
            'deustropin',
            'oral-stack',
            'oral stack',
            'precontest',
            'pre-contest',
            'super-lean',
            'long-power',
            'mass-mix',
            'bulk-500',
            'hp-rip',
            'eq-test',
            'cutting-oral',
            'bulking-oral',
            'tamox',
        ];
    }
}
