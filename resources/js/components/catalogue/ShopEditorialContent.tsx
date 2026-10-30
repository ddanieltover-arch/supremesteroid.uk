import { Link } from '@inertiajs/react';
import { Award, CheckCircle2, ShieldCheck, Truck, CreditCard, ShoppingBag, HelpCircle, ArrowRight } from 'lucide-react';

export function ShopEditorialContent() {
  const mainCategories = [
    { name: 'Oral Steroids', desc: 'Tablets and capsules for lean muscle, strength, and quick oral absorption.', url: '/category/oral-steroids' },
    { name: 'Injectable Steroids', desc: 'Sustained-release ester compounds for steady anabolic hormone levels.', url: '/category/injectable-steroids' },
    { name: 'Post Cycle Therapy (PCT)', desc: 'Hormonal recovery agents including Nolvadex, Clomid, Arimidex, and HCG.', url: '/category/pct' },
    { name: 'Fat Burners & Cutting', desc: 'Metabolic enhancers, Clenbuterol, T3 Cytomel, and cutting thermogenics.', url: '/category/fat-burner' },
    { name: 'SARMs UK', desc: 'Selective androgen receptor modulators including RAD-140, MK-677, and LGD-4033.', url: '/category/sarms-uk' },
    { name: 'Growth Hormone (HGH)', desc: 'Pharma-grade Somatropin, IGF-1, and cellular recovery peptides.', url: '/category/growth-hormone' },
    { name: 'Kamagra & Vitality', desc: 'Erectile support, Sildenafil, and Tadalafil formulations.', url: '/category/kamagra' },
  ];

  const premiumBrands = [
    { name: 'Pharmaqo Labs', slug: 'pharmaqo-labs', badge: 'Flagship Partner' },
    { name: 'Proper Labs', slug: 'proper-labs', badge: 'Certified GMP' },
    { name: 'IMUSCLE SARMs', slug: 'imuscle-sarms', badge: 'High Purity' },
    { name: 'Ajanta Pharma', slug: 'ajanta-pharma', badge: 'Pharma Grade' },
    { name: 'Beligas Pharmaceuticals', slug: 'beligas-pharmaceuticals', badge: 'Global Brand' },
    { name: 'Deus Medical', slug: 'deus-medical', badge: 'Lab Verified' },
    { name: 'Evo Tech', slug: 'evo-tech', badge: 'Premium Quality' },
    { name: 'SAMARTH', slug: 'samarth', badge: 'Medical Grade' },
    { name: 'Centurion Lab', slug: 'centurion-lab', badge: 'Authentic' },
  ];

  const whyChoosePoints = [
    { title: 'Certified and Trusted Source', desc: 'Supreme Steroids is an authentic source of high-quality anabolic steroids, SARMs, growth hormones, and fat burners for performance enhancement.' },
    { title: 'Precise Product Information', desc: 'Each of our products provides comprehensive information, including full composition, dosage, active life, and transparent upfront pricing.' },
    { title: 'Easy and Simple Shopping Experience', desc: 'Our customer-centric platform ensures fast, intuitive browsing and 100% discreet delivery across the UK, protecting your privacy at every step.' },
    { title: 'Safe and Rapid Service', desc: 'All orders are dispatched within 1–2 working days with express tracking, ensuring prompt, reliable arrival at your doorstep.' },
    { title: 'Cost-Effective Products', desc: 'Our prime goal is delivering genuine, pharma-grade compounds at affordable market-competitive prices, plus 20% off with crypto.' },
    { title: 'Reliable Customer Support', desc: 'Our diligent customer service team is always ready to guide you whenever you need assistance, ensuring an optimised ordering experience.' },
  ];

  const customerPreferences = [
    'Cost-effective products & bulk discounts',
    'Expert advice on cycles and stacks',
    'Secure payment options including Cards & Crypto',
    'Simple, hassle-free online ordering',
    'No prescription required',
    'Dedicated customer support desk',
    'Easy access to lab-tested steroids in the UK',
    'Plain, unmarked packaging & fast UK delivery',
  ];

  const buyingSteps = [
    { step: '01', title: 'Choose Your Products', desc: 'Browse our extensive catalogue and select your required compounds directly sourced from leading brands like Pharmaqo, Proper Labs, and Beligas.' },
    { step: '02', title: 'Add to Cart', desc: 'Select desired quantities and strengths, add items to your cart, and proceed to our secure, SSL-encrypted checkout.' },
    { step: '03', title: 'Fast UK Delivery', desc: 'Once your order is confirmed, our team packages it discreetly and dispatches it within 1–2 working days with Royal Mail tracking.' },
    { step: '04', title: 'Secure Payment Methods', desc: 'Pay safely using your preferred method: debit/credit card, direct bank transfer, or cryptocurrency for an automatic 20% discount.' },
  ];

  const shopFaqs = [
    {
      q: 'What is your return policy?',
      a: 'To be eligible for a return, the product must be completely unused, sealed, and in its original manufacturer packaging. The policy applies to purchases made within 30 days of receipt.',
    },
    {
      q: 'How do I know your products are real and lab-tested?',
      a: 'We only deliver factory-sealed products from registered pharmaceutical brands that adhere to strict GMP and MHRA quality standards. You can verify batch numbers, scratch-off codes, and lab reports directly online.',
    },
    {
      q: 'What payment methods are accepted at Supreme Steroids?',
      a: 'Supreme Steroids accepts convenient payment methods including Visa, Mastercard, Direct Bank Transfer, and Bitcoin/Cryptocurrency (with an extra 20% discount).',
    },
    {
      q: 'How to buy anabolic steroids online in the UK from Supreme Steroids?',
      a: 'Simply explore our shop catalogue, choose your required items, add them to your cart, and complete our straightforward checkout. Your order will be dispatched discreetly within 1–2 working days.',
    },
    {
      q: 'Do you deliver anabolic steroids all over the UK?',
      a: 'Yes, we provide prime-quality, lab-verified, and trusted performance compounds to all addresses across England, Scotland, Wales, and Northern Ireland.',
    },
    {
      q: 'Why do people purchase anabolic steroids?',
      a: 'Bodybuilders and fitness enthusiasts purchase anabolic steroids to increase muscle mass, boost skeletal strength, and elevate sports performance. Medical compounds also help manage inflammation, hormonal deficiencies, and muscle-wasting conditions.',
    },
  ];

  return (
    <section className="mt-20 border-t border-slate-200 bg-white py-16 sm:py-20">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {/* Header Introduction */}
        <div className="max-w-4xl">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-blue-700">
            <Award className="h-4 w-4" /> Authorised &amp; Reputable UK Source
          </span>
          <h2 className="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">
            Supreme Steroids – An Authorised and Reputable Steroid Source
          </h2>
          <p className="mt-4 text-base leading-relaxed text-slate-600 sm:text-lg">
            Since 2020, we have been delivering a wide range of steroids to help you build <strong>muscle</strong>, <strong>reduce fat</strong>, <strong>boost performance</strong>, and <strong>increase energy</strong>. No matter if you are an experienced user or a first-time customer, Supreme Steroids offers a wide variety of tested and trusted steroids. We want to help you boost physical performance and achieve your fitness goals safely.
          </p>
        </div>

        {/* Main Product Categories Cards */}
        <div className="mt-12">
          <h3 className="text-xl font-bold text-slate-900">Explore Primary Categories</h3>
          <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {mainCategories.map((cat) => (
              <Link
                key={cat.url}
                href={cat.url}
                className="group relative flex flex-col justify-between rounded-2xl border border-slate-200 bg-slate-50/70 p-5"
              >
                <div>
                  <h4 className="font-semibold text-slate-900 group-hover:text-blue-600 transition-colors">{cat.name}</h4>
                  <p className="mt-2 text-xs text-slate-600 leading-relaxed">{cat.desc}</p>
                </div>
                <div className="mt-4 flex items-center text-xs font-semibold text-blue-600">
                  Shop category <ArrowRight className="ml-1.5 h-3.5 w-3.5" />
                </div>
              </Link>
            ))}
          </div>
        </div>

        {/* Premium Brands Grid */}
        <div className="mt-16 rounded-3xl bg-slate-900 p-8 text-white sm:p-12">
          <div className="max-w-3xl">
            <span className="text-xs font-bold uppercase tracking-widest text-blue-400">Trusted Manufacturers</span>
            <h3 className="mt-2 text-2xl font-bold sm:text-3xl">Premium Brands We Collect Products From</h3>
            <p className="mt-3 text-sm text-slate-300 leading-relaxed sm:text-base">
              We aim to provide top-tier, pharma-grade products to all our UK customers to ensure optimal performance. It's why we only collaborate with registered and recognised brands that meet our strict purity standards:
            </p>
          </div>
          <div className="mt-8 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
            {premiumBrands.map((b) => (
              <Link
                key={b.slug}
                href={`/brand/${b.slug}`}
                className="flex flex-col items-center justify-center rounded-xl border border-slate-700 bg-slate-800/80 p-4 text-center transition-all hover:border-blue-500 hover:bg-slate-800"
              >
                <span className="text-sm font-semibold text-slate-100">{b.name}</span>
                <span className="mt-1 text-[10px] font-medium text-blue-400 uppercase tracking-wider">{b.badge}</span>
              </Link>
            ))}
          </div>
        </div>

        {/* Why Choose & Preferences 2-col */}
        <div className="mt-16 grid gap-10 lg:grid-cols-2">
          {/* Why Choose */}
          <div>
            <h3 className="text-2xl font-bold text-slate-900">Why Choose Supreme Steroids</h3>
            <div className="mt-6 space-y-4">
              {whyChoosePoints.map((item, idx) => (
                <div key={idx} className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                  <h4 className="font-semibold text-slate-900 text-sm">{item.title}</h4>
                  <p className="mt-1.5 text-xs text-slate-600 leading-relaxed">{item.desc}</p>
                </div>
              ))}
            </div>
          </div>

          {/* Preferences Checklist */}
          <div>
            <h3 className="text-2xl font-bold text-slate-900">Why Customers Prefer Buying From Us</h3>
            <p className="mt-3 text-sm text-slate-600 leading-relaxed">
              Thousands of UK bodybuilders and athletes trust our platform for fast, discrete, and genuine product delivery:
            </p>
            <div className="mt-6 rounded-2xl border border-slate-200 bg-slate-50/70 p-6">
              <ul className="space-y-3.5">
                {customerPreferences.map((pref, i) => (
                  <li key={i} className="flex items-center gap-3 text-sm text-slate-700">
                    <CheckCircle2 className="h-4 w-4 text-emerald-600 shrink-0" />
                    <span>{pref}</span>
                  </li>
                ))}
              </ul>
            </div>

            {/* Discreet Delivery & Security Card */}
            <div className="mt-6 rounded-2xl border border-blue-200 bg-blue-50/60 p-6 text-slate-800">
              <div className="flex items-center gap-3">
                <Truck className="h-5 w-5 text-blue-600" />
                <h4 className="font-bold text-slate-900">100% Discreet Packaging Guarantee</h4>
              </div>
              <p className="mt-2 text-xs leading-relaxed text-slate-600">
                All packages are shipped in plain, unmarked cardboard boxes or padded envelopes with no mention of brand names, steroids, or website contents. Your total privacy is guaranteed.
              </p>
            </div>
          </div>
        </div>

        {/* 4-Step How to Buy Process */}
        <div className="mt-16 rounded-3xl border border-slate-200 bg-slate-50 p-8 sm:p-12">
          <div className="text-center max-w-2xl mx-auto">
            <span className="text-xs font-bold uppercase tracking-widest text-blue-600">Simple Ordering</span>
            <h3 className="mt-2 text-2xl font-bold text-slate-900 sm:text-3xl">How to Buy from Supreme Steroids</h3>
            <p className="mt-2 text-sm text-slate-600">Our ordering process is quick, confidential, and straightforward:</p>
          </div>
          <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {buyingSteps.map((step) => (
              <div key={step.step} className="relative rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div className="text-3xl font-extrabold text-brand/30">{step.step}</div>
                <h4 className="mt-2 font-semibold text-slate-900 text-base">{step.title}</h4>
                <p className="mt-2 text-xs leading-relaxed text-slate-600">{step.desc}</p>
              </div>
            ))}
          </div>
        </div>

        {/* Shop FAQs */}
        <div className="mt-16">
          <div className="max-w-2xl mx-auto text-center">
            <h3 className="text-2xl font-bold text-slate-900 sm:text-3xl">Frequently Asked Questions</h3>
            <p className="mt-2 text-sm text-slate-600">Key questions about purchasing, delivery, and authenticity answered.</p>
          </div>
          <div className="mt-8 grid gap-6 md:grid-cols-2">
            {shopFaqs.map((faq, i) => (
              <div key={i} className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div className="flex items-start gap-3">
                  <HelpCircle className="h-5 w-5 text-brand shrink-0 mt-0.5" />
                  <div>
                    <h4 className="font-semibold text-slate-900 text-sm sm:text-base">{faq.q}</h4>
                    <p className="mt-2 text-xs sm:text-sm text-slate-600 leading-relaxed">{faq.a}</p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
