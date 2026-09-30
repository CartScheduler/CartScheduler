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
//   --bind            -> default 127.0.0.1
//
// The bridge runs commands on your machine on behalf of whoever can reach the
// port, so it treats every request as hostile: arguments are passed as an argv
// array rather than a shell string, the line/column are parsed as integers, the
// file has to resolve inside --host-root, and requests carrying an Origin or
// Referer (i.e. sent by a web page you happened to visit) are refused.
//
// --bind defaults to loopback. Docker Desktop reaches that through
// host.docker.internal, but on a Linux host, host-gateway lands on the bridge
// address instead, so there you need --bind=0.0.0.0 — which exposes the port to
// your local network. Prefer binding to the docker0 address if you know it.

import http from 'node:http';
import path from 'node:path';
import { execFile } from 'node:child_process';
import { pathToFileURL } from 'node:url';

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

/**
 * A line/column is a positive integer or it is nothing. Anything else is
 * someone trying their luck, so it falls back to 1 rather than being passed on.
 */
function toPosition(value) {
  const parsed = Number.parseInt(value ?? '', 10);
  return String(Number.isFinite(parsed) && parsed > 0 ? parsed : 1);
}

/**
 * Translate a container-side path to its host equivalent, then confine it to
 * hostRoot. Returns null if it escapes — `path.join` has already collapsed any
 * `..` by then, so the prefix test sees the real destination.
 */
export function resolveHostFile(file, { hostRoot, containerRoot }) {
  const mapped = file.startsWith(containerRoot)
    ? path.join(hostRoot, file.slice(containerRoot.length))
    : file;

  const resolved = path.resolve(mapped);
  const root = path.resolve(hostRoot);

  return resolved === root || resolved.startsWith(root + path.sep) ? resolved : null;
}

export function createRequestHandler({ hostRoot, containerRoot, launch = execFile }) {
  return (req, res) => {
    // A page in the developer's browser can be made to issue a GET at this
    // port. It cannot read the reply, but the side effect alone is the problem,
    // so anything arriving with a web origin attached is turned away.
    if (req.headers.origin || req.headers.referer) {
      res.writeHead(403);
      return res.end('cross-origin requests are not accepted');
    }

    if (req.method !== 'GET') {
      res.writeHead(405);
      return res.end('method not allowed');
    }

    const url = new URL(req.url, 'http://localhost');
    const file = url.searchParams.get('file'); // container-side filename
    const line = toPosition(url.searchParams.get('line'));
    const column = toPosition(url.searchParams.get('column'));
    // column isn't used by the `phpstorm` CLI launcher, but is accepted
    // here in case you swap in an editor that supports it.

    if (!file) {
      res.writeHead(400);
      return res.end('missing ?file=');
    }

    const hostFile = resolveHostFile(file, { hostRoot, containerRoot });

    if (hostFile === null) {
      console.error(`Refusing to open ${file}: outside ${hostRoot}`);
      res.writeHead(403);
      return res.end('outside host root');
    }

    console.log(`Opening ${hostFile} at line ${line}:${column}`);

    // execFile, not exec: no shell parses these, so the arguments cannot break
    // out into commands of their own.
    launch('phpstorm', ['--line', line, '--column', column, hostFile], (err) => {
      if (err) console.error('Failed to launch editor:', err.message);
    });

    res.end('ok');
  };
}

export function startBridge({ port, bind, hostRoot, containerRoot }) {
  const server = http.createServer(createRequestHandler({ hostRoot, containerRoot }));

  server.on('error', (err) => {
    if (err.code === 'EADDRINUSE') {
      // Already running from a previous `npm run dev` — nothing to do.
      console.log(`Editor bridge already running on port ${port}, skipping.`);
      process.exit(0);
    }
    throw err;
  });

  server.listen(port, bind, () => {
    console.log(`Editor bridge listening on http://${bind}:${port}`);
  });

  return server;
}

/** Only take over the process when run as a script, so tests can import it. */
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  const args = parseArgs(process.argv.slice(2));

  const PORT = Number.parseInt(args.port ?? '3334', 10);
  const BIND = args.bind || '127.0.0.1';
  const HOST_ROOT = args['host-root'] || process.cwd();
  const CONTAINER_ROOT = args['container-root'];

  if (!CONTAINER_ROOT) {
    console.error('Missing required flag: --container-root=/path/inside/container');
    process.exit(1);
  }

  console.log('Editor bridge config:');
  console.log(`  PORT           = ${PORT}`);
  console.log(`  BIND           = ${BIND}`);
  console.log(`  CONTAINER_ROOT = ${CONTAINER_ROOT}`);
  console.log(`  HOST_ROOT      = ${HOST_ROOT}`);

  startBridge({ port: PORT, bind: BIND, hostRoot: HOST_ROOT, containerRoot: CONTAINER_ROOT });
}
