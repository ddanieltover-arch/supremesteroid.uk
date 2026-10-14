import { ErrorState } from '@/components/errors/ErrorState';

export default function NotFound({ message }: { message?: string }) {
  return (
    <ErrorState
      seoTitle="Page not found"
      title="Page not found"
      message={message || 'The page you requested is not available.'}
    />
  );
}
