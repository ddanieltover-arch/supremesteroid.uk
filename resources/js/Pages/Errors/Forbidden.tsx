import { ErrorState } from '@/components/errors/ErrorState';

export default function Forbidden({ message }: { message?: string }) {
  return (
    <ErrorState
      seoTitle="Not allowed"
      title="You cannot view this page"
      message={message || 'You are not allowed to view that page.'}
    />
  );
}
