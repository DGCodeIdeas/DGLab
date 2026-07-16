#!/usr/bin/env bash
# shellcheck disable=SC1091
# anvil/lib/project.sh
#
# Project lifecycle: register new projects, scan the www directory for
# unregistered folders, list projects, and build frontend assets with sass.
# All functions are idempotent / re-runnable. Sourcing is safe.

set -euo pipefail

if [[ -z "${ANVIL_ROOT:-}" ]]; then
  # shellcheck source=../config/anvil.conf
  source "$(dirname "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")")/config/anvil.conf"
fi

# vhost + ssl are required by project registration.
# shellcheck source=./vhost.sh
source "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")/vhost.sh"
# shellcheck source=./ssl.sh
source "$(dirname "$(readlink -f "${BASH_SOURCE[0]}")")/ssl.sh"

# anvil_project_register PROJECT_NAME
#   Creates the project web root, writes a .anvil-registered marker, and
#   triggers SSL + vhost generation. Idempotent.
anvil_project_register() {
  local project="${1:?Usage: anvil_project_register PROJECT_NAME}"
  local project_dir="${WWW_DIR}/${project}"
  local marker="${project_dir}/.anvil-registered"

  mkdir -p "$project_dir"
  if [[ -f "$marker" ]]; then
    echo "Project ${project} already registered."
  else
    touch "$marker"
    echo "Registered project ${project} at ${project_dir}"
  fi

  # Generate SSL + vhost; do not abort registration if these soft-fail
  # (e.g. docker stack not running yet).
  anvil_ssl_project "$project" || echo "WARN: ssl generation failed for ${project}" >&2
  anvil_vhost_generate "$project" || echo "WARN: vhost generation failed for ${project}" >&2
}

# anvil_project_scan
#   Scans WWW_DIR for unregistered project folders, registers each (which
#   triggers vhost + ssl generation and writes the marker), and reports.
anvil_project_scan() {
  mkdir -p "$WWW_DIR"
  local found=0
  shopt -s nullglob
  for dir in "$WWW_DIR"/*/; do
    local name
    name="$(basename "$dir")"
    if [[ -f "${dir}.anvil-registered" ]]; then
      continue
    fi
    echo "Found unregistered project: ${name}"
    anvil_project_register "$name" || echo "WARN: failed to register ${name}" >&2
    found=$((found + 1))
  done
  shopt -u nullglob
  echo "Scan complete: ${found} project(s) registered."
}

# anvil_project_list
#   Read-only listing of projects and their registration state. Used by
#   `anvilctl status` so status never has side effects.
anvil_project_list() {
  mkdir -p "$WWW_DIR"
  echo "Projects in ${WWW_DIR}:"
  local any=0
  shopt -s nullglob
  for dir in "$WWW_DIR"/*/; do
    local name
    name="$(basename "$dir")"
    if [[ -f "${dir}.anvil-registered" ]]; then
      echo "  [registered]   ${name}"
    else
      echo "  [unregistered] ${name}"
    fi
    any=1
  done
  shopt -u nullglob
  if [[ "$any" -eq 0 ]]; then
    echo "  (none)"
  fi
}

# anvil_build_assets
#   Compiles SCSS -> CSS for every project using dart-sass (sass). Idempotent:
#   only (re)compiles top-level, non-partial .scss files.
anvil_build_assets() {
  if ! command -v sass >/dev/null 2>&1; then
    echo "ERROR: 'sass' (dart-sass) not found on PATH; run install.sh first." >&2
    return 1
  fi
  mkdir -p "$WWW_DIR"
  local built=0
  shopt -s nullglob
  for dir in "$WWW_DIR"/*/; do
    local scss_dir="${dir}assets/scss"
    local css_dir="${dir}assets/css"
    if [[ -d "$scss_dir" ]]; then
      mkdir -p "$css_dir"
      for scss in "$scss_dir"/*.scss; do
        local base
        base="$(basename "$scss" .scss)"
        # Skip partials (files beginning with _).
        [[ "$base" == _* ]] && continue
        sass "$scss" "${css_dir}/${base}.css" --no-source-map
        built=$((built + 1))
      done
    fi
  done
  shopt -u nullglob
  echo "Asset build complete: ${built} stylesheet(s) compiled."
}
