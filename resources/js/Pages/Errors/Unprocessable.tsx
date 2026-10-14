import { ErrorState } from '@/components/errors/ErrorState';

export default function Unprocessable({ message }: { message?: string }) {
  return (
    <ErrorState
      seoTitle="Request not processed"
      title="We could not process that request"
      message={message || 'Please review the details and try again.'}
    />
  );
}
