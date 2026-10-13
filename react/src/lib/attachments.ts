// Pièces jointes — règles reprises du bloc "upload" de config/settings.json
// (acceptedTypes, maxSizeMb, maxTokens). Tout est lu dans le navigateur :
//   - image        → envoyée au modèle (vision) en base64 ;
//   - texte / .md  → contenu ajouté au message ;
//   - Word (.docx) → texte extrait du document, ajouté au message.
// PDF : accepté par settings.json mais pas encore lu ici (nécessite une
// librairie de lecture PDF) — l'utilisateur en est informé.

import type { UploadCfg } from './apiClient';

export type Attachment =
  | { kind: 'image'; name: string; dataUrl: string }
  | { kind: 'doc'; name: string; text: string; truncated: boolean };

export type ReadError = 'type' | 'size' | 'pdf' | 'read' | 'empty';

const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

// Type réel du fichier (les navigateurs laissent souvent .md / .docx sans type).
function mimeOf(file: File): string {
  if (file.type) return file.type;
  const ext = file.name.toLowerCase().split('.').pop() ?? '';
  if (ext === 'md' || ext === 'markdown') return 'text/markdown';
  if (ext === 'txt') return 'text/plain';
  if (ext === 'docx') return DOCX;
  if (ext === 'pdf') return 'application/pdf';
  return '';
}

function accepted(mime: string, types: string[]): boolean {
  return types.some((t) => (t.endsWith('/*') ? mime.startsWith(t.slice(0, -1)) : mime === t));
}

// Attribut accept de l'input fichier, dérivé de settings.json.
export function acceptAttr(cfg: UploadCfg): string {
  const extra: string[] = [];
  if (cfg.acceptedTypes.includes('text/markdown')) extra.push('.md');
  if (cfg.acceptedTypes.includes(DOCX)) extra.push('.docx');
  return [...cfg.acceptedTypes, ...extra].join(',');
}

function readDataUrl(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const fr = new FileReader();
    fr.onload = () => resolve(typeof fr.result === 'string' ? fr.result : '');
    fr.onerror = () => reject(new Error('read'));
    fr.readAsDataURL(file);
  });
}

// ── Word (.docx) : un .docx est une archive zip ; le texte est dans word/document.xml ──

async function inflateRaw(data: Uint8Array): Promise<Uint8Array> {
  const stream = new Blob([new Uint8Array(data)]).stream().pipeThrough(new DecompressionStream('deflate-raw'));
  return new Uint8Array(await new Response(stream).arrayBuffer());
}

async function zipEntry(buf: ArrayBuffer, wanted: string): Promise<Uint8Array | null> {
  const v = new DataView(buf);
  const u8 = new Uint8Array(buf);
  // fin du répertoire central
  let eocd = -1;
  for (let i = buf.byteLength - 22; i >= Math.max(0, buf.byteLength - 65557); i--) {
    if (v.getUint32(i, true) === 0x06054b50) {
      eocd = i;
      break;
    }
  }
  if (eocd < 0) return null;
  const count = v.getUint16(eocd + 10, true);
  let p = v.getUint32(eocd + 16, true);
  const dec = new TextDecoder();
  for (let n = 0; n < count; n++) {
    if (v.getUint32(p, true) !== 0x02014b50) return null;
    const method = v.getUint16(p + 10, true);
    const csize = v.getUint32(p + 20, true);
    const nlen = v.getUint16(p + 28, true);
    const elen = v.getUint16(p + 30, true);
    const clen = v.getUint16(p + 32, true);
    const local = v.getUint32(p + 42, true);
    const name = dec.decode(u8.subarray(p + 46, p + 46 + nlen));
    if (name === wanted) {
      const lnlen = v.getUint16(local + 26, true);
      const lelen = v.getUint16(local + 28, true);
      const start = local + 30 + lnlen + lelen;
      const data = u8.subarray(start, start + csize);
      if (method === 0) return data;
      if (method === 8) return inflateRaw(data);
      return null;
    }
    p += 46 + nlen + elen + clen;
  }
  return null;
}

function decodeEntities(s: string): string {
  return s
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&apos;/g, "'")
    .replace(/&#x([0-9a-f]+);/gi, (_, h: string) => String.fromCodePoint(parseInt(h, 16)))
    .replace(/&#(\d+);/g, (_, d: string) => String.fromCodePoint(Number(d)))
    .replace(/&amp;/g, '&');
}

export function docxXmlToText(xml: string): string {
  return decodeEntities(
    xml
      .replace(/<w:tab\/>/g, '\t')
      .replace(/<w:br[^>]*\/>/g, '\n')
      .replace(/<\/w:p>/g, '\n')
      .replace(/<[^>]+>/g, ''),
  )
    .replace(/\n{3,}/g, '\n\n')
    .trim();
}

export async function docxToText(buf: ArrayBuffer): Promise<string | null> {
  const xml = await zipEntry(buf, 'word/document.xml');
  return xml ? docxXmlToText(new TextDecoder('utf-8').decode(xml)) : null;
}

// ── Lecture d'un fichier selon les règles de settings.json ──

export async function readAttachment(file: File, cfg: UploadCfg): Promise<Attachment | ReadError> {
  const mime = mimeOf(file);
  if (!accepted(mime, cfg.acceptedTypes)) return 'type';
  if (file.size > cfg.maxSizeMb * 1024 * 1024) return 'size';

  try {
    if (mime.startsWith('image/')) {
      const dataUrl = await readDataUrl(file);
      return dataUrl ? { kind: 'image', name: file.name, dataUrl } : 'read';
    }
    if (mime === 'application/pdf') return 'pdf';

    let text: string | null;
    if (mime === DOCX) text = await docxToText(await file.arrayBuffer());
    else text = await file.text();
    if (text === null) return 'read';
    text = text.trim();
    if (!text) return 'empty';

    // Budget maxTokens de settings.json (≈ 4 caractères par token).
    const maxChars = cfg.maxTokens * 4;
    const truncated = text.length > maxChars;
    return { kind: 'doc', name: file.name, text: truncated ? text.slice(0, maxChars) : text, truncated };
  } catch {
    return 'read';
  }
}

// Contenu envoyé au modèle : message + documents joints.
export function withDocs(text: string, docs: { name: string; text: string; truncated: boolean }[], truncLabel: string): string {
  if (!docs.length) return text;
  const parts = docs.map((d) => `[Pièce jointe : ${d.name}${d.truncated ? ` — ${truncLabel}` : ''}]\n${d.text}`);
  return [text, ...parts].filter(Boolean).join('\n\n');
}
