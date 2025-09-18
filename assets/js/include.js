async function includePartials() {
  const nodes = document.querySelectorAll("[data-include]");
  await Promise.all([...nodes].map(async (el) => {
    const url = el.getAttribute("data-include");
    const res = await fetch(url, { cache: "no-cache" });
    el.outerHTML = await res.text();
  }));

  // Jahr im Footer
  const y = document.getElementById("year");
  if (y) y.textContent = new Date().getFullYear();

  // Aktive Tabs markieren
  const page = document.body.dataset.page;
  if (page) {
    document.querySelectorAll(".tabs a").forEach(a => {
      const href = a.getAttribute("href");
      const name = href.replace(".html", "");
      if (name === page) a.classList.add("active");
      else a.classList.remove("active");
    });
  }

  // Theme-Button erst NACH dem Laden der Partials initialisieren
  setupThemeToggle();

  // Transitions erst nach dem ersten Paint aktivieren (kein Initial-Flackern)
  enableThemeTransitions();
}

/* ---------------- THEME TOGGLE ---------------- */
function applyStoredTheme() {
  const root = document.documentElement; // <html>
  const pref = localStorage.getItem("theme"); // "light" | "dark" | null
  if (pref === "light" || pref === "dark") {
    root.setAttribute("data-theme", pref);
  } else {
    root.removeAttribute("data-theme"); // System (prefers-color-scheme)
  }
  updateThemeButtonIcon();
}

function cycleTheme() {
  // Safety: falls sehr früh geklickt wird, Transition sicher aktivieren
  enableThemeTransitions();

  const current = document.documentElement.getAttribute("data-theme"); // "light" | "dark" | null
  const next = current === "dark" ? "light" : "dark";
  localStorage.setItem("theme", next);
  applyStoredTheme();
}

function resetToSystem() {
  localStorage.removeItem("theme");
  applyStoredTheme();
}

function updateThemeButtonIcon() {
  const btn = document.getElementById("theme-toggle");
  if (!btn) return;
  const current = document.documentElement.getAttribute("data-theme");
  // Icon: wenn dark aktiv ⇒ Sonne, sonst Mond
  btn.textContent = current === "dark" ? "☀️" : "🌙";
  btn.title = current === "dark"
    ? "Switch to light (long-press for system)"
    : "Switch to dark (long-press for system)";
}

function setupThemeToggle() {
  applyStoredTheme();
  const btn = document.getElementById("theme-toggle");
  if (!btn) return;

  // Klick: Dark <-> Light
  btn.addEventListener("click", cycleTheme);

  // Langdruck (~600ms) setzt auf System zurück
  let pressTimer = null;
  btn.addEventListener("mousedown", () => {
    pressTimer = setTimeout(resetToSystem, 600);
  });
  ["mouseup", "mouseleave"].forEach(ev => {
    btn.addEventListener(ev, () => {
      if (pressTimer) clearTimeout(pressTimer);
    });
  });

  // Optional: Rechtsklick = System
  btn.addEventListener("contextmenu", (e) => {
    e.preventDefault();
    resetToSystem();
  });
}

/* ---------------- THEME TRANSITION ENABLE ---------------- */
function enableThemeTransitions() {
  // Erst nach zwei Frames aktivieren → garantiert nach initialem Paint
  requestAnimationFrame(() => {
    requestAnimationFrame(() => {
      document.documentElement.classList.add("theme-anim");
    });
  });
}

document.addEventListener("DOMContentLoaded", includePartials);