#!/usr/bin/env bash
# anvil/install.sh
#
# Anvil Phase 1 bootstrap installer.
#
# Presents a dialog/whiptail UI (welcome -> component checklist -> progress
# gauge -> summary) and idempotently installs:
#   * Docker Engine + Compose plugin (official Docker apt repo, not snap)
#   * dnsmasq + inotify-tools (apt)
#   * mkcert binary (manual GitHub release download) + local CA
#   * dart-sass (sass) binary (manual GitHub release download)
#   * dnsmasq configuration for *.test -> 127.0.0.1, resolving the
#     systemd-resolved stub-listener conflict.
#
# Design principles:
#   * Every step is check-before-act and safe to re-run.
#   * Binary downloads that fail are REPORTED, never a hard failure.
#   * Requires root; re-execs via sudo when invoked unprivileged.

set -euo pipefail

# ---------------------------------------------------------------------------
# Privilege check: re-exec with sudo if not root.
# ---------------------------------------------------------------------------
if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
  echo "This installer must run as root. Re-executing with sudo..." >&2
  exec sudo "$0" "$@"
fi

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
  echo "        Please install one first: apt-get install -y dialog" >&2
  exit 1
fi

# Warnings collected across the run (reported in the summary).
WARNINGS=()
# Pre-install tool-presence report (orientation behaviour).
DETECT_REPORT=()

warn() {
  WARNINGS+=("$1")
  echo "WARNING: $1" >&2
}

ui_msg() {
  "$UI" --title "$1" --msgbox "$2" 20 70
}

# ---------------------------------------------------------------------------
# Orientation: detect presence of required/expected tooling.
# ---------------------------------------------------------------------------
detect_presence() {
  local tools=("dialog" "whiptail" "docker" "mkcert" "dnsmasq" "inotifywait" "sass" "dig")
  local line
  for t in "${tools[@]}"; do
    if [[ "$t" == "docker" ]]; then
      # docker alone is not enough; require the compose plugin too.
      if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
        line="OK   : docker + compose"
      else
        line="MISS : docker + compose"
      fi
    else
      if command -v "$t" >/dev/null 2>&1; then
        line="OK   : $t"
      else
        line="MISS : $t"
      fi
    fi
    DETECT_REPORT+=("$line")
  done
}

# ---------------------------------------------------------------------------
# Component install functions (all idempotent).
# ---------------------------------------------------------------------------

install_tools() {
  if command -v inotifywait >/dev/null 2>&1; then
    echo "inotify-tools already present, skipping."
    return 0
  fi
  apt-get update -qq || warn "apt-get update failed"
  apt-get install -y inotify-tools || warn "failed to install inotify-tools"
}

install_docker() {
  if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    echo "docker + compose already installed."
  else
    apt-get update -qq || warn "apt-get update failed"
    apt-get install -y ca-certificates curl gnupg || { warn "failed to install docker prerequisites"; return 1; }

    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc \
      || { warn "failed to download Docker GPG key"; return 1; }
    chmod a+r /etc/apt/keyrings/docker.asc

    local codename
    codename="$(awk -F= '/^VERSION_CODENAME=/{print $2}' /etc/os-release)"
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${codename} stable" \
      > /etc/apt/sources.list.d/docker.list

    apt-get update -qq || warn "apt-get update (docker) failed"
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin \
      || { warn "failed to install Docker packages"; return 1; }
  fi

  # Ensure the compose plugin is present even if docker-ce was already there.
  if ! docker compose version >/dev/null 2>&1; then
    apt-get install -y docker-compose-plugin || warn "docker compose plugin missing"
  else
    echo "docker compose plugin present."
  fi
}

