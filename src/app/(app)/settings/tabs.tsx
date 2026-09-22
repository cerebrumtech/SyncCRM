"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/cn";

const TABS = [
  { href: "/settings/users", label: "Users" },
  { href: "/settings/teams", label: "Teams" },
  { href: "/settings/pipelines", label: "Pipelines" },
  { href: "/settings/fields", label: "Custom fields" },
  { href: "/settings/import", label: "Import CSV" },
  { href: "/settings/organization", label: "Organisation" },
  { href: "/settings/audit", label: "Audit log" },
];

export function SettingsTabs() {
  const pathname = usePathname();
  return (
    <nav className="flex gap-1 border-b border-line-100">
      {TABS.map((t) => {
        const active = pathname.startsWith(t.href);
        return (
          <Link
            key={t.href}
            href={t.href}
            className={cn(
              "-mb-px border-b-2 px-3 py-2 text-sm font-medium",
              active ? "border-primary text-primary" : "border-transparent text-ink-700 hover:text-ink",
            )}
          >
            {t.label}
          </Link>
        );
      })}
    </nav>
  );
}
