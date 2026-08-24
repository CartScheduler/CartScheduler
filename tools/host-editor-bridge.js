#!/usr/bin/env node
// host-editor-bridge.js
//
// Run this on your HOST machine (not inside the container). It listens for
// "open this file" requests coming from `launch-editor` running inside your
// Docker container, and spawns PhpStorm locally to open the file.
//
// Config (script args only, e.g. --port=3334 --container-root=/app):
//   --port            -> default 3334
//   --host-root       -> default: CWD
//   --container-root  -> required, no default

import http from 'node:http';
import { exec } from 'node:child_process';

function parseArgs(argv) {
  const out = {};
  for (let i = 0; i < argv.length; i++) {
    const arg = argv[i];
    if (!arg.startsWith('--')) continue;
    const eq = arg.indexOf('=');
    if (eq !== -1) {
      out[arg.slice(2, eq)] = arg.slice(eq + 1);
    } else {
      const key = arg.slice(2);
      const next = argv[i + 1];
      if (next && !next.startsWith('--')) {
        out[key] = next;
        i++;
      } else {
        out[key] = 'true';
      }
    }
  }
  return out;
}

const args = parseArgs(process.argv.slice(2));

const PORT = parseInt(args.port || 3334, 10);
const HOST_ROOT = args['host-root'] || process.cwd();
const CONTAINER_ROOT = args['container-root'];

if (!CONTAINER_ROOT) {
  console.error('Missing required flag: --container-root=/path/inside/container');
  process.exit(1);
}

console.log('Editor bridge config:');
console.log(`  PORT           = ${PORT}`);
console.log(`  CONTAINER_ROOT = ${CONTAINER_ROOT}`);
console.log(`  HOST_ROOT      = ${HOST_ROOT}`);

const server = http.createServer((req, res) => {
  const url = new URL(req.url, 'http://localhost');
  const file = url.searchParams.get('file'); // container-side filename
  const line = url.searchParams.get('line') || '1';
  const column = url.searchParams.get('column') || '1';
  // column isn't used by the `phpstorm` CLI launcher, but is accepted
  // here in case you swap in an editor that supports it.

  if (!file) {
    res.writeHead(400);
    return res.end('missing ?file=');
  }

  const hostFile = file.startsWith(CONTAINER_ROOT)
    ? HOST_ROOT + file.slice(CONTAINER_ROOT.length)
    : file;

  console.log(`Opening ${hostFile} at line ${line}:${column}`);
  
  exec(`phpstorm --line ${line} --column ${column} "${hostFile}"`, (err) => {
    if (err) console.error('Failed to launch editor:', err.message);
  });

  res.end('ok');
});

server.on('error', (err) => {
  if (err.code === 'EADDRINUSE') {
    // Already running from a previous `npm run dev` — nothing to do.
    console.log(`Editor bridge already running on port ${PORT}, skipping.`);
    process.exit(0);
  }
  throw err;
});

server.listen(PORT, () => {
  console.log(`Editor bridge listening on http://localhost:${PORT}`);
});
