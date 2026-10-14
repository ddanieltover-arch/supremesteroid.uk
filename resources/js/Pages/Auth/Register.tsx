import { AuthCard } from '@/components/auth/AuthCard';
import { useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

export default function Register() {
  const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });

  return (
    <AuthCard title="Create account" seo={{ title: 'Create account', description: 'Create a Supreme Steroids account.', robots: 'noindex,nofollow' }}>
      <form
        className="grid gap-3"
        onSubmit={(event) => {
          event.preventDefault();
          form.post('/register');
        }}
      >
        <Input label="Name" value={form.data.name} error={form.errors.name} onChange={(event) => form.setData('name', event.target.value)} required />
        <Input label="Email" type="email" value={form.data.email} error={form.errors.email} onChange={(event) => form.setData('email', event.target.value)} required />
        <Input label="Password" type="password" value={form.data.password} error={form.errors.password} onChange={(event) => form.setData('password', event.target.value)} required />
        <Input label="Confirm password" type="password" value={form.data.password_confirmation} onChange={(event) => form.setData('password_confirmation', event.target.value)} required />
        <Button type="submit" variant="gold" isLoading={form.processing}>
          Register
        </Button>
      </form>
    </AuthCard>
  );
}
