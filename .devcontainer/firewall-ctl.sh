#!/usr/bin/env bash
#
# firewall-ctl.sh — host-side control for the devcontainer firewall.
#
# Drives /usr/local/bin/configure-firewall.sh inside a running devcontainer, and
# edits the host copy of firewall-whitelist-domains.json.
#
set -euo pipefail

VERSION="1.0.0"

# ---------------------------------------------------------------------------
# Defaults
# ---------------------------------------------------------------------------
WORKSPACE_FOLDER=""
WHITELIST_FILE=""
CONTAINER_SCRIPT="/usr/local/bin/configure-firewall.sh"
CONTAINER_WHITELIST=""   # empty = auto-detect from the candidates below
CONTAINER_WHITELIST_CANDIDATES=(
    "/etc/firewall/firewall-whitelist-domains.json"
    "/usr/local/bin/firewall-whitelist-domains.json"
)
ENGINE="auto"            # auto | docker | devcontainer
CONTAINER_ID=""
EXTRA_ARGS=()
FLUSH_CONNTRACK=0
WATCH_INTERVAL=2
NO_PUSH=0

# ---------------------------------------------------------------------------
# Output helpers
# ---------------------------------------------------------------------------
if [ -t 2 ]; then
    C_RED=$'\033[31m'; C_YEL=$'\033[33m'; C_GRN=$'\033[32m'; C_DIM=$'\033[2m'; C_OFF=$'\033[0m'
else
    C_RED=""; C_YEL=""; C_GRN=""; C_DIM=""; C_OFF=""
fi
log()  { echo "${C_DIM}==>${C_OFF} $*" >&2; }
ok()   { echo "${C_GRN}==>${C_OFF} $*" >&2; }
warn() { echo "${C_YEL}warning:${C_OFF} $*" >&2; }
die()  { echo "${C_RED}error:${C_OFF} $*" >&2; exit 1; }

usage() {
    cat >&2 <<EOF
firewall-ctl.sh $VERSION — control the devcontainer firewall from the host

USAGE
  firewall-ctl.sh <command> [args] [options]

COMMANDS
  reload                  Push the whitelist (if needed) and rebuild the allow-list
  init                    Re-run the full firewall setup (iptables + allow-list)
  list                    Print the allow-list currently active in the container
  add <domain>...         Add domains to the whitelist, then reload
  remove <domain>...      Remove domains from the whitelist, then reload
  domains                 Print the domains in the host whitelist file
  validate                Check the host whitelist file is well-formed JSON
  test <url>...           Check from inside the container whether a URL is reachable
  watch                   Reload automatically whenever the whitelist file changes
  status                  Show container, engine, and whitelist sync state
  exec -- <cmd>...        Run an arbitrary command inside the container

OPTIONS
  -w, --workspace-folder <path>  Devcontainer workspace (default: git root, else cwd)
  -f, --file <path>              Host whitelist JSON
                                 (default: <workspace>/.devcontainer/firewall-whitelist-domains.json)
      --engine <auto|docker|devcontainer>
                                 How to reach the container (default: auto)
      --container <id|name>      Target this container directly, skipping discovery
      --container-file <path>    Whitelist path inside the container
                                 (default: auto-detect /etc/firewall, then /usr/local/bin)
      --strict                   Pass --strict to the in-container script
      --flush-conntrack          Pass --flush-conntrack to the in-container script
      --no-push                  Never copy the whitelist in; assume it is bind-mounted
      --interval <seconds>       Poll interval for 'watch' fallback (default: $WATCH_INTERVAL)
  -h, --help                     Show this help
  -V, --version                  Show version

EXAMPLES
  firewall-ctl.sh add pypi.org files.pythonhosted.org
  firewall-ctl.sh test https://pypi.org https://example.com
  firewall-ctl.sh watch
EOF
}

