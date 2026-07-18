"use client";

import { AppShell } from "@/components/AppShell";
import { EmptyState } from "@/components/EmptyState";
import { Modal } from "@/components/Modal";
import { PageHeader } from "@/components/PageHeader";
import { StatusPill } from "@/components/StatusPill";
import { apiFetch, ApiError } from "@/lib/api";
import type { KeywordProject, Marketplace, Paginated, Product } from "@/lib/types";
import { FormEvent, useCallback, useEffect, useState } from "react";

const emptyForm = { product_id: "", marketplace_id: "A21TJRUUN4KGV", name: "", language: "en", keywords: "" };

export default function KeywordsPage() {
  const [projects, setProjects] = useState<KeywordProject[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [marketplaces, setMarketplaces] = useState<Marketplace[]>([]);
  const [form, setForm] = useState(emptyForm);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [projectResponse, productResponse, marketplaceResponse] = await Promise.all([
        apiFetch<Paginated<KeywordProject>>("/keyword-projects?per_page=100"),
        apiFetch<Paginated<Product>>("/products?per_page=100"),
        apiFetch<{ data: Marketplace[] }>("/marketplaces"),
      ]);
      setProjects(projectResponse.data); setProducts(productResponse.data); setMarketplaces(marketplaceResponse.data); setError("");
    } catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to load keyword projects."); }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { void load(); }, [load]);

  async function submit(event: FormEvent) {
    event.preventDefault(); setSubmitting(true); setError("");
    try {
      const keywords = form.keywords.split("\n").map((value) => value.trim()).filter(Boolean);
      await apiFetch("/keyword-projects", { method: "POST", body: JSON.stringify({ ...form, product_id: Number(form.product_id), keywords }) });
      setForm(emptyForm); setOpen(false); setSuccess("Keyword project created."); await load();
    } catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to create keyword project."); }
    finally { setSubmitting(false); }
  }

  const tracked = projects.reduce((total, project) => total + (project.keywords?.length ?? 0), 0);
  return <AppShell><main className="main"><PageHeader eyebrow="Search intelligence" title="Keywords & rankings" description="Create real keyword projects for each ASIN and store rank observations from your configured data provider." actions={<button className="primary" onClick={() => setOpen(true)} disabled={products.length === 0} title={products.length === 0 ? "Add a product before creating a keyword project" : undefined}>+ Create project</button>} />{error && <div className="errorBox">{error}</div>}{success && <div className="successBox">{success}</div>}<section className="summaryStrip"><div><span>Projects</span><strong>{projects.length}</strong></div><div><span>Tracked keywords</span><strong>{tracked}</strong></div><div><span>Products available</span><strong>{products.length}</strong></div></section><section className="panel"><div className="panelHeader"><div><h2>Keyword projects</h2><p>Rank values remain empty until a rank provider or approved import writes observations.</p></div></div>{loading ? <div className="loadingPanel">Loading keyword projects…</div> : projects.length === 0 ? <EmptyState title="No keyword projects" description={products.length === 0 ? "Keyword projects track search terms for a product, so add a product first." : "Create a project and enter the actual search terms your team wants to monitor."} actionLabel={products.length === 0 ? "Add a product" : undefined} actionHref={products.length === 0 ? "/products" : undefined} /> : <div className="tableWrap"><table><thead><tr><th>Project</th><th>Product</th><th>Marketplace</th><th>Keywords</th><th>Latest observed ranks</th><th>Status</th></tr></thead><tbody>{projects.map((project) => { const ranked = (project.keywords ?? []).filter((keyword) => keyword.rankings?.[0]); return <tr key={project.id}><td><strong>{project.name}</strong><small className="cellSubtext">Language: {project.language}</small></td><td>{project.product?.name ?? "—"}<small className="cellSubtext">{project.product?.asin}</small></td><td><code>{project.marketplace_id}</code></td><td>{project.keywords?.length ?? 0}</td><td>{ranked.length === 0 ? "No observations" : ranked.slice(0, 3).map((keyword) => `${keyword.phrase}: ${keyword.rankings?.[0]?.organic_position ?? "—"}`).join(" · ")}</td><td><StatusPill value={project.status} /></td></tr>; })}</tbody></table></div>}</section><Modal title="Create keyword project" open={open} onClose={() => setOpen(false)}><form className="formStack" onSubmit={submit}><label>Product<select value={form.product_id} onChange={(event) => setForm({ ...form, product_id: event.target.value })} required><option value="">Select product</option>{products.map((product) => <option key={product.id} value={product.id}>{product.name} · {product.asin}</option>)}</select></label><label>Project name<input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} required placeholder="Primary Amazon.in keywords" /></label><div className="formGrid"><label>Marketplace<select value={form.marketplace_id} onChange={(event) => setForm({ ...form, marketplace_id: event.target.value })} required>{marketplaces.map((marketplace) => <option key={marketplace.id} value={marketplace.amazon_marketplace_id}>{marketplace.name}</option>)}</select></label><label>Language<input value={form.language} onChange={(event) => setForm({ ...form, language: event.target.value })} maxLength={10} /></label></div><label>Keywords <small>One keyword per line</small><textarea rows={10} value={form.keywords} onChange={(event) => setForm({ ...form, keywords: event.target.value })} required placeholder={"baby mosquito net\nmosquito net for baby bed\nfoldable baby mosquito net"} /></label><div className="modalActions"><button type="button" className="secondary" onClick={() => setOpen(false)}>Cancel</button><button className="primary" disabled={submitting}>{submitting ? "Creating…" : "Create project"}</button></div></form></Modal></main></AppShell>;
}
