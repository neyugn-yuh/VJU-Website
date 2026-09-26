#!/usr/bin/env node
// Cutover URL crawl (Phase 08/09): every legacy URL must answer 200, or one 301/302 to a 200 (or 410 if retired).
//
//   node tools/check-urls.mjs --inventory=storage/app/private/migration/url-inventory.csv --base=https://staging.vju.ac.vn [--concurrency=8]
//
// Legacy URLs from the inventory are replayed against --base (path + query kept). Writes url-check-report.csv.
import { readFileSync, writeFileSync } from 'node:fs';

const args = Object.fromEntries(process.argv.slice(2).map((a) => a.replace(/^--/, '').split('=')));
if (!args.inventory || !args.base) {
    console.error('Usage: node tools/check-urls.mjs --inventory=<url-inventory.csv> --base=<https://new-site> [--concurrency=8]');
    process.exit(2);
}

const base = args.base.replace(/\/$/, '');
const concurrency = Number(args.concurrency ?? 8);

function parseCsv(text) {
    const rows = [];
    let row = [], field = '', quoted = false;
    for (let i = 0; i < text.length; i++) {
        const c = text[i];
        if (quoted) {
            if (c === '"' && text[i + 1] === '"') { field += '"'; i++; }
            else if (c === '"') quoted = false;
            else field += c;
        } else if (c === '"') quoted = true;
        else if (c === ',') { row.push(field); field = ''; }
        else if (c === '\n') { row.push(field); rows.push(row); row = []; field = ''; }
        else if (c !== '\r') field += c;
    }
    if (field || row.length) { row.push(field); rows.push(row); }
    return rows;
}

const [header, ...rows] = parseCsv(readFileSync(args.inventory, 'utf8').replace(/^﻿/, ''));
const urlIndex = header.indexOf('url');
const urls = [...new Set(rows.map((r) => r[urlIndex]).filter(Boolean))];

async function check(legacy) {
    const u = new URL(legacy);
    const target = base + u.pathname + u.search;
    try {
        const first = await fetch(target, { redirect: 'manual' });
        if (first.status === 200 || first.status === 410) return { legacy, status: first.status, final: target, ok: true, hops: 0 };
        if ([301, 302, 308].includes(first.status)) {
            const location = new URL(first.headers.get('location'), target).toString();
            const second = await fetch(location, { redirect: 'manual' });
            const ok = second.status === 200;
            return { legacy, status: `${first.status}>${second.status}`, final: location, ok, hops: 1, note: ok ? '' : 'redirect chain or redirect to non-200' };
        }
        return { legacy, status: first.status, final: target, ok: false, hops: 0 };
    } catch (e) {
        return { legacy, status: 'ERR', final: target, ok: false, hops: 0, note: String(e.message ?? e) };
    }
}

const results = [];
let next = 0;
await Promise.all(Array.from({ length: concurrency }, async () => {
    while (next < urls.length) {
        const url = urls[next++];
        results.push(await check(url));
        if (results.length % 100 === 0) console.log(`${results.length}/${urls.length}`);
    }
}));

const failed = results.filter((r) => !r.ok);
const esc = (v) => `"${String(v ?? '').replaceAll('"', '""')}"`;
writeFileSync('url-check-report.csv', ['legacy_url,status,final_url,ok,note', ...results.map((r) => [r.legacy, r.status, r.final, r.ok, r.note].map(esc).join(','))].join('\n'));

console.log(`checked ${results.length}: ${results.length - failed.length} ok, ${failed.length} failed → url-check-report.csv`);
process.exit(failed.length ? 1 : 0);
