'use client';

import { useState } from 'react';

// Rend le contenu d'un message : blocs de code (avec copie + langage) et
// prose en markdown léger (gras, code en ligne, listes, liens, retours ligne).
// Repris de l'esprit de renderMsg() de l'ancienne version, en sûr (pas d'HTML brut du modèle).

type Seg = { type: 'code'; lang: string; code: string } | { type: 'text'; text: string };

function parse(content: string): Seg[] {
  const segs: Seg[] = [];
  const re = /```([a-zA-Z0-9+#.-]*)\n?([\s\S]*?)```/g;
  let last = 0;
  let m: RegExpExecArray | null;
  while ((m = re.exec(content)) !== null) {
    if (m.index > last) segs.push({ type: 'text', text: content.slice(last, m.index) });
    segs.push({ type: 'code', lang: m[1] ?? '', code: (m[2] ?? '').replace(/\n$/, '') });
    last = re.lastIndex;
  }
  if (last < content.length) segs.push({ type: 'text', text: content.slice(last) });
  return segs.length ? segs : [{ type: 'text', text: content }];
}

function escapeHtml(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// Markdown en ligne minimal et sûr (entrées déjà échappées).
function inline(s: string): string {
  return escapeHtml(s)
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/(^|[^*])\*([^*]+)\*/g, '$1<em>$2</em>')
    .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
}

// \u0001 / \u0002 : début / fin du passage en cours de lecture (surlignage).
const HL_OPEN = '<mark class="tts-hl">';
const HL_CLOSE = '</mark>';

function Prose({ text }: { text: string }) {
  const lines = text.split('\n');
  const html: string[] = [];
  let inList = false;
  let hlOpen = false;
  for (const raw of lines) {
    const line = raw.trimEnd();
    const startsOpen = hlOpen;
    for (const ch of line) {
      if (ch === '\u0001') hlOpen = true;
      else if (ch === '\u0002') hlOpen = false;
    }
    const visible = line.replace(/[\u0001\u0002]/g, '');
    const li = line.match(/^\s*[-*]\s+(.*)$/);
    const wrap = (h: string) =>
      (startsOpen ? HL_OPEN : '') + h.replace(/\u0001/g, HL_OPEN).replace(/\u0002/g, HL_CLOSE) + (hlOpen ? HL_CLOSE : '');
    if (li && visible.trim()) {
      if (!inList) {
        html.push('<ul>');
        inList = true;
      }
      html.push(`<li>${wrap(inline(li[1] ?? ''))}</li>`);
    } else {
      if (inList) {
        html.push('</ul>');
        inList = false;
      }
      if (visible.trim()) html.push(`<p>${wrap(inline(line))}</p>`);
    }
  }
  if (inList) html.push('</ul>');
  return <div className="prose" dangerouslySetInnerHTML={{ __html: html.join('') }} />;
}

function CodeBlock({ lang, code, copyLabel, copiedLabel }: { lang: string; code: string; copyLabel: string; copiedLabel: string }) {
  const [copied, setCopied] = useState(false);
  async function copy() {
    try {
      await navigator.clipboard.writeText(code.replace(/[\u0001\u0002]/g, ''));
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      /* presse-papiers indisponible */
    }
  }
  return (
    <div className="codeblock">
      <div className="codeblock-head">
        <span className="codeblock-lang">{lang || 'code'}</span>
        <button type="button" className="codeblock-copy" onClick={copy}>
          {copied ? copiedLabel : copyLabel}
        </button>
      </div>
      <pre>
        <code dangerouslySetInnerHTML={{ __html: escapeHtml(code.replace(/[\u0001\u0002]/g, '')) }} />
      </pre>
    </div>
  );
}

export function MessageContent({
  content,
  copyLabel,
  copiedLabel,
  highlight,
}: {
  content: string;
  copyLabel: string;
  copiedLabel: string;
  highlight?: { start: number; end: number };
}) {
  let src = content;
  if (highlight && highlight.end > highlight.start && highlight.end <= content.length) {
    src = content.slice(0, highlight.start) + '\u0001' + content.slice(highlight.start, highlight.end) + '\u0002' + content.slice(highlight.end);
  }
  const segs = parse(src);
  return (
    <>
      {segs.map((seg, i) =>
        seg.type === 'code' ? (
          <CodeBlock key={i} lang={seg.lang} code={seg.code} copyLabel={copyLabel} copiedLabel={copiedLabel} />
        ) : (
          <Prose key={i} text={seg.text} />
        ),
      )}
    </>
  );
}
