import { ErrorState } from '@/components/errors/ErrorState';

export default function ServiceUnavailable({ message }: { message?: string }) {
  return (
    <ErrorState
      seoTitle="Store unavailable"
      title="The store is temporarily unavailable"
      message={message || 'Please try again shortly.'}
    />
  );
}
