import { requireUser } from "@/lib/auth";
import { dedupeRules } from "@/lib/dedupe";
import { Card, CardHeader } from "@/components/ui/card";
import { DedupeForm, OrgForm } from "./client";

export const metadata = { title: "Organisation" };

export default async function OrganizationPage() {
  const me = await requireUser();
  const rules = await dedupeRules(me.orgId);
  return (
    <div className="grid gap-5 lg:grid-cols-2">
      <Card>
        <CardHeader title="Organisation" />
        <div className="p-4">
          <OrgForm name={me.org.name} timezone={me.org.timezone} currency={me.org.currency} />
        </div>
      </Card>
      <Card>
        <CardHeader title="Duplicate prevention" />
        <div className="p-4">
          <DedupeForm rules={rules} />
        </div>
      </Card>
    </div>
  );
}
