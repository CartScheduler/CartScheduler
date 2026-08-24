#!/bin/bash
set -euo pipefail  # Exit on error, undefined vars, and pipeline failures
IFS=$'\n\t'       # Stricter word splitting

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# sudo resets the environment by default (env_reset), so FIREWALL_WHITELIST_FILE
# set via devcontainer.json's containerEnv does NOT survive
# `sudo configure-firewall.sh`. Never rely on it being present: search known
# locations instead, and treat the env var as an override when it does arrive.
WHITELIST_CANDIDATES=(
    "/etc/firewall/firewall-whitelist-domains.json"
    "${SCRIPT_DIR}/firewall-whitelist-domains.json"
)

WHITELIST_FILE="${FIREWALL_WHITELIST_FILE:-}"
if [ -z "$WHITELIST_FILE" ]; then
    for _candidate in "${WHITELIST_CANDIDATES[@]}"; do
        if [ -f "$_candidate" ]; then
            WHITELIST_FILE="$_candidate"
            break
        fi
    done
    # Nothing found: fall through with the preferred path so the error names it.
    WHITELIST_FILE="${WHITELIST_FILE:-${WHITELIST_CANDIDATES[0]}}"
fi

IPSET_NAME="${FIREWALL_IPSET_NAME:-allowed-domains}"
IPSET_TMP="${IPSET_NAME}-tmp"

# Every A record we allow is also pinned into /etc/hosts, inside the block these
# markers delimit. See write_host_pins for why.
#
# Deliberately not overridable by an environment variable, unlike the paths above:
# `node` can reach this script through sudo, and a settable destination for a
# root-owned write is a way out of the sandbox rather than a convenience.
HOSTS_FILE="/etc/hosts"
HOSTS_BEGIN="# BEGIN firewall pins (configure-firewall.sh) - do not edit"
HOSTS_END="# END firewall pins (configure-firewall.sh)"
HOST_PINS=""

# api.github.com allows 60 unauthenticated requests an hour per source IP, and CI
# runners share their egress address with the rest of the platform, so this call
# fails intermittently through no fault of the container. Drop a pre-fetched copy
# of the response at $META_FALLBACK — authenticated, from somewhere with its own
# rate limit — and it is used when the live fetch cannot be had. Both are
# allow-list sources, so both are refused over sudo along with --file.
META_URL="${FIREWALL_GITHUB_META_URL:-https://api.github.com/meta}"
META_FALLBACK="${FIREWALL_GITHUB_META_FALLBACK:-/etc/firewall/github-meta-fallback.json}"

MODE="init"
STRICT_DNS="${FIREWALL_STRICT_DNS:-0}"
FLUSH_CONNTRACK=0
WHITELIST_OVERRIDDEN=0

usage() {
    cat >&2 <<EOF
Usage: $(basename "$0") [MODE] [OPTIONS]

Modes:
  --init              Full setup: iptables rules + build the allow-list (default)
  --reload            Rebuild the allow-list only, leaving iptables rules untouched
  --list              Print the currently active allow-list and exit
  --status            Report whether the firewall is enforcing.
                      Exit 0 enforcing, 3 sealed (closed, no allow-list), 1 open.

Options:
  --file <path>       Whitelist JSON to use, overriding auto-detection.
  --strict            Fail (and, on reload, keep the old list) if any domain
                      cannot be resolved. Default is to warn and skip.
  --flush-conntrack   On reload, drop existing connection tracking entries so
                      that connections to removed domains are cut immediately.

Whitelist resolution order:
  1. --file <path>
  2. \$FIREWALL_WHITELIST_FILE  (note: sudo strips this unless env_keep is set)
  3. /etc/firewall/firewall-whitelist-domains.json
  4. <script dir>/firewall-whitelist-domains.json

Environment:
  FIREWALL_IPSET_NAME       ipset name (default: allowed-domains)
  FIREWALL_STRICT_DNS=1     Same as --strict
EOF
}

