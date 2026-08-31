# RUNBOOK-ANVIL-DNS.md — Anvil broke DNS after install on Xubuntu

**Symptom:** after installing Anvil (the repo's AWS EC2 provisioning tool) on Xubuntu, you can't reach the
internet — even though the machine has a working network connection.

**Verified root cause:** Anvil's install left `/etc/resolv.conf` pointing at `nameserver 127.0.0.1`. A local
resolver (systemd-resolved vs. NetworkManager's dnsmasq) is contending for port 53, and the one bound to
127.0.0.1 either isn't running or isn't authoritative — so every hostname lookup fails. `8.8.8.8` may still
ping (routing is fine); `google.com` fails (DNS is broken).

> **Source note:** the original chat contained *speculative* "quick fixes" (e.g., blindly stopping WireGuard/
> Tailscale) because the user hadn't yet posted diagnostics. The actual posted diagnostic was just
> `nameserver 127.0.0.1`. This runbook is written for that confirmed state. If `ping 8.8.8.8` also fails, the
> problem is routing/interface, not DNS — stop and re-diagnose.

---

## 1. Diagnose (confirm before fixing)

```bash
# DNS vs routing split:
ping -c 3 127.0.0.1        # loopback
ping -c 3 8.8.8.8          # public IP — does it work? (bypasses DNS)
ping -c 3 google.com      # hostname — does it fail? (tests DNS)

# What's in resolv.conf?
cat /etc/resolv.conf
ls -la /etc/resolv.conf   # symlink to /run/systemd/resolve/stub-resolv.conf?

# Who is managing DNS / holding port 53?
systemctl status systemd-resolved
nmcli device status
sudo ss -lunp | grep ':53'   # or: sudo netstat -lunp | grep ':53'
```

Decision: if `8.8.8.8` works but `google.com` fails **and** `resolv.conf` says `nameserver 127.0.0.1` → this
runbook applies.

## 2. Immediate fix — restore public DNS (gets you back online now)

```bash
# If resolv.conf is a plain file, just rewrite it:
sudo rm -f /etc/resolv.conf
echo "nameserver 8.8.8.8" | sudo tee /etc/resolv.conf
echo "nameserver 1.1.1.1" | sudo tee -a /etc/resolv.conf

# If it's a symlink to systemd-resolved's stub, instead restart the resolver:
sudo systemctl restart systemd-resolved
nmcli networking on
```

Test: `ping -c 3 google.com` should now succeed.

## 3. Permanent fix — stop the 53-port contention (survives reboot)

The clean fix is to make NetworkManager's dnsmasq plugin the single DNS path, not a system dnsmasq bound to
53, and not let Anvil's installer hijack `resolv.conf`.

```bash
# Preferred: use NetworkManager's dnsmasq plugin (not a standalone system dnsmasq on port 53)
# /etc/NetworkManager/NetworkManager.conf:
#   [main]
#   dns=dnsmasq
sudo systemctl restart NetworkManager

# Confirm resolv.conf now points at NetworkManager's dnsmasq (usually 127.0.0.1 managed by NM),
# and that NM's dnsmasq is the process on :53 — not a stray systemd/standalone one.
sudo ss -lunp | grep ':53'

# If a stray standalone dnsmasq/systemd-resolved is still fighting, disable the conflicting unit:
# (only after confirming NM dnsmasq is healthy)
# sudo systemctl disable --now <conflicting-unit>
```

If `/etc/resolv.conf` is a symlink to `/run/systemd/resolve/stub-resolv.conf`, ensure `systemd-resolved` is
the authoritative resolver and is actually running — that symlink state survives reboot.

## 4. Verify

```bash
ping -c 3 google.com
resolvectl status        # or: systemd-resolve --status
getent hosts google.com
```

All three should return a resolved address.

## 5. Prevention (for the DGLab team)

- Anvil's installer should **not** overwrite `resolv.conf` with `127.0.0.1` unless it also starts and verifies a
  working local resolver. Track as a known hazard in `MEMORY.md` §9 and `MEMORY-GOVERNANCE.md` §A.5 (#9).
- Prefer the NetworkManager dnsmasq plugin over a standalone dnsmasq bound to port 53.

---

### Provenance

Reconstructed from `Design_Models_Misc/2026-08-10-Check Repo Updates-Kimi.md` (the "installed anvil… can't
connect to the Internet" thread and the `nameserver 127.0.0.1` diagnostic). Speculative fixes from the pre-
diagnostic part of the thread were excluded; this runbook targets the confirmed `127.0.0.1` state.
