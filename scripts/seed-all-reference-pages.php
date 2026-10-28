<?php

/**
 * Seed all reference content pages and blog posts into the database.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BlogPost;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

echo "=== Seeding All Reference Pages & Blog Posts ===\n\n";

$admin = User::first();
if (!$admin) {
    echo "❌ No admin user found in database!\n";
    exit(1);
}

$scrapedData = json_decode(file_get_contents('storage/app/catalogue-import/scraped-reference-pages.json'), true);

DB::beginTransaction();

try {
    // ══════════════════════════════════════════════════════════════════
    // 1. CONTENT PAGES
    // ══════════════════════════════════════════════════════════════════

    // ── FAQ ──────────────────────────────────────────────────────────
    $faqHtml = <<<HTML
<div class="faq-accordion space-y-6">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-bold text-slate-900">What is your return policy?</h3>
        <p class="mt-2 text-slate-600">The product must be unused, in its safe, original packaging, and sealed. Our return policy applies to purchases made within 30 days of receipt.</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-bold text-slate-900">How do I know your products are authentic?</h3>
        <p class="mt-2 text-slate-600">We source exclusively from certified, recognised brands that meet strict UK and EU manufacturing standards. All products feature verifiable batch numbers and security codes that can be authenticated directly on the manufacturer's official portal.</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-bold text-slate-900">How long does shipping take within the UK?</h3>
        <p class="mt-2 text-slate-600">We offer Royal Mail Tracked 24 and Next-Day Guaranteed delivery options. Orders placed before 1:00 PM Monday through Friday are dispatched on the same day with tracked delivery references sent directly to your email.</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-bold text-slate-900">What payment methods do you accept?</h3>
        <p class="mt-2 text-slate-600">We accept Bank Transfer (Faster Payments) and Cryptocurrency (Bitcoin / USDT). When paying with Bitcoin, you receive an automatic <strong>20% discount</strong> applied at checkout (excluding existing sale promotions).</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-bold text-slate-900">How are orders packaged for delivery?</h3>
        <p class="mt-2 text-slate-600">All orders are shipped in 100% discreet, plain padded envelopes or unbranded postal boxes. There are no logos, company references, or indication of the parcel contents anywhere on the exterior packaging.</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-bold text-slate-900">What should I do if my parcel is delayed or damaged?</h3>
        <p class="mt-2 text-slate-600">If your parcel has not arrived within the expected tracking timeframe or arrives damaged, please reach out immediately to our support team with your order reference. We provide free reshipment or refund coverage on all tracked delivery issues.</p>
    </div>
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h3 class="text-lg font-bold text-slate-900">How can I contact customer support?</h3>
        <p class="mt-2 text-slate-600">Email our support team at <a href="mailto:info@supremesteroid.uk" class="text-blue-600 font-semibold hover:underline">info@supremesteroid.uk</a> for order inquiries, product questions, and delivery assistance. We aim to respond during business hours Monday through Friday.</p>
    </div>
</div>
HTML;

    // ── BPC-157 UK GUIDE ─────────────────────────────────────────────
    $bpcHtml = <<<HTML
<div class="space-y-6">
    <div class="rounded-xl bg-blue-50 border border-blue-200 p-6 text-blue-900">
        <h3 class="font-semibold text-lg text-blue-950">Educational & Research Reference</h3>
        <p class="mt-2 text-sm leading-relaxed">BPC-157 (Body Protective Compound-157) is a pentadecapeptide composed of 15 amino acids derived from human gastric juice. It has gained widespread attention in sports medicine, orthopedic therapy, and tissue engineering research for its regenerative properties.</p>
    </div>

    <h2 class="text-2xl font-bold text-slate-900">Key Scientific & Physiological Actions</h2>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h4 class="font-bold text-slate-900">Angiogenesis & Blood Vessel Growth</h4>
            <p class="mt-2 text-sm text-slate-600">Promotes vascular endothelial growth factor (VEGF) expression and accelerates the formation of new capillary networks to deliver oxygen and nutrients to damaged tissues.</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h4 class="font-bold text-slate-900">Tendon, Ligament & Bone Repair</h4>
            <p class="mt-2 text-sm text-slate-600">Preclinical studies demonstrate accelerated collagen synthesis and structural tendon-to-bone healing, especially in Achilles tendon and collateral ligament models.</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h4 class="font-bold text-slate-900">Gastrointestinal Protection</h4>
            <p class="mt-2 text-sm text-slate-600">Maintains gut mucosal integrity, counteracts NSAID-induced ulcers, and supports the resolution of inflammatory gut conditions.</p>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <h4 class="font-bold text-slate-900">Cellular Anti-Inflammatory Effects</h4>
            <p class="mt-2 text-sm text-slate-600">Modulates focal adhesion kinase (FAK) signaling pathways and downregulates pro-inflammatory cytokines during acute tissue injury.</p>
        </div>
    </div>

    <h2 class="text-2xl font-bold text-slate-900 mt-8">Administration & Formulation Summary</h2>
    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-slate-700 font-semibold">
                <tr>
                    <th class="px-4 py-3 text-left">Property</th>
                    <th class="px-4 py-3 text-left">Specification</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-slate-600">
                <tr><td class="px-4 py-3 font-medium text-slate-900">Compound Name</td><td class="px-4 py-3">BPC-157 (Body Protection Compound)</td></tr>
                <tr><td class="px-4 py-3 font-medium text-slate-900">Sequence</td><td class="px-4 py-3">Gly-Glu-Pro-Pro-Pro-Gly-Lys-Pro-Ala-Asp-Asp-Ala-Gly-Leu-Val</td></tr>
                <tr><td class="px-4 py-3 font-medium text-slate-900">Molecular Formula</td><td class="px-4 py-3">C62H98N16O22</td></tr>
                <tr><td class="px-4 py-3 font-medium text-slate-900">Common Dosages</td><td class="px-4 py-3">250 mcg – 500 mcg daily</td></tr>
                <tr><td class="px-4 py-3 font-medium text-slate-900">Administration</td><td class="px-4 py-3">Subcutaneous / Intramuscular reconstitution, or oral arginine salt</td></tr>
                <tr><td class="px-4 py-3 font-medium text-slate-900">Storage</td><td class="px-4 py-3">Lyophilized powder at 2–8°C; reconstituted solution refrigerated</td></tr>
            </tbody>
        </table>
    </div>

    <div class="mt-8 rounded-xl border border-slate-200 bg-slate-50 p-6">
        <h3 class="text-lg font-bold text-slate-900">Sourcing Authentic BPC-157 in the UK</h3>
        <p class="mt-2 text-sm text-slate-600">Supreme Steroids provides independently verified BPC-157 peptides produced by accredited manufacturers including Pharmaqo Labs, Beligas Pharmaceuticals, and Apex Laboratories. All vials include batch verification reports and strict purity guarantees.</p>
        <div class="mt-4">
            <a href="/shop?search=BPC-157" class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Browse BPC-157 Products</a>
        </div>
    </div>
</div>
HTML;

    // ── THE BSL 24 ───────────────────────────────────────────────────
    $bslHtml = <<<HTML
<div class="space-y-6">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-2xl font-bold text-slate-900">BSL24 Partnership with Supreme Steroids</h2>
        <p class="mt-3 text-slate-600 leading-relaxed">BSL24 (Best Steroids London) collaborates directly with Supreme Steroids to supply genuine, pharmaceutical-standard formulations across the UK. With over a decade of verified supply chain history, BSL24 guarantees authentic, laboratory-screened products backed by verified customer reviews and prompt UK fulfillment.</p>
    </div>

    <div class="grid gap-6 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-blue-600 font-bold text-lg">01. Bulking Compounds</div>
            <p class="mt-2 text-sm text-slate-600">Formulated for targeted muscle mass, nitrogen retention, and raw strength. Featuring trusted profiles such as Testosterone Enanthate, Deca-Durabolin, Dianabol, and Anadrol.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-blue-600 font-bold text-lg">02. Cutting & Definition</div>
            <p class="mt-2 text-sm text-slate-600">Engineered to preserve lean mass during calorie deficits while promoting vascularity and fat loss. Featuring Masteron, Winstrol, Anavar, and Primobolan.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="text-blue-600 font-bold text-lg">03. PCT & Cycle Support</div>
            <p class="mt-2 text-sm text-slate-600">Essential post-cycle therapy protocols to restore endogenous endocrine production, maintain gains, and protect cardiovascular and liver health. Featuring Clomid, Nolvadex, and Arimidex.</p>
        </div>
    </div>

    <div class="rounded-xl bg-slate-900 p-8 text-white">
        <h3 class="text-xl font-bold">Why Choose BSL24 on Supreme Steroids?</h3>
        <ul class="mt-4 space-y-3 text-sm text-slate-300">
            <li class="flex items-start gap-2"><span class="text-blue-400 font-bold">✓</span> <strong>Lab-Tested Quality:</strong> Every batch is analytically verified with accessible high-performance liquid chromatography (HPLC) purity reports.</li>
            <li class="flex items-start gap-2"><span class="text-blue-400 font-bold">✓</span> <strong>Next-Day Tracked Delivery:</strong> Fully tracked Royal Mail dispatch with discreet plain packaging guaranteed on every single order.</li>
            <li class="flex items-start gap-2"><span class="text-blue-400 font-bold">✓</span> <strong>Bitcoin 20% Discount:</strong> Instant 20% savings when paying with cryptocurrency, protecting your privacy and security.</li>
        </ul>
        <div class="mt-6">
            <a href="/shop" class="inline-flex items-center rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-500">Explore Full BSL24 Catalogue</a>
        </div>
    </div>
</div>
HTML;

    // ── TERMS & CONDITIONS ───────────────────────────────────────────
    $termsHtml = <<<HTML
<div class="space-y-6 text-slate-700 leading-relaxed text-sm">
    <p class="text-base font-medium text-slate-900">Welcome to Supreme Steroids. By accessing our platform, registering an account, or placing an order, you agree to comply with and be bound by the following Terms & Conditions.</p>

    <h2 class="text-xl font-bold text-slate-900 mt-6">1. Eligibility & Age Restriction</h2>
    <p>You must be at least 18 years of age to purchase products or use any services on this website. By proceeding with an order, you warrant that you are of legal age and have full legal capacity to enter into binding agreements.</p>

    <h2 class="text-xl font-bold text-slate-900 mt-6">2. Lawful Compliance & Disclaimer</h2>
    <p>All products offered on this platform are intended strictly for lawful personal wellness, research, or nutritional use in compliance with applicable United Kingdom regulations. Information presented on this site is for educational purposes only and does not substitute for qualified medical consultation.</p>

    <h2 class="text-xl font-bold text-slate-900 mt-6">3. Orders, Pricing & Server Calculation</h2>
    <p>All prices are listed in GBP (£). The final order total, discounts, and delivery charges are calculated and confirmed exclusively by our backend system at checkout. We reserve the right to correct pricing typographical errors or cancel orders originating from invalid sessions or system anomalies.</p>

    <h2 class="text-xl font-bold text-slate-900 mt-6">4. Payment Terms & Bitcoin Discount</h2>
    <p>Payment must be completed in full before orders are approved for dispatch. We accept Bank Transfers (UK Faster Payments) and selected cryptocurrencies (Bitcoin, USDT). An automatic 20% discount is applied to orders settled via Bitcoin, excluding items already marked with promotional sale discounts.</p>

    <h2 class="text-xl font-bold text-slate-900 mt-6">5. Dispatch & Delivery Policy</h2>
    <p>Orders are dispatched via tracked postal carriers in plain, unbranded packaging. Delivery timeframes provided are estimates. The customer is responsible for ensuring the accuracy and completeness of their shipping address upon checkout.</p>

    <h2 class="text-xl font-bold text-slate-900 mt-6">6. Returns & Cancellation</h2>
    <p>Customers have 30 days from the delivery date to request a return for unused, sealed products in original packaging. Please refer to our detailed <a href="/returns" class="text-blue-600 font-semibold underline">Return Policy</a> for return authorization procedures.</p>
</div>
HTML;

    // ── RETURN POLICY ────────────────────────────────────────────────
    $returnHtml = <<<HTML
<div class="space-y-6 text-slate-700 leading-relaxed text-sm">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-xl font-bold text-slate-900">Our 30-Day Return Commitment</h2>
        <p class="mt-2">At Supreme Steroids, customer confidence and product integrity are our utmost priorities. If you are not completely satisfied with your order, you may request a return or replacement within <strong>30 days of delivery</strong>.</p>
    </div>

    <h3 class="text-lg font-bold text-slate-900 mt-6">Eligibility Requirements</h3>
    <ul class="list-disc pl-5 space-y-2">
        <li>The item must be in its original, unopened, and tamper-evident packaging.</li>
        <li>Security seals, hologram verification labels, and batch numbers must remain intact.</li>
        <li>The return request must be submitted within 30 days of the recorded delivery date.</li>
    </ul>

    <h3 class="text-lg font-bold text-slate-900 mt-6">Damaged, Incorrect or Missing Items</h3>
    <p>If your package arrives damaged, with missing components, or contains incorrect items, please contact our support desk within 48 hours of receipt. Include photographs of the outer parcel and damaged products along with your order reference. We will immediately arrange a priority replacement at no additional charge.</p>

    <h3 class="text-lg font-bold text-slate-900 mt-6">Refund Processing</h3>
    <p>Approved refunds are processed to your original payment method (bank transfer or crypto wallet credit) within 3 to 5 business days following return parcel inspection.</p>

    <div class="mt-6 rounded-lg bg-blue-50 border border-blue-200 p-4 text-blue-900 text-sm">
        Need assistance with a return? Contact our support team directly at <a href="mailto:info@supremesteroid.uk" class="font-bold underline">info@supremesteroid.uk</a>.
    </div>
</div>
HTML;

    // ── XMAS & HOLIDAY UPDATES ───────────────────────────────────────
    $xmasHtml = <<<HTML
<div class="space-y-6 text-slate-700 leading-relaxed text-sm">
    <div class="rounded-xl bg-gradient-to-r from-blue-700 to-indigo-800 p-8 text-white shadow-lg">
        <span class="inline-block rounded-full bg-white/20 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-white">Seasonal Notice</span>
        <h2 class="mt-3 text-3xl font-extrabold text-white">Holiday Dispatch & Special Offers</h2>
        <p class="mt-2 text-blue-100 max-w-2xl leading-relaxed">Plan your orders ahead of seasonal postal deadlines. Save more with our holiday discounts and guaranteed tracked dispatch.</p>
    </div>

    <div class="grid gap-6 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-slate-900">Holiday Postal Cutoff Dates</h3>
            <p class="mt-2 text-slate-600">To guarantee delivery before bank holidays, ensure orders are placed and confirmed by <strong>1:00 PM on December 21st</strong> for Royal Mail Tracked 24.</p>
            <ul class="mt-3 space-y-1.5 text-xs text-slate-500">
                <li>• Royal Mail Tracked 48 cutoff: Dec 19th</li>
                <li>• Royal Mail Tracked 24 cutoff: Dec 21st</li>
                <li>• Special Guaranteed Delivery: Dec 22nd</li>
            </ul>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-slate-900">Exclusive Holiday Savings</h3>
            <p class="mt-2 text-slate-600">Combine seasonal tier savings with our permanent <strong>20% Bitcoin checkout discount</strong> for maximum value on your cycles and peptide protocols.</p>
            <div class="mt-4 rounded-lg bg-slate-50 border border-slate-200 p-3 text-xs font-mono text-slate-800">
                Use code: <span class="font-bold text-blue-600">HOLIDAY2026</span> at checkout
            </div>
        </div>
    </div>
</div>
HTML;

    // ── ABOUT US ─────────────────────────────────────────────────────
    $aboutHtml = <<<HTML
<div class="space-y-6 text-slate-700 leading-relaxed text-sm">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-2xl font-bold text-slate-900">About Supreme Steroids</h2>
        <p class="mt-3 leading-relaxed">Supreme Steroids is the UK's leading platform for premium, laboratory-verified performance formulations, peptides, and wellness compounds. We pride ourselves on complete transparency, verifiable batch analytics, and uncompromised fulfillment standards.</p>
    </div>

    <div class="grid gap-6 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-bold text-slate-900">100% Verified Quality</h3>
            <p class="mt-2 text-xs text-slate-600 leading-relaxed">Every compound in our catalogue is sourced directly from licensed, certified manufacturers with verifiable anti-counterfeit holographic labels.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-bold text-slate-900">Rapid UK Fulfillment</h3>
            <p class="mt-2 text-xs text-slate-600 leading-relaxed">Dispatched from domestic fulfillment centres with Royal Mail Tracked 24 shipping. No customs delays or international seizure risks.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-bold text-slate-900">Customer Confidentiality</h3>
            <p class="mt-2 text-xs text-slate-600 leading-relaxed">We employ zero-log server architecture, encrypted checkout sessions, and 100% plain, unmarked parcel packaging.</p>
        </div>
    </div>
</div>
HTML;

    // ── CONTACT US ───────────────────────────────────────────────────
    $contactHtml = <<<HTML
<div class="space-y-6 text-slate-700 leading-relaxed text-sm">
    <div class="grid gap-6 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold text-slate-900">Direct Customer Support</h2>
            <p class="mt-2 text-slate-600">Our support team is available Monday through Friday from 9:00 AM to 6:00 PM GMT to assist with order tracking, product specifications, and general inquiries.</p>

            <div class="mt-6 space-y-4">
                <div>
                    <span class="block text-xs uppercase tracking-wider font-semibold text-slate-400">Email Address</span>
                    <a href="mailto:info@supremesteroid.uk" class="text-blue-600 font-semibold hover:underline">info@supremesteroid.uk</a>
                </div>
                <div>
                    <span class="block text-xs uppercase tracking-wider font-semibold text-slate-400">London Office Reference</span>
                    <p class="text-slate-800">8 King Street, Hammersmith, London W6 9HW, United Kingdom</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50 p-6">
            <h3 class="text-lg font-bold text-slate-900">Order Inquiry Guidelines</h3>
            <p class="mt-2 text-slate-600 text-xs">When contacting support regarding an existing order, please have your <strong>Order Reference ID</strong> and dispatch tracking number ready so we can assist you without delay.</p>
            <div class="mt-4 rounded-lg bg-white border border-slate-200 p-4">
                <p class="text-xs font-semibold text-slate-900">Discreet & Encrypted</p>
                <p class="mt-1 text-xs text-slate-500">All communication via email is securely handled. We never disclose your personal details or order history to third parties.</p>
            </div>
        </div>
    </div>
</div>
HTML;

    // ── SHIPPING & DELIVERY ──────────────────────────────────────────
    $shippingHtml = <<<HTML
<div class="space-y-6 text-slate-700 leading-relaxed text-sm">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <h2 class="text-2xl font-bold text-slate-900">UK Shipping & Delivery Standards</h2>
        <p class="mt-2 text-slate-600">All orders are dispatched domestically from within the United Kingdom using tracked, insured delivery services. We guarantee rapid handling and discreet packaging on every order.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-bold text-slate-900 text-base">Royal Mail Tracked 24</h3>
            <p class="mt-1 text-xs text-slate-500">Estimated delivery: 1 - 2 business days</p>
            <p class="mt-3 text-slate-600">Ideal for rapid, priority dispatch. Full point-to-point tracking and delivery notifications via SMS or email.</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-bold text-slate-900 text-base">Royal Mail Tracked 48</h3>
            <p class="mt-1 text-xs text-slate-500">Estimated delivery: 2 - 3 business days</p>
            <p class="mt-3 text-slate-600">Standard reliable delivery across England, Scotland, Wales, and Northern Ireland with tracking number included.</p>
        </div>
    </div>

    <div class="rounded-xl bg-slate-50 border border-slate-200 p-6">
        <h3 class="font-bold text-slate-900">Discretion Guaranteed</h3>
        <p class="mt-2 text-slate-600 text-sm">Parcels arrive in plain, unmarked packaging without branding, logos, or product disclosures. The return address references our neutral fulfillment facility.</p>
    </div>
</div>
HTML;

    // ── PRIVACY POLICY ───────────────────────────────────────────────
    $privacyHtml = <<<HTML
<div class="space-y-6 text-slate-700 leading-relaxed text-sm">
    <p>Supreme Steroids is committed to safeguarding the privacy and security of our visitors and registered account holders in compliance with the UK Data Protection Act 2018 and UK GDPR.</p>

    <h2 class="text-xl font-bold text-slate-900 mt-6">1. Data We Collect</h2>
    <p>We collect only the minimum information necessary to process your orders: recipient name, shipping address, and email for dispatch notifications. Payment processing is handled securely off-site without storing sensitive financial credentials.</p>

    <h2 class="text-xl font-bold text-slate-900 mt-6">2. Data Security & Storage</h2>
    <p>We utilize industry-standard SSL/TLS 256-bit encryption. Customer session data and order records are stored within protected, hardened databases with strict access controls.</p>

    <h2 class="text-xl font-bold text-slate-900 mt-6">3. Third-Party Sharing</h2>
    <p>We never sell, rent, or trade your personal information. Data is shared exclusively with necessary service providers, such as postal delivery carriers, solely to fulfill physical parcel shipment.</p>
</div>
HTML;

    // ── COOKIE POLICY ────────────────────────────────────────────────
    $cookieHtml = <<<HTML
<div class="space-y-6 text-slate-700 leading-relaxed text-sm">
    <p>This Cookie Policy explains how Supreme Steroids uses cookies and similar technologies to enhance your browsing and shopping experience.</p>

    <h2 class="text-xl font-bold text-slate-900 mt-6">1. Essential Cookies</h2>
    <p>These cookies are strictly necessary for core website functionality, including maintaining your shopping basket, CSRF security tokens, and user authentication.</p>

    <h2 class="text-xl font-bold text-slate-900 mt-6">2. Performance & Preference Cookies</h2>
    <p>We use anonymous session cookies to remember your display preferences, view settings, and improve platform loading speeds. These cookies do not store personally identifiable data.</p>
</div>
HTML;

    $pagesToSeed = [
        'faq' => ['title' => 'Frequently Asked Questions', 'content' => $faqHtml],
        'bpc-157-uk' => ['title' => 'Buy BPC 157 UK – Peptide Guide', 'content' => $bpcHtml],
        'thebsl24' => ['title' => 'BSL24 Supreme UK – Best Steroids London', 'content' => $bslHtml],
        'terms' => ['title' => 'Terms & Conditions', 'content' => $termsHtml],
        'terms-conditions' => ['title' => 'Terms & Conditions', 'content' => $termsHtml],
        'returns' => ['title' => 'Return and Refund Policy', 'content' => $returnHtml],
        'return-policy' => ['title' => 'Return and Refund Policy', 'content' => $returnHtml],
        'xmas-updates' => ['title' => 'Holiday & Dispatch Updates', 'content' => $xmasHtml],
        'about' => ['title' => 'About Supreme Steroids', 'content' => $aboutHtml],
        'contact' => ['title' => 'Contact Us', 'content' => $contactHtml],
        'shipping' => ['title' => 'Shipping & Delivery Policy', 'content' => $shippingHtml],
        'privacy' => ['title' => 'Privacy Policy', 'content' => $privacyHtml],
        'cookies' => ['title' => 'Cookie Policy', 'content' => $cookieHtml],
    ];

    foreach ($pagesToSeed as $slug => $data) {
        Page::updateOrCreate(
            ['slug' => $slug],
            [
                'title' => $data['title'],
                'content' => $data['content'],
                'is_published' => true,
            ]
        );
        echo "  ✓ Seeded page: [{$slug}] {$data['title']}\n";
    }

    // ══════════════════════════════════════════════════════════════════
    // 2. BLOG POSTS (All 9 In-Depth Articles)
    // ══════════════════════════════════════════════════════════════════
    echo "\nSeeding 9 Educational Blog Posts...\n";

    $blogSlugs = [
        'primobolan-vs-masteron-which-one-is-better-for-cutting-goals',
        'is-oatmeal-good-for-weight-loss',
        'ecdysterone-benefits-what-you-need-to-know',
        'why-is-dianabol-dying',
        'what-is-muscle-memory-a-detailed-guide',
        'what-is-somatic-exercise-a-detailed-guide',
        'can-you-mix-boldenone-with-testosterone',
        'how-much-ipamorelin-should-you-take-dosage-timing-cycles-explained',
        'what-if-i-took-anavar-pre-workout',
    ];

    $featuredImages = [
        'primobolan-vs-masteron-which-one-is-better-for-cutting-goals' => 'https://supremestd.b-cdn.net/wp-content/uploads/Primobolan-Depot-100-Pharmaqo-Labs.jpeg',
        'is-oatmeal-good-for-weight-loss' => 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=800&auto=format&fit=crop&q=80',
        'ecdysterone-benefits-what-you-need-to-know' => 'https://images.unsplash.com/photo-1587854692152-cbe660dbde88?w=800&auto=format&fit=crop&q=80',
        'why-is-dianabol-dying' => 'https://supremestd.b-cdn.net/wp-content/uploads/Dianabol 10mg Pharmaqo Labs.jpeg',
        'what-is-muscle-memory-a-detailed-guide' => 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?w=800&auto=format&fit=crop&q=80',
        'what-is-somatic-exercise-a-detailed-guide' => 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=800&auto=format&fit=crop&q=80',
        'can-you-mix-boldenone-with-testosterone' => 'https://supremestd.b-cdn.net/wp-content/uploads/Testosterone-E-300-Test-E-Pharmaqo-Labs- (1).jpeg',
        'how-much-ipamorelin-should-you-take-dosage-timing-cycles-explained' => 'https://supremestd.b-cdn.net/files/BPC-157-1-1.jpg',
        'what-if-i-took-anavar-pre-workout' => 'https://supremestd.b-cdn.net/wp-content/uploads/Anavar 10mg – Pharmaqo Labs.jpeg',
    ];

    foreach ($blogSlugs as $slug) {
        $pageData = $scrapedData[$slug] ?? null;
        if (!$pageData) {
            echo "  ⚠️ Missing data for $slug\n";
            continue;
        }

        $html = $pageData['html'];
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new DOMXPath($dom);

        // Extract article container
        $articleNodes = $xpath->query('//*[contains(@class, "bg-white") and contains(@class, "rounded-lg") and contains(@class, "max-w-4xl")] | //article | //div[contains(@class, "entry-content")]');
        $articleHtml = '';
        if ($articleNodes->length > 0) {
            $articleHtml = $dom->saveHTML($articleNodes->item(0));
        } else {
            $articleHtml = $html;
        }

        // Clean unwanted elements
        $articleHtml = preg_replace('/<header\b[^>]*>(.*?)<\/header>/is', '', $articleHtml);
        $articleHtml = preg_replace('/<footer\b[^>]*>(.*?)<\/footer>/is', '', $articleHtml);
        $articleHtml = preg_replace('/<nav\b[^>]*>(.*?)<\/nav>/is', '', $articleHtml);
        $articleHtml = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $articleHtml);
        $articleHtml = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $articleHtml);
        $articleHtml = preg_replace('/<form\b[^>]*>(.*?)<\/form>/is', '', $articleHtml);

        $cleanText = strip_tags($articleHtml);
        $cleanText = preg_replace('/\s+/', ' ', trim($cleanText));
        $excerpt = Str::limit($cleanText, 250);

        $title = $pageData['title'] ?: Str::title(str_replace('-', ' ', $slug));

        BlogPost::updateOrCreate(
            ['slug' => $slug],
            [
                'author_id' => $admin->id,
                'title' => $title,
                'excerpt' => $excerpt,
                'content' => $articleHtml,
                'featured_image_url' => $featuredImages[$slug] ?? null,
                'is_published' => true,
                'published_at' => now()->subDays(rand(2, 30)),
            ]
        );

        echo "  ✓ Seeded post: [{$slug}] {$title}\n";
    }

    DB::commit();

    echo "\n=== All Pages & Blog Posts Seeded Successfully! ===\n";

} catch (\Throwable $e) {
    DB::rollBack();
    echo "\n❌ Error: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
