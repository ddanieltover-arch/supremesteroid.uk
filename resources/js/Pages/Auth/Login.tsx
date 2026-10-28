import { AuthCard } from '@/components/auth/AuthCard';
import { Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';

export default function Login() {
  const form = useForm({
    email: '',
    password: '',
    remember: false,
  });

  return (
    <AuthCard title="Sign in" seo={{ title: 'Sign in', description: 'Sign in to your account.', robots: 'noindex,nofollow' }}>
      <form
        className="grid gap-3"
        onSubmit={(event) => {
          event.preventDefault();
          form.post('/login');
        }}
      >
        <Input label="Email" type="email" autoComplete="email" value={form.data.email} error={form.errors.email} onChange={(event) => form.setData('email', event.target.value)} required />
        <Input label="Password" type="password" autoComplete="current-password" value={form.data.password} error={form.errors.password} onChange={(event) => form.setData('password', event.target.value)} required />
        <label className="flex items-center gap-2 text-sm text-slate-500">
          <input type="checkbox" checked={form.data.remember} onChange={(event) => form.setData('remember', event.target.checked)} />
          Remember me
        </label>
        <Button type="submit" variant="gold" isLoading={form.processing}>
          Continue
        </Button>
      </form>
      <div className="mt-4 flex flex-col gap-2 text-sm">
        <Link href="/register" className="text-blue-600">
          Create an account
        </Link>
        <Link href="/forgot-password" className="text-slate-500">
          Forgot password
        </Link>
      </div>
    </AuthCard>
  );
}
