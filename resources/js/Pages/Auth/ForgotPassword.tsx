import { AuthCard } from '@/components/auth/AuthCard';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

export default function ForgotPassword() {
  const form = useForm({ email: '' });

  return (
    <AuthCard title="Reset password" seo={{ title: 'Reset password', description: 'Request a password reset.', robots: 'noindex,nofollow' }}>
      <form
        className="grid gap-3"
        onSubmit={(event) => {
          event.preventDefault();
          form.post('/forgot-password');
        }}
      >
        <Input label="Email" type="email" value={form.data.email} error={form.errors.email} onChange={(event) => form.setData('email', event.target.value)} required />
        <Button type="submit" variant="gold" isLoading={form.processing}>
          Send reset link
        </Button>
      </form>
    </AuthCard>
  );
}
