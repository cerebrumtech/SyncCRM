<?php

namespace App\Controllers;

use App\Libraries\Audit;
use App\Libraries\Permissions;
use App\Libraries\Records as Rec;
use App\Models\AttachmentModel;
use App\Models\NoteModel;
use App\Models\RecordShareModel;
use App\Models\TeamModel;
use App\Models\UserModel;

/** Notes, attachments and sharing for contacts, companies and deals. */
class Records extends BaseController
{
    private function parent(): array
    {
        $entity = strtoupper((string) $this->str('entity'));
        $id = $this->intOrNull('entity_id') ?? $this->fail('Record not found.');
        $record = Rec::load($this->orgId(), $entity, $id);
        return [$entity, $id, $record, Rec::parentColumn($entity)];
    }

    public function addNote()
    {
        return $this->attempt(function () {
            [$entity, $id, , $col] = $this->parent();
            $body = $this->str('body', 5000) ?? $this->fail('Write something first.');
            $noteId = model(NoteModel::class)->insert(['organization_id' => $this->orgId(), 'body' => $body, 'author_id' => $this->me['id'], $col => $id]);
            Audit::log($this->me, 'add_note', $entity, $id, null, null, ['noteId' => $noteId]);
            return redirect()->to(Rec::path($entity, $id) . '#notes')->with('success', 'Note added.');
        });
    }

    public function deleteNote(int $noteId)
    {
        return $this->attempt(function () use ($noteId) {
            $note = model(NoteModel::class)->findInOrg($this->orgId(), $noteId) ?? $this->fail('Note not found.');
            Permissions::assert($note['author_id'] === $this->me['id'] || $this->me['role'] !== 'MEMBER', 'You can only delete your own notes.');
            model(NoteModel::class)->delete($noteId);
            [$entity, $id] = $this->parentOf($note);
            return redirect()->to(Rec::path($entity, $id) . '#notes')->with('success', 'Note deleted.');
        });
    }

    private function parentOf(array $row): array
    {
        if ($row['contact_id']) {
            return ['CONTACT', (int) $row['contact_id']];
        }
        if ($row['company_id']) {
            return ['COMPANY', (int) $row['company_id']];
        }
        return ['DEAL', (int) $row['deal_id']];
    }

    public function upload()
    {
        return $this->attempt(function () {
            [$entity, $id, $record, $col] = $this->parent();
            Permissions::assert(Permissions::canEditRecord($this->me, $entity, $record));
            $file = $this->request->getFile('file');
            if (! $file || ! $file->isValid() || $file->getSize() === 0) {
                $this->fail('Choose a file to upload.');
            }
            if ($file->getSize() > Rec::MAX_UPLOAD) {
                $this->fail('Files must be under 15 MB.');
            }
            $original = $file->getClientName();
            $safe = preg_replace('/[^\w.\-]+/', '_', $original);
            $safe = mb_substr($safe, 0, 120) ?: 'file';
            $rel = $this->orgId() . '/' . bin2hex(random_bytes(8)) . '-' . $safe;
            $dir = Rec::uploadDir() . '/' . $this->orgId();
            $size = $file->getSize();
            $file->move($dir, basename($rel));
            // Read the type from the stored bytes. The browser also sends one, but the
            // uploader controls that, and this value decides how the file is served back.
            $mime = Rec::detectMime($dir . '/' . basename($rel));
            $attId = model(AttachmentModel::class)->insert(['organization_id' => $this->orgId(), 'filename' => $original, 'storage_path' => $rel, 'mime_type' => $mime, 'size' => $size, 'uploaded_by_id' => $this->me['id'], $col => $id]);
            Audit::log($this->me, 'upload_file', $entity, $id, null, null, ['file' => $original, 'attachmentId' => $attId]);
            return redirect()->to(Rec::path($entity, $id) . '#files')->with('success', 'File uploaded.');
        });
    }

    public function deleteAttachment(int $attId)
    {
        return $this->attempt(function () use ($attId) {
            $att = model(AttachmentModel::class)->findInOrg($this->orgId(), $attId) ?? $this->fail('File not found.');
            Permissions::assert($att['uploaded_by_id'] === $this->me['id'] || $this->me['role'] !== 'MEMBER', 'You can only remove files you uploaded.');
            model(AttachmentModel::class)->delete($attId);
            $abs = Rec::uploadDir() . '/' . $att['storage_path'];
            if (is_file($abs)) {
                @unlink($abs);
            }
            [$entity, $id] = $this->parentOf($att);
            Audit::log($this->me, 'delete_file', $entity, $id, null, ['file' => $att['filename']]);
            return redirect()->to(Rec::path($entity, $id) . '#files')->with('success', 'File removed.');
        });
    }

