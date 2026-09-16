import StorefrontLayout from '../../Layouts/StorefrontLayout';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

export default function ResetPassword({ email, token }: { email?: string; token: string }) {
  const form = useForm({
    email: email ?? '',
    token,
    password: '',
    password_confirmation: '',
  });

  return (
    <StorefrontLayout seo={{ title: 'Set new password', description: 'Choose a new password.', robots: 'noindex,nofollow' }}>
      <h1 className="text-3xl font-semibold text-white">Set a new password</h1>
      <form
        className="mt-8 grid max-w-md gap-3"
        onSubmit={(event) => {
          event.preventDefault();
          form.post('/reset-password');
        }}
      >
        <Input label="Email" type="email" value={form.data.email} error={form.errors.email} onChange={(event) => form.setData('email', event.target.value)} required />
        <Input label="Password" type="password" value={form.data.password} error={form.errors.password} onChange={(event) => form.setData('password', event.target.value)} required />
        <Input label="Confirm password" type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} required />
        <Button type="submit" variant="gold" isLoading={form.processing}>
          Update password
        </Button>
      </form>
    </StorefrontLayout>
  );
}
