# RUNBOOK-BLUETOOTH.md — Receive a file from Android to Xubuntu via Bluetooth (OBEX)

**Use case:** send a `.txt` (or any file) from an Android phone to your Xubuntu machine over Bluetooth, using
the Bluez OBEX tooling. No GUI required.

**Verified path (CLI):** `bluetoothctl` to pair/trust → `obexctl` (or `obexpushd`) to receive.

---

## 1. One-time: pair the devices

**On Xubuntu:**
```bash
bluetoothctl
[bluetooth]# power on
[bluetooth]# agent on
[bluetooth]# default-agent
[bluetooth]# scan on
# wait for your Android device name to appear, then:
[bluetooth]# pair XX:XX:XX:XX:XX:XX   # replace with your Android MAC
[bluetooth]# trust XX:XX:XX:XX:XX:XX
[bluetooth]# quit
```
**On Android:** accept the pairing prompt.

## 2. Start the OBEX file receiver

**Option A — `obexctl` (interactive, recommended):**
```bash
obexctl
[obex]# agent on
[obex]# accept yes
```
This registers an agent that auto-accepts incoming pushes. Leave it running.

**Option B — `obexpushd` (listen-only daemon):**
```bash
obexpushd -B -o ~/Downloads/
# file goes straight to ~/Downloads/
```

If `obexctl` says *"No such interface 'org.bluez.obex.Agent1'"*, the obex service isn't running:
```bash
/usr/lib/bluetooth/obexd &
# or: systemctl --user start obex
```

## 3. Send from Android

1. Open your file manager, find the `.txt`.
2. Long-press → **Share** → **Bluetooth**.
3. Tap your Xubuntu computer name → **Send**.

## 4. Accept on Xubuntu

With `obexctl` running, a prompt appears:
```text
[NEW] Session /org/bluez/obex/client/sessionX
RequestAuthorization (/org/bluez/obex/client/sessionX)
Authorize (/org/bluez/obex/client/sessionX, incoming file)?
```
Type `y`. Watch:
```text
Transfer /org/bluez/obex/client/sessionX/transferY
        Status: active
        Name: yourfile.txt
        Size: 1234
```
When it says `complete`, type `quit`.

## 5. Where did the file go?

```bash
ls ~/Downloads/
# or check the transfer path shown in obexctl
# force a specific folder: export XDG_DOWNLOAD_DIR="$HOME/Downloads"
```

## 6. One-shot receive (no per-file prompt)

Leave `obexctl` running with `agent on` + `accept yes`. Every file sent from the paired Android auto-accepts.

## 7. Troubleshooting

| Symptom | Fix |
|---|---|
| No Bluetooth adapter shown | `lsusb | grep -i bluetooth`; enable the adapter / plug a dongle |
| `bluetoothctl` shows powered off | `sudo systemctl start bluetooth && sudo systemctl enable bluetooth` |
| `obexctl` "No such interface" | start `obexd` (`/usr/lib/bluetooth/obexd &`) or `systemctl --user start obex` |
| Pairing fails | remove device in `bluetoothctl` (`remove XX:XX…`), re-scan, re-pair |

---

### Provenance

Reconstructed from `Design_Models_Misc/2026-08-10-Check Repo Updates-Kimi.md` (the "send a txt through Bluetooth
from Android to Xubuntu" thread: `bluetoothctl` pairing, `obexctl`/`obexpushd` receive, troubleshooting). This is
a developer-environment ops runbook, not architecture — included because it lived alongside the other
consolidated material.
