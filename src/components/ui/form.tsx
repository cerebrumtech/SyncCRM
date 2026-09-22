"use client";

import { useFormStatus } from "react-dom";
import { Button, type ButtonProps } from "./button";

export function SubmitButton({ children, pendingText, ...props }: ButtonProps & { pendingText?: string }) {
  const { pending } = useFormStatus();
  return (
    <Button type="submit" disabled={pending} {...props}>
      {pending ? (pendingText ?? "Saving…") : children}
    </Button>
  );
}

export function FormError({ message }: { message?: string }) {
  if (!message) return null;
  return (
    <div
      className="rounded-md border border-danger/30 bg-danger-bg px-3 py-2 text-sm text-danger-fg"
      role="alert"
      data-testid="form-error"
    >
      {message}
    </div>
  );
}

export function FormSuccess({ message }: { message?: string }) {
  if (!message) return null;
  return (
    <div
      className="rounded-md border border-success/30 bg-success-bg px-3 py-2 text-sm text-success-fg"
      role="status"
      data-testid="form-success"
    >
      {message}
    </div>
  );
}
