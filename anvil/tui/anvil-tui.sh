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
  out="$(anvil_docker_ps 2>&1; printf '\n--- Projects ---\n'; anvil_projects_status 2>&1)" || true
  [[ -z "$out" ]] && out="(no status output)"
  local tmp
  tmp="$(mktemp)"
  printf '%s\n' "$out" > "$tmp"
  "$UI" --title "Anvil Status" --textbox "$tmp" 24 80
  rm -f "$tmp"
}

# tui_projects
#   Formats the engine's TSV project status into a readable table.
tui_projects() {
  local out
  out="$(anvil_projects_status 2>&1)" || true
  local tmp
  tmp="$(mktemp)"
  {
    printf '%-22s %-42s %s\n' "PROJECT" "URL" "SSL"
    printf '%s\n' "--------------------------------------------------------------"
    if [[ -z "$out" ]]; then
      printf '%s\n' "(no projects registered)"
    else
      while IFS=$'\t' read -r name url ssl; do
        [[ -z "$name" ]] && continue
        printf '%-22s %-42s %s\n' "$name" "$url" "$ssl"
      done <<< "$out"
    fi
  } > "$tmp"
  "$UI" --title "Projects" --textbox "$tmp" 24 80
  rm -f "$tmp"
}

# tui_new
#   New Project wizard: input box -> anvil_project_register.
tui_new() {
  local name
  name="$("$UI" --title "New Project" --inputbox "Enter the new project name:" 8 50 3>&1 1>&2 2>&3)" || return 0
  [[ -z "$name" ]] && return 0
  tui_run "New Project: ${name}" anvil_project_register "$name"
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

# ---------------------------------------------------------------------------
# Main menu loop
# ---------------------------------------------------------------------------
tui_main() {
  while true; do
    local choice
    choice="$("$UI" --clear --title "Anvil Control" \
      --menu "Select an action:" 20 60 10 \
      "1" "Start stack + Web UI" \
      "2" "Stop stack + Web UI" \
      "3" "Restart" \
      "4" "Status" \
      "5" "Projects" \
      "6" "New Project" \
      "7" "DB Actions" \
      "8" "Logs" \
      "9" "Exit" 3>&1 1>&2 2>&3)" || { clear; exit 0; }

    case "$choice" in
      1)
        tui_run "Start Docker stack" anvil_docker_up
        tui_run "Start Web UI" anvil_web_up
        ;;
      2)
        tui_run "Stop Web UI" anvil_web_down
        tui_run "Stop Docker stack" anvil_docker_down
        ;;
      3)
        tui_run "Stop Web UI" anvil_web_down
        tui_run "Stop Docker stack" anvil_docker_down
        tui_run "Start Docker stack" anvil_docker_up
        tui_run "Start Web UI" anvil_web_up
        ;;
      4) tui_status ;;
      5) tui_projects ;;
      6) tui_new ;;
      7) tui_db ;;
      8) tui_logs ;;
      9|"") clear; exit 0 ;;
    esac
  done
}

tui_main
