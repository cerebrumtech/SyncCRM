"use client";

import { useEffect, useState } from "react";
import { Checkbox, Field, Input, Select } from "@/components/ui/input";
import type { CustomValues } from "@/lib/custom-fields";

export type FieldDef = {
  key: string;
  label: string;
  type: "TEXT" | "NUMBER" | "DATE" | "SELECT" | "CHECKBOX";
  options: string[];
  required: boolean;
};

export function CustomFieldInputs({ defs, values }: { defs: FieldDef[]; values: CustomValues }) {
  if (defs.length === 0) return null;
  return (
    <div className="grid gap-3 sm:grid-cols-2">
      {defs.map((d) => {
        const id = `cf_${d.key}`;
        const v = values[d.key];
        if (d.type === "CHECKBOX") {
          return (
            <label key={d.key} className="flex items-center gap-2 pt-6 text-sm">
              <Checkbox name={id} defaultChecked={v === true} /> {d.label}
            </label>
          );
        }
        return (
          <Field key={d.key} label={d.label} htmlFor={id} required={d.required}>
            {d.type === "SELECT" ? (
              <Select id={id} name={id} defaultValue={typeof v === "string" ? v : ""} required={d.required}>
                <option value="">—</option>
                {d.options.map((o) => (
                  <option key={o} value={o}>
                    {o}
                  </option>
                ))}
              </Select>
            ) : (
              <Input
                id={id}
                name={id}
                type={d.type === "NUMBER" ? "number" : d.type === "DATE" ? "date" : "text"}
                step={d.type === "NUMBER" ? "any" : undefined}
                defaultValue={v === null || v === undefined ? "" : String(v)}
                required={d.required}
              />
            )}
          </Field>
        );
      })}
    </div>
  );
}

export function TagInput({ name = "tags", defaultValue = [], suggestions = [] }: { name?: string; defaultValue?: string[]; suggestions?: string[] }) {
  const [tags, setTags] = useState<string[]>(defaultValue);
  const [draft, setDraft] = useState("");

  function add(raw: string) {
    const t = raw.trim().replace(/,+$/, "").slice(0, 40);
    if (t && !tags.includes(t)) setTags([...tags, t]);
    setDraft("");
  }

  return (
    <div>
      <input type="hidden" name={name} value={tags.join(",")} />
      <div className="flex min-h-9 flex-wrap items-center gap-1.5 rounded-md border border-line bg-white px-2 py-1 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/25">
        {tags.map((t) => (
          <span key={t} className="inline-flex items-center gap-1 rounded-full bg-info-bg px-2 py-0.5 text-xs font-medium text-info-fg">
            {t}
            <button type="button" className="text-info-fg/70 hover:text-info-fg" onClick={() => setTags(tags.filter((x) => x !== t))} aria-label={`Remove ${t}`}>
              ×
            </button>
          </span>
        ))}
        <input
          list={`${name}-suggestions`}
          className="min-w-24 flex-1 border-0 bg-transparent px-1 text-sm outline-none placeholder:text-ink-500"
          placeholder={tags.length ? "" : "Add tag, press Enter"}
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter" || e.key === ",") {
              e.preventDefault();
              add(draft);
            } else if (e.key === "Backspace" && !draft && tags.length) {
              setTags(tags.slice(0, -1));
            }
          }}
          onBlur={() => draft && add(draft)}
        />
      </div>
      <datalist id={`${name}-suggestions`}>
        {suggestions.filter((s) => !tags.includes(s)).map((s) => (
          <option key={s} value={s} />
        ))}
      </datalist>
    </div>
  );
}

type Option = { id: string; label: string; sub?: string };

// Async single-select with search, submitting the chosen id in a hidden input.
export function RecordPicker({
  name,
  placeholder,
  search,
  initial,
  onChange,
  required,
}: {
  name: string;
  placeholder: string;
  search: (q: string) => Promise<{ ok: true; data?: Option[] } | { ok: false; error: string }>;
  initial?: Option | null;
  onChange?: (opt: Option | null) => void;
  required?: boolean;
}) {
  const [selected, setSelected] = useState<Option | null>(initial ?? null);
  const [q, setQ] = useState("");
  const [results, setResults] = useState<Option[]>([]);
  const [open, setOpen] = useState(false);

  useEffect(() => {
    if (!open) return;
    const t = setTimeout(async () => {
      const res = await search(q);
      if (res.ok) setResults(res.data ?? []);
    }, 150);
    return () => clearTimeout(t);
  }, [q, open, search]);

  if (selected) {
    return (
      <div className="flex h-9 items-center justify-between rounded-md border border-line bg-surface px-3 text-sm">
        <input type="hidden" name={name} value={selected.id} />
        <span className="truncate">
          {selected.label} {selected.sub && <span className="text-ink-500">· {selected.sub}</span>}
        </span>
        <button
          type="button"
          className="text-xs text-ink-500 hover:text-ink"
          onClick={() => {
            setSelected(null);
            onChange?.(null);
          }}
        >
          Change
        </button>
      </div>
    );
  }

  return (
    <div className="relative">
      <input type="hidden" name={name} value="" />
      <Input
        placeholder={placeholder}
        value={q}
        required={required}
        onChange={(e) => setQ(e.target.value)}
        onFocus={() => setOpen(true)}
        onBlur={() => setTimeout(() => setOpen(false), 150)}
        autoComplete="off"
      />
      {open && results.length > 0 && (
        <ul className="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-md border border-line-100 bg-white py-1 shadow-lg">
          {results.map((r) => (
            <li key={r.id}>
              <button
                type="button"
                className="flex w-full items-center justify-between px-3 py-1.5 text-left text-sm hover:bg-surface"
                onMouseDown={(e) => e.preventDefault()}
                onClick={() => {
                  setSelected(r);
                  setOpen(false);
                  onChange?.(r);
                }}
              >
                <span>{r.label}</span>
                {r.sub && <span className="text-xs text-ink-500">{r.sub}</span>}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
