import { requireUser } from "@/lib/auth";
import { prisma } from "@/lib/db";
import { formatDateTime } from "@/lib/format";
import { Card, CardHeader } from "@/components/ui/card";
import { Avatar } from "@/components/ui/avatar";
import { Badge } from "@/components/ui/badge";
import { InviteButton, InviteRowActions, UserRowActions } from "./client";

export const metadata = { title: "Users" };

export default async function UsersPage() {
  const me = await requireUser();
  const [users, invites] = await Promise.all([
    prisma.user.findMany({
      where: { orgId: me.orgId },
      orderBy: [{ isActive: "desc" }, { createdAt: "asc" }],
      select: { id: true, name: true, email: true, role: true, isActive: true, color: true, lastLoginAt: true },
    }),
    prisma.invite.findMany({
      where: { orgId: me.orgId, status: "PENDING", expiresAt: { gt: new Date() } },
      orderBy: { createdAt: "desc" },
    }),
  ]);
  const activeUsers = users.filter((u) => u.isActive).map((u) => ({ id: u.id, name: u.name }));

  return (
    <div className="space-y-5">
      <Card>
        <CardHeader title={`Users (${users.length})`} action={<InviteButton canInviteOwner={me.role === "OWNER"} />} />
        <table className="w-full text-sm">
          <thead>
            <tr className="bg-navy text-left text-[13px] font-semibold text-white">
              <th className="px-4 py-2.5">User</th>
              <th className="px-4 py-2.5">Role</th>
              <th className="px-4 py-2.5">Status</th>
              <th className="px-4 py-2.5">Last sign-in</th>
              <th className="px-4 py-2.5 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            {users.map((u, i) => (
              <tr key={u.id} className={i % 2 ? "bg-white" : "bg-page"}>
                <td className="px-4 py-2.5">
                  <div className="flex items-center gap-2.5">
                    <Avatar name={u.name} color={u.color} />
                    <div>
                      <p className="font-medium text-ink">
                        {u.name} {u.id === me.id && <span className="text-xs text-ink-500">(you)</span>}
                      </p>
                      <p className="text-xs text-ink-500">{u.email}</p>
                    </div>
                  </div>
                </td>
                <td className="px-4 py-2.5">
                  <Badge tone={u.role === "OWNER" ? "navy" : u.role === "ADMIN" ? "info" : "neutral"}>
                    {u.role}
                  </Badge>
                </td>
                <td className="px-4 py-2.5">
                  <Badge tone={u.isActive ? "success" : "danger"}>{u.isActive ? "Active" : "Deactivated"}</Badge>
                </td>
                <td className="px-4 py-2.5 text-ink-700">{formatDateTime(u.lastLoginAt)}</td>
                <td className="px-4 py-2.5 text-right">
                  <UserRowActions
                    user={u}
                    isSelf={u.id === me.id}
                    actorIsOwner={me.role === "OWNER"}
                    reassignOptions={activeUsers.filter((a) => a.id !== u.id)}
                  />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      <Card>
        <CardHeader title={`Pending invites (${invites.length})`} />
        {invites.length === 0 ? (
          <p className="px-4 py-6 text-sm text-ink-500">No pending invites.</p>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="bg-navy text-left text-[13px] font-semibold text-white">
                <th className="px-4 py-2.5">Email</th>
                <th className="px-4 py-2.5">Role</th>
                <th className="px-4 py-2.5">Expires</th>
                <th className="px-4 py-2.5 text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {invites.map((inv, i) => (
                <tr key={inv.id} className={i % 2 ? "bg-white" : "bg-page"}>
                  <td className="px-4 py-2.5 font-medium">{inv.email}</td>
                  <td className="px-4 py-2.5">
                    <Badge>{inv.role}</Badge>
                  </td>
                  <td className="px-4 py-2.5 text-ink-700">{formatDateTime(inv.expiresAt)}</td>
                  <td className="px-4 py-2.5 text-right">
                    <InviteRowActions inviteId={inv.id} link={`${process.env.APP_URL ?? ""}/invite/${inv.token}`} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>
    </div>
  );
}
