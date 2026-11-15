/*
 * Vancouver Events / Save Loadout
 * Local-only personal shortlist. No accounts, analytics, remote writes or RSVP.
 * CSV is for spreadsheet software; ICS is importable into calendar software.
 */
(function (root) {
  "use strict";

  const KEY = "suzy-vancouver-events-lineup-v1";
  const MAX_EVENTS = 100;

  function normalizeEvent(input) {
    if (!input || typeof input !== "object") return null;
    const id = String(input.id || "").trim();
    const title = String(input.title || "").trim().slice(0, 260);
    const start = Number(input.start);
    const end = Number(input.end) || 0;
    const url = String(input.url || "").trim();
    let safeURL;
    try {
      safeURL = new URL(url);
    } catch (_) {
      return null;
    }
    if (!/^[a-f0-9]{40}$/i.test(id) || !title || !Number.isInteger(start) || start < 946684800
      || !["https:", "http:"].includes(safeURL.protocol)) return null;
    return {
      id: id.toLowerCase(),
      title,
      start,
      end: Number.isInteger(end) && end > start ? end : 0,
      url: safeURL.href,
      location: String(input.location || "").slice(0, 240),
      source: String(input.source || "").slice(0, 200),
      timeNote: String(input.timeNote || "").slice(0, 200)
    };
  }

  function parseSaved(raw) {
    try {
      const value = JSON.parse(raw);
      if (!Array.isArray(value)) return [];
      const found = new Set();
      return value.slice(0, MAX_EVENTS * 2).map(normalizeEvent).filter((event) => {
        if (!event || found.has(event.id) || found.size >= MAX_EVENTS) return false;
        found.add(event.id);
        return true;
      });
    } catch (_) {
      return [];
    }
  }

  function escapeICSText(text) {
    return String(text || "").replace(/\\/g, "\\\\").replace(/\r\n|\r|\n/g, "\\n").replace(/;/g, "\\;").replace(/,/g, "\\,");
  }

  // RFC 5545 folds content lines after 75 octets, not 75 JS code units.
  function foldICS(line) {
    const encoder = typeof TextEncoder === "function" ? new TextEncoder() : null;
    let current = "";
    let bytes = 0;
    const out = [];
    for (const char of line) {
      const count = encoder ? encoder.encode(char).length : unescape(encodeURIComponent(char)).length;
      if (current && bytes + count > 73) {
        out.push(current);
        current = " " + char;
        bytes = 1 + count;
      } else {
        current += char;
        bytes += count;
      }
    }
    if (current) out.push(current);
    return out.join("\r\n");
  }

  function utcStamp(seconds) {
    return new Date(seconds * 1000).toISOString().replace(/[-:]/g, "").replace(/\.\d{3}/, "");
  }

  function makeICS(events, generatedAt) {
    const now = generatedAt instanceof Date ? generatedAt : new Date();
    const dtstamp = utcStamp(Math.floor(now.getTime() / 1000));
    const lines = [
      "BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//Suzy Easton//Vancouver Events Lineup//EN",
      "CALSCALE:GREGORIAN", "METHOD:PUBLISH", "X-WR-CALNAME:Vancouver Events - My Lineup"
    ];
    for (const e of events.map(normalizeEvent).filter(Boolean)) {
      const description = [
        "Saved from Vancouver Events. This is not an RSVP or ticket.",
        e.source ? "Listing source: " + e.source : "",
        e.timeNote ? "Time note: " + e.timeNote : "",
        "Confirm details with the organizer: " + e.url
      ].filter(Boolean).join("\n");
      lines.push("BEGIN:VEVENT", "UID:" + e.id + "@suzyeaston.ca",
        "DTSTAMP:" + dtstamp,
        "DTSTART:" + utcStamp(e.start));
      if (e.end > e.start) lines.push("DTEND:" + utcStamp(e.end));
      lines.push("SUMMARY:" + escapeICSText(e.title),
        "LOCATION:" + escapeICSText(e.location),
        "DESCRIPTION:" + escapeICSText(description),
        "URL:" + e.url,
        "STATUS:TENTATIVE", "END:VEVENT");
    }
    lines.push("END:VCALENDAR");
    return lines.map(foldICS).join("\r\n") + "\r\n";
  }

  function safeCSVCell(text) {
    let value = String(text == null ? "" : text);
    // Prevent a malicious event title/venue being interpreted as a spreadsheet formula.
    if (/^[\s]*[=+\-@]/.test(value) || /^[\t\r]/.test(value)) value = "'" + value;
    return '"' + value.replace(/"/g, '""') + '"';
  }

  function makeCSV(events) {
    const rows = [["Title", "Start (UTC)", "End (UTC)", "Venue", "Source", "Event URL", "Time note"]];
    for (const e of events.map(normalizeEvent).filter(Boolean)) {
      rows.push([e.title, new Date(e.start * 1000).toISOString(), e.end ? new Date(e.end * 1000).toISOString() : "",
        e.location, e.source, e.url, e.timeNote]);
    }
    return "\uFEFF" + rows.map((row) => row.map(safeCSVCell).join(",")).join("\r\n") + "\r\n";
  }

  function startUI(win, doc) {
    const panel = doc.getElementById("yve-lineup");
    if (!panel) return;
    const list = doc.getElementById("yve-lineup-items");
    const count = doc.getElementById("yve-lineup-count");
    const empty = doc.getElementById("yve-lineup-empty");
    const message = doc.getElementById("yve-lineup-message");
    const exports = Array.from(panel.querySelectorAll("[data-yve-export]"));
    const clear = doc.getElementById("yve-lineup-clear");
    const savedButtons = () => Array.from(doc.querySelectorAll("button[data-yve-save]"));
    let canPersist = true;
    let saved = [];
    try {
      saved = parseSaved(win.localStorage.getItem(KEY));
    } catch (_) {
      canPersist = false;
    }

    function persist() {
      if (!canPersist) return;
      try {
        win.localStorage.setItem(KEY, JSON.stringify(saved));
      } catch (_) {
        canPersist = false;
      }
    }

    function say(text) {
      if (message) message.textContent = text;
    }

    function formatDate(timestamp) {
      return new Intl.DateTimeFormat("en-CA", {
        timeZone: "America/Vancouver", weekday: "short", month: "short", day: "numeric",
        hour: "numeric", minute: "2-digit", timeZoneName: "short"
      }).format(new Date(timestamp * 1000));
    }

    function render() {
      count.textContent = String(saved.length);
      if (empty) empty.hidden = saved.length !== 0;
      for (const button of exports) button.disabled = !saved.length;
      if (clear) clear.disabled = !saved.length;
      savedButtons().forEach((button) => {
        let event = null;
        try { event = normalizeEvent(JSON.parse(button.dataset.yveEvent)); } catch (_) {}
        const active = !!event && saved.some((e) => e.id === event.id);
        button.setAttribute("aria-pressed", String(active));
        button.textContent = active ? "♥ Saved" : "♡ Save event";
      });
      list.replaceChildren();
      [...saved].sort((a, b) => a.start - b.start).forEach((event) => {
        const li = doc.createElement("li");
        li.className = "yve__saved-item";
        const details = doc.createElement("div");
        const title = doc.createElement("a");
        title.href = event.url;
        title.target = "_blank";
        title.rel = "noopener noreferrer";
        title.textContent = event.title;
        const date = doc.createElement("p");
        date.textContent = formatDate(event.start) + (event.location ? " · " + event.location : "");
        details.append(title, date);
        const remove = doc.createElement("button");
        remove.type = "button";
        remove.className = "yve__remove";
        remove.textContent = "Remove";
        remove.setAttribute("aria-label", "Remove " + event.title + " from saved lineup");
        remove.addEventListener("click", () => {
          saved = saved.filter((item) => item.id !== event.id);
          persist();
          render();
          say("Removed from your lineup.");
        });
        li.append(details, remove);
        list.append(li);
      });
      if (!canPersist) {
        say("Browser storage isn't available. Your picks will last only until this page closes. Export them to keep a copy.");
      }
    }

    function download(filename, text, mime) {
      const blob = new Blob([text], { type: mime });
      const url = win.URL.createObjectURL(blob);
      const anchor = doc.createElement("a");
      anchor.href = url;
      anchor.download = filename;
      doc.body.append(anchor);
      anchor.click();
      anchor.remove();
      win.setTimeout(() => win.URL.revokeObjectURL(url), 1000);
    }

    doc.addEventListener("click", (event) => {
      const button = event.target.closest("button[data-yve-save]");
      if (!button) return;
      let selected = null;
      try { selected = normalizeEvent(JSON.parse(button.dataset.yveEvent)); } catch (_) {}
      if (!selected) { say("This listing couldn't be saved."); return; }
      if (saved.some((e) => e.id === selected.id)) {
        saved = saved.filter((e) => e.id !== selected.id);
        say("Removed from your lineup.");
      } else {
        if (saved.length >= MAX_EVENTS) { say("Lineup full. Remove a saved event to add another."); return; }
        saved.push(selected);
        say("Saved! Your event is in My Lineup.");
      }
      persist();
      render();
    });

    exports.forEach((button) => button.addEventListener("click", () => {
      if (!saved.length) return;
      const ordered = [...saved].sort((a, b) => a.start - b.start);
      if (button.dataset.yveExport === "ics") {
        download("vancouver-events-lineup.ics", makeICS(ordered), "text/calendar;charset=utf-8");
        say("Calendar file downloaded. Import it into Apple, Google or Outlook Calendar. No invitation or RSVP was sent.");
      } else if (button.dataset.yveExport === "csv") {
        download("vancouver-events-lineup.csv", makeCSV(ordered), "text/csv;charset=utf-8");
        say("Spreadsheet CSV downloaded.");
      }
    }));

    if (clear) clear.addEventListener("click", () => {
      if (!saved.length || !win.confirm("Remove all saved Vancouver events from this browser?")) return;
      saved = [];
      persist();
      render();
      say("Your saved lineup is empty.");
    });

    render();
  }

  const api = { normalizeEvent, parseSaved, escapeICSText, foldICS, makeICS, makeCSV };
  if (typeof module !== "undefined" && module.exports) module.exports = api;
  if (root && root.document) {
    if (root.document.readyState === "loading") {
      root.document.addEventListener("DOMContentLoaded", () => startUI(root, root.document));
    } else {
      startUI(root, root.document);
    }
  }
})(typeof window !== "undefined" ? window : null);
