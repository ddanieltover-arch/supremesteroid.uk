import StorefrontLayout from '../Layouts/StorefrontLayout';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Alert } from '@/components/ui/Alert';
import { Input } from '@/components/ui/Input';

export default function DesignSystem() {
  return (
    <StorefrontLayout seo={{ title: 'Design system', description: 'Supreme Steroids design primitives.', robots: 'noindex,follow' }}>
      <h1 className="text-3xl font-semibold text-slate-900">Design system</h1>
      <p className="mt-3 max-w-2xl text-slate-500">
        Light clinical storefront language. Background #f9f9f9, accent #1e6fd9, emerald for verified / lab-tested status.
      </p>
      <div className="mt-8 flex flex-wrap gap-3">
        <Button variant="gold">Brand</Button>
        <Button variant="primary">Primary</Button>
        <Button variant="secondary">Secondary</Button>
        <Button variant="danger">Danger</Button>
        <Badge variant="emerald">Verified</Badge>
        <Badge variant="gold">Featured</Badge>
      </div>
      <div className="mt-8 max-w-md space-y-4">
        <Input label="Example field" placeholder="Value" />
        <Alert type="success">Order created successfully.</Alert>
        <Alert type="warning">Your basket has changed. We have refreshed the latest pricing and availability.</Alert>
      </div>
    </StorefrontLayout>
  );
}
