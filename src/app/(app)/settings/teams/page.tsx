import { requireUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { Card, CardHeader, EmptyState } from "@/components/ui/card";
import { NewTeamForm, TeamCard } from "./client";

export const metadata = { title: "Teams" };

export default async function TeamsPage() {
  const me = await requireUser();
  const [teams, users] = await Promise.all([
    prisma.team.findMany({
      where: { orgId: me.orgId },
      orderBy: { name: "asc" },
      include: { members: { select: { userId: true } } },
    }),
    prisma.user.findMany({
      where: { orgId: me.orgId, isActive: true },
      orderBy: { name: "asc" },
      select: { id: true, name: true, color: true },
    }),
  ]);

  return (
    <div className="grid gap-5 lg:grid-cols-[320px_1fr]">
      <Card>
        <CardHeader title="New team" />
        <div className="p-4">
          <NewTeamForm />
          <p className="mt-3 text-xs text-ink-500">
            Teams let you share records with a group of people instead of one user at a time.
          </p>
        </div>
      </Card>
      <div className="space-y-4">
        {teams.length === 0 ? (
          <EmptyState title="No teams yet" description="Create a team on the left, then add members." />
        ) : (
          teams.map((t) => (
            <TeamCard key={t.id} team={{ id: t.id, name: t.name, memberIds: t.members.map((m) => m.userId) }} users={users} />
          ))
        )}
      </div>
    </div>
  );
}
