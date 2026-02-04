#!/usr/bin/env bash

detect_pkg() {
    local image="$1"
    local CRANE_BIN="${CRANE:-crane}"

    # 1. Stufe: History durchsuchen (Schnell)
    local history
    history=$("$CRANE_BIN" config "$image" 2>/dev/null | jq -r '.history[].created_by // empty' 2>/dev/null)

    if [ -n "$history" ]; then
        if echo "$history" | grep -qi "apk"; then echo "apk"; return; fi
        if echo "$history" | grep -qiE "debian|ubuntu|apt"; then echo "apt"; return; fi
        if echo "$history" | grep -qiE "yum|dnf|centos|fedora"; then echo "yum"; return; fi
        if echo "$history" | grep -qi "zypper"; then echo "zypper"; return; fi
        if echo "$history" | grep -qi "pacman"; then echo "pacman"; return; fi
        if echo "$history" | grep -qi "busybox"; then echo "busybox"; return; fi
    fi

    # 2. Stufe: Dateisystem durchsuchen (Gründlich, falls History nicht eindeutig)
    # Wir nutzen 'crane export' um die Dateiliste zu streamen, ohne das Image zu laden
    local files
    # Wir suchen nach markanten Dateien/Verzeichnissen
    files=$("$CRANE_BIN" export "$image" - 2>/dev/null | tar -tf - 2>/dev/null)

    if [ -n "$files" ]; then
        if echo "$files" | grep -E "etc/apk/|lib/apk/|sbin/apk" >/dev/null 2>&1; then echo "apk"; return; fi
        if echo "$files" | grep -E "usr/bin/apt|var/lib/dpkg/" >/dev/null 2>&1; then echo "apt"; return; fi
        if echo "$files" | grep -E "usr/bin/yum|usr/bin/dnf|etc/yum.repos.d/" >/dev/null 2>&1; then echo "yum"; return; fi
        if echo "$files" | grep -E "usr/bin/zypper|etc/zypp/" >/dev/null 2>&1; then echo "zypper"; return; fi
        if echo "$files" | grep -E "usr/bin/pacman|etc/pacman.conf" >/dev/null 2>&1; then echo "pacman"; return; fi
        if echo "$files" | grep "bin/busybox" >/dev/null 2>&1; then echo "busybox"; return; fi
    fi

    # 3. Spezialfall: Scratch / Distroless (nur wenn History vorhanden aber leer/minimal)
    if [ -n "$history" ]; then
        if echo "$history" | grep -qiE "scratch|distroless"; then
            echo "none"
            return
        fi
    fi

    # Wenn wir gar nichts gefunden haben, aber files/history da waren, ist es vielleicht wirklich leer oder unbekannt
    if [ -z "$history" ] && [ -z "$files" ]; then
        echo "unknown"
    else
        echo "unknown"
    fi
}

detect_pkg "$1"
