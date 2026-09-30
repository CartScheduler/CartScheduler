#!/bin/bash
#
# host-port-relay.sh — put each allowed host port on the container's loopback.
#
# Runs from postStartCommand as `node`, after configure-firewall.sh has installed
# the rules. This is a convenience and never a boundary: the firewall decides what
# may be reached, and all this changes is the address you reach it at. A port with
# no rule still gets a relay, and connections through it are still rejected.
#
# Why relay at all, when the firewall has already allowed the port? Because the
# address that works is a fact about the container runtime — 192.168.65.254 on
# Docker Desktop, the bridge gateway elsewhere — and some servers care which name
# you arrived by. Chrome's remote debugging port is the awkward case: it serves the
# DevTools endpoint only when the Host header is an IP literal or `localhost`, so
# http://host.docker.internal:9222 is refused outright even though the packet lands.
# Relaying onto loopback makes the Host header `localhost:<port>`, which Chrome
# accepts and which every CDP client already defaults to.
#
# Note that a relay takes the port over *inside* the container: nothing else here
# can bind it while the relay holds it. Ports already in use are left alone.
#
# Every failure below is logged and skipped rather than raised. postStartCommand is
# what `waitFor` waits on, so a non-zero exit here would fail container start — and
# no relay is worth that. This script exits 0 unconditionally.

set -uo pipefail

WHITELIST="${FIREWALL_WHITELIST_FILE:-/etc/firewall/firewall-whitelist-domains.json}"
TARGET_HOST="${HOST_RELAY_TARGET:-host.docker.internal}"

log() { echo "[relay] $*"; }

# Resolution is checked lazily, on the first port that actually needs it: doing it
# up front would mean a host that cannot be named suppresses the diagnostics for
# every other reason a port might be skipped.
TARGET_STATE="unknown"
target_resolves() {
    case "$TARGET_STATE" in
        yes) return 0 ;;
        no)  return 1 ;;
    esac
    if getent hosts "$TARGET_HOST" >/dev/null 2>&1; then
        TARGET_STATE="yes"
        return 0
    fi
    TARGET_STATE="no"
    return 1
}

port_is_taken() { (exec 3<>"/dev/tcp/127.0.0.1/$1") 2>/dev/null; }

command -v socat >/dev/null 2>&1 \
    || { log "socat is not installed; no relays started"; exit 0; }

[ -f "$WHITELIST" ] \
    || { log "no whitelist at $WHITELIST; no relays started"; exit 0; }

if ! ports=$(jq -r 'if type == "array" then [] else (.host_ports // []) end | .[]' \
                 "$WHITELIST" 2>/dev/null); then
    log "could not read host_ports from $WHITELIST; no relays started"
    exit 0
fi

started=0
while read -r port; do
    [ -n "$port" ] || continue

    if ! [[ "$port" =~ ^[0-9]{1,5}$ ]] || [ "$port" -lt 1 ] || [ "$port" -gt 65535 ]; then
        log "skipping invalid port: $port"
        continue
    fi

    # Binding below 1024 needs root, and the sudoers rule here covers
    # configure-firewall.sh alone. Those ports are still allowed by the firewall —
    # reach them at $TARGET_HOST:$port directly, and mind the Host header.
    if [ "$port" -lt 1024 ]; then
        log "skipping tcp/$port: privileged ports cannot be bound as $(id -un)"
        continue
    fi

    # Either a relay from an earlier start, or something in the container that owns
    # this port and has a better claim to it than we do.
    if port_is_taken "$port"; then
        log "tcp/$port already has a listener; leaving it alone"
        continue
    fi

    if ! target_resolves; then
        log "$TARGET_HOST does not resolve; cannot relay tcp/$port"
        continue
    fi

    nohup socat "TCP-LISTEN:${port},fork,reuseaddr,bind=127.0.0.1" \
                "TCP:${TARGET_HOST}:${port}" >/dev/null 2>&1 &

    # Report what happened rather than what was attempted: `cmd &` succeeds whether
    # or not socat lives past the fork, so ask the port instead.
    sleep 0.3
    if port_is_taken "$port"; then
        log "localhost:${port} -> ${TARGET_HOST}:${port}"
        started=$((started + 1))
    else
        log "relay for tcp/${port} did not come up"
    fi
done <<< "$ports"

[ "$started" -eq 0 ] || log "$started relay(s) listening on loopback"
exit 0