# ---------------------------------------------------------------------------
# Argument parsing
# ---------------------------------------------------------------------------
[ $# -gt 0 ] || { usage; exit 2; }

COMMAND="$1"; shift
POSITIONAL=()
PASSTHROUGH=()

while [ $# -gt 0 ]; do
    case "$1" in
        -w|--workspace-folder) WORKSPACE_FOLDER="${2:-}"; shift 2 ;;
        -f|--file)             WHITELIST_FILE="${2:-}"; shift 2 ;;
        --engine)              ENGINE="${2:-}"; shift 2 ;;
        --container)           CONTAINER_ID="${2:-}"; shift 2 ;;
        --container-file)      CONTAINER_WHITELIST="${2:-}"; shift 2 ;;
        --strict)              EXTRA_ARGS+=("--strict"); shift ;;
        --flush-conntrack)     EXTRA_ARGS+=("--flush-conntrack"); FLUSH_CONNTRACK=1; shift ;;
        --no-push)             NO_PUSH=1; shift ;;
        --interval)            WATCH_INTERVAL="${2:-}"; shift 2 ;;
        -h|--help)             usage; exit 0 ;;
        -V|--version)          echo "$VERSION"; exit 0 ;;
        --)                    shift; PASSTHROUGH=("$@"); break ;;
        -*)                    die "unknown option: $1" ;;
        *)                     POSITIONAL+=("$1"); shift ;;
    esac
done

case "$ENGINE" in
    auto|docker|devcontainer) ;;
    *) die "--engine must be auto, docker, or devcontainer" ;;
esac

# ---------------------------------------------------------------------------
# Path resolution
# ---------------------------------------------------------------------------
resolve_paths() {
    if [ -z "$WORKSPACE_FOLDER" ]; then
        WORKSPACE_FOLDER=$(git rev-parse --show-toplevel 2>/dev/null || pwd)
    fi
    [ -d "$WORKSPACE_FOLDER" ] || die "workspace folder not found: $WORKSPACE_FOLDER"
    WORKSPACE_FOLDER=$(cd "$WORKSPACE_FOLDER" && pwd)

    if [ -z "$WHITELIST_FILE" ]; then
        local candidates=(
            "$WORKSPACE_FOLDER/.devcontainer/firewall/firewall-whitelist-domains.json"
            "$WORKSPACE_FOLDER/.devcontainer/firewall-whitelist-domains.json"
            "$WORKSPACE_FOLDER/firewall-whitelist-domains.json"
        )
        local c
        for c in "${candidates[@]}"; do
            if [ -f "$c" ]; then WHITELIST_FILE="$c"; break; fi
        done
        [ -n "$WHITELIST_FILE" ] || WHITELIST_FILE="${candidates[0]}"
    fi
}

need_whitelist() {
    [ -f "$WHITELIST_FILE" ] \
        || die "whitelist not found: $WHITELIST_FILE (pass --file to point at it)"
}

# ---------------------------------------------------------------------------
# Container discovery
#
# Devcontainers are labelled with the host folder they were created from, so we
# can find a running one without starting anything.
# ---------------------------------------------------------------------------
have() { command -v "$1" >/dev/null 2>&1; }

discover_container() {
    [ -n "$CONTAINER_ID" ] && return 0
    have docker || return 1
    CONTAINER_ID=$(docker ps -q \
        --filter "label=devcontainer.local_folder=$WORKSPACE_FOLDER" 2>/dev/null | head -n1)
    [ -n "$CONTAINER_ID" ]
}

resolve_engine() {
    case "$ENGINE" in
        docker)
            have docker || die "docker CLI not found but --engine docker was requested"
            discover_container \
                || die "no running devcontainer found for $WORKSPACE_FOLDER (pass --container)"
            ;;
        devcontainer)
            have devcontainer \
                || die "devcontainer CLI not found (npm install -g @devcontainers/cli)"
            ;;
        auto)
            if discover_container; then
                ENGINE="docker"
            elif have devcontainer; then
                ENGINE="devcontainer"
            else
                die "no running devcontainer found for $WORKSPACE_FOLDER, and the devcontainer CLI is not installed
  Start it with:  devcontainer up --workspace-folder \"$WORKSPACE_FOLDER\"
  Or install it:  npm install -g @devcontainers/cli"
            fi
            ;;
    esac
    log "engine: $ENGINE${CONTAINER_ID:+ (container ${CONTAINER_ID:0:12})}"

    # Fail here with one clear line. Without this, 'auto' falls through to the
    # devcontainer CLI whenever the docker label lookup misses, and every
    # subsequent call spews a 'Dev container not found' stack trace instead.
    assert_container_ready
}