install_dns() {
  # --- dnsmasq package ---
  if command -v dnsmasq >/dev/null 2>&1; then
    echo "dnsmasq already installed."
  else
    apt-get update -qq || warn "apt-get update failed"
    apt-get install -y dnsmasq || { warn "failed to install dnsmasq"; return 1; }
  fi

  # --- dnsmasq config: *.test -> 127.0.0.1 ---
  # Capture the system's REAL upstream nameservers (from systemd-resolved's
  # generated resolv.conf) so dnsmasq can forward non-.test queries without
  # looping back to itself. Fall back to public DNS if none are found.
  local upstreams=""
  local real_resolv="/run/systemd/resolve/resolv.conf"
  if [[ -f "$real_resolv" ]]; then
    upstreams="$(grep -E '^nameserver' "$real_resolv" 2>/dev/null | awk '{print $2}' | grep -v '^127\.0\.0\.53$' || true)"
  fi

  local dnsmasq_conf="/etc/dnsmasq.d/anvil.conf"
  {
    echo "# Anvil-managed dnsmasq configuration (Phase 1)"
    echo "address=/.test/127.0.0.1"
    if [[ -n "$upstreams" ]]; then
      while IFS= read -r ip; do
        [[ -n "$ip" ]] && echo "server=${ip}"
      done <<< "$upstreams"
    else
      echo "server=1.1.1.1"
      echo "server=8.8.8.8"
    fi
  } > "$dnsmasq_conf"
  echo "wrote ${dnsmasq_conf}"

  # --- Disable systemd-resolved stub listener (the port-53 conflict) ---
  local resolved_dir="/etc/systemd/resolved.conf.d"
  local resolved_conf="${resolved_dir}/anvil.conf"
  mkdir -p "$resolved_dir"
  {
    echo "[Resolve]"
    echo "DNSStubListener=no"
  } > "$resolved_conf"
  echo "wrote ${resolved_conf}"

  # --- Point /etc/resolv.conf at dnsmasq (127.0.0.1) ---
  # /etc/resolv.conf is often a symlink to systemd-resolved's stub file.
  # Replace it with a static file to avoid the loop and the now-disabled stub.
  if [[ -L /etc/resolv.conf ]]; then
    rm -f /etc/resolv.conf
  fi
  if ! grep -q "nameserver 127.0.0.1" /etc/resolv.conf 2>/dev/null; then
    echo "nameserver 127.0.0.1" > /etc/resolv.conf
    echo "wrote /etc/resolv.conf -> 127.0.0.1"
  else
    echo "/etc/resolv.conf already points to 127.0.0.1"
  fi

  # --- Restart services ---
  systemctl restart systemd-resolved || warn "failed to restart systemd-resolved"
  systemctl enable dnsmasq || warn "failed to enable dnsmasq"
  systemctl restart dnsmasq || warn "failed to restart dnsmasq"

  # --- Verify ---
  if command -v dig >/dev/null 2>&1; then
    if dig +short demo.test @127.0.0.1 2>/dev/null | grep -q "127.0.0.1"; then
      echo "VERIFY OK: demo.test resolves to 127.0.0.1"
    else
      warn "dig demo.test did not resolve to 127.0.0.1 (check dnsmasq status)"
    fi
  else
    warn "dig not installed; skipping *.test verification"
  fi
}

install_mkcert() {
  if command -v mkcert >/dev/null 2>&1; then
    echo "mkcert already installed."
  else
    # Ensure curl is available for the download.
    if ! command -v curl >/dev/null 2>&1; then
      apt-get update -qq || warn "apt-get update failed"
      apt-get install -y curl ca-certificates || { warn "curl unavailable; cannot download mkcert"; return 1; }
    fi

    local arch url
    arch="$(uname -m)"
    case "$arch" in
      x86_64|amd64) arch="linux-amd64" ;;
      aarch64|arm64) arch="linux-arm64" ;;
      armv7l)        arch="linux-arm" ;;
      *) warn "unsupported architecture '${arch}' for mkcert"; return 1 ;;
    esac

    local api_url="https://api.github.com/repos/filosoft/mkcert/releases/latest"
    url="$(curl -fsSL "$api_url" \
      | grep -oP '"browser_download_url":\s*"\Khttps://[^\"]*mkcert[^\"]*'"$arch"'[^\"]*' \
      | head -1)" || true

    if [[ -z "$url" ]]; then
      warn "could not determine mkcert download URL; install manually from https://github.com/filosoft/mkcert/releases"
      return 1
    fi

    echo "Downloading mkcert from ${url}"
    if curl -fsSL "$url" -o /usr/local/bin/mkcert; then
      chmod +x /usr/local/bin/mkcert
      echo "mkcert installed to /usr/local/bin/mkcert"
    else
      warn "failed to download mkcert from ${url}; install manually"
      return 1
    fi
  fi

  # Install the local CA (idempotent — safe to re-run).
  if command -v mkcert >/dev/null 2>&1; then
    mkcert -install || warn "mkcert -install failed"
  fi
}

install_sass() {
  if command -v sass >/dev/null 2>&1; then
    echo "sass already installed."
    return 0
  fi

  if ! command -v curl >/dev/null 2>&1; then
    apt-get update -qq || warn "apt-get update failed"
    apt-get install -y curl ca-certificates || { warn "curl unavailable; cannot download dart-sass"; return 1; }
  fi

  local arch url tmp sass_bin
  arch="$(uname -m)"
  case "$arch" in
    x86_64|amd64) arch="linux-x64" ;;
    aarch64|arm64) arch="linux-arm64" ;;
    *) warn "unsupported architecture '${arch}' for dart-sass"; return 1 ;;
  esac

  local api_url="https://api.github.com/repos/sass/dart-sass/releases/latest"
  url="$(curl -fsSL "$api_url" \
    | grep -oP '"browser_download_url":\s*"\Khttps://[^\"]*dart-sass-[^\"]*'"$arch"'\.tar\.gz' \
    | head -1)" || true

  if [[ -z "$url" ]]; then
    warn "could not determine dart-sass download URL; install sass manually"
    return 1
  fi

  tmp="$(mktemp -d)"
  if curl -fsSL "$url" -o "${tmp}/sass.tar.gz"; then
    tar -xzf "${tmp}/sass.tar.gz" -C "$tmp"
    sass_bin="$(find "$tmp" -name sass -type f | head -1)"
    if [[ -n "$sass_bin" ]]; then
      cp "$sass_bin" /usr/local/bin/sass
      chmod +x /usr/local/bin/sass
      echo "sass installed to /usr/local/bin/sass"
    else
      warn "sass binary not found in downloaded archive"
      rm -rf "$tmp"
      return 1
    fi
  else
    warn "failed to download dart-sass from ${url}"
    rm -rf "$tmp"
    return 1
  fi
  rm -rf "$tmp"
}

