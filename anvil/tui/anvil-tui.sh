#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/tui/anvil-tui.sh
#
# Anvil TUI skin — a dialog/whiptail menu that drives the SAME engine the
# Web UI and anvilctl use. It sources the shared lib functions and calls them
# directly; NO business logic is duplicated here. Every menu action maps to a
# lib function (or to anvilctl for parity).
#
# UI backend: prefers `dialog`, falls back to `whiptail`.

set -euo pipefail

# Resolve Anvil root from this script: anvil/tui/anvil-tui.sh -> anvil/
ANVIL_ROOT="$(dirname "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")")"
export ANVIL_ROOT

# shellcheck source=../config/anvil.conf
source "${ANVIL_ROOT}/config/anvil.conf"
# shellcheck source=../lib/core.sh
source "${ANVIL_ROOT}/lib/core.sh"
# shellcheck source=../lib/registry.sh
source "${ANVIL_ROOT}/lib/registry.sh"
# shellcheck source=../lib/docker.sh
source "${ANVIL_ROOT}/lib/docker.sh"
# shellcheck source=../lib/project.sh
source "${ANVIL_ROOT}/lib/project.sh"
# shellcheck source=../lib/db.sh
source "${ANVIL_ROOT}/lib/db.sh"
# shellcheck source=../lib/web.sh
source "${ANVIL_ROOT}/lib/web.sh"

# ---------------------------------------------------------------------------
# UI backend selection (dialog preferred, whiptail fallback).
# ---------------------------------------------------------------------------
UI=""
if command -v dialog >/dev/null 2>&1; then
  UI="dialog"
elif command -v whiptail >/dev/null 2>&1; then
  UI="whiptail"
else
  echo "ERROR: neither 'dialog' nor 'whiptail' is installed." >&2
  echo "        Install one first: apt-get install -y dialog" >&2
  exit 1
fi

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

# tui_run DESCRIPTION -- command [args...]
#   Runs an engine command, captures its combined output, and shows the result
#   in a message box. Failures are reported but never abort the menu (the `if`
#   guards the command substitution under `set -e`).
tui_run() {
  local desc="$1"
  shift
  local out
  if out="$("$@" 2>&1)"; then
    "$UI" --title "$desc" --msgbox "${out:-$desc: done.}" 20 70
  else
    "$UI" --title "$desc (failed)" --msgbox "${out:-$desc: failed.}" 20 70
  fi
}

# tui_status
#   Shows docker stack status + project list in a scrollable textbox.
tui_status() {
  local out
  out="$("${ANVIL_ROOT}/bin/anvilctl" status 2>&1)" || true
  [[ -z "$out" ]] && out="(no status output)"
  local tmp
  tmp="$(mktemp)"
  printf '%s\n' "$out" > "$tmp"
  "$UI" --title "Anvil v3 Status" --textbox "$tmp" 24 80
  rm -f "$tmp"
}

# tui_projects
#   Formats the engine's TSV project status into a readable table.
tui_projects() {
  local out
  out="$(anvil_registry_list 2>&1)" || true
  local tmp
  tmp="$(mktemp)"
  {
    printf '%-22s %-50s %s\n' "TENANT SLUG" "ROOT" "CREATED"
    printf '%s\n' "--------------------------------------------------------------------------------"
    if [[ -z "$out" ]]; then
      printf '%s\n' "(no tenants registered)"
    else
      # Skip the header line from anvil_registry_list.
      tail -n +2 <<< "$out" | while IFS=$'\t' read -r slug root created; do
        [[ -z "$slug" ]] && continue
        printf '%-22s %-50s %s\n' "$slug" "$root" "$created"
      done
    fi
  } > "$tmp"
  "$UI" --title "Tenants (registry)" --textbox "$tmp" 24 80
  rm -f "$tmp"
}

# tui_doctor — runs anvilctl doctor and shows the verdict.
tui_doctor() {
  tui_run "anvilctl doctor (CVE floors + port registry)" "${ANVIL_ROOT}/bin/anvilctl" doctor
}

# tui_deploy — deploy wizard: env input → strategy → release → run.
tui_deploy() {
  local env strategy release
  env="$(_tui_input "Deploy" "Environment (staging|production):" "staging")" || return 0
  [[ -z "$env" ]] && return 0
  strategy="$(_tui_input "Deploy" "Strategy (blue-green):" "blue-green")" || return 0
  release="$(_tui_input "Deploy" "Release digest (empty = autodetect from CI/Git):" "")" || return 0
  local cmd=("${ANVIL_ROOT}/bin/anvilctl" deploy "$env" --strategy "${strategy:-blue-green}")
  [[ -n "$release" ]] && cmd+=(--release "$release")
  tui_run "Deploy ${env}" "${cmd[@]}"
}