# Run a command inside the container. Non-fatal: caller checks the exit code.
in_container() {
    if [ "$ENGINE" = "docker" ]; then
        docker exec -i "$CONTAINER_ID" "$@"
    else
        devcontainer exec --workspace-folder "$WORKSPACE_FOLDER" -- "$@"
    fi
}

in_container_quiet() { in_container "$@" >/dev/null 2>&1; }

# Run a command inside the container as root.
#
# The devcontainer's sudoers rule is scoped to configure-firewall.sh alone, so sudo
# cannot be used for anything else without a password prompt — and there is no
# TTY to prompt on. Where docker is reachable we exec as uid 0 directly, which
# sidesteps sudo entirely. The devcontainer-CLI path has no user override, so it
# falls back to sudo and only works for the whitelisted script.
in_container_root() {
    if [ "$ENGINE" = "docker" ] || discover_container; then
        docker exec -i -u 0 "$CONTAINER_ID" "$@"
    else
        devcontainer exec --workspace-folder "$WORKSPACE_FOLDER" -- sudo "$@"
    fi
}

assert_container_ready() {
    in_container_quiet test -x "$CONTAINER_SCRIPT" \
        || die "$CONTAINER_SCRIPT not found or not executable in the container.
  Is the container running, and does the image include configure-firewall.sh?"
}

# ---------------------------------------------------------------------------
# Whitelist file handling
# ---------------------------------------------------------------------------
require_jq() { have jq || die "jq is required on the host for this command"; }

validate_json() {
    need_whitelist
    require_jq
    jq -e . "$WHITELIST_FILE" >/dev/null 2>&1 \
        || die "not valid JSON: $WHITELIST_FILE"
}

host_sha() {
    if have sha256sum; then sha256sum "$1" | awk '{print $1}'
    elif have shasum;  then shasum -a 256 "$1" | awk '{print $1}'
    else die "need sha256sum or shasum on the host"
    fi
}

# The whitelist may live at /etc/firewall (directory bind mount) or at
# /usr/local/bin (baked into the image); ask the container which it is.
resolve_container_whitelist() {
    [ -n "$CONTAINER_WHITELIST" ] && return 0
    local p
    for p in "${CONTAINER_WHITELIST_CANDIDATES[@]}"; do
        if in_container_quiet test -f "$p"; then
            CONTAINER_WHITELIST="$p"
            log "container whitelist: $p"
            return 0
        fi
    done
    CONTAINER_WHITELIST="${CONTAINER_WHITELIST_CANDIDATES[0]}"
    warn "no whitelist found in the container; assuming $CONTAINER_WHITELIST"
}

# Callers must call resolve_container_whitelist themselves first. Doing it here
# would run it inside the caller's $(...) subshell, so the resolved path would be
# discarded and every later use of $CONTAINER_WHITELIST would be empty.
container_sha() {
    in_container sh -c "sha256sum '$CONTAINER_WHITELIST' 2>/dev/null | cut -d' ' -f1" 2>/dev/null || true
}