while [ $# -gt 0 ]; do
    case "$1" in
        --init)             MODE="init" ;;
        --reload)           MODE="reload" ;;
        --list)             MODE="list" ;;
        --status)           MODE="status" ;;
        --strict)           STRICT_DNS=1 ;;
        --file)             WHITELIST_FILE="${2:?--file requires a path}"; WHITELIST_OVERRIDDEN=1; shift ;;
        --flush-conntrack)  FLUSH_CONNTRACK=1 ;;
        -h|--help)          usage; exit 0 ;;
        *) echo "ERROR: unknown argument: $1" >&2; usage; exit 2 ;;
    esac
    shift
done

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------
log() { echo "[firewall] $*"; }
# For anything logged from a function whose stdout is captured by a command
# substitution — on stdout it would be swallowed into the caller's variable
# rather than shown.
warn() { echo "[firewall] $*" >&2; }
die() { echo "[firewall] ERROR: $*" >&2; exit 1; }

# True when we were invoked through sudo by an unprivileged user — i.e. by `node`
# via the narrow sudoers rule, rather than by root proper.
via_sudo_from_user() { [ -n "${SUDO_UID:-}" ] && [ "${SUDO_UID}" != "0" ]; }

# Choosing the allow-list is the one thing that widens egress rather than
# narrowing it, and `node` is allowed to run this script as root. Honouring
# --file there would hand a compromised agent the whole sandbox: write a
# whitelist naming any host, re-init, and the firewall authorises it. Refuse
# before anything is touched, so a rejected call leaves the running firewall
# exactly as it was.
#
# Root proper is unaffected: firewall-ctl.sh drives this with `docker exec -u 0`,
# which sets no SUDO_UID, and only falls back to sudo when the docker CLI is
# unreachable — a path that never passes --file.
if via_sudo_from_user; then
    [ "$WHITELIST_OVERRIDDEN" -eq 0 ] \
        || die "--file is not permitted via sudo: it would allow choosing an arbitrary allow-list"
    [ -z "${FIREWALL_WHITELIST_FILE:-}" ] \
        || die "FIREWALL_WHITELIST_FILE is not honoured via sudo"
    [ -z "${FIREWALL_GITHUB_META_URL:-}" ] \
        || die "FIREWALL_GITHUB_META_URL is not honoured via sudo"
    [ -z "${FIREWALL_GITHUB_META_FALLBACK:-}" ] \
        || die "FIREWALL_GITHUB_META_FALLBACK is not honoured via sudo"
fi

require_root() {
    [ "$(id -u)" -eq 0 ] || die "must be run as root (try: sudo $0 ...)"
}

# The kernel has an IPv6 stack at all. Link-local only still counts: what matters
# is whether ip6tables has anything to enforce against.
ipv6_present() { [ -f /proc/net/if_inet6 ]; }

# Deny IPv6 outright.
#
# The allow-list is IPv4-only by construction — add_github_ranges explicitly drops
# GitHub's IPv6 ranges, and add_domains only ever reads A records — so there is no
# such thing as an allowed IPv6 destination. Every rule installed here is an
# iptables rule, which IPv6 traffic never touches. On a host where Docker has IPv6
# enabled or the network is dual-stack, that means the entire allow-list can be
# walked around by resolving AAAA instead of A.
deny_ipv6() {
    if ! command -v ip6tables >/dev/null 2>&1; then
        ipv6_present \
            && die "ip6tables is missing but this container has an IPv6 stack; refusing to leave IPv6 unfiltered" \
            || { log "No ip6tables and no IPv6 stack: nothing to restrict"; return 0; }
    fi

    # Policy first, so a failure part-way through still leaves IPv6 closed.
    if ! ip6tables -P OUTPUT DROP 2>/dev/null; then
        ipv6_present \
            && die "failed to set an IPv6 default-deny policy while an IPv6 stack is present" \
            || { log "IPv6 stack unavailable: skipping ip6tables"; return 0; }
    fi
    ip6tables -P INPUT DROP
    ip6tables -P FORWARD DROP
    ip6tables -F
    ip6tables -X 2>/dev/null || true
    ip6tables -A INPUT -i lo -j ACCEPT
    ip6tables -A OUTPUT -o lo -j ACCEPT
    log "IPv6: default-deny installed (the allow-list is IPv4-only)"
}

