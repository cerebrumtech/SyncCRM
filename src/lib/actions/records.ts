"use server";

import { revalidatePath } from "next/cache";
import { z } from "zod";
import { prisma } from "@/lib/db";
import { audit } from "@/lib/audit";
import { assert, canEditRecord } from "@/lib/permissions";
import { MAX_UPLOAD_BYTES, removeUpload, saveUpload } from "@/lib/storage";
import { act, fail, str } from "./_helpers";
import type { EntityType } from "@/generated/prisma/client";

type Parent = { entity: EntityType; id: string };

async function loadParent(orgId: string, parent: Parent) {
  const where = { id: parent.id, orgId };
  const select = { id: true, ownerId: true, orgId: true };
  const rec =
    parent.entity === "CONTACT"
      ? await prisma.contact.findFirst({ where, select })
      : parent.entity === "COMPANY"
        ? await prisma.company.findFirst({ where, select })
        : await prisma.deal.findFirst({ where, select });
  if (!rec) fail("Record not found.");
  return rec;
}

function parentKey(parent: Parent) {
  return parent.entity === "CONTACT" ? { contactId: parent.id } : parent.entity === "COMPANY" ? { companyId: parent.id } : { dealId: parent.id };
}

function parentPath(parent: Parent) {
  return `/${parent.entity === "CONTACT" ? "contacts" : parent.entity === "COMPANY" ? "companies" : "deals"}/${parent.id}`;
}

export async function addNote(parent: Parent, formData: FormData) {
  return act(async (user) => {
    const body = z.string().trim().min(1, "Write something first.").max(5000).parse(str(formData, "body"));
    await loadParent(user.orgId, parent);
    const note = await prisma.note.create({
      data: { orgId: user.orgId, body, authorId: user.id, ...parentKey(parent) },
    });
    await audit({ orgId: user.orgId, actorId: user.id, action: "add_note", entity: parent.entity, entityId: parent.id, after: { noteId: note.id } });
    revalidatePath(parentPath(parent));
  });
}

export async function deleteNote(noteId: string) {
  return act(async (user) => {
    const note = await prisma.note.findFirst({ where: { id: noteId, orgId: user.orgId } });
    if (!note) fail("Note not found.");
    assert(note.authorId === user.id || user.role !== "MEMBER", "You can only delete your own notes.");
    await prisma.note.delete({ where: { id: note.id } });
    const parent: Parent = note.contactId
      ? { entity: "CONTACT", id: note.contactId }
      : note.companyId
        ? { entity: "COMPANY", id: note.companyId }
        : { entity: "DEAL", id: note.dealId! };
    revalidatePath(parentPath(parent));
  });
}

export async function uploadAttachment(parent: Parent, formData: FormData) {
  return act(async (user) => {
    const file = formData.get("file");
    if (!(file instanceof File) || file.size === 0) fail("Choose a file to upload.");
    if (file.size > MAX_UPLOAD_BYTES) fail("Files must be under 15 MB.");
    const rec = await loadParent(user.orgId, parent);
    assert(await canEditRecord(user, parent.entity, rec));
    const saved = await saveUpload(user.orgId, file);
    const att = await prisma.attachment.create({
      data: { orgId: user.orgId, uploadedById: user.id, ...saved, ...parentKey(parent) },
    });
    await audit({ orgId: user.orgId, actorId: user.id, action: "upload_file", entity: parent.entity, entityId: parent.id, after: { file: att.filename } });
    revalidatePath(parentPath(parent));
  });
}

export async function deleteAttachment(attachmentId: string) {
  return act(async (user) => {
    const att = await prisma.attachment.findFirst({ where: { id: attachmentId, orgId: user.orgId } });
    if (!att) fail("File not found.");
    assert(att.uploadedById === user.id || user.role !== "MEMBER", "You can only remove files you uploaded.");
    await prisma.attachment.delete({ where: { id: att.id } });
    await removeUpload(att.storagePath);
    const parent: Parent = att.contactId
      ? { entity: "CONTACT", id: att.contactId }
      : att.companyId
        ? { entity: "COMPANY", id: att.companyId }
        : { entity: "DEAL", id: att.dealId! };
    await audit({ orgId: user.orgId, actorId: user.id, action: "delete_file", entity: parent.entity, entityId: parent.id, before: { file: att.filename } });
    revalidatePath(parentPath(parent));
  });
}

export async function listTags() {
  return act(async (user) => {
    const tags = await prisma.tag.findMany({ where: { orgId: user.orgId }, orderBy: { name: "asc" } });
    return tags.map((t) => t.name);
  });
}
