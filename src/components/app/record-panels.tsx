"use client";

import { useRef, useState, useTransition } from "react";
import { FileText, Paperclip, Trash2, Download } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/input";
import { FormError } from "@/components/ui/form";
import { Avatar } from "@/components/ui/avatar";
import { addNote, deleteNote, deleteAttachment, uploadAttachment } from "@/lib/actions/records";
import { formatDateTime } from "@/lib/format";
import type { EntityType } from "@/generated/prisma/client";

type Parent = { entity: EntityType; id: string };

export type NoteItem = { id: string; body: string; createdAt: string; author: { id: string; name: string; color: string } };

export function NotesPanel({ parent, notes, meId, isAdmin }: { parent: Parent; notes: NoteItem[]; meId: string; isAdmin: boolean }) {
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  const formRef = useRef<HTMLFormElement>(null);

  return (
    <div className="space-y-4">
      <form
        ref={formRef}
        action={(fd) =>
          start(async () => {
            const res = await addNote(parent, fd);
            if (!res.ok) setError(res.error);
            else {
              setError(undefined);
              formRef.current?.reset();
            }
          })
        }
        className="space-y-2"
      >
        <FormError message={error} />
        <Textarea name="body" placeholder="Add a note… (visible to your team only)" required />
        <div className="flex justify-end">
          <Button type="submit" size="sm" disabled={pending}>
            {pending ? "Adding…" : "Add note"}
          </Button>
        </div>
      </form>
      {notes.length === 0 ? (
        <p className="text-sm text-ink-500">No notes yet.</p>
      ) : (
        <ul className="space-y-3">
          {notes.map((n) => (
            <li key={n.id} className="rounded-md border border-line-100 bg-page p-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2 text-xs text-ink-500">
                  <Avatar name={n.author.name} color={n.author.color} size={20} />
                  <span className="font-medium text-ink-700">{n.author.name}</span>
                  <span>· {formatDateTime(n.createdAt)}</span>
                </div>
                {(n.author.id === meId || isAdmin) && (
                  <button
                    type="button"
                    className="text-ink-500 hover:text-danger"
                    title="Delete note"
                    onClick={() => {
                      if (confirm("Delete this note?")) start(async () => void (await deleteNote(n.id)));
                    }}
                  >
                    <Trash2 size={14} />
                  </button>
                )}
              </div>
              <p className="mt-2 whitespace-pre-wrap text-sm text-ink">{n.body}</p>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

export type FileItem = { id: string; filename: string; mimeType: string; size: number; createdAt: string; uploadedBy: { id: string; name: string } };

function fmtSize(n: number) {
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(0)} KB`;
  return `${(n / 1024 / 1024).toFixed(1)} MB`;
}

export function FilesPanel({ parent, files, meId, isAdmin }: { parent: Parent; files: FileItem[]; meId: string; isAdmin: boolean }) {
  const [error, setError] = useState<string>();
  const [pending, start] = useTransition();
  const inputRef = useRef<HTMLInputElement>(null);

  return (
    <div className="space-y-4">
      <form
        action={(fd) =>
          start(async () => {
            const res = await uploadAttachment(parent, fd);
            setError(res.ok ? undefined : res.error);
            if (inputRef.current) inputRef.current.value = "";
          })
        }
        className="flex flex-wrap items-center gap-2"
      >
        <input ref={inputRef} type="file" name="file" required className="text-sm file:mr-3 file:rounded-md file:border file:border-line file:bg-white file:px-3 file:py-1.5 file:text-sm file:font-medium hover:file:bg-surface" />
        <Button type="submit" size="sm" variant="secondary" disabled={pending}>
          <Paperclip size={14} /> {pending ? "Uploading…" : "Upload"}
        </Button>
        <span className="text-xs text-ink-500">Max 15 MB</span>
      </form>
      <FormError message={error} />
      {files.length === 0 ? (
        <p className="text-sm text-ink-500">No files attached.</p>
      ) : (
        <ul className="divide-y divide-line-100 rounded-md border border-line-100">
          {files.map((f) => (
            <li key={f.id} className="flex items-center gap-3 px-3 py-2">
              <FileText size={18} className="shrink-0 text-ink-500" />
              <div className="min-w-0 flex-1">
                <a href={`/api/files/${f.id}`} target="_blank" className="block truncate text-sm font-medium text-primary hover:underline">
                  {f.filename}
                </a>
                <p className="text-xs text-ink-500">
                  {fmtSize(f.size)} · {f.uploadedBy.name} · {formatDateTime(f.createdAt)}
                </p>
              </div>
              <a href={`/api/files/${f.id}`} download className="text-ink-500 hover:text-ink" title="Download">
                <Download size={15} />
              </a>
              {(f.uploadedBy.id === meId || isAdmin) && (
                <button
                  type="button"
                  className="text-ink-500 hover:text-danger"
                  title="Delete file"
                  onClick={() => {
                    if (confirm(`Delete ${f.filename}?`)) start(async () => void (await deleteAttachment(f.id)));
                  }}
                >
                  <Trash2 size={15} />
                </button>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