# Any failure between creating the temp set and swapping it in leaves the live
# set completely untouched, so a bad config can never open or close the firewall
# half-way.
cleanup_tmp() { ipset destroy "$IPSET_TMP" 2>/dev/null || true; }

# Pin every allowed name to the exact addresses that were allow-listed for it.
#
# The allow-list is a snapshot of DNS taken at build time, which quietly assumes
# that a name resolves to the same thing later. Behind a CDN it does not:
# repo.packagist.org hands out a different single A record per query, from a pool
# spread across unrelated networks, so `composer install` was reaching an address
# that was never in the ipset and getting rejected — intermittently, which is the
# worst way to get it. Widening the allow-list to cover the pool means allow-listing
# most of a CDN provider.
#
# Pinning inverts the problem. Nothing in the container can dial an address that
# was not allow-listed, because it can no longer learn one: glibc answers from
# /etc/hosts and never asks DNS for these names. All records are pinned, not just
# the first, so a client can still fail over between allowed addresses.
#
# Two consequences worth knowing:
#   - A pinned address that goes unhealthy stays broken until the next reload.
#     That is the same staleness the ipset already has, now visible in one more
#     place.
#   - Only A records are written, so AAAA for a pinned name resolves to nothing.
#     That suits a firewall whose allow-list is IPv4-only by construction.
#
# Written in place with `cat`, never `mv`: Docker bind-mounts /etc/hosts, so
# replacing the file fails with EBUSY.
write_host_pins() {
    local pins="$1" preserved tmp="${HOSTS_FILE}.firewall-tmp"

    preserved=$(awk -v b="$HOSTS_BEGIN" -v e="$HOSTS_END" '
        $0 == b { skip = 1 }
        !skip   { print }
        $0 == e { skip = 0 }
    ' "$HOSTS_FILE") || { log "WARNING: could not read $HOSTS_FILE, skipping pins"; return 0; }

    {
        [ -z "$preserved" ] || printf '%s\n' "$preserved"
        if [ -n "$pins" ]; then
            printf '%s\n' "$HOSTS_BEGIN"
            printf '%s\n' "$pins"
            printf '%s\n' "$HOSTS_END"
        fi
    } > "$tmp" || { rm -f "$tmp"; log "WARNING: could not stage $HOSTS_FILE, skipping pins"; return 0; }

    if cat "$tmp" > "$HOSTS_FILE" 2>/dev/null; then
        [ -z "$pins" ] || log "Pinned $(printf '%s\n' "$pins" | wc -l | tr -d ' ') address(es) in $HOSTS_FILE"
    else
        log "WARNING: could not write $HOSTS_FILE; allowed names stay subject to DNS rotation"
    fi
    rm -f "$tmp"
}

# Sealed means nothing may leave, so leaving pins behind would only mislead
# whoever reads the file next.
clear_host_pins() { write_host_pins "" || true; }

# Leave the container with no way out except loopback.
#
# `--init` flushes the existing rules long before it is in a position to install
# the default-deny policy, so every failure in between — an unreachable GitHub
# meta endpoint, an unresolvable domain under --strict, a malformed whitelist —
# used to leave the container with no firewall at all. An agent running in that
# window has unrestricted egress and no way to tell. Sealing is the safe end
# state: wrong, but wrong in the direction that cannot leak.
seal_firewall() {
    # Tolerated deliberately: if stdout has gone away (see the SIGPIPE trap in
    # do_init) a failed log write must not stop us closing the firewall.
    log "Sealing container: default-deny policy, loopback only" || true
    iptables -F 2>/dev/null || true
    iptables -X 2>/dev/null || true
    iptables -P INPUT DROP 2>/dev/null || true
    iptables -P FORWARD DROP 2>/dev/null || true
    iptables -P OUTPUT DROP 2>/dev/null || true
    iptables -A INPUT -i lo -j ACCEPT 2>/dev/null || true
    iptables -A OUTPUT -o lo -j ACCEPT 2>/dev/null || true
    if command -v ip6tables >/dev/null 2>&1; then
        ip6tables -P INPUT DROP 2>/dev/null || true
        ip6tables -P FORWARD DROP 2>/dev/null || true
        ip6tables -P OUTPUT DROP 2>/dev/null || true
        ip6tables -F 2>/dev/null || true
    fi
    ipset destroy "$IPSET_NAME" 2>/dev/null || true
    ipset destroy "$IPSET_TMP" 2>/dev/null || true
    clear_host_pins
}

validate_whitelist() {
    [ -f "$WHITELIST_FILE" ] || die "whitelist file not found: $WHITELIST_FILE"
    jq -e . "$WHITELIST_FILE" >/dev/null 2>&1 \
        || die "whitelist file is not valid JSON: $WHITELIST_FILE"
    log "Using whitelist: $WHITELIST_FILE"
}

# Accepts either a bare array of domains, or an object with a "domains" key.
read_domains() {
    jq -r 'if type == "array" then . else (.domains // []) end | .[]' "$WHITELIST_FILE"
}

read_cidrs() {
    jq -r 'if type == "array" then [] else (.cidrs // []) end | .[]' "$WHITELIST_FILE"
}

github_enabled() {
    local enabled
    enabled=$(jq -r 'if type == "array" then true else (.github_meta.enabled // false) end' "$WHITELIST_FILE")
    [ "$enabled" = "true" ]
}

read_github_sections() {
    jq -r 'if type == "array" then ["web","api","git"] else (.github_meta.sections // ["web","api","git"]) end | .[]' "$WHITELIST_FILE"
}

is_ipv4_cidr() { [[ "$1" =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/[0-9]{1,2}$ ]]; }
is_ipv4()      { [[ "$1" =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]]; }
is_hostname()  { [[ "$1" =~ ^[A-Za-z0-9]([A-Za-z0-9._-]*[A-Za-z0-9])?$ ]]; }

# ---------------------------------------------------------------------------
# Building the allow-list
# ---------------------------------------------------------------------------
# Transient failures and a spent rate limit look identical from here, so retry a
# few times and then say which one it looked like — "missing section: web" on its
# own reads like a broken whitelist and sends you looking in the wrong place.
fetch_github_meta() {
    local attempt=1 max=3 delay=2 response status body

    while :; do
        status="none"
        if response=$(curl -sS --connect-timeout 10 --max-time 30 \
                           -w $'\n%{http_code}' "$META_URL" 2>/dev/null); then
            status="${response##*$'\n'}"
            body="${response%$'\n'*}"
            if [ "$status" = "200" ] && [ -n "$body" ]; then
                printf '%s' "$body"
                return 0
            fi
        fi

        warn "WARNING: GitHub meta fetch failed (attempt ${attempt}/${max}, HTTP ${status})"
        case "$status" in
            403|429) warn "WARNING: that status is almost always the api.github.com rate limit, which is per source IP" ;;
        esac

        [ "$attempt" -lt "$max" ] || return 1
        attempt=$((attempt + 1))
        sleep "$delay"
        delay=$((delay * 3))
    done
}

