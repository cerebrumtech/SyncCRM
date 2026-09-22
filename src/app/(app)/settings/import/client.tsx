"use client";

import { useState, useTransition } from "react";
import { Button } from "@/components/ui/button";
import { Field, Select } from "@/components/ui/input";
import { FormError, FormSuccess } from "@/components/ui/form";
import { parseCsv } from "@/lib/csv";
import { importTargets, runImport, type ImportEntity, type ImportSummary } from "@/lib/actions/import";

type Target = { key: string; label: string };

// Suggest a target for a header by normalised name.
function guess(header: string, targets: Target[]) {
  const h = header.toLowerCase().replace(/[^a-z]/g, "");
  const aliases: Record<string, string[]> = {
    firstName: ["firstname", "first", "givenname", "name"],
    lastName: ["lastname", "last", "surname"],
    email: ["email", "emailaddress", "mail"],
    phone: ["phone", "mobile", "phonenumber", "mobilenumber", "contactnumber"],
    whatsappNumber: ["whatsapp", "whatsappnumber"],
    jobTitle: ["jobtitle", "title", "designation", "role"],
    companyName: ["company", "companyname", "organisation", "organization", "society"],
    ownerEmail: ["owner", "owneremail", "assignedto"],
    tags: ["tags", "tag", "labels"],
    name: ["name", "companyname", "company"],
    industry: ["industry", "type", "segment"],
    website: ["website", "url", "web"],
    addressLine: ["address", "addressline", "street"],
    city: ["city", "town"],
    state: ["state"],
    postalCode: ["pin", "pincode", "postalcode", "zip"],
    country: ["country"],
    description: ["description", "notes"],
  };
  for (const t of targets) {
    if (aliases[t.key]?.includes(h)) return t.key;
    if (t.key.startsWith("cf_") && t.label.toLowerCase().replace(/[^a-z]/g, "").startsWith(h) && h.length > 2) return t.key;
  }
  return "";
}

export function ImportWizard() {
  const [entity, setEntity] = useState<ImportEntity>("CONTACT");
  const [rows, setRows] = useState<string[][]>([]);
  const [headers, setHeaders] = useState<string[]>([]);
  const [targets, setTargets] = useState<Target[]>([]);
  const [mapping, setMapping] = useState<Record<string, string>>({});
  const [onDuplicate, setOnDuplicate] = useState<"skip" | "update" | "create">("skip");
  const [error, setError] = useState<string>();
  const [result, setResult] = useState<ImportSummary | null>(null);
  const [pending, start] = useTransition();

  async function onFile(file: File | undefined) {
    setError(undefined);
    setResult(null);
    if (!file) return;
    const text = await file.text();
    const parsed = parseCsv(text);
    if (parsed.length < 2) return setError("The file needs a header row and at least one data row.");
    const [head, ...body] = parsed;
    const res = await importTargets(entity);
    if (!res.ok) return setError(res.error);
    const t = res.data ?? [];
    setTargets(t);
    setHeaders(head!);
    setRows(body);
    const m: Record<string, string> = {};
    head!.forEach((h, i) => {
      const g = guess(h, t);
      if (g && !Object.values(m).includes(g)) m[String(i)] = g;
    });
    setMapping(m);
  }

  const mapped = Object.values(mapping).filter(Boolean);

  return (
    <div className="space-y-5">
      <div className="grid gap-3 sm:grid-cols-[200px_1fr]">
        <Field label="Record type" htmlFor="imp-entity">
          <Select id="imp-entity" value={entity} onChange={(e) => { setEntity(e.target.value as ImportEntity); setRows([]); setHeaders([]); }}>
            <option value="CONTACT">Contacts</option>
            <option value="COMPANY">Companies</option>
          </Select>
        </Field>
        <Field label="CSV file" htmlFor="imp-file" hint="First row must be column headers. Up to 5,000 rows.">
          <input id="imp-file" type="file" accept=".csv,text/csv" onChange={(e) => onFile(e.target.files?.[0])} className="block text-sm file:mr-3 file:rounded-md file:border file:border-line file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-surface" />
        </Field>
      </div>
      <FormError message={error} />

      {headers.length > 0 && !result && (
        <>
          <div>
            <h3 className="mb-2 text-base font-semibold">Map columns</h3>
            <p className="mb-3 text-sm text-ink-700">{rows.length} rows found. Choose which CRM field each column goes to; unmapped columns are ignored.</p>
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-navy text-left text-[13px] font-semibold text-white">
                  <th className="px-3 py-2">CSV column</th>
                  <th className="px-3 py-2">Sample</th>
                  <th className="px-3 py-2">Import as</th>
                </tr>
              </thead>
              <tbody>
                {headers.map((h, i) => (
                  <tr key={i} className={i % 2 ? "bg-white" : "bg-page"}>
                    <td className="px-3 py-1.5 font-medium">{h}</td>
                    <td className="max-w-xs truncate px-3 py-1.5 text-ink-500">{rows.slice(0, 3).map((r) => r[i]).filter(Boolean).join(" · ")}</td>
                    <td className="px-3 py-1.5">
                      <Select className="h-8 w-64" value={mapping[String(i)] ?? ""} onChange={(e) => setMapping({ ...mapping, [String(i)]: e.target.value })}>
                        <option value="">— Skip —</option>
                        {targets.map((t) => (
                          <option key={t.key} value={t.key} disabled={mapped.includes(t.key) && mapping[String(i)] !== t.key}>
                            {t.label}
                          </option>
                        ))}
                      </Select>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="flex flex-wrap items-end gap-3">
            <Field label="If a matching record exists" htmlFor="imp-dup" hint={entity === "CONTACT" ? "Matched by email or phone" : "Matched by company name"}>
              <Select id="imp-dup" className="w-72" value={onDuplicate} onChange={(e) => setOnDuplicate(e.target.value as typeof onDuplicate)}>
                <option value="skip">Skip the row</option>
                <option value="update">Update the existing record (fill blanks, add tags)</option>
                <option value="create">Create a duplicate anyway</option>
              </Select>
            </Field>
            <Button
              disabled={pending || mapped.length === 0}
              onClick={() =>
                start(async () => {
                  setError(undefined);
                  const res = await runImport(entity, rows, mapping, { onDuplicate });
                  if (!res.ok) return setError(res.error);
                  setResult(res.data!);
                })
              }
            >
              {pending ? `Importing ${rows.length} rows…` : `Import ${rows.length} rows`}
            </Button>
          </div>
        </>
      )}

      {result && (
        <div className="space-y-3">
          <FormSuccess message={`Import finished: ${result.created} created, ${result.updated} updated, ${result.skipped} skipped, ${result.errors.length} errors.`} />
          {result.errors.length > 0 && (
            <ul className="max-h-48 space-y-0.5 overflow-auto rounded-md border border-line-100 bg-page p-3 text-xs text-danger-fg">
              {result.errors.map((e, i) => (
                <li key={i}>{e}</li>
              ))}
            </ul>
          )}
          <Button variant="secondary" onClick={() => { setResult(null); setRows([]); setHeaders([]); }}>
            Import another file
          </Button>
        </div>
      )}
    </div>
  );
}
