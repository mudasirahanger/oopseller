"use client";

import { AppShell } from "@/components/AppShell";
import { EmptyState } from "@/components/EmptyState";
import { Modal } from "@/components/Modal";
import { PageHeader } from "@/components/PageHeader";
import { StatusPill } from "@/components/StatusPill";
import { apiFetch, ApiError } from "@/lib/api";
import type { AmazonAccount, ChannelCatalogEntry, Client, Marketplace, Paginated } from "@/lib/types";
import { FormEvent, useCallback, useEffect, useState } from "react";

export default function IntegrationsPage() {
  const [channels, setChannels] = useState<ChannelCatalogEntry[]>([]);
  const [accounts, setAccounts] = useState<AmazonAccount[]>([]);
  const [clients, setClients] = useState<Client[]>([]);
  const [marketplaces, setMarketplaces] = useState<Marketplace[]>([]);
  const [meta, setMeta] = useState({ configured: false, draft_mode: true, redirect_uri: "" });
  const [form, setForm] = useState({ client_id: "", marketplace_id: "A21TJRUUN4KGV", draft: true });
  const [open, setOpen] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [busyId, setBusyId] = useState<number | null>(null);

  const load = useCallback(async () => {
    try {
      const [channelResponse, accountResponse, clientResponse, marketplaceResponse] = await Promise.all([
        apiFetch<{ data: ChannelCatalogEntry[] }>("/integrations/channels"),
        apiFetch<{ data: AmazonAccount[]; meta: typeof meta }>("/integrations/amazon/accounts"),
        apiFetch<Paginated<Client>>("/clients?per_page=100"),
        apiFetch<{ data: Marketplace[] }>("/marketplaces"),
      ]);
      setChannels(channelResponse.data); setAccounts(accountResponse.data); setMeta(accountResponse.meta); setForm((current) => ({ ...current, draft: accountResponse.meta.draft_mode })); setClients(clientResponse.data); setMarketplaces(marketplaceResponse.data); setError("");
    } catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to load integrations."); }
  }, []);

  useEffect(() => {
    void load();
    const params = new URLSearchParams(window.location.search);
    if (params.get("amazon") === "connected") setSuccess("Amazon seller account connected. Initial listing sync has been queued.");
    if (params.get("amazon") === "error") setError(params.get("message") || "Amazon authorization failed.");
  }, [load]);

  async function connect(event: FormEvent) {
    event.preventDefault(); setError("");
    try {
      const response = await apiFetch<{ data: { authorization_url: string } }>("/integrations/amazon/authorize", { method: "POST", body: JSON.stringify({ ...form, client_id: Number(form.client_id) }) });
      window.location.assign(response.data.authorization_url);
    } catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to start Amazon authorization."); }
  }

  async function sync(account: AmazonAccount, marketplaceId: string) {
    setBusyId(account.id); setError("");
    try { await apiFetch(`/integrations/amazon/accounts/${account.id}/sync`, { method: "POST", body: JSON.stringify({ marketplace_id: marketplaceId }) }); setSuccess("Amazon listing sync queued."); await load(); }
    catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to queue sync."); }
    finally { setBusyId(null); }
  }

  async function disconnect(account: AmazonAccount) {
    if (!window.confirm(`Disconnect ${account.name}? Stored Amazon refresh credentials will be removed.`)) return;
    setBusyId(account.id);
    try { await apiFetch(`/integrations/amazon/accounts/${account.id}`, { method: "DELETE" }); setSuccess("Amazon account disconnected."); await load(); }
    catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to disconnect account."); }
    finally { setBusyId(null); }
  }

  function channelStatusLabel(channel: ChannelCatalogEntry): string {
    if (channel.status === "coming_soon") return "Coming soon";
    if (channel.status === "needs_configuration") return "Needs configuration";
    return channel.accounts_active > 0 ? `${channel.accounts_active} connected` : "Available";
  }

  return <AppShell><main className="main"><PageHeader eyebrow="Data connections" title="Integration hub" description="Connect marketplaces and e-commerce platforms per client. Amazon uses Login with Amazon and the official Selling Partner API." actions={<button className="primary" disabled={!meta.configured || clients.length === 0} onClick={() => setOpen(true)}>Connect Amazon</button>} />{!meta.configured && <div className="warningBox"><strong>Amazon credentials are not configured.</strong><p>Add the LWA client ID, client secret, SP-API application ID, and registered redirect URI to the server environment.</p><code>{meta.redirect_uri || "AMAZON_REDIRECT_URI"}</code></div>}{clients.length === 0 && <div className="warningBox">Add a client before connecting their seller account.</div>}{error && <div className="errorBox">{error}</div>}{success && <div className="successBox">{success}</div>}<section className="panel"><div className="panelHeader"><div><h2>Platforms</h2><p>Supported sales channels — more adapters plug into the same channel core</p></div></div><div className="channelGrid">{channels.map((channel) => <article className={channel.status === "coming_soon" ? "channelCard comingSoon" : "channelCard"} key={channel.platform}><span className="channelLogo">{channel.name.slice(0, 1)}</span><div className="channelInfo"><strong>{channel.name}</strong><small>{channel.auth_type === "oauth" ? "OAuth connection" : channel.auth_type === "token" ? "Access token" : "API key"}</small></div><div className="channelStatus"><span className={channel.status === "available" && channel.accounts_active > 0 ? "pill active" : "pill"}>{channelStatusLabel(channel)}</span>{channel.platform === "amazon" ? <button className="secondary smallButton" disabled={!meta.configured || clients.length === 0} onClick={() => setOpen(true)}>Connect</button> : <button className="secondary smallButton" disabled title="This platform adapter is on the roadmap">Connect</button>}</div></article>)}</div></section><section className="panel"><div className="panelHeader"><div><h2>Seller Central accounts</h2><p>{accounts.length} Amazon connection{accounts.length === 1 ? "" : "s"}</p></div></div>{accounts.length === 0 ? <EmptyState title="No Amazon accounts connected" description="Connect a client account using the Amazon seller authorization flow." /> : <div className="integrationList">{accounts.map((account) => <article className="integrationCard" key={account.id}><div><strong>{account.client?.name ?? account.name}</strong><small>Seller ID: {account.account_identifier}</small><small>Region: {account.region.toUpperCase()}</small></div><div><StatusPill value={account.status} />{account.last_synced_at && <small>Last synced {new Date(account.last_synced_at).toLocaleString()}</small>}{account.last_sync_error && <small className="errorText">{account.last_sync_error}</small>}</div><div className="marketplaceChips">{account.marketplaces?.map((marketplace) => <button key={marketplace.id} className="secondary smallButton" disabled={busyId === account.id || account.status !== "active"} onClick={() => sync(account, marketplace.amazon_marketplace_id)}>Sync {marketplace.country_code}</button>)}</div><button className="dangerButton" disabled={busyId === account.id || account.status === "disconnected"} onClick={() => disconnect(account)}>Disconnect</button></article>)}</div>}</section><Modal title="Connect Amazon seller account" open={open} onClose={() => setOpen(false)}><form className="formStack" onSubmit={connect}><label>Client<select value={form.client_id} onChange={(event) => setForm({ ...form, client_id: event.target.value })} required><option value="">Select client</option>{clients.map((client) => <option key={client.id} value={client.id}>{client.name}</option>)}</select></label><label>Seller Central marketplace<select value={form.marketplace_id} onChange={(event) => setForm({ ...form, marketplace_id: event.target.value })} required>{marketplaces.map((marketplace) => <option key={marketplace.id} value={marketplace.amazon_marketplace_id}>{marketplace.name}</option>)}</select><small>The marketplace determines the correct regional Seller Central consent URL.</small></label><label className="checkRow"><input type="checkbox" checked={form.draft} onChange={(event) => setForm({ ...form, draft: event.target.checked })} /><span><strong>Application is still in Draft</strong><small>Adds the Amazon beta authorization parameter for testing.</small></span></label><div className="modalActions"><button type="button" className="secondary" onClick={() => setOpen(false)}>Cancel</button><button className="primary">Continue to Amazon</button></div></form></Modal></main></AppShell>;
}