load_meta_fallback() {
    [ -f "$META_FALLBACK" ] || return 1
    jq -e . "$META_FALLBACK" >/dev/null 2>&1 \
        || die "GitHub meta fallback is not valid JSON: $META_FALLBACK"
    cat "$META_FALLBACK"
}

add_github_ranges() {
    local set_name="$1"
    local gh_ranges sections=() section cidrs all_cidrs="" cidr

    log "Fetching GitHub IP ranges from $META_URL..."
    if ! gh_ranges=$(fetch_github_meta); then
        gh_ranges=$(load_meta_fallback) \
            || die "failed to fetch GitHub IP ranges, and no usable fallback at $META_FALLBACK"
        log "WARNING: using pre-fetched GitHub ranges from $META_FALLBACK; they may be stale"
    fi
    [ -n "$gh_ranges" ] || die "failed to fetch GitHub IP ranges"

    mapfile -t sections < <(read_github_sections)
    [ "${#sections[@]}" -gt 0 ] || die "github_meta.sections is empty"

    for section in "${sections[@]}"; do
        cidrs=$(echo "$gh_ranges" | jq -r --arg s "$section" '.[$s] // empty | .[]') \
            || die "GitHub meta response missing section: $section"
        [ -n "$cidrs" ] || die "GitHub meta response missing or empty section: $section"
        all_cidrs+="${cidrs}"$'\n'
    done

    # GitHub publishes IPv6 ranges too; this ruleset is IPv4-only, so drop them.
    all_cidrs=$(printf '%s' "$all_cidrs" | grep -E '^[0-9]+\.' || true)
    [ -n "$all_cidrs" ] || die "no IPv4 ranges found in GitHub meta response"

    if command -v aggregate >/dev/null 2>&1; then
        all_cidrs=$(printf '%s\n' "$all_cidrs" | aggregate -q)
    fi

    log "Processing GitHub IPs..."
    while read -r cidr; do
        [ -n "$cidr" ] || continue
        is_ipv4_cidr "$cidr" || die "invalid CIDR range from GitHub meta: $cidr"
        ipset add "$set_name" "$cidr" -exist
    done < <(printf '%s\n' "$all_cidrs")
}

