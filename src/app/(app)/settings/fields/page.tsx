import { requireUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { Card, CardHeader } from "@/components/ui/card";
import { FieldList, NewFieldForm } from "./client";

export const metadata = { title: "Custom fields" };

const ENTITIES = [
  { key: "CONTACT", label: "Contacts" },
  { key: "COMPANY", label: "Companies" },
  { key: "DEAL", label: "Deals" },
] as const;

export default async function FieldsPage() {
  const me = await requireUser();
  const defs = await prisma.customFieldDefinition.findMany({ where: { orgId: me.orgId }, orderBy: [{ entity: "asc" }, { position: "asc" }] });
  return (
    <div className="grid gap-5 lg:grid-cols-[340px_1fr]">
      <Card className="self-start">
        <CardHeader title="New custom field" />
        <div className="p-4">
          <NewFieldForm />
          <p className="mt-3 text-xs text-ink-500">
            Custom fields appear on the record form and detail page, and can be required to enter a pipeline stage.
          </p>
        </div>
      </Card>
      <div className="space-y-4">
        {ENTITIES.map((e) => (
          <Card key={e.key}>
            <CardHeader title={e.label} />
            <div className="p-4">
              <FieldList fields={defs.filter((d) => d.entity === e.key).map((d) => ({ id: d.id, key: d.key, label: d.label, type: d.type, options: d.options, required: d.required }))} />
            </div>
          </Card>
        ))}
      </div>
    </div>
  );
}
