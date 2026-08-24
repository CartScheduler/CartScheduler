# shellcheck shell=sh
#
# Sourced by every interactive shell (bash and zsh) via the system-wide rc files.
#
# A container whose firewall is not enforcing looks exactly like one that is, from
# the inside. That happens more easily than you would think: postStartCommand is
# what runs `configure-firewall.sh --init`, and anything that stops it running —
# most commonly a stale container whose workspaceFolder no longer exists — leaves
# the container up with unrestricted egress and no visible sign of it.
#
# This asks --status, not --list. Whether the ipset exists says nothing about
# whether anything is enforced: rules can be flushed and the policy left at ACCEPT
# with the set still sitting there, which reads as healthy while every destination
# on the internet is reachable.
__firewall_status() {
    sudo -n /usr/local/bin/configure-firewall.sh --status >/dev/null 2>&1
}

__firewall_status
case $? in
    0)  # Enforcing. Say nothing.
        ;;
    3)  # Closed, but with no usable allow-list — safe, just not working.
        printf '\033[1;33m%s\033[0m\n' "NOTE: the firewall is sealed — no network access at all."
        printf '\033[1;33m%s\033[0m\n' "A previous --init failed. Fix: sudo /usr/local/bin/configure-firewall.sh --init"
        ;;
    *)  printf '\033[1;31m%s\033[0m\n' "WARNING: the outbound firewall is NOT active in this container."
        printf '\033[1;31m%s\033[0m\n' "Network access is unrestricted. This container is not a sandbox."
        printf '\033[1;31m%s\033[0m\n' "Fix: sudo /usr/local/bin/configure-firewall.sh --init"
        ;;
esac

unset -f __firewall_status
