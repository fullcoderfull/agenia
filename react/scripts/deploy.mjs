// Copie le build statique (react/out) dans <dossier servi par IIS>\app.
// Dossier servi : variable AGENIA_WEB_ROOT, sinon le dossier parent de react\.
import { cpSync, existsSync, rmSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const src = path.join(root, 'out');
const webRoot = path.resolve(process.env.AGENIA_WEB_ROOT || path.join(root, '..'));
const dest = path.join(webRoot, 'app');

if (!existsSync(path.join(src, 'index.html'))) {
  console.error('Build introuvable : lancez "npm run build" (out/index.html absent).');
  process.exit(1);
}
if (!existsSync(webRoot)) {
  console.error(`Dossier servi introuvable : ${webRoot}`);
  process.exit(1);
}

rmSync(dest, { recursive: true, force: true });
cpSync(src, dest, { recursive: true });
console.log(`Publié : ${src} -> ${dest}`);