# If the JSON is bind-mounted the hashes already match and this is a no-op.
# Otherwise the file is baked into the image, so copy the host version in.
push_whitelist() {
    [ "$NO_PUSH" -eq 1 ] && { log "skipping push (--no-push)"; return 0; }
    need_whitelist
    resolve_container_whitelist

    local h c
    h=$(host_sha "$WHITELIST_FILE")
    c=$(container_sha | tr -d '[:space:]')

    if [ "$h" = "$c" ]; then
        log "whitelist already in sync"
        return 0
    fi

    log "pushing whitelist into the container..."
    local b64
    b64=$(base64 < "$WHITELIST_FILE" | tr -d '\n')

    # The shell here is already root, so a plain redirect suffices; no sudo, and
    # therefore no password prompt on a TTY-less exec.
    if ! in_container_root sh -c \
        "printf '%s' '$b64' | base64 -d > '$CONTAINER_WHITELIST'"; then
        die "could not write $CONTAINER_WHITELIST inside the container.
  If it is bind-mounted read-only the write is expected to fail, and the hashes
  should have matched — check that the mount source really is
  $WHITELIST_FILE
  and that the host file was edited in place rather than replaced.
  If the docker CLI is unavailable, the fallback path uses sudo, which the
  devcontainer only permits for configure-firewall.sh."
    fi
    ok "whitelist pushed"
}

# jq edits, written atomically so a failed edit can't truncate the file.
edit_whitelist() {
    local filter="$1"; shift
    local tmp
    tmp=$(mktemp "${WHITELIST_FILE}.XXXXXX")
    if jq "$@" "$filter" "$WHITELIST_FILE" > "$tmp"; then
        # Deliberately NOT 'mv': a single-file bind mount is bound to the inode,
        # and replacing the inode would leave the container reading the old file
        # forever. Copying the contents back writes through the mount.
        cat "$tmp" > "$WHITELIST_FILE"
        rm -f "$tmp"
    else
        rm -f "$tmp"
        die "failed to edit $WHITELIST_FILE"
    fi
}

is_hostname() { [[ "$1" =~ ^[A-Za-z0-9]([A-Za-z0-9._-]*[A-Za-z0-9])?$ ]]; }

# ---------------------------------------------------------------------------
# Commands
# ---------------------------------------------------------------------------
run_script() {
    local mode="$1"; shift
    assert_container_ready
    in_container_root "$CONTAINER_SCRIPT" "$mode" "$@"
}

cmd_reload() {
    resolve_engine
    validate_json
    push_whitelist
    run_script --reload ${EXTRA_ARGS[@]+"${EXTRA_ARGS[@]}"}
    ok "reloaded"
}

cmd_init() {
    resolve_engine
    validate_json
    push_whitelist
    warn "re-running full setup; in-flight connections will be interrupted"
    run_script --init ${EXTRA_ARGS[@]+"${EXTRA_ARGS[@]}"}
    ok "firewall re-initialised"
}

cmd_list() {
    resolve_engine
    run_script --list
}

cmd_domains() {
    validate_json
    jq -r 'if type=="array" then . else (.domains // []) end | .[]' "$WHITELIST_FILE"
}

