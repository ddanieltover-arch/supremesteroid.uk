<?php

/**
 * Add missing content pages to match the reference site.
 * Creates: payment-methods, lab-tests, reviews, delivery-info (alias to shipping)
 * Also ensures about-us, contact-us slugs exist as aliases.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pages = [
    [
        'title' => 'Payment Methods',
        'slug' => 'payment-methods',
        'content' => '<div class="prose max-w-none">
<h2>Accepted Payment Methods</h2>
<p>At Supreme Steroids, we offer multiple secure payment options for your convenience.</p>

<h3>Bank Transfer</h3>
<p>Direct bank transfer is our primary payment method. Once you place your order, you will receive our banking details via email. Please use your order number as the payment reference. Orders are processed once payment has been confirmed.</p>

<h3>Cryptocurrency</h3>
<p>We accept cryptocurrency payments including Bitcoin (BTC), Ethereum (ETH), and USDT. Crypto payments offer enhanced privacy and are processed quickly. A 5% discount is applied to all cryptocurrency orders.</p>

<h3>Payment Security</h3>
<p>All payment information is handled securely. We never store sensitive banking details on our servers. Bank transfers are processed through established UK banking channels.</p>

<h3>Payment Confirmation</h3>
<p>After submitting your payment, please allow up to 24 hours for bank transfer confirmation. Cryptocurrency payments are typically confirmed within 1-2 hours depending on network congestion.</p>
</div>',
        'is_published' => true,
    ],
    [
        'title' => 'Lab Tests & Authenticity',
        'slug' => 'lab-tests',
        'content' => '<div class="prose max-w-none">
<h2>Laboratory Testing & Product Authenticity</h2>
<p>At Supreme Steroids, product quality and authenticity are our top priorities. Every batch of products we stock undergoes rigorous third-party laboratory testing.</p>

<h3>Our Testing Process</h3>
<p>We work with independent UKAS-accredited laboratories to verify the purity, potency, and safety of all our products. Each product batch is tested for:</p>
<ul>
<li><strong>Active ingredient concentration</strong> – Ensuring the stated dosage matches actual content</li>
<li><strong>Purity analysis</strong> – Checking for contaminants, heavy metals, and unwanted compounds</li>
<li><strong>Sterility testing</strong> – For all injectable products</li>
<li><strong>Endotoxin testing</strong> – Ensuring products meet pharmaceutical safety standards</li>
</ul>

<h3>Certificates of Analysis</h3>
<p>Lab test certificates are available on individual product pages where applicable. Look for the "Laboratory Tested" badge on our product listings. Each certificate includes the batch number, test date, and results.</p>

<h3>Brand Verification</h3>
<p>We are authorised stockists for all brands listed on our website. Our supply chain is fully verified to ensure you receive genuine, pharmaceutical-grade products.</p>

<h3>Quality Guarantee</h3>
<p>If you receive a product that does not meet our quality standards, we will replace it free of charge. Your safety and satisfaction are our absolute priorities.</p>
</div>',
        'is_published' => true,
    ],
    [
        'title' => 'Customer Reviews',
        'slug' => 'reviews',
        'content' => '<div class="prose max-w-none">
<h2>What Our Customers Say</h2>
<p>We pride ourselves on providing the highest quality products and exceptional customer service. Here is what our verified customers have to say about their experience with Supreme Steroids.</p>

<h3>Trustpilot Rating</h3>
<p>We maintain a strong rating across independent review platforms. Our commitment to quality, fast delivery, and responsive customer support has earned us the trust of thousands of customers across the UK.</p>

<h3>Leave a Review</h3>
<p>Had a great experience? We would love to hear from you. You can leave a review on any product page after your purchase, or contact us directly with your feedback.</p>

<h3>Review Policy</h3>
<p>All reviews on our website are from verified purchasers. We do not edit, filter, or remove genuine reviews. Both positive and constructive feedback help us improve our service.</p>
</div>',
        'is_published' => true,
    ],
    [
        'title' => 'Delivery & Shipping Information',
        'slug' => 'delivery-info',
        'content' => '<div class="prose max-w-none">
<h2>Delivery & Shipping Information</h2>
<p>We offer fast, discreet delivery across the United Kingdom.</p>

<h3>Standard Delivery (Royal Mail Tracked 48)</h3>
<ul>
<li><strong>Cost:</strong> £4.99</li>
<li><strong>Timeframe:</strong> 2-3 working days</li>
<li><strong>Tracking:</strong> Full tracking provided</li>
</ul>

<h3>Express Delivery (Royal Mail Tracked 24)</h3>
<ul>
<li><strong>Cost:</strong> £7.99</li>
<li><strong>Timeframe:</strong> Next working day</li>
<li><strong>Tracking:</strong> Full tracking provided</li>
</ul>

<h3>Free Delivery</h3>
<p>All orders over £150 qualify for free standard delivery.</p>

<h3>Discreet Packaging</h3>
<p>All orders are shipped in plain, unmarked packaging with no external branding or indication of contents. Your privacy is our priority.</p>

<h3>Dispatch Times</h3>
<p>Orders placed before 2pm (Monday-Friday) with confirmed payment are dispatched the same day. Orders placed after 2pm or on weekends are dispatched the next working day.</p>

<h3>International Delivery</h3>
<p>We currently ship to UK addresses only. We are working on expanding our delivery network to serve international customers in the future.</p>
</div>',
        'is_published' => true,
    ],
    [
        'title' => 'About Us',
        'slug' => 'about-us',
        'content' => '<div class="prose max-w-none">
<h2>About Supreme Steroids</h2>
<p>Supreme Steroids is the UK\'s trusted source for premium pharmaceutical-grade performance enhancement products. Established with a commitment to quality, authenticity, and customer satisfaction, we have become one of the most respected suppliers in the industry.</p>

<h3>Our Mission</h3>
<p>To provide athletes, bodybuilders, and fitness enthusiasts with access to the highest quality, laboratory-tested products at competitive prices, supported by expert guidance and exceptional customer service.</p>

<h3>Quality Assurance</h3>
<p>Every product in our catalogue undergoes rigorous third-party laboratory testing. We work exclusively with established pharmaceutical manufacturers and maintain strict quality control throughout our supply chain.</p>

<h3>Why Choose Us?</h3>
<ul>
<li><strong>Laboratory Tested</strong> – Independent UKAS-accredited lab verification for every batch</li>
<li><strong>Genuine Products</strong> – Authorised stockist for all brands</li>
<li><strong>Fast Delivery</strong> – Same-day dispatch on orders before 2pm</li>
<li><strong>Discreet Packaging</strong> – Plain, unmarked parcels</li>
<li><strong>Expert Support</strong> – Knowledgeable team available 7 days a week</li>
<li><strong>Secure Payments</strong> – Multiple safe payment options</li>
</ul>

<h3>Our Team</h3>
<p>Our team comprises experienced professionals with deep knowledge of performance enhancement, nutrition, and fitness. We are here to help you make informed decisions and achieve your goals safely.</p>
</div>',
        'is_published' => true,
    ],
    [
        'title' => 'Contact Us',
        'slug' => 'contact-us',
        'content' => '<div class="prose max-w-none">
<h2>Contact Us</h2>
<p>We are here to help. Get in touch with our team for any questions, order enquiries, or support.</p>

<h3>Email</h3>
<p><a href="mailto:info@supremesteroid.uk">info@supremesteroid.uk</a></p>

<h3>Response Times</h3>
<ul>
<li><strong>Email:</strong> We aim to respond within 2-4 hours during business hours</li>
<li><strong>Business Hours:</strong> Monday to Friday, 9am – 6pm GMT</li>
<li><strong>Weekend Support:</strong> Limited support available Saturday 10am – 2pm</li>
</ul>

<h3>Order Enquiries</h3>
<p>For order-related questions, please include your order number in your message for faster assistance.</p>

<h3>General Enquiries</h3>
<p>For product advice, dosage guidance, or general questions about our products and services, our knowledgeable team is happy to assist.</p>
</div>',
        'is_published' => true,
    ],
    [
        'title' => 'Returns & Refunds',
        'slug' => 'returns-refunds',
        'content' => '<div class="prose max-w-none">
<h2>Returns & Refunds Policy</h2>
<p>Your satisfaction is important to us. Please read our returns and refunds policy carefully.</p>

<h3>Damaged or Incorrect Items</h3>
<p>If you receive a damaged or incorrect item, please contact us within 48 hours of delivery with photographic evidence. We will arrange a replacement or full refund at no additional cost.</p>

<h3>Quality Issues</h3>
<p>If you believe a product does not meet our quality standards, please contact us with details. We take quality complaints very seriously and will investigate promptly.</p>

<h3>Non-Returnable Items</h3>
<p>Due to the nature of our products and health regulations, we cannot accept returns of opened or used items. All sealed, unused products can be returned within 14 days for a full refund.</p>

<h3>Refund Processing</h3>
<p>Refunds are processed within 5-7 working days of receiving the returned item or approving a claim. Refunds are issued via the original payment method.</p>

<h3>Lost Parcels</h3>
<p>If your tracked parcel shows as delivered but you have not received it, please contact us within 7 days. We will investigate with the courier and arrange a replacement if the parcel cannot be located.</p>
</div>',
        'is_published' => true,
    ],
    [
        'title' => 'Terms & Conditions',
        'slug' => 'terms-conditions',
        'content' => '<div class="prose max-w-none">
<h2>Terms & Conditions</h2>
<p>By using this website and placing an order, you agree to the following terms and conditions.</p>

<h3>Age Requirement</h3>
<p>You must be at least 18 years of age to purchase products from this website. By placing an order, you confirm that you are 18 or older.</p>

<h3>Product Information</h3>
<p>We make every effort to ensure product descriptions, images, and pricing are accurate. However, we reserve the right to correct any errors and update information without notice.</p>

<h3>Orders & Payment</h3>
<p>All orders are subject to availability. We reserve the right to refuse or cancel any order. Payment must be received in full before orders are dispatched.</p>

<h3>Intellectual Property</h3>
<p>All content on this website, including text, images, logos, and design, is the property of Supreme Steroids and is protected by copyright law.</p>

<h3>Limitation of Liability</h3>
<p>Products are sold for research purposes only. Supreme Steroids accepts no liability for misuse of products. Users are responsible for complying with all applicable laws in their jurisdiction.</p>

<h3>Governing Law</h3>
<p>These terms are governed by the laws of England and Wales.</p>
</div>',
        'is_published' => true,
    ],
    [
        'title' => 'Privacy Policy',
        'slug' => 'privacy-policy',
        'content' => '<div class="prose max-w-none">
<h2>Privacy Policy</h2>
<p>Supreme Steroids is committed to protecting your privacy. This policy explains how we collect, use, and protect your personal data.</p>

<h3>Information We Collect</h3>
<ul>
<li>Name and contact details (email, phone number, delivery address)</li>
<li>Order history and payment information</li>
<li>Website usage data (cookies, analytics)</li>
</ul>

<h3>How We Use Your Data</h3>
<ul>
<li>Processing and fulfilling your orders</li>
<li>Communicating order updates and delivery information</li>
<li>Improving our website and services</li>
<li>Marketing communications (only with your consent)</li>
</ul>

<h3>Data Protection</h3>
<p>We implement appropriate security measures to protect your personal data against unauthorized access, alteration, or destruction. We do not sell or share your data with third parties for marketing purposes.</p>

<h3>Your Rights</h3>
<p>Under GDPR, you have the right to access, correct, or delete your personal data. Contact us at info@supremesteroid.uk to exercise your rights.</p>

<h3>Cookies</h3>
<p>We use cookies to improve your browsing experience and analyse website traffic. You can manage cookie preferences through your browser settings.</p>
</div>',
        'is_published' => true,
    ],
];

$created = 0;
$updated = 0;
$skipped = 0;

foreach ($pages as $pageData) {
    $page = App\Models\Page::query()->where('slug', $pageData['slug'])->first();
    if ($page) {
        $page->fill($pageData);
        if ($page->isDirty()) {
            $page->save();
            echo "UPDATED: /{$pageData['slug']} – {$pageData['title']}\n";
            $updated++;
        } else {
            echo "SKIP: /{$pageData['slug']} already up to date\n";
            $skipped++;
        }
        continue;
    }

    App\Models\Page::create($pageData);
    echo "CREATED: /{$pageData['slug']} – {$pageData['title']}\n";
    $created++;
}

// Normalize any leftover reference emails in published page HTML.
$normalized = App\Models\Page::query()
    ->where('content', 'like', '%help@supremesteroids.co.uk%')
    ->orWhere('content', 'like', '%hello@example.com%')
    ->get();

foreach ($normalized as $page) {
    $page->content = str_replace(
        ['help@supremesteroids.co.uk', 'hello@example.com'],
        ['info@supremesteroid.uk', 'info@supremesteroid.uk'],
        (string) $page->content,
    );
    $page->save();
    echo "NORMALIZED EMAIL: /{$page->slug}\n";
    $updated++;
}

echo "\nDone. Created: $created, Updated: $updated, Skipped: $skipped\n";

// List all pages
echo "\n=== All Content Pages ===\n";
$allPages = App\Models\Page::orderBy('slug')->get();
foreach ($allPages as $page) {
    $pub = $page->is_published ? 'Y' : 'N';
    echo "  [$pub] /{$page->slug} – {$page->title}\n";
}
echo "Total: {$allPages->count()}\n";