add_static_cidrs() {
    local set_name="$1" cidr
    while read -r cidr; do
        [ -n "$cidr" ] || continue
        is_ipv4_cidr "$cidr" || is_ipv4 "$cidr" \
            || die "invalid CIDR in $WHITELIST_FILE: $cidr"
        log "Adding static range $cidr"
        ipset add "$set_name" "$cidr" -exist
    done < <(read_cidrs)
}

add_domains() {
    local set_name="$1" domain ips ip count=0 failed=0

    HOST_PINS=""
    while read -r domain; do
        [ -n "$domain" ] || continue
        is_hostname "$domain" || die "invalid domain in $WHITELIST_FILE: $domain"

        log "Resolving $domain..."
        ips=$(dig +noall +answer A "$domain" | awk '$4 == "A" {print $5}')
        if [ -z "$ips" ]; then
            if [ "$STRICT_DNS" = "1" ]; then
                die "failed to resolve $domain (strict mode)"
            fi
            log "WARNING: Failed to resolve $domain, skipping"
            failed=$((failed + 1))
            continue
        fi

        while read -r ip; do
            [ -n "$ip" ] || continue
            is_ipv4 "$ip" || die "invalid IP from DNS for $domain: $ip"
            log "Adding $ip for $domain"
            ipset add "$set_name" "$ip" -exist
            HOST_PINS="${HOST_PINS}${HOST_PINS:+$'\n'}${ip}"$'\t'"${domain}"
            count=$((count + 1))
        done < <(echo "$ips")
    done < <(read_domains)

    [ "$failed" -eq 0 ] || log "WARNING: $failed domain(s) could not be resolved"
}

# Populates a fresh temp set, then atomically swaps it into place. iptables
# rules reference the set by name, so nothing needs to be re-created here.
build_allowed_set() {
    validate_whitelist

    trap cleanup_tmp EXIT
    cleanup_tmp
    ipset create "$IPSET_TMP" hash:net

    if github_enabled; then
        add_github_ranges "$IPSET_TMP"
    else
        log "GitHub meta ranges disabled in $WHITELIST_FILE"
    fi
    add_static_cidrs "$IPSET_TMP"
    add_domains "$IPSET_TMP"

    local entries
    entries=$(ipset list "$IPSET_TMP" -t | awk '/Number of entries/ {print $NF}')
    [ "${entries:-0}" -gt 0 ] || die "refusing to install an empty allow-list"

    ipset create "$IPSET_NAME" hash:net -exist
    ipset swap "$IPSET_TMP" "$IPSET_NAME"
    ipset destroy "$IPSET_TMP"
    trap - EXIT

    # After the swap, never before: a pin is a promise that the address is in the
    # live set, and until the swap it is only in the temp one.
    write_host_pins "$HOST_PINS"

    log "Allow-list installed: $entries entries"
}

# ---------------------------------------------------------------------------
# Modes
# ---------------------------------------------------------------------------
do_list() {
    ipset list "$IPSET_NAME" 2>/dev/null || die "ipset '$IPSET_NAME' does not exist; run --init first"
}

