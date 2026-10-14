import { ErrorState } from '@/components/errors/ErrorState';

export default function Expired({ message }: { message?: string }) {
  return (
    <ErrorState
      seoTitle="Session expired"
      title="Session expired"
      message={message || 'Your session expired. Please refresh and try again.'}
    />
  );
}
