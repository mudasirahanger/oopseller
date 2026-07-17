"use client";

import { AppShell } from "@/components/AppShell";
import { EmptyState } from "@/components/EmptyState";
import { Modal } from "@/components/Modal";
import { PageHeader } from "@/components/PageHeader";
import { StatusPill } from "@/components/StatusPill";
import { apiFetch, ApiError } from "@/lib/api";
import type { AmazonAccount, Client, Marketplace, Paginated, Product } from "@/lib/types";
import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";

const emptyForm = { client_id: "", amazon_account_id: "", marketplace_id: "A21TJRUUN4KGV", asin: "", sku: "", name: "", product_type: "", import_from_amazon: false };

export default function ProductsPage() {
  const [products, setProducts] = useState<Product[]>([]);
  const [clients, setClients] = useState<Client[]>([]);
  const [accounts, setAccounts] = useState<AmazonAccount[]>([]);
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
      const [productResponse, clientResponse, accountResponse, marketplaceResponse] = await Promise.all([
        apiFetch<Paginated<Product>>("/products?per_page=100"),
        apiFetch<Paginated<Client>>("/clients?per_page=100"),
        apiFetch<{ data: AmazonAccount[] }>("/integrations/amazon/accounts"),
        apiFetch<{ data: Marketplace[] }>("/marketplaces"),
      ]);
      setProducts(productResponse.data); setClients(clientResponse.data); setAccounts(accountResponse.data); setMarketplaces(marketplaceResponse.data); setError("");
    } catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to load products."); }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { void load(); }, [load]);
  const availableAccounts = useMemo(() => accounts.filter((account) => String(account.client_id) === form.client_id && account.status === "active"), [accounts, form.client_id]);

  async function submit(event: FormEvent) {
    event.preventDefault(); setSubmitting(true); setError("");
    try {
      await apiFetch("/products", {
        method: "POST",
        body: JSON.stringify({
          ...form,
          client_id: Number(form.client_id),
          channel_account_id: form.amazon_account_id ? Number(form.amazon_account_id) : null,
          marketplace_id: form.marketplace_id || null,
          asin: form.asin.toUpperCase().trim(),
        }),
      });
      setOpen(false); setForm(emptyForm); setSuccess(form.import_from_amazon ? "Product imported from Amazon." : "Product added."); await load();
    } catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to add product."); }
    finally { setSubmitting(false); }
  }

  return <AppShell><main className="main"><PageHeader eyebrow="Catalog management" title="Products & ASINs" description="Manage real client products and seller marketplace listings." actions={<button className="primary" onClick={() => setOpen(true)}>+ Add product</button>} />{error && <div className="errorBox">{error}</div>}{success && <div className="successBox">{success}</div>}<section className="panel"><div className="panelHeader"><div><h2>Managed catalogue</h2><p>{products.length} product{products.length === 1 ? "" : "s"}</p></div></div>{loading ? <div className="loadingPanel">Loading products…</div> : products.length === 0 ? <EmptyState title="No products yet" description="Add a product manually or import an ASIN and seller SKU from a connected Amazon account." /> : <div className="tableWrap"><table><thead><tr><th>Product</th><th>Client</th><th>ASIN / SKU</th><th>Source</th><th>Marketplace listings</th><th>Status</th></tr></thead><tbody>{products.map((product) => <tr key={product.id}><td><div className="productCell">{product.image_url ? <img src={product.image_url} alt="" /> : <span className="imagePlaceholder">{product.name.slice(0, 1)}</span>}<div><strong>{product.name}</strong><small>{product.product_type || "Product type not set"}</small></div></div></td><td>{product.client?.name ?? "—"}</td><td><code>{product.asin}</code><small className="cellSubtext">{product.sku || "No seller SKU"}</small></td><td><span className="pill">{product.source || "manual"}</span></td><td>{product.listings?.length ?? 0}</td><td><StatusPill value={product.status} /></td></tr>)}</tbody></table></div>}</section><Modal title="Add product" open={open} onClose={() => setOpen(false)}><form className="formStack" onSubmit={submit}><label className="checkRow"><input type="checkbox" checked={form.import_from_amazon} onChange={(event) => setForm({ ...form, import_from_amazon: event.target.checked })} /><span><strong>Import from Amazon SP-API</strong><small>Fetch catalog details and, when a seller SKU is supplied, the seller listing.</small></span></label><label>Client<select value={form.client_id} onChange={(event) => setForm({ ...form, client_id: event.target.value, amazon_account_id: "" })} required><option value="">Select client</option>{clients.map((client) => <option key={client.id} value={client.id}>{client.name}</option>)}</select></label>{form.import_from_amazon && <label>Amazon seller account<select value={form.amazon_account_id} onChange={(event) => setForm({ ...form, amazon_account_id: event.target.value })} required><option value="">Select connected account</option>{availableAccounts.map((account) => <option key={account.id} value={account.id}>{account.name} · {account.account_identifier}</option>)}</select>{form.client_id && availableAccounts.length === 0 && <small>No active Amazon account is connected for this client.</small>}</label>}<div className="formGrid"><label>ASIN<input value={form.asin} onChange={(event) => setForm({ ...form, asin: event.target.value.toUpperCase() })} minLength={10} maxLength={10} required placeholder="B0XXXXXXXX" /></label><label>Seller SKU<input value={form.sku} onChange={(event) => setForm({ ...form, sku: event.target.value })} placeholder="Required to import seller listing" /></label></div><label>Marketplace<select value={form.marketplace_id} onChange={(event) => setForm({ ...form, marketplace_id: event.target.value })} required={form.import_from_amazon}><option value="">No listing yet</option>{marketplaces.map((marketplace) => <option key={marketplace.id} value={marketplace.amazon_marketplace_id}>{marketplace.name}</option>)}</select></label>{!form.import_from_amazon && <><label>Product name<input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} required /></label><label>Amazon product type<input value={form.product_type} onChange={(event) => setForm({ ...form, product_type: event.target.value.toUpperCase() })} placeholder="Example: LUGGAGE" /></label></>}<div className="modalActions"><button type="button" className="secondary" onClick={() => setOpen(false)}>Cancel</button><button className="primary" disabled={submitting}>{submitting ? "Working…" : form.import_from_amazon ? "Import product" : "Add product"}</button></div></form></Modal></main></AppShell>;
}
