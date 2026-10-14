import { ErrorState } from '@/components/errors/ErrorState';

export default function TooManyRequests({ message }: { message?: string }) {
  return (
    <ErrorState
      seoTitle="Too many requests"
      title="Please wait a moment"
      message={message || 'Too many requests. Please wait a moment and try again.'}
    />
  );
}