    /** Streams an attachment after checking it belongs to the user's organisation. */
    public function file(int $attId)
    {
        $att = model(AttachmentModel::class)->findInOrg($this->orgId(), $attId);
        if (! $att) {
            return $this->response->setStatusCode(404)->setBody('Not found');
        }
        $abs = realpath(Rec::uploadDir() . '/' . $att['storage_path']);
        $root = realpath(Rec::uploadDir());
        if (! $abs || ! $root || ! str_starts_with($abs, $root . DIRECTORY_SEPARATOR) || ! is_file($abs)) {
            return $this->response->setStatusCode(404)->setBody('File missing');
        }
        // Re-derive the type from the bytes on every request: rows stored before this
        // check existed may hold a type the uploader chose.
        $mime   = Rec::detectMime($abs);
        $inline = Rec::isInlineSafe($mime);
        // Anything not on the allowlist is downloaded as opaque bytes, so a file that
        // happens to be HTML or SVG cannot run script on this origin.
        $served = $inline ? $mime : 'application/octet-stream';
        $name   = str_replace(['"', "\r", "\n"], '', $att['filename']);
        return $this->response->setHeader('Content-Type', $served)
            ->setHeader('Content-Disposition', ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"')
            ->setHeader('Content-Length', (string) filesize($abs))
            ->setHeader('X-Content-Type-Options', 'nosniff')
            // Defence in depth: even if something renders, it gets no origin and no scripts.
            ->setHeader('Content-Security-Policy', "default-src 'none'; img-src 'self'; object-src 'none'; sandbox")
            ->setBody(file_get_contents($abs));
    }

    public function share()
    {
        return $this->attempt(function () {
            [$entity, $id, $record] = $this->parent();
            Permissions::assert(Permissions::canEditRecord($this->me, $entity, $record) && (Permissions::isAdmin($this->me) || (int) $record['owner_id'] === (int) $this->me['id'] || ! $record['owner_id']), 'Only the owner or an admin can share this record.');
            $userId = $this->intOrNull('user_id');
            $teamId = $this->intOrNull('team_id');
            if (! $userId && ! $teamId) {
                $this->fail('Choose a user or a team.');
            }
            if ($userId && ! model(UserModel::class)->findInOrg($this->orgId(), $userId)) {
                $this->fail('User not found.');
            }
            if ($teamId && ! model(TeamModel::class)->findInOrg($this->orgId(), $teamId)) {
                $this->fail('Team not found.');
            }
            $m = model(RecordShareModel::class);
            $m->where('organization_id', $this->orgId())->where('entity', $entity)->where('entity_id', $id)->where($userId ? 'user_id' : 'team_id', $userId ?: $teamId)->delete();
            $m->insert(['organization_id' => $this->orgId(), 'entity' => $entity, 'entity_id' => $id, 'user_id' => $userId ?: null, 'team_id' => $userId ? null : $teamId, 'can_edit' => $this->on('can_edit')]);
            Audit::log($this->me, 'share', $entity, $id, Rec::label($entity, $record), null, ['userId' => $userId, 'teamId' => $teamId, 'canEdit' => $this->on('can_edit')]);
            return redirect()->to(Rec::path($entity, $id))->with('success', 'Sharing updated.');
        }, 'share-dialog');
    }

    public function unshare(int $shareId)
    {
        return $this->attempt(function () use ($shareId) {
            $share = model(RecordShareModel::class)->findInOrg($this->orgId(), $shareId) ?? $this->fail('Share not found.');
            $record = Rec::load($this->orgId(), $share['entity'], (int) $share['entity_id']);
            Permissions::assert(Permissions::isAdmin($this->me) || (int) $record['owner_id'] === (int) $this->me['id'], 'Only the owner or an admin can change sharing.');
            model(RecordShareModel::class)->delete($shareId);
            return redirect()->to(Rec::path($share['entity'], (int) $share['entity_id']))->with('success', 'Share removed.');
        });
    }
}
