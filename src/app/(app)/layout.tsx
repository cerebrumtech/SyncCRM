import { requireUser } from "@/lib/auth";
import { canManageSettings } from "@/lib/permissions";
import { Sidebar } from "@/components/app/sidebar";

export default async function AppLayout({ children }: { children: React.ReactNode }) {
  const user = await requireUser();
  return (
    <div className="flex h-screen overflow-hidden bg-page">
      <Sidebar
        user={{ name: user.name, email: user.email, color: user.color, role: user.role }}
        orgName={user.org.name}
        showSettings={canManageSettings(user)}
      />
      <main className="flex-1 overflow-y-auto">
        <div className="mx-auto max-w-[1800px] px-6 py-6">{children}</div>
      </main>
    </div>
  );
}
