import Link from "next/link";
import { CalendarCheck, FileText, History, StickyNote } from "lucide-react";
import { formatDateTime } from "@/lib/format";
import type { TimelineItem } from "@/lib/queries";

const ICON = {
  activity: CalendarCheck,
  note: StickyNote,
  file: FileText,
  audit: History,
};

export function Timeline({ items }: { items: TimelineItem[] }) {
  if (items.length === 0) return <p className="text-sm text-ink-500">Nothing has happened yet.</p>;
  return (
    <ol className="relative space-y-4 border-l border-line-100 pl-6">
      {items.map((it) => {
        const Icon = ICON[it.kind];
        return (
          <li key={it.id} className="relative">
            <span className="absolute -left-[31px] top-0.5 flex h-5 w-5 items-center justify-center rounded-full border border-line-100 bg-white text-ink-500">
              <Icon size={11} />
            </span>
            <p className="text-sm font-medium text-ink">
              {it.href ? (
                <Link href={it.href} className="hover:text-primary">
                  {it.title}
                </Link>
              ) : (
                it.title
              )}
            </p>
            {it.detail && <p className="mt-0.5 whitespace-pre-wrap text-sm text-ink-700">{it.detail}</p>}
            <p className="mt-0.5 text-xs text-ink-500">
              {formatDateTime(it.at)} {it.meta && `· ${it.meta}`}
            </p>
          </li>
        );
      })}
    </ol>
  );
}
