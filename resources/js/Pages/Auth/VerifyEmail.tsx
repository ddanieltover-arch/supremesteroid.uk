import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/Button';

export default function VerifyEmail() {
  const form = useForm({});

  return (
    <StorefrontLayout seo={{ title: 'Verify email', description: 'Verify your email address.', robots: 'noindex,nofollow' }}>
      <h1 className="text-3xl font-semibold text-white">Verify your email</h1>
      <p className="mt-4 max-w-lg text-slate-400">We sent a verification link to your email address. You can request another copy if it has not arrived.</p>
      <Button
        className="mt-6"
        variant="gold"
        isLoading={form.processing}
        onClick={() => form.post('/email/verification-notification')}
      >
        Resend verification email
      </Button>
    </StorefrontLayout>
  );
}
