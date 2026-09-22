import { redirect } from "next/navigation";
import { requireUser } from "@/lib/auth";
import { canManageSettings } from "@/lib/permissions";
import { PageHeader } from "@/components/ui/card";
import { SettingsTabs } from "./tabs";

export default async function SettingsLayout({ children }: { children: React.ReactNode }) {
  const user = await requireUser();
  if (!canManageSettings(user)) redirect("/dashboard");
  return (
    <>
      <PageHeader title="Settings" subtitle="Workspace configuration for administrators." />
      <SettingsTabs />
      <div className="mt-5">{children}</div>
    </>
  );
}
