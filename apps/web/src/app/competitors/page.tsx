"use client";

import { AppShell } from "@/components/AppShell";
import { EmptyState } from "@/components/EmptyState";
import { Modal } from "@/components/Modal";
import { PageHeader } from "@/components/PageHeader";
import { StatusPill } from "@/components/StatusPill";
import { apiFetch, ApiError } from "@/lib/api";
import type { Competitor, Marketplace, Paginated, Product } from "@/lib/types";
import { FormEvent, useCallback, useEffect, useState } from "react";

const emptyForm = { product_id: "", marketplace_id: "A21TJRUUN4KGV", asin: "", name: "" };

export default function CompetitorsPage() {
  const [competitors, setCompetitors] = useState<Competitor[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [marketplaces, setMarketplaces] = useState<Marketplace[]>([]);
  const [form, setForm] = useState(emptyForm);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const load = useCallback(async () => { setLoading(true); try { const [competitorResponse, productResponse, marketplaceResponse] = await Promise.all([apiFetch<Paginated<Competitor>>("/competitors?per_page=100"), apiFetch<Paginated<Product>>("/products?per_page=100"), apiFetch<{ data: Marketplace[] }>("/marketplaces")]); setCompetitors(competitorResponse.data); setProducts(productResponse.data); setMarketplaces(marketplaceResponse.data); setError(""); } catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to load competitors."); } finally { setLoading(false); } }, []);
  useEffect(() => { void load(); }, [load]);
  async function submit(event: FormEvent) { event.preventDefault(); setSubmitting(true); setError(""); try { await apiFetch("/competitors", { method: "POST", body: JSON.stringify({ ...form, product_id: Number(form.product_id), asin: form.asin.toUpperCase().trim() }) }); setForm(emptyForm); setOpen(false); setSuccess("Competitor added."); await load(); } catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to add competitor."); } finally { setSubmitting(false); } }
  return <AppShell><main className="main"><PageHeader eyebrow="Market monitoring" title="Competitor intelligence" description="Track competitor ASINs selected by your team. No invented price, review, or ranking data is displayed." actions={<button className="primary" onClick={() => setOpen(true)} disabled={products.length === 0}>+ Add competitor</button>} />{error && <div className="errorBox">{error}</div>}{success && <div className="successBox">{success}</div>}<section className="panel"><div className="panelHeader"><div><h2>Tracked competitors</h2><p>{competitors.length} competitor ASIN{competitors.length === 1 ? "" : "s"}</p></div></div>{loading ? <div className="loadingPanel">Loading competitors…</div> : competitors.length === 0 ? <EmptyState title="No competitors tracked" description={products.length === 0 ? "Add a client product first." : "Add the competitor ASINs that matter for a client product."} /> : <div className="tableWrap"><table><thead><tr><th>Competitor</th><th>Compared with</th><th>Marketplace</th><th>Latest price</th><th>Rating / reviews</th><th>Status</th></tr></thead><tbody>{competitors.map((competitor) => <tr key={competitor.id}><td><strong>{competitor.name || competitor.asin}</strong><small className="cellSubtext"><code>{competitor.asin}</code></small></td><td>{competitor.product?.name ?? "—"}<small className="cellSubtext">{competitor.product?.asin}</small></td><td><code>{competitor.marketplace_id}</code></td><td>{competitor.latest_snapshot?.price != null ? `₹${competitor.latest_snapshot.price}` : "Not collected"}</td><td>{competitor.latest_snapshot?.rating != null ? `${competitor.latest_snapshot.rating} / ${competitor.latest_snapshot.review_count ?? 0}` : "Not collected"}</td><td><StatusPill value={competitor.status} /></td></tr>)}</tbody></table></div>}</section><Modal title="Add competitor" open={open} onClose={() => setOpen(false)}><form className="formStack" onSubmit={submit}><label>Client product<select value={form.product_id} onChange={(event) => setForm({ ...form, product_id: event.target.value })} required><option value="">Select product</option>{products.map((product) => <option key={product.id} value={product.id}>{product.name} · {product.asin}</option>)}</select></label><div className="formGrid"><label>Competitor ASIN<input value={form.asin} onChange={(event) => setForm({ ...form, asin: event.target.value.toUpperCase() })} minLength={10} maxLength={10} required /></label><label>Label or brand<input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /></label></div><label>Marketplace<select value={form.marketplace_id} onChange={(event) => setForm({ ...form, marketplace_id: event.target.value })} required>{marketplaces.map((marketplace) => <option key={marketplace.id} value={marketplace.amazon_marketplace_id}>{marketplace.name}</option>)}</select></label><div className="modalActions"><button type="button" className="secondary" onClick={() => setOpen(false)}>Cancel</button><button className="primary" disabled={submitting}>{submitting ? "Adding…" : "Add competitor"}</button></div></form></Modal></main></AppShell>;
}
