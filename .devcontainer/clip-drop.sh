#!/usr/bin/env bash
#
# clip-drop.sh — host-side clipboard bridge for the devcontainer.
#
# Writes the image on the macOS clipboard into the drop directory that is
# bind-mounted read-only at /clipdrop, then prints the path that image has
# *inside* the container. Bind that to a key (see NOTES.md) and pasting a
# screenshot into Claude becomes one keystroke.
#
# There is no clipboard to share in the first place: Claude Code runs inside the
# container, the macOS pasteboard lives on the host, and the Linux tools it would
# reach for (xclip, wl-paste) need a display server no devcontainer has. Nor is
# one coming — the upstream request to bridge it over VS Code's IPC socket was
# closed as not planned. But Claude reads an image given a path, so a shared
# directory and a path on the prompt is the whole trick.
#
set -euo pipefail

VERSION="1.0.0"

# Must match the mount in devcontainer.json. Both sides are overridable, for the
# same reason CLAUDE_HOST_CONFIG_DIR is: the host path is relative to $HOME.
HOST_DIR="${HOME}/${CLAUDE_CLIPDROP_DIR:-.clipdrop}"
CONTAINER_DIR="/clipdrop"
KEEP=50
MODE="print"

if [ -t 2 ]; then
    C_RED=$'\033[31m'; C_DIM=$'\033[2m'; C_OFF=$'\033[0m'
else
    C_RED=""; C_DIM=""; C_OFF=""
fi
log() { echo "${C_DIM}==>${C_OFF} $*" >&2; }
die() { echo "${C_RED}error:${C_OFF} $*" >&2; exit 1; }

usage() {
    cat >&2 <<EOF
clip-drop.sh $VERSION — put the clipboard image where the container can read it

Usage: clip-drop.sh [--copy | --print] [--keep N]

  --print    Print the container path to stdout, with a trailing space, and
             nothing else. This is the form iTerm2's "Run Coprocess" key action
             wants: a coprocess's stdout is treated as keyboard input, so the
             path is typed straight into Claude's prompt. (default)
  --copy     Copy the container path to the clipboard instead, for a hotkey
             runner — Raycast, Alfred, Keyboard Maestro — driving a terminal
             that cannot run commands from a keybinding. Ghostty is one: its
             key actions are a fixed set with nothing exec-shaped in it.
  --keep N   Keep the N most recent images (default: $KEEP).

Environment:
  CLAUDE_CLIPDROP_DIR   Drop directory relative to \$HOME (default: .clipdrop)

Exits 0 and does nothing when the clipboard holds no image, so a key bound to
this is harmless to press with text on the clipboard.
EOF
}

while [ $# -gt 0 ]; do
    case "$1" in
        --print) MODE="print"; shift ;;
        --copy)  MODE="copy"; shift ;;
        --keep)  KEEP="${2:-}"; [ -n "$KEEP" ] || die "--keep needs a number"; shift 2 ;;
        -h|--help) usage; exit 0 ;;
        *) usage; die "unknown argument: $1" ;;
    esac
done

[ "$(uname -s)" = "Darwin" ] || die "this script is macOS-only (it reads the macOS pasteboard)"

mkdir -p "$HOST_DIR"

name="clip-$(date +%Y%m%d-%H%M%S).png"
out="${HOST_DIR}/${name}"

# osascript rather than pngpaste: same result with nothing to install. The
# clipboard coercion fails outright when there is no image on the pasteboard,
# which is exactly the "you pressed the key with text copied" case — so it is a
# silent no-op rather than an error, and any half-written file is cleaned up.
if ! osascript \
        -e 'set png to (the clipboard as «class PNGf»)' \
        -e "set f to open for access POSIX file \"${out}\" with write permission" \
        -e 'write png to f' \
        -e 'close access f' >/dev/null 2>&1; then
    rm -f "$out"
    log "no image on the clipboard"
    exit 0
fi

[ -s "$out" ] || { rm -f "$out"; log "no image on the clipboard"; exit 0; }

# Oldest first, so the newest $KEEP survive. Nothing else prunes this directory,
# and screenshots are not small.
if [ "$KEEP" -gt 0 ] 2>/dev/null; then
    # shellcheck disable=SC2012  # names here are ours and contain no newlines
    ls -t "${HOST_DIR}"/clip-*.png 2>/dev/null | tail -n "+$((KEEP + 1))" | while read -r old; do
        rm -f "$old"
    done
fi

path="${CONTAINER_DIR}/${name}"

if [ "$MODE" = "copy" ]; then
    printf '%s' "$path" | pbcopy
    log "copied $path to the clipboard"
else
    # The trailing space matters: this is typed into a prompt, and Claude needs
    # the path to end before whatever you type next begins.
    printf '%s ' "$path"
fi