cmd_add() {
    [ "${#POSITIONAL[@]}" -gt 0 ] || die "usage: firewall-ctl.sh add <domain>..."
    validate_json
    local d changed=0
    for d in "${POSITIONAL[@]}"; do
        is_hostname "$d" || die "invalid domain: $d"
        if jq -e --arg d "$d" \
            'if type=="array" then . else (.domains // []) end | index($d) != null' \
            "$WHITELIST_FILE" >/dev/null; then
            log "already present: $d"
            continue
        fi
        edit_whitelist 'if type=="array" then . + [$d]
                        else .domains = ((.domains // []) + [$d]) end' --arg d "$d"
        ok "added $d"
        changed=1
    done
    [ "$changed" -eq 1 ] || { log "nothing to do"; return 0; }
    cmd_reload
}

cmd_remove() {
    [ "${#POSITIONAL[@]}" -gt 0 ] || die "usage: firewall-ctl.sh remove <domain>..."
    validate_json
    local d changed=0
    for d in "${POSITIONAL[@]}"; do
        if ! jq -e --arg d "$d" \
            'if type=="array" then . else (.domains // []) end | index($d) != null' \
            "$WHITELIST_FILE" >/dev/null; then
            warn "not in whitelist: $d"
            continue
        fi
        edit_whitelist 'if type=="array" then map(select(. != $d))
                        else .domains = ((.domains // []) | map(select(. != $d))) end' --arg d "$d"
        ok "removed $d"
        changed=1
    done
    [ "$changed" -eq 1 ] || { log "nothing to do"; return 0; }
    cmd_reload
    [ "$FLUSH_CONNTRACK" -eq 1 ] \
        || warn "existing connections survive removal; use --flush-conntrack to cut them now"
}

cmd_test() {
    [ "${#POSITIONAL[@]}" -gt 0 ] || die "usage: firewall-ctl.sh test <url>..."
    resolve_engine
    local url code rc status=0
    for url in "${POSITIONAL[@]}"; do
        [[ "$url" == http*://* ]] || url="https://$url"
        set +e
        code=$(in_container curl -sS -o /dev/null -w '%{http_code}' \
                   --connect-timeout 5 --max-time 10 "$url" 2>/dev/null)
        rc=$?
        set -e
        if [ "$rc" -eq 0 ]; then
            echo "${C_GRN}ALLOWED${C_OFF}  $url  (HTTP ${code:-?})"
        else
            echo "${C_RED}BLOCKED${C_OFF}  $url  (curl exit $rc)"
            status=1
        fi
    done
    return $status
}

cmd_status() {
    resolve_engine
    echo "workspace : $WORKSPACE_FOLDER"
    echo "whitelist : $WHITELIST_FILE"
    if [ -f "$WHITELIST_FILE" ]; then
        local h c
        resolve_container_whitelist
        h=$(host_sha "$WHITELIST_FILE")
        c=$(container_sha | tr -d '[:space:]')
        if [ -z "$c" ]; then
            echo "sync      : container copy not found at $CONTAINER_WHITELIST"
        elif [ "$h" = "$c" ]; then
            echo "sync      : in sync (bind-mounted or already pushed)"
        else
            echo "sync      : ${C_YEL}host and container differ${C_OFF} — run 'reload' to push"
        fi
    else
        echo "whitelist : ${C_RED}missing${C_OFF}"
    fi
    local n
    n=$(in_container_root sh -c \
        "ipset list allowed-domains -t 2>/dev/null | awk '/Number of entries/ {print \$NF}'" 2>/dev/null || true)
    echo "allow-list: ${n:-not installed} entries"
}

cmd_watch() {
    resolve_engine
    need_whitelist
    log "watching $WHITELIST_FILE (Ctrl-C to stop)"

    if have fswatch; then
        fswatch -o "$WHITELIST_FILE" | while read -r _; do
            log "change detected"
            cmd_reload || warn "reload failed; keeping previous allow-list"
        done
    elif have inotifywait; then
        while inotifywait -qq -e close_write,move_self "$WHITELIST_FILE" 2>/dev/null; do
            log "change detected"
            cmd_reload || warn "reload failed; keeping previous allow-list"
        done
    else
        log "fswatch/inotifywait not found; polling every ${WATCH_INTERVAL}s"
        local last="" now
        while true; do
            now=$(host_sha "$WHITELIST_FILE")
            if [ -n "$last" ] && [ "$now" != "$last" ]; then
                log "change detected"
                cmd_reload || warn "reload failed; keeping previous allow-list"
            fi
            last="$now"
            sleep "$WATCH_INTERVAL"
        done
    fi
}

cmd_exec() {
    [ "${#PASSTHROUGH[@]}" -gt 0 ] || die "usage: firewall-ctl.sh exec -- <command>..."
    resolve_engine
    in_container "${PASSTHROUGH[@]}"
}

# ---------------------------------------------------------------------------
# Dispatch
# ---------------------------------------------------------------------------
resolve_paths

case "$COMMAND" in
    reload)   cmd_reload ;;
    init)     cmd_init ;;
    list)     cmd_list ;;
    add)      cmd_add ;;
    remove|rm) cmd_remove ;;
    domains)  cmd_domains ;;
    validate) validate_json; ok "$WHITELIST_FILE is valid" ;;
    test)     cmd_test ;;
    watch)    cmd_watch ;;
    status)   cmd_status ;;
    exec)     cmd_exec ;;
    help|-h|--help) usage ;;
    *)        die "unknown command: $COMMAND (try --help)" ;;
esac
