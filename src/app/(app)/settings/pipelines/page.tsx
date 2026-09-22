import { requireUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { getFieldDefs } from "@/lib/custom-fields";
import { Card, CardHeader } from "@/components/ui/card";
import { NewPipelineForm, PipelineEditor } from "./client";

export const metadata = { title: "Pipelines" };

export default async function PipelinesSettingsPage() {
  const me = await requireUser();
  const [pipelines, dealFields] = await Promise.all([
    prisma.pipeline.findMany({
      where: { orgId: me.orgId },
      orderBy: { position: "asc" },
      include: { stages: { orderBy: { position: "asc" }, include: { _count: { select: { deals: true } } } }, _count: { select: { deals: true } } },
    }),
    getFieldDefs(me.orgId, "DEAL"),
  ]);
  const ruleFields = [
    { key: "amount", label: "Amount" },
    { key: "expectedCloseDate", label: "Expected close date" },
    { key: "contactId", label: "Contact" },
    { key: "companyId", label: "Company" },
    { key: "ownerId", label: "Owner" },
    ...dealFields.map((f) => ({ key: `cf_${f.key}`, label: f.label })),
  ];

  return (
    <div className="grid gap-5 lg:grid-cols-[320px_1fr]">
      <Card className="self-start">
        <CardHeader title="New pipeline" />
        <div className="p-4">
          <NewPipelineForm />
          <p className="mt-3 text-xs text-ink-500">
            Each pipeline has its own stages, e.g. Sales, Onboarding, Renewals or Support. New pipelines start with
            New → In Progress → Won / Lost.
          </p>
        </div>
      </Card>
      <div className="space-y-4">
        {pipelines.map((p) => (
          <PipelineEditor
            key={p.id}
            pipeline={{
              id: p.id,
              name: p.name,
              isDefault: p.isDefault,
              dealCount: p._count.deals,
              stages: p.stages.map((s) => ({
                id: s.id,
                name: s.name,
                probability: s.probability,
                color: s.color,
                kind: s.isWon ? "WON" : s.isLost ? "LOST" : "OPEN",
                requiredFields: s.requiredFields,
                dealCount: s._count.deals,
              })),
            }}
            ruleFields={ruleFields}
            canDelete={pipelines.length > 1}
          />
        ))}
      </div>
    </div>
  );
}
