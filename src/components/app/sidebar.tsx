"use client";

import Link from "next/link";
import Image from "next/image";
import { usePathname } from "next/navigation";
import {
  LayoutDashboard,
  Users,
  Building2,
  KanbanSquare,
  Package,
  CalendarCheck,
  Settings,
  LogOut,
} from "lucide-react";
import { cn } from "@/lib/cn";
import { Avatar } from "@/components/ui/avatar";
import { logout } from "@/lib/actions/auth";

const NAV = [
  { href: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
  { href: "/deals", label: "Deals", icon: KanbanSquare },
  { href: "/contacts", label: "Contacts", icon: Users },
  { href: "/companies", label: "Companies", icon: Building2 },
  { href: "/activities", label: "Activities", icon: CalendarCheck },
  { href: "/products", label: "Products", icon: Package },
];

export function Sidebar({
  user,
  orgName,
  showSettings,
}: {
  user: { name: string; email: string; color: string; role: string };
  orgName: string;
  showSettings: boolean;
}) {
  const pathname = usePathname();
  const items = showSettings ? [...NAV, { href: "/settings", label: "Settings", icon: Settings }] : NAV;

  return (
    <aside className="flex h-full w-60 shrink-0 flex-col bg-navy text-white">
      <div className="flex h-16 items-center gap-2 border-b border-white/10 px-4">
        <div className="flex h-9 w-9 items-center justify-center rounded-md bg-white">
          <Image src="/brand/logo-horizontal.svg" alt="SyncWorks" width={30} height={17} />
        </div>
        <div className="min-w-0">
          <p className="truncate text-sm font-semibold leading-tight">SyncCRM</p>
          <p className="truncate text-[11px] text-white/60">{orgName}</p>
        </div>
      </div>
      <nav className="flex-1 space-y-0.5 px-2 py-3">
        {items.map((item) => {
          const active = pathname === item.href || pathname.startsWith(item.href + "/");
          return (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                "flex items-center gap-2.5 rounded-md px-3 py-2 text-[13px] font-medium text-white/75 transition hover:bg-white/10 hover:text-white",
                active && "bg-primary text-white hover:bg-primary",
              )}
            >
              <item.icon size={17} />
              {item.label}
            </Link>
          );
        })}
      </nav>
      <div className="border-t border-white/10 p-3">
        <div className="flex items-center gap-2.5">
          <Avatar name={user.name} color={user.color} size={32} />
          <div className="min-w-0 flex-1">
            <p className="truncate text-[13px] font-medium">{user.name}</p>
            <p className="truncate text-[11px] capitalize text-white/60">{user.role.toLowerCase()}</p>
          </div>
          <form action={logout}>
            <button
              type="submit"
              className="rounded p-1.5 text-white/60 hover:bg-white/10 hover:text-white"
              title="Sign out"
            >
              <LogOut size={16} />
            </button>
          </form>
        </div>
      </div>
    </aside>
  );
}
