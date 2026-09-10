import { chromium } from 'playwright';
import { access, mkdir, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const pageUrl = 'https://www.budget.gouv.fr/documentation/documents-budgetaires/exercice-2024/plrg-2024';
const repository = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const output = resolve(repository, process.env.RAP_CATALOG ?? 'data/processed/rap/2024/catalog.json');
const shouldDownload = process.argv.includes('--download');
const selectedProgram = process.env.RAP_PROGRAM ?? null;
const entries = new Map();
const unresolved = [];
const downloaded = [];
const downloadFailed = [];
const browser = await chromium.launch({ headless: process.env.HEADFUL !== '1' });
const page = await browser.newPage({ locale: 'fr-FR', userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/131 Safari/537.36' });

try {
  for (let number = 0; number < 50; number += 1) {
    const url = `${pageUrl}?docuement_dossier%5B0%5D=typologie%3A115&page=${number}`;
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 120000 });
    await page.waitForLoadState('networkidle', { timeout: 5000 }).catch(() => {});
    const found = await page.locator('a').evaluateAll((anchors) => anchors.map((anchor) => {
      const label = anchor.textContent?.replace(/\s+/gu, ' ').trim() ?? '';
      if (! /t[ée]l[ée]charger.*\bpdf\b/iu.test(label)) return null;
      let node = anchor;
      let rapContext = null;
      for (let i = 0; i < 7 && node; i += 1, node = node.parentElement) {
        const context = node.textContent?.replace(/([\p{L}])(\d)/gu, '$1 $2').replace(/(\d)([\p{L}])/gu, '$1 $2').replace(/\s+/gu, ' ').trim() ?? '';
        const match = context.match(/(?:^|\s)(\d{3})\s*[-–—]\s*(.+?)(?=\s+Télécharger|$)/iu);
        if (/\bRAP\b/iu.test(context)) {
          rapContext = context;
          if (match) return { program: match[1], name: match[2].trim(), url: new URL(anchor.href, location.href).href };
        }
      }
      return rapContext ? { unresolved: true, name: rapContext, url: new URL(anchor.href, location.href).href } : null;
    })).then((values) => values.filter(Boolean));
    found.filter((entry) => !entry.unresolved).forEach((entry) => entries.set(entry.program, { ...entry, page: number }));
    unresolved.push(...found.filter((entry) => entry.unresolved).map((entry) => ({ ...entry, page: number })));
    console.log(`page=${number + 1} entries=${found.length} programmes=${entries.size} unresolved=${unresolved.length}`);
    if (found.length < 10 && number > 0) console.log((await page.locator('body').innerText()).slice(-8000));
    if (number > 0 && found.length === 0) break;
  }
  if (shouldDownload) {
    const raw = resolve(repository, 'data/raw/rap/2024');
    await mkdir(raw, { recursive: true });
    const pending = [...entries.values(), ...unresolved.map((entry, index) => ({ ...entry, filename: `unresolved-${String(index + 1).padStart(3, '0')}` }))].filter((entry) => selectedProgram === null || entry.program === selectedProgram);
    for (let offset = 0; offset < pending.length; offset += 8) {
      await Promise.all(pending.slice(offset, offset + 8).map(async (entry) => {
        const path = resolve(raw, `${entry.filename ?? `P${entry.program}`}.pdf`);
        try {
          await access(path);
          downloaded.push(entry.program ?? entry.filename);
          return;
        } catch {}
        try {
          const response = await page.request.get(entry.url, { timeout: 60000 });
          const contentType = response.headers()['content-type'] ?? '';
          if (!response.ok() || !contentType.toLowerCase().startsWith('application/pdf')) throw new Error(`HTTP ${response.status()} / ${contentType}`);
          await writeFile(path, await response.body());
          downloaded.push(entry.program ?? entry.filename);
        } catch (error) {
          downloadFailed.push({ program: entry.program ?? null, filename: entry.filename ?? null, url: entry.url, error: String(error) });
        }
      }));
      console.log(`downloads=${Math.min(offset + 8, pending.length)}/${pending.length}`);
    }
  }
} finally {
  await browser.close();
}

if (entries.size === 0 && unresolved.length === 0) throw new Error('Aucune entrée RAP détectée par le navigateur.');
await mkdir(dirname(output), { recursive: true });
await writeFile(output, JSON.stringify({ source: pageUrl, discovered: entries.size + unresolved.length, programmes: entries.size, unresolved, entries: [...entries.values()], downloaded: downloaded.length, download_failed: downloadFailed }, null, 2));
console.log(`catalog=${output} discovered=${entries.size + unresolved.length} programmes=${entries.size} unresolved=${unresolved.length} downloaded=${downloaded.length} failed=${downloadFailed.length}`);