# Is the firewall actually enforcing?
#
# The presence of the ipset says nothing on its own: rules can be flushed and the
# policy left at ACCEPT with the set still sitting there, which looks healthy to
# anything that only asks whether the set exists — while every destination is
# reachable. Check what is enforced instead.
#
# Exit codes are the interface here, because the answer is three-valued:
#   0  enforcing
#   3  sealed: closed, but with no working allow-list
#   1  not enforcing: egress is open
do_status() {
    require_root
    local entries policy_drop=1 rule_present=1 set_ok=1 ipv6_ok=1

    iptables -S OUTPUT 2>/dev/null | grep -qx -- "-P OUTPUT DROP" || policy_drop=0
    iptables -C OUTPUT -m set --match-set "$IPSET_NAME" dst -j ACCEPT 2>/dev/null || rule_present=0

    if ipset list "$IPSET_NAME" -t >/dev/null 2>&1; then
        entries=$(ipset list "$IPSET_NAME" -t | awk '/Number of entries/ {print $NF}')
        [ "${entries:-0}" -gt 0 ] || set_ok=0
    else
        set_ok=0
    fi

    # An unfiltered IPv6 stack is an open door regardless of how good the IPv4
    # rules are, so it counts as not enforcing rather than as a warning.
    if ipv6_present && command -v ip6tables >/dev/null 2>&1; then
        ip6tables -S OUTPUT 2>/dev/null | grep -qx -- "-P OUTPUT DROP" || ipv6_ok=0
    fi

    if [ "$policy_drop" -eq 1 ] && [ "$rule_present" -eq 1 ] \
       && [ "$set_ok" -eq 1 ] && [ "$ipv6_ok" -eq 1 ]; then
        log "Firewall is enforcing (${entries} allow-list entries)"
        return 0
    fi

    [ "$policy_drop" -eq 1 ] || warn "IPv4 OUTPUT policy is not DROP"
    [ "$rule_present" -eq 1 ] || warn "allow-list rule is not installed"
    [ "$set_ok" -eq 1 ] || warn "allow-list is missing or empty"
    [ "$ipv6_ok" -eq 1 ] || warn "IPv6 OUTPUT policy is not DROP"

    # Sealed counts as closed: unusable, but nothing can get out.
    if [ "$policy_drop" -eq 1 ] && [ "$ipv6_ok" -eq 1 ]; then
        return 3
    fi
    return 1
}

do_reload() {
    require_root
    iptables -C OUTPUT -m set --match-set "$IPSET_NAME" dst -j ACCEPT 2>/dev/null \
        || die "firewall rules are not installed; run '$0 --init' first"

    log "Reloading allow-list from $WHITELIST_FILE"
    build_allowed_set

    if [ "$FLUSH_CONNTRACK" = "1" ]; then
        if command -v conntrack >/dev/null 2>&1; then
            log "Flushing connection tracking table..."
            conntrack -F 2>/dev/null || log "WARNING: conntrack flush failed"
        else
            log "WARNING: conntrack not installed, skipping flush"
        fi
    fi

    log "Reload complete"
}