# tui_rollback — rollback wizard.
tui_rollback() {
  local env
  env="$(_tui_input "Rollback" "Environment (staging|production):" "staging")" || return 0
  [[ -z "$env" ]] && return 0
  tui_run "Rollback ${env}" "${ANVIL_ROOT}/bin/anvilctl" rollback "$env"
}

# tui_stack — dev stack shape toggle.
tui_stack() {
  local choice
  choice="$("$UI" --title "Stack Shape (dev)" --menu "Choose stack mode:" 10 50 3 \
    "1" "Slim — collapsed single FrankenPHP process (default dev)" \
    "2" "Full — Caddy + Tengine + FrankenPHP parity trio" \
    "3" "Back" 3>&1 1>&2 2>&3)" || return 0
  case "$choice" in
    1) tui_run "Stack slim" "${ANVIL_ROOT}/bin/anvilctl" stack slim ;;
    2) tui_run "Stack full" "${ANVIL_ROOT}/bin/anvilctl" stack full ;;
    3|"") return 0 ;;
  esac
}

# _tui_input TITLE LABEL DEFAULT — input box helper.
_tui_input() {
  local title="$1" label="$2" default="${3:-}"
  "$UI" --title "$title" --inputbox "$label" 8 60 "$default" 3>&1 1>&2 2>&3
}

# tui_new
#   New tenant wizard: input box -> anvil_registry_register.
tui_new() {
  local name
  name="$("$UI" --title "New Tenant" --inputbox "Enter the new tenant slug (lowercase, [a-z0-9-]+):" 8 50 3>&1 1>&2 2>&3)" || return 0
  [[ -z "$name" ]] && return 0
  tui_run "New Tenant: ${name}" anvil_registry_register "$name"
}

# tui_db
#   DB Actions submenu: create database via anvil_db_create.
tui_db() {
  local choice
  choice="$("$UI" --title "DB Actions" --menu "Choose an action:" 12 50 4 \
    "1" "Create database" \
    "2" "Back" 3>&1 1>&2 2>&3)" || return 0
  case "$choice" in
    1)
      local db
      db="$("$UI" --title "Create Database" --inputbox "Database name:" 8 50 3>&1 1>&2 2>&3)" || return 0
      [[ -z "$db" ]] && return 0
      tui_run "Create DB: ${db}" anvil_db_create "$db"
      ;;
    2|"") return 0 ;;
  esac
}

# tui_logs
#   Shows a tail of the Docker stack logs in a textbox (portable across dialog
#   and whiptail; whiptail lacks a true tailbox).
tui_logs() {
  local out
  out="$(anvil_docker_logs --tail=200 2>&1)" || true
  [[ -z "$out" ]] && out="(no logs / Docker stack not running)"
  local tmp
  tmp="$(mktemp)"
  printf '%s\n' "$out" > "$tmp"
  "$UI" --title "Logs (tail)" --textbox "$tmp" 30 100
  rm -f "$tmp"
}

# tui_main
#   Main menu loop. v3 panels: doctor, deploy, rollback, stack shape.
tui_main() {
  while true; do
    local choice
    choice="$("$UI" --clear --title "Anvil v3 Control" \
      --menu "Select an action:" 24 60 14 \
      "1"  "Start stack" \
      "2"  "Stop stack" \
      "3"  "Status (versions, listeners, tenants)" \
      "4"  "Doctor (CVE floors + port registry)" \
      "5"  "Stack shape (dev: slim|full)" \
      "6"  "Tenants" \
      "7"  "New tenant" \
      "8"  "DB Actions" \
      "9"  "Logs" \
      "10" "Deploy (blue/green)" \
      "11" "Rollback" \
      "12" "Exit" 3>&1 1>&2 2>&3)" || { clear; exit 0; }

    case "$choice" in
      1) tui_run "Start stack" "${ANVIL_ROOT}/bin/anvilctl" start ;;
      2) tui_run "Stop stack" "${ANVIL_ROOT}/bin/anvilctl" stop ;;
      3) tui_status ;;
      4) tui_doctor ;;
      5) tui_stack ;;
      6) tui_projects ;;
      7) tui_new ;;
      8) tui_db ;;
      9) tui_logs ;;
      10) tui_deploy ;;
      11) tui_rollback ;;
      12|"") clear; exit 0 ;;
    esac
  done
}

tui_main
