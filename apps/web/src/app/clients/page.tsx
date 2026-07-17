"use client";

import { AppShell } from "@/components/AppShell";
import { EmptyState } from "@/components/EmptyState";
import { Modal } from "@/components/Modal";
import { PageHeader } from "@/components/PageHeader";
import { StatusPill } from "@/components/StatusPill";
import { apiFetch, ApiError } from "@/lib/api";
import type { Client, Paginated } from "@/lib/types";
import { FormEvent, useCallback, useEffect, useState } from "react";

const emptyForm = { name: "", contact_name: "", contact_email: "", status: "onboarding", notes: "" };

export default function ClientsPage() {
  const [clients, setClients] = useState<Client[]>([]);
  const [loading, setLoading] = useState(true);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState(emptyForm);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try { const response = await apiFetch<Paginated<Client>>("/clients?per_page=100"); setClients(response.data); setError(""); }
    catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to load clients."); }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { void load(); }, [load]);

  async function submit(event: FormEvent) {
    event.preventDefault(); setSubmitting(true); setError("");
    try {
      await apiFetch("/clients", { method: "POST", body: JSON.stringify(form) });
      setOpen(false); setForm(emptyForm); setSuccess("Client added successfully."); await load();
    } catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to add client."); }
    finally { setSubmitting(false); }
  }

  return <AppShell><main className="main"><PageHeader eyebrow="Client operations" title="Clients" description="Onboard Amazon sellers and keep each client workspace isolated." actions={<button className="primary" onClick={() => setOpen(true)}>+ Add client</button>} />{error && <div className="errorBox">{error}</div>}{success && <div className="successBox">{success}</div>}<section className="panel"><div className="panelHeader"><div><h2>Client portfolio</h2><p>{clients.length} client workspace{clients.length === 1 ? "" : "s"}</p></div></div>{loading ? <div className="loadingPanel">Loading clients…</div> : clients.length === 0 ? <EmptyState title="No clients yet" description="Add a real seller client. No demonstration data is inserted." /> : <div className="tableWrap"><table><thead><tr><th>Client</th><th>Contact</th><th>Status</th><th>Amazon accounts</th><th>Products</th><th>Tasks</th></tr></thead><tbody>{clients.map((client) => <tr key={client.id}><td><strong>{client.name}</strong></td><td>{client.contact_name || "—"}<small className="cellSubtext">{client.contact_email || ""}</small></td><td><StatusPill value={client.status} /></td><td>{client.channel_accounts_count ?? 0}</td><td>{client.products_count ?? 0}</td><td>{client.tasks_count ?? 0}</td></tr>)}</tbody></table></div>}</section><Modal title="Add client" open={open} onClose={() => setOpen(false)}><form className="formStack" onSubmit={submit}><label>Client or brand name<input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} required /></label><div className="formGrid"><label>Contact name<input value={form.contact_name} onChange={(event) => setForm({ ...form, contact_name: event.target.value })} /></label><label>Contact email<input type="email" value={form.contact_email} onChange={(event) => setForm({ ...form, contact_email: event.target.value })} /></label></div><label>Status<select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })}><option value="onboarding">Onboarding</option><option value="active">Active</option><option value="paused">Paused</option></select></label><label>Notes<textarea rows={4} value={form.notes} onChange={(event) => setForm({ ...form, notes: event.target.value })} /></label><div className="modalActions"><button type="button" className="secondary" onClick={() => setOpen(false)}>Cancel</button><button className="primary" disabled={submitting}>{submitting ? "Adding…" : "Add client"}</button></div></form></Modal></main></AppShell>;
}
