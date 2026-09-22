import { prisma } from "@/lib/db";
import { Button } from "@/components/ui/button";
import { InviteForm } from "./invite-form";

export const metadata = { title: "Accept invite" };

export default async function InvitePage(props: PageProps<"/invite/[token]">) {
  const { token } = await props.params;
  const invite = await prisma.invite.findUnique({
    where: { token },
    include: { org: { select: { name: true } }, invitedBy: { select: { name: true } } },
  });
  const valid = invite && invite.status === "PENDING" && invite.expiresAt > new Date();

  if (!valid) {
    return (
      <>
        <h1 className="text-xl font-bold text-navy">Invite not available</h1>
        <p className="mt-2 text-sm text-ink-700">
          This invite link is invalid, has expired, or was already used. Ask an administrator to send a new one.
        </p>
        <Button href="/login" variant="secondary" className="mt-6">
          Go to sign in
        </Button>
      </>
    );
  }

  return (
    <>
      <h1 className="text-xl font-bold text-navy">Join {invite.org.name}</h1>
      <p className="mt-1 text-sm text-ink-700">
        {invite.invitedBy.name} invited <strong>{invite.email}</strong> as {invite.role.toLowerCase()}. Set a
        password to finish.
      </p>
      <div className="mt-6">
        <InviteForm token={token} />
      </div>
    </>
  );
}
