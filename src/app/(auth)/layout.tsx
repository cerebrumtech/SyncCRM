import Image from "next/image";

export default function AuthLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex min-h-screen flex-col bg-page">
      <div className="flex flex-1 items-center justify-center px-4 py-10">
        <div className="w-full max-w-md">
          <div className="mb-6 flex justify-center">
            <Image
              src="/brand/logo-horizontal.svg"
              alt="SyncWorks Technologies Pvt. Ltd."
              width={200}
              height={112}
              preload
            />
          </div>
          <div className="rounded-[10px] border border-line-100 bg-white p-8 shadow-sm">{children}</div>
        </div>
      </div>
      <footer className="border-t border-line-100 bg-surface px-4 py-3 text-center text-xs text-ink-500">
        SyncCRM · SyncWorks Technologies Pvt. Ltd. · Trust · Clarity · Compliance
      </footer>
    </div>
  );
}
