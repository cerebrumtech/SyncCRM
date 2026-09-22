import { Card, CardHeader } from "@/components/ui/card";
import { ImportWizard } from "./client";

export const metadata = { title: "Import CSV" };

export default function ImportPage() {
  return (
    <Card>
      <CardHeader title="Import contacts or companies from CSV" />
      <div className="p-4">
        <ImportWizard />
      </div>
    </Card>
  );
}
