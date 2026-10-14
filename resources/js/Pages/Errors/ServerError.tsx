import { ErrorState } from '@/components/errors/ErrorState';

export default function ServerError({ message }: { message?: string }) {
  return (
    <ErrorState
      seoTitle="Temporary interruption"
      title="Temporary service interruption"
      message={message || 'The store is experiencing a temporary service issue. Please try again shortly.'}
    />
  );
}
