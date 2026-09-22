import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import { SetupForm } from "./setup-form";

export const metadata = { title: "Set up workspace" };

export default async function SetupPage() {
  const orgCount = await prisma.organization.count();
  if (orgCount > 0) redirect("/login");
  return (
    <>
      <h1 className="text-xl font-bold text-navy">Set up your workspace</h1>
      <p className="mt-1 text-sm text-ink-700">
        Create the organisation and the first owner account. You can invite teammates afterwards.
      </p>
      <div className="mt-6">
        <SetupForm />
      </div>
    </>
  );
}
