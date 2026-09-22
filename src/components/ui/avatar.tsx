import { initials } from "@/lib/format";
import { cn } from "@/lib/cn";

export function Avatar({
  name,
  color = "#0068FF",
  size = 28,
  className,
}: {
  name: string;
  color?: string;
  size?: number;
  className?: string;
}) {
  return (
    <span
      className={cn(
        "inline-flex shrink-0 items-center justify-center rounded-full font-semibold text-white",
        className,
      )}
      style={{ width: size, height: size, background: color, fontSize: Math.max(10, size * 0.4) }}
      title={name}
    >
      {initials(name)}
    </span>
  );
}
