import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";
import { LoginForm } from "./login-form";

export const metadata = { title: "Sign in" };

export default async function LoginPage(props: PageProps<"/login">) {
  const user = await getCurrentUser();
  if (user) redirect("/dashboard");
  const orgCount = await prisma.organization.count();
  if (orgCount === 0) redirect("/setup");
  const { next } = await props.searchParams;
  return (
    <>
      <h1 className="text-xl font-bold text-navy">Sign in to SyncCRM</h1>
      <p className="mt-1 text-sm text-ink-700">Use your work email and password.</p>
      <div className="mt-6">
        <LoginForm next={typeof next === "string" ? next : undefined} />
      </div>
    </>
  );
}
