import * as React from "react";
import { cn } from "@/lib/cn";

const tones = {
  neutral: "bg-surface text-ink-700",
  info: "bg-info-bg text-info-fg",
  success: "bg-success-bg text-success-fg",
  warning: "bg-warning-bg text-warning-fg",
  danger: "bg-danger-bg text-danger-fg",
  navy: "bg-navy text-white",
} as const;

export type BadgeTone = keyof typeof tones;

export function Badge({
  tone = "neutral",
  className,
  ...props
}: React.HTMLAttributes<HTMLSpanElement> & { tone?: BadgeTone }) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold leading-5",
        tones[tone],
        className,
      )}
      {...props}
    />
  );
}

export function TagChip({ name }: { name: string }) {
  return (
    <span className="inline-flex items-center rounded-full border border-line-100 bg-white px-2 py-0.5 text-xs text-ink-700">
      {name}
    </span>
  );
}
