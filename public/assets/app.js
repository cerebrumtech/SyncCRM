/* SyncCRM front-end behaviours: dialogs, confirms, pickers, tabs, kanban, line items. No framework. */
(function () {
  "use strict";
  // Web font, requested after the page is usable. It is deliberately not a <link> in the
  // markup: a render-blocking stylesheet on a third-party host stalls every page when that
  // host is slow or unreachable, and an inline onload to avoid that needs a CSP exception.
  try {
    var f = document.createElement("link");
    f.rel = "stylesheet";
    f.href = "https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap";
    document.head.appendChild(f);
  } catch (e) {}
  const $ = (sel, root) => (root || document).querySelector(sel);
  const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));
  const csrfName = () => ($("meta[name=csrf-name]") || {}).content || "csrf_token";
  const csrfHash = () => ($("meta[name=csrf-token]") || {}).content || "";

  window.postJSON = async function (url, data) {
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": csrfHash(), "X-Requested-With": "XMLHttpRequest", Accept: "application/json" },
      body: JSON.stringify(data || {}),
      credentials: "same-origin",
    });
    let json = null;
    try { json = await res.json(); } catch (e) { json = { ok: false, error: "Unexpected response (" + res.status + ")" }; }
    return json;
  };
  window.getJSON = async function (url) {
    const res = await fetch(url, { headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" }, credentials: "same-origin" });
    try { return await res.json(); } catch (e) { return { ok: false, error: "Unexpected response" }; }
  };

  // Dialogs: <button data-open="id">, <button data-close>, auto-open after a failed submit
  function openDialog(id) {
    const d = document.getElementById(id);
    if (d && typeof d.showModal === "function" && !d.open) d.showModal();
  }
  window.openDialog = openDialog;
  document.addEventListener("click", (e) => {
    const o = e.target.closest("[data-open]");
    if (o) { e.preventDefault(); openDialog(o.getAttribute("data-open")); const fill = o.getAttribute("data-fill"); if (fill) { try { const data = JSON.parse(fill); const d = document.getElementById(o.getAttribute("data-open")); const fm = d.querySelector("form"); if (fm && !o.hasAttribute("data-no-reset")) { fm.reset(); $$("[data-picker]", fm).forEach((pk) => pk.clear && pk.clear()); const lg = fm.querySelector("[name=logged]"); if (lg) lg.value = ""; } Object.entries(data).forEach(([k, v]) => { if (k === "_title") { const h = d.querySelector("[data-dialog-title]"); if (h) h.textContent = v; return; } if (k.startsWith("_")) return; const el = d.querySelector('[name="' + k + '"]'); if (!el) { const pk = d.querySelector('[data-picker][data-name="' + k + '"]'); if (pk && pk.set) pk.set(v == null ? "" : v, data[k.replace(/_id$/, "_label")] || ""); return; } if (el.type === "checkbox") el.checked = !!v; else el.value = v == null ? "" : v; el.dispatchEvent(new Event("change", { bubbles: true })); }); const act = o.getAttribute("data-action"); if (act) d.querySelector("form").action = act; d.dispatchEvent(new Event("fill")); } catch (err) {} } return; }
    const c = e.target.closest("[data-close]");
    if (c) { e.preventDefault(); const d = c.closest("dialog"); if (d) d.close(); return; }
    const t = e.target.closest("[data-tab]");
    if (t) { e.preventDefault(); const group = t.closest("[data-tabs]"); $$("[data-tab]", group).forEach((x) => x.classList.toggle("active", x === t)); $$("[data-panel]", group.parentElement).forEach((p) => (p.hidden = p.getAttribute("data-panel") !== t.getAttribute("data-tab"))); try { history.replaceState(null, "", "#" + t.getAttribute("data-tab")); } catch (err) {} return; }
    const dd = e.target.closest("[data-dropdown]");
    if (dd) { e.preventDefault(); const menu = dd.nextElementSibling; const open = !menu.hidden; $$(".dropdown").forEach((m) => (m.hidden = true)); menu.hidden = open; return; }
    if (!e.target.closest(".dropdown")) $$(".dropdown").forEach((m) => (m.hidden = true));
    const ts = e.target.closest("[data-toggle-sidebar]");
    if (ts) { const sb = $("#sidebar"); sb.classList.toggle("hidden"); sb.classList.toggle("flex"); sb.classList.toggle("absolute"); sb.classList.toggle("inset-y-0"); sb.classList.toggle("z-40"); }
  });
  document.addEventListener("click", (e) => { const d = e.target; if (d instanceof HTMLDialogElement && d.open) { const r = d.getBoundingClientRect(); if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) d.close(); } });
  document.addEventListener("submit", (e) => {
    const f = e.target;
    if (f.matches("[data-confirm]") && !window.confirm(f.getAttribute("data-confirm"))) { e.preventDefault(); return; }
    const btn = f.querySelector("button[type=submit]:not([data-no-disable])");
    if (btn && !f.hasAttribute("data-keep-enabled")) setTimeout(() => { btn.disabled = true; btn.dataset.label = btn.textContent; btn.textContent = "Please wait…"; }, 0);
  });
  window.addEventListener("pageshow", () => $$("button[data-label]").forEach((b) => { b.disabled = false; b.textContent = b.dataset.label; }));

  document.addEventListener("DOMContentLoaded", () => {
    const auto = $("[data-auto-open]");
    if (auto) openDialog(auto.getAttribute("data-auto-open"));
    if (location.hash) { const tab = $('[data-tab="' + location.hash.slice(1) + '"]'); if (tab) tab.click(); }
    $$("[data-picker]").forEach(initPicker);
    $$("[data-kanban]").forEach(initKanban);
    $$("[data-line-items]").forEach(initLineItems);
    $$("[data-stage-select]").forEach(initStageSelect);
    $$("[data-pipeline-select]").forEach((sel) => { const stage = sel.form.querySelector("[data-stage-for-pipeline]"); const sync = () => { let firstVisible = null; Array.from(stage.options).forEach((o) => { const show = o.getAttribute("data-pipeline") === sel.value; o.hidden = !show; if (show && !firstVisible) firstVisible = o; }); if (!stage.selectedOptions[0] || stage.selectedOptions[0].hidden) stage.value = firstVisible ? firstVisible.value : ""; }; sel.addEventListener("change", sync); sync(); });
    $$("[data-copy]").forEach((b) => b.addEventListener("click", () => { navigator.clipboard.writeText(b.getAttribute("data-copy")).then(() => { b.textContent = "Copied"; }); }));
    $$("[data-activity-form]").forEach(initActivityForm);
    // Custom fields (Settings > Fields): the Options box only applies to a dropdown.
    $$("[data-field-type]").forEach((t) => {
      const opts = document.querySelector("[data-options-field]");
      if (!opts) return;
      const sync = () => { opts.hidden = t.value !== "SELECT"; };
      t.addEventListener("change", sync);
      sync();
    });

    // Pipelines (Settings > Pipelines): opening the stage editor ticks the
    // required-field boxes that stage already has.
    document.addEventListener("click", (e) => {
      const b = e.target.closest("[data-open=stage-edit-dialog]");
      if (!b) return;
      let req = [];
      try { req = JSON.parse(b.getAttribute("data-required") || "[]"); } catch (err) { req = []; }
      $$("#stage-edit-dialog input[name='required_fields[]']").forEach((cb) => { cb.checked = req.includes(cb.value); });
    });

    // Activities: when arriving from a link that points at one activity, highlight it.
    const focus = document.querySelector("[data-focus-activity]");
    if (focus) {
      const el = document.getElementById("activity-" + focus.getAttribute("data-focus-activity"));
      if (el) { el.classList.add("bg-primary-50"); el.scrollIntoView({ block: "center" }); }
    }

    $$("[data-check-all]").forEach((cb) => cb.addEventListener("change", () => $$(cb.getAttribute("data-check-all")).forEach((x) => (x.checked = cb.checked))));
    $$("[data-submit-on-change]").forEach((el) => el.addEventListener("change", () => el.form && el.form.requestSubmit()));
  });

  // Record picker: <div data-picker="/api/search/contacts" data-name="contact_id" data-value="12" data-label="Priya Shah">
  function initPicker(root) {
    const url = root.getAttribute("data-picker");
    const name = root.getAttribute("data-name");
    const hidden = document.createElement("input"); hidden.type = "hidden"; hidden.name = name; hidden.value = root.getAttribute("data-value") || "";
    const input = document.createElement("input"); input.type = "text"; input.className = "input"; input.placeholder = root.getAttribute("data-placeholder") || "Search…"; input.autocomplete = "off"; input.value = root.getAttribute("data-label") || "";
    const list = document.createElement("div"); list.className = "picker-list"; list.hidden = true;
    root.classList.add("relative"); root.append(hidden, input, list);
    let timer = null;
    input.addEventListener("input", () => { hidden.value = ""; clearTimeout(timer); const q = input.value.trim(); if (!q) { list.hidden = true; return; } timer = setTimeout(async () => { const r = await getJSON(url + "?q=" + encodeURIComponent(q)); list.innerHTML = ""; (r.data || []).forEach((row) => { const b = document.createElement("button"); b.type = "button"; b.innerHTML = "<span class='font-medium'>" + esc(row.label) + "</span>" + (row.sub ? " <span class='muted'>" + esc(row.sub) + "</span>" : ""); b.addEventListener("click", () => { hidden.value = row.id; input.value = row.label; list.hidden = true; root.dispatchEvent(new CustomEvent("picked", { detail: row, bubbles: true })); }); list.appendChild(b); }); if (!(r.data || []).length) { const p = document.createElement("div"); p.className = "px-3 py-2 text-[13px] muted"; p.textContent = "No matches"; list.appendChild(p); } list.hidden = false; }, 180); });
    input.addEventListener("blur", () => setTimeout(() => (list.hidden = true), 150));
    input.addEventListener("focus", () => { if (list.children.length && input.value.trim()) list.hidden = false; });
    root.clear = () => { hidden.value = ""; input.value = ""; };
    root.set = (id, label) => { hidden.value = id; input.value = label; };
  }
  function esc(s) { return String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c])); }

  // Kanban: columns <div data-column="stageId">, cards <div data-deal="id">. POST /deals/{id}/move
  function initKanban(board) {
    if (typeof Sortable === "undefined") return;
    $$("[data-column]", board).forEach((col) => {
      new Sortable(col, {
        group: "deals", animation: 150, ghostClass: "sortable-ghost", chosenClass: "sortable-chosen", draggable: "[data-deal]",
        onEnd: async (evt) => {
          const dealId = evt.item.getAttribute("data-deal");
          const stageId = evt.to.getAttribute("data-column");
          const position = evt.newIndex;
          const fromStage = evt.from.getAttribute("data-column");
          if (fromStage === stageId && evt.oldIndex === evt.newIndex) return;
          const r = await postJSON("/deals/" + dealId + "/move", { stage_id: stageId, position });
          if (!r.ok) { alert(r.error || "Could not move the deal."); location.reload(); return; }
          if (r.data && r.data.missing && r.data.missing.length) { alert("To be in this stage the deal needs: " + r.data.missing.join(", ") + "."); location.reload(); return; }
          if (r.data && r.data.needsLostReason) { const d = $("#lost-reason-dialog"); d.querySelector("[name=deal_id]").value = dealId; d.querySelector("[name=stage_id]").value = stageId; d.querySelector("[name=position]").value = position; openDialog("lost-reason-dialog"); return; }
          location.reload();
        },
      });
    });
    const lostForm = $("#lost-reason-dialog form");
    if (lostForm) lostForm.addEventListener("submit", async (e) => { e.preventDefault(); const fd = new FormData(lostForm); const r = await postJSON("/deals/" + fd.get("deal_id") + "/move", { stage_id: fd.get("stage_id"), position: Number(fd.get("position")), lost_reason: fd.get("lost_reason") }); if (!r.ok) alert(r.error || "Could not move the deal."); location.reload(); });
    $("#lost-reason-dialog") && $("#lost-reason-dialog").addEventListener("close", () => { if (!$("#lost-reason-dialog").returnValue) location.reload(); });
  }

  // Stage stepper on the deal page: <select data-stage-select data-deal="id">
  function initStageSelect(sel) {
    sel.addEventListener("change", async () => {
      const dealId = sel.getAttribute("data-deal");
      const r = await postJSON("/deals/" + dealId + "/move", { stage_id: sel.value, position: null });
      if (!r.ok) { alert(r.error || "Could not change the stage."); location.reload(); return; }
      if (r.data && r.data.missing && r.data.missing.length) { alert("To move to this stage the deal needs: " + r.data.missing.join(", ") + "."); location.reload(); return; }
      if (r.data && r.data.needsLostReason) { const d = $("#lost-reason-dialog"); d.querySelector("[name=deal_id]").value = dealId; d.querySelector("[name=stage_id]").value = sel.value; d.querySelector("[name=position]").value = ""; openDialog("lost-reason-dialog"); const f = d.querySelector("form"); f.onsubmit = async (e) => { e.preventDefault(); const fd = new FormData(f); const rr = await postJSON("/deals/" + dealId + "/move", { stage_id: sel.value, position: null, lost_reason: fd.get("lost_reason") }); if (!rr.ok) alert(rr.error); location.reload(); }; return; }
      location.reload();
    });
  }

  // Activity dialog: sections per type, all-day toggle, logged-call extras
  function initActivityForm(form) {
    const type = $("[data-activity-type]", form), allDay = $("[data-all-day]", form), due = $("[name=due_at]", form), logged = $("[name=logged]", form);
    const sync = () => {
      $$("[data-type-only]", form).forEach((s) => { const show = s.getAttribute("data-type-only") === type.value; s.hidden = !show; $$("input,select", s).forEach((i) => (i.disabled = !show)); });
      $("[data-due-label]", form).textContent = type.value === "EVENT" ? "Starts" : type.value === "CALL" ? "When" : "Due";
      due.required = type.value === "EVENT";
      const isLogged = type.value === "CALL" && ["on", "true", "1"].includes(String(logged.value));
      const lo = $("[data-logged-only]", form); lo.hidden = !isLogged; $$("input", lo).forEach((i) => (i.disabled = !isLogged));
      $("[data-submit-label]", form).textContent = isLogged ? "Log call" : "Save";
      const v = due.value; const wantType = allDay.checked ? "date" : "datetime-local"; if (due.type !== wantType) { due.type = wantType; due.value = allDay.checked ? v.slice(0, 10) : (v.length === 10 ? v + "T10:00" : v); }
      $("[name=end_at]", form).disabled = allDay.checked || type.value !== "EVENT";
    };
    type.addEventListener("change", sync); allDay.addEventListener("change", sync);
    form.closest("dialog").addEventListener("fill", sync);
    sync();
  }

  // Line items editor
  function initLineItems(root) {
    const tbody = $("tbody", root);
    const products = JSON.parse(root.getAttribute("data-products") || "[]");
    const rowTpl = $("template", root);
    function recalc() {
      let subtotal = 0, tax = 0;
      $$("tr[data-row]", tbody).forEach((tr) => {
        const q = parseFloat($("[name$='[quantity]']", tr).value) || 0, p = parseFloat($("[name$='[unit_price]']", tr).value) || 0, d = parseFloat($("[name$='[discount_percent]']", tr).value) || 0, t = parseFloat($("[name$='[tax_rate]']", tr).value) || 0;
        const net = q * p * (1 - d / 100); const total = net * (1 + t / 100);
        subtotal += net; tax += net * (t / 100);
        $("[data-total]", tr).textContent = fmt(total);
      });
      $("[data-subtotal]", root).textContent = fmt(subtotal); $("[data-tax]", root).textContent = fmt(tax); $("[data-grand]", root).textContent = fmt(subtotal + tax);
      renumber();
    }
    function fmt(n) { return "₹" + Math.round(n).toLocaleString("en-IN"); }
    function renumber() { $$("tr[data-row]", tbody).forEach((tr, i) => $$("[name]", tr).forEach((el) => (el.name = el.name.replace(/items\[\d+\]/, "items[" + i + "]")))); }
    function addRow(data) {
      const tr = rowTpl.content.firstElementChild.cloneNode(true);
      tbody.appendChild(tr);
      if (data) Object.entries(data).forEach(([k, v]) => { const el = $("[name$='[" + k + "]']", tr); if (el) el.value = v; });
      const sel = $("select[name$='[product_id]']", tr);
      sel.addEventListener("change", () => { const p = products.find((x) => String(x.id) === sel.value); if (p) { $("[name$='[name]']", tr).value = p.name; $("[name$='[unit_price]']", tr).value = p.price; $("[name$='[tax_rate]']", tr).value = p.tax_rate; } recalc(); });
      $$("input", tr).forEach((i) => i.addEventListener("input", recalc));
      $("[data-remove]", tr).addEventListener("click", () => { tr.remove(); recalc(); });
      recalc();
    }
    $("[data-add-row]", root).addEventListener("click", () => addRow());
    JSON.parse(root.getAttribute("data-items") || "[]").forEach(addRow);
    recalc();
  }
})();