install_firewall() {
    # 1. Extract Docker DNS (pre-defined by Docker to be `127.0.0.11`)
    # info BEFORE any flushing
    DOCKER_DNS_RULES=$(iptables-save -t nat | grep "127\.0\.0\.11" || true)

    # Flush existing rules and delete existing ipsets
    iptables -F
    iptables -X
    iptables -t nat -F
    iptables -t nat -X
    iptables -t mangle -F
    iptables -t mangle -X
    ipset destroy "$IPSET_NAME" 2>/dev/null || true
    ipset destroy "$IPSET_TMP" 2>/dev/null || true

    # Building the allow-list needs egress of its own — DNS lookups and, when
    # github_meta is on, a call to api.github.com. `iptables -F` leaves the
    # policies alone, so a previous seal (or a previous successful init) would
    # still be denying that traffic and the rebuild could never succeed. Reopen
    # for the build window only; every exit path from here lands in
    # seal_firewall or in the DROP policies installed at the end.
    iptables -P INPUT ACCEPT
    iptables -P FORWARD ACCEPT
    iptables -P OUTPUT ACCEPT

    # 2. Selectively restore ONLY internal Docker DNS resolution
    if [ -n "$DOCKER_DNS_RULES" ]; then
        log "Restoring Docker DNS rules..."
        iptables -t nat -N DOCKER_OUTPUT 2>/dev/null || true
        iptables -t nat -N DOCKER_POSTROUTING 2>/dev/null || true
        echo "$DOCKER_DNS_RULES" | xargs -L 1 iptables -t nat
    else
        log "No Docker DNS rules to restore"
    fi

    # First allow DNS and localhost before any restrictions
    # Allow outbound DNS
    iptables -A OUTPUT -p udp --dport 53 -j ACCEPT
    # Allow inbound DNS responses
    iptables -A INPUT -p udp --sport 53 -j ACCEPT
    # Allow outbound SSH
    iptables -A OUTPUT -p tcp --dport 22 -j ACCEPT
    # Allow inbound SSH responses
    iptables -A INPUT -p tcp --sport 22 -m state --state ESTABLISHED -j ACCEPT
    # Allow localhost
    iptables -A INPUT -i lo -j ACCEPT
    iptables -A OUTPUT -o lo -j ACCEPT

    # Nothing below this line applies to IPv6, so close it before we open
    # anything at all.
    deny_ipv6

    # Build the allow-list from the JSON whitelist
    build_allowed_set

    # Get host IP from default route
    HOST_IP=$(ip route | grep default | cut -d" " -f3)
    [ -n "$HOST_IP" ] || die "failed to detect host IP"

    HOST_NETWORK=$(echo "$HOST_IP" | sed "s/\.[0-9]*$/.0\/24/")
    log "Host network detected as: $HOST_NETWORK"

    # Set up remaining iptables rules
    iptables -A INPUT -s "$HOST_NETWORK" -j ACCEPT
    iptables -A OUTPUT -d "$HOST_NETWORK" -j ACCEPT

    # Set default policies to DROP first
    iptables -P INPUT DROP
    iptables -P FORWARD DROP
    iptables -P OUTPUT DROP

    # First allow established connections for already approved traffic
    iptables -A INPUT -m state --state ESTABLISHED,RELATED -j ACCEPT
    iptables -A OUTPUT -m state --state ESTABLISHED,RELATED -j ACCEPT

    # Then allow only specific outbound traffic to allowed domains
    iptables -A OUTPUT -m set --match-set "$IPSET_NAME" dst -j ACCEPT

    # Explicitly REJECT all other outbound traffic for immediate feedback
    iptables -A OUTPUT -j REJECT --reject-with icmp-admin-prohibited

    log "Firewall configuration complete"
    log "Verifying firewall rules..."
    if curl --connect-timeout 5 https://example.com >/dev/null 2>&1; then
        die "firewall verification failed - was able to reach https://example.com"
    else
        log "Firewall verification passed - unable to reach https://example.com as expected"
    fi

    # Verify GitHub API access
    if github_enabled; then
        if ! curl --connect-timeout 5 https://api.github.com/zen >/dev/null 2>&1; then
            die "firewall verification failed - unable to reach https://api.github.com"
        else
            log "Firewall verification passed - able to reach https://api.github.com as expected"
        fi
    fi
}

do_init() {
    require_root
    validate_whitelist

    # Ignore SIGPIPE for the duration.
    #
    # Anything that reads only part of our output — `| grep -q`, `| head` — closes
    # the pipe early and the default SIGPIPE would kill us mid-configuration,
    # after the rules are flushed but before the default-deny policy is in. That
    # is the fail-open case this function exists to prevent, and it should not be
    # reachable by something as ordinary as piping the log somewhere.
    trap '' PIPE

    # The subshell is what makes this work: `die` inside install_firewall exits
    # the subshell rather than the script, so control comes back here and we get
    # to choose the end state rather than stopping wherever the failure landed.
    if ! ( install_firewall ); then
        seal_firewall
        die "init failed - the container is sealed and has no egress. Fix the cause above and re-run --init."
    fi
}

case "$MODE" in
    init)   do_init ;;
    reload) do_reload ;;
    list)   do_list ;;
    status) do_status ;;
esac