# Map a step tag to its install function.
run_step() {
  case "$1" in
    tools)  install_tools ;;
    docker) install_docker ;;
    dns)    install_dns ;;
    mkcert) install_mkcert ;;
    sass)   install_sass ;;
    *) echo "Unknown step: $1" >&2 ;;
  esac
}

# ---------------------------------------------------------------------------
# Main flow.
# ---------------------------------------------------------------------------
main() {
  # 1) Welcome
  ui_msg "Welcome to Anvil" "Anvil is a local-dev automation and EC2 deployment tool.

This installer sets up the Phase 1 foundation:
  - Docker Engine + Compose plugin
  - dnsmasq for *.test local DNS
  - mkcert (local CA + per-project certs)
  - dart-sass for asset builds
  - inotify-tools for file watching

All steps are idempotent and safe to re-run.

Press OK to choose components."

  # 2) Orientation: detect what is already present.
  detect_presence

  # 3) Component checklist (default on for anything not yet satisfied).
  _docker_ok() { command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; }
  _dns_ok()    { command -v dnsmasq >/dev/null 2>&1; }
  _mkcert_ok() { command -v mkcert >/dev/null 2>&1; }
  _sass_ok()   { command -v sass >/dev/null 2>&1; }
  _tools_ok()  { command -v inotifywait >/dev/null 2>&1; }
  _status()    { if "$1"; then echo off; else echo on; fi; }

  local -a CHECK_ARGS=(
    tools  "inotify-tools (file watching)"        "$(_status _tools_ok)"
    docker "Docker Engine + Compose plugin"       "$(_status _docker_ok)"
    dns    "dnsmasq + *.test local DNS"           "$(_status _dns_ok)"
    mkcert "mkcert CA + binary"                   "$(_status _mkcert_ok)"
    sass   "dart-sass (sass) binary"              "$(_status _sass_ok)"
  )

  local out rc
  out="$("$UI" --separate-output --checklist \
    "Select components to install (Space toggles, Enter confirms):" 20 70 10 \
    "${CHECK_ARGS[@]}" 3>&1 1>&2 2>&3)" || rc=$?
  rc="${rc:-0}"
  if [[ "$rc" -ne 0 ]]; then
    ui_msg "Cancelled" "Installation cancelled. No changes were made."
    exit 0
  fi

  local -a SELECTED=()
  if [[ -n "$out" ]]; then
    # shellcheck disable=SC2206
    SELECTED=($out)   # split on whitespace (--separate-output => one tag per line)
  fi

  if [[ ${#SELECTED[@]} -eq 0 ]]; then
    ui_msg "Nothing selected" "No components were selected. Nothing to do."
    exit 0
  fi

  # 4) Order the selected steps into a deterministic sequence.
  local -a ORDER=(tools docker dns mkcert sass)
  local -a STEPS=()
  local o s
  for o in "${ORDER[@]}"; do
    for s in "${SELECTED[@]}"; do
      [[ "$s" == "$o" ]] && STEPS+=("$o")
    done
  done

  # 5) Progress gauge: run each step, reporting percentage + label.
  local total="${#STEPS[@]}"
  local i=0 step pct
  exec 3> >("$UI" --gauge "Installing Anvil components..." 12 70 0)
  for step in "${STEPS[@]}"; do
    i=$((i + 1))
    pct=$(( i * 100 / total ))
    echo "$pct" >&3
    echo "XXX" >&3
    echo "Step ${i}/${total}: ${step}" >&3
    echo "XXX" >&3
    run_step "$step" || true
  done
  echo 100 >&3
  exec 3>&-

  # 6) Summary (orientation report + any warnings).
  local summary
  summary="Anvil Phase 1 installation complete."
  summary+=$'\n\n'"Components processed: ${STEPS[*]}"
  summary+=$'\n\n'"Pre-install tool check:"
  local line
  for line in "${DETECT_REPORT[@]}"; do
    summary+=$'\n'"  ${line}"
  done
  summary+=$'\n'
  if [[ ${#WARNINGS[@]} -gt 0 ]]; then
    summary+=$'\n'"Warnings / issues:"
    local w
    for w in "${WARNINGS[@]}"; do
      summary+=$'\n'"  - ${w}"
    done
  else
    summary+=$'\n'"No warnings."
  fi

  ui_msg "Summary" "$summary"
}

main "$@"
