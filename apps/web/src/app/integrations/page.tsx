"use client";

import { AppShell } from "@/components/AppShell";
import { EmptyState } from "@/components/EmptyState";
import { Modal } from "@/components/Modal";
import { PageHeader } from "@/components/PageHeader";
import { StatusPill } from "@/components/StatusPill";
import { apiFetch, ApiError } from "@/lib/api";
import type { AmazonAccount, ChannelAccountItem, ChannelCatalogEntry, Client, Marketplace, Paginated } from "@/lib/types";
import { FormEvent, useCallback, useEffect, useState } from "react";

export default function IntegrationsPage() {
  const [channels, setChannels] = useState<ChannelCatalogEntry[]>([]);
  const [accounts, setAccounts] = useState<AmazonAccount[]>([]);
  const [channelAccounts, setChannelAccounts] = useState<ChannelAccountItem[]>([]);
  const [clients, setClients] = useState<Client[]>([]);
  const [marketplaces, setMarketplaces] = useState<Marketplace[]>([]);
  const [meta, setMeta] = useState({ configured: false, draft_mode: true, redirect_uri: "", sandbox_default: false });
  const [form, setForm] = useState({ client_id: "", marketplace_id: "A21TJRUUN4KGV", draft: true, sandbox: false });
  const [open, setOpen] = useState(false);
  const [amazonMode, setAmazonMode] = useState<"oauth" | "manual">("manual");
  const [manualForm, setManualForm] = useState({ seller_id: "", refresh_token: "" });
  const [docsChannel, setDocsChannel] = useState<ChannelCatalogEntry | null>(null);
  const [connectChannel, setConnectChannel] = useState<ChannelCatalogEntry | null>(null);
  const [connectForm, setConnectForm] = useState<Record<string, string>>({});
  const [channelMode, setChannelMode] = useState<"oauth" | "credentials">("credentials");
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [busyId, setBusyId] = useState<number | null>(null);
  const [submitting, setSubmitting] = useState(false);

  const load = useCallback(async () => {
    try {
      const [channelResponse, accountResponse, channelAccountResponse, clientResponse, marketplaceResponse] = await Promise.all([
        apiFetch<{ data: ChannelCatalogEntry[] }>("/integrations/channels"),
        apiFetch<{ data: AmazonAccount[]; meta: typeof meta }>("/integrations/amazon/accounts"),
        apiFetch<{ data: ChannelAccountItem[] }>("/integrations/channels/accounts"),
        apiFetch<Paginated<Client>>("/clients?per_page=100"),
        apiFetch<{ data: Marketplace[] }>("/marketplaces"),
      ]);
      setChannels(channelResponse.data); setAccounts(accountResponse.data); setChannelAccounts(channelAccountResponse.data); setMeta(accountResponse.meta); setForm((current) => ({ ...current, draft: accountResponse.meta.draft_mode, sandbox: accountResponse.meta.sandbox_default })); setClients(clientResponse.data); setMarketplaces(marketplaceResponse.data); setError("");
    } catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to load integrations."); }
  }, []);

  useEffect(() => {
    void load();
    const params = new URLSearchParams(window.location.search);
    if (params.get("amazon") === "connected") setSuccess("Amazon seller account connected. Initial listing sync has been queued.");
    if (params.get("amazon") === "error") setError(params.get("message") || "Amazon authorization failed.");
    if (params.get("status") === "connected" && params.get("channel")) setSuccess(`${params.get("channel")} account connected. Initial listing sync has been queued.`);
    if (params.get("status") === "error" && params.get("channel")) setError(params.get("message") || `${params.get("channel")} authorization failed.`);
  }, [load]);

  async function connectAmazon(event: FormEvent) {
    event.preventDefault(); setError("");
    try {
      const response = await apiFetch<{ data: { authorization_url: string } }>("/integrations/amazon/authorize", { method: "POST", body: JSON.stringify({ ...form, client_id: Number(form.client_id) }) });
      window.location.assign(response.data.authorization_url);
    } catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to start Amazon authorization."); }
  }

  async function connectAmazonManually(event: FormEvent) {
    event.preventDefault(); setError(""); setSubmitting(true);
    try {
      await apiFetch("/integrations/amazon/accounts/manual", {
        method: "POST",
        body: JSON.stringify({
          client_id: Number(form.client_id),
          marketplace_id: form.marketplace_id,
          seller_id: manualForm.seller_id,
          refresh_token: manualForm.refresh_token,
          sandbox: form.sandbox,
        }),
      });
      setSuccess("Amazon seller account connected. Initial listing sync has been queued.");
      setOpen(false);
      setManualForm({ seller_id: "", refresh_token: "" });
      await load();
    } catch (exception) {
      setError(exception instanceof ApiError ? exception.message : "Unable to connect this Amazon account. Double-check the seller ID and refresh token.");
    } finally { setSubmitting(false); }
  }

  // Channels like Flipkart are OAuth for partner apps but ALSO accept
  // per-seller self-access app credentials; for those the modal offers both
  // modes, defaulting to credentials (works without server-level config).
  const hasBothModes = (channel: ChannelCatalogEntry) => channel.auth_type === "oauth" && channel.credential_fields.length > 0;
  const effectiveMode = (channel: ChannelCatalogEntry): "oauth" | "credentials" =>
    hasBothModes(channel) ? channelMode : (channel.auth_type === "oauth" ? "oauth" : "credentials");

  function openConnect(channel: ChannelCatalogEntry) {
    if (channel.platform === "amazon") { setAmazonMode("manual"); setOpen(true); return; }
    setConnectForm({ client_id: "" });
    setChannelMode("credentials");
    setConnectChannel(channel);
  }

  async function submitConnect(event: FormEvent) {
    event.preventDefault();
    if (!connectChannel) return;
    setError(""); setSubmitting(true);
    try {
      if (effectiveMode(connectChannel) === "oauth") {
        const response = await apiFetch<{ data: { authorization_url: string } }>(`/integrations/channels/${connectChannel.platform}/authorize`, {
          method: "POST",
          body: JSON.stringify({ client_id: Number(connectForm.client_id) }),
        });
        window.location.assign(response.data.authorization_url);
        return;
      }
      const credentials: Record<string, string> = {};
      for (const field of connectChannel.credential_fields) credentials[field.key] = connectForm[field.key] ?? "";
      await apiFetch(`/integrations/channels/${connectChannel.platform}/connect`, {
        method: "POST",
        body: JSON.stringify({ client_id: Number(connectForm.client_id), credentials }),
      });
      setSuccess(`${connectChannel.name} account connected. Run a sync to import listings.`);
      setConnectChannel(null);
      await load();
    } catch (exception) {
      setError(exception instanceof ApiError ? exception.message : `Unable to connect ${connectChannel.name}.`);
    } finally { setSubmitting(false); }
  }

  async function syncAmazon(account: AmazonAccount, marketplaceId: string) {
    setBusyId(account.id); setError("");
    try { await apiFetch(`/integrations/amazon/accounts/${account.id}/sync`, { method: "POST", body: JSON.stringify({ marketplace_id: marketplaceId }) }); setSuccess("Amazon listing sync queued."); await load(); }
    catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to queue sync."); }
    finally { setBusyId(null); }
  }

  async function syncAmazonOrders(account: AmazonAccount) {
    setBusyId(account.id); setError("");
    try { await apiFetch(`/integrations/channel-accounts/${account.id}/sync-orders`, { method: "POST" }); setSuccess("Amazon order sync queued."); await load(); }
    catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to queue order sync."); }
    finally { setBusyId(null); }
  }

  async function disconnectAmazon(account: AmazonAccount) {
    if (!window.confirm(`Disconnect ${account.name}? Stored Amazon refresh credentials will be removed.`)) return;
    setBusyId(account.id);
    try { await apiFetch(`/integrations/amazon/accounts/${account.id}`, { method: "DELETE" }); setSuccess("Amazon account disconnected."); await load(); }
    catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to disconnect account."); }
    finally { setBusyId(null); }
  }

  async function syncChannel(account: ChannelAccountItem) {
    setBusyId(account.id); setError("");
    try { await apiFetch(`/integrations/channel-accounts/${account.id}/sync`, { method: "POST" }); setSuccess(`${account.platform} listing sync queued.`); await load(); }
    catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to queue sync."); }
    finally { setBusyId(null); }
  }

  async function syncChannelOrders(account: ChannelAccountItem) {
    setBusyId(account.id); setError("");
    try { await apiFetch(`/integrations/channel-accounts/${account.id}/sync-orders`, { method: "POST" }); setSuccess(`${account.platform} order sync queued.`); await load(); }
    catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to queue order sync."); }
    finally { setBusyId(null); }
  }

  async function disconnectChannel(account: ChannelAccountItem) {
    if (!window.confirm(`Disconnect ${account.name}? Stored credentials will be removed.`)) return;
    setBusyId(account.id);
    try { await apiFetch(`/integrations/channel-accounts/${account.id}`, { method: "DELETE" }); setSuccess("Channel account disconnected."); await load(); }
    catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to disconnect account."); }
    finally { setBusyId(null); }
  }

  function channelStatusLabel(channel: ChannelCatalogEntry): string {
    if (channel.status === "coming_soon") return "Coming soon";
    if (channel.status === "needs_configuration") return "Needs configuration";
    return channel.accounts_active > 0 ? `${channel.accounts_active} connected` : "Available";
  }

  // "needs_configuration" (server-side app credentials missing) still opens
  // the modal so the client can be picked and the setup steps are visible —
  // submitting surfaces a clear "not configured" error instead of a silently
  // disabled button with no explanation.
  const connectable = (channel: ChannelCatalogEntry) => channel.status !== "coming_soon" && clients.length > 0;

  function syncStatusLine(account: AmazonAccount | ChannelAccountItem, isSandbox: boolean) {
    const run = account.sync_runs?.[0];
    if (!run) return null;
    const zeroResult = run.status === "completed" && run.processed === 0 && run.failed === 0;
    return <div className="syncStatusLine">
      <span className="syncStatusRow"><StatusPill value={run.status} /><small>{run.type} · {new Date(run.created_at).toLocaleString()} · {run.processed} processed{run.failed > 0 ? ` · ${run.failed} failed` : ""}</small></span>
      {run.status === "failed" && run.error && <small className="errorText">{run.error}</small>}
      {zeroResult && run.type === "listings" && isSandbox && <small className="syncHint">Sandbox found 0 listings — Amazon&apos;s Sandbox environment doesn&apos;t simulate a real seller catalog for a general listing search, only specific documented test cases. Connect a real (non-Sandbox) account to see actual inventory.</small>}
    </div>;
  }

  return <AppShell><main className="main"><PageHeader eyebrow="Data connections" title="Integration hub" description="Connect marketplaces and e-commerce platforms per client. Every connector has a setup guide behind its docs icon." actions={<button className="primary" disabled={!meta.configured || clients.length === 0} onClick={() => { setAmazonMode("manual"); setOpen(true); }}>Connect Amazon</button>} />{!meta.configured && <div className="warningBox"><strong>Amazon credentials are not configured.</strong><p>Add the LWA client ID, client secret, SP-API application ID, and registered redirect URI to the server environment.</p><code>{meta.redirect_uri || "AMAZON_REDIRECT_URI"}</code></div>}{clients.length === 0 && <div className="warningBox">Add a client before connecting their seller account.</div>}{error && <div className="errorBox">{error}</div>}{success && <div className="successBox">{success}</div>}<section className="panel"><div className="panelHeader"><div><h2>Platforms</h2><p>Amazon, Flipkart, Meesho, and Snapdeal are live — more adapters plug into the same channel core</p></div></div><div className="channelGrid">{channels.map((channel) => <article className={channel.status === "coming_soon" ? "channelCard comingSoon" : "channelCard"} key={channel.platform}><div className="channelTop"><span className="channelLogo">{channel.name.slice(0, 1)}</span><button type="button" className="docsButton" title={`${channel.name} setup guide`} aria-label={`${channel.name} setup guide`} onClick={() => setDocsChannel(channel)}>📖</button></div><div className="channelInfo"><strong>{channel.name}</strong><small>{channel.auth_type === "oauth" ? "OAuth connection" : channel.auth_type === "token" ? "Access token" : "API key"}</small></div><div className="channelStatus"><span className={channel.status === "available" && channel.accounts_active > 0 ? "pill active" : "pill"}>{channelStatusLabel(channel)}</span><button className="secondary smallButton" disabled={!connectable(channel)} title={channel.status === "coming_soon" ? "This platform adapter is on the roadmap" : undefined} onClick={() => openConnect(channel)}>Connect</button></div></article>)}</div></section><section className="panel"><div className="panelHeader"><div><h2>Seller Central accounts</h2><p>{accounts.length} Amazon connection{accounts.length === 1 ? "" : "s"}</p></div></div>{accounts.length === 0 ? <EmptyState title="No Amazon accounts connected" description="Connect a client account using the Amazon seller authorization flow." /> : <div className="integrationList">{accounts.map((account) => <article className="integrationCard" key={account.id}><div><strong>{account.client?.name ?? account.name}{account.metadata?.sandbox && <span className="pill sandboxPill">Sandbox</span>}</strong><small>Seller ID: {account.account_identifier}</small><small>Region: {account.region.toUpperCase()}</small></div><div><StatusPill value={account.status} />{syncStatusLine(account, Boolean(account.metadata?.sandbox))}</div><div className="marketplaceChips">{account.marketplaces?.map((marketplace) => <button key={marketplace.id} className="secondary smallButton" disabled={busyId === account.id || account.status !== "active"} onClick={() => syncAmazon(account, marketplace.amazon_marketplace_id)}>Sync {marketplace.country_code}</button>)}<button className="secondary smallButton" disabled={busyId === account.id || account.status !== "active"} onClick={() => syncAmazonOrders(account)}>Sync orders</button></div><button className="dangerButton" disabled={busyId === account.id || account.status === "disconnected"} onClick={() => disconnectAmazon(account)}>Disconnect</button></article>)}</div>}</section><section className="panel"><div className="panelHeader"><div><h2>Channel accounts</h2><p>{channelAccounts.length} connection{channelAccounts.length === 1 ? "" : "s"} on Flipkart, Meesho, Snapdeal, and other channels</p></div></div>{channelAccounts.length === 0 ? <EmptyState title="No channel accounts connected" description="Connect Flipkart with OAuth, or Meesho and Snapdeal with API credentials from their seller panels." /> : <div className="integrationList">{channelAccounts.map((account) => <article className="integrationCard" key={account.id}><div><strong>{account.client?.name ?? account.name}</strong><small>{account.platform.toUpperCase()} · {account.account_identifier}</small><small>{account.name}</small></div><div><StatusPill value={account.status} />{syncStatusLine(account, false)}</div><div className="marketplaceChips"><button className="secondary smallButton" disabled={busyId === account.id || account.status !== "active"} onClick={() => syncChannel(account)}>Sync listings</button><button className="secondary smallButton" disabled={busyId === account.id || account.status !== "active"} onClick={() => syncChannelOrders(account)}>Sync orders</button></div><button className="dangerButton" disabled={busyId === account.id || account.status === "disconnected"} onClick={() => disconnectChannel(account)}>Disconnect</button></article>)}</div>}</section><Modal title="Connect Amazon seller account" open={open} onClose={() => setOpen(false)}><div className="rangeGroup modalModeToggle"><button type="button" className={amazonMode === "oauth" ? "rangeButton active" : "rangeButton"} onClick={() => setAmazonMode("oauth")}>OAuth (Public app)</button><button type="button" className={amazonMode === "manual" ? "rangeButton active" : "rangeButton"} onClick={() => setAmazonMode("manual")}>Refresh token (Private app)</button></div><label className="checkRow sandboxToggle"><input type="checkbox" checked={form.sandbox} onChange={(event) => setForm({ ...form, sandbox: event.target.checked })} /><span><strong>Use Sandbox environment</strong><small>Works the same on either tab above — it does not require a Public app, and does not change which tab you should use to connect. Per-account, so a real account and a sandbox account can stay connected at the same time.</small></span></label>{amazonMode === "oauth" ? <form className="formStack" onSubmit={connectAmazon}><div className="warningBox"><strong>Only works for Public (Solution Provider) apps.</strong><p>If your Amazon app is Private/self-authorized, this will fail with error MD9100 — the Sandbox checkbox does not fix this, since the failure happens on Amazon&apos;s consent page before any API call is made. Use <strong>Refresh token (Private app)</strong> instead.</p></div><label>Client<select value={form.client_id} onChange={(event) => setForm({ ...form, client_id: event.target.value })} required><option value="">Select client</option>{clients.map((client) => <option key={client.id} value={client.id}>{client.name}</option>)}</select></label><label>Seller Central marketplace<select value={form.marketplace_id} onChange={(event) => setForm({ ...form, marketplace_id: event.target.value })} required>{marketplaces.map((marketplace) => <option key={marketplace.id} value={marketplace.amazon_marketplace_id}>{marketplace.name}</option>)}</select><small>The marketplace determines the correct regional Seller Central consent URL.</small></label><label className="checkRow"><input type="checkbox" checked={form.draft} onChange={(event) => setForm({ ...form, draft: event.target.checked })} /><span><strong>Application is still in Draft</strong><small>Adds the Amazon beta authorization parameter for testing.</small></span></label><div className="modalActions"><button type="button" className="secondary" onClick={() => setOpen(false)}>Cancel</button><button className="primary">Continue to Amazon</button></div></form> : <form className="formStack" onSubmit={connectAmazonManually}><div className="infoBox"><strong>For Private SP-API applications</strong><p>In Seller Central go to <strong>Apps &amp; Services → Manage Your Apps</strong>, open this app, and click <strong>Authorize</strong>. Amazon displays a refresh token on screen (starts with <code>Atzr|</code>) — paste it below along with the Selling Partner (seller) ID shown on the same screen.</p></div><label>Client<select value={form.client_id} onChange={(event) => setForm({ ...form, client_id: event.target.value })} required><option value="">Select client</option>{clients.map((client) => <option key={client.id} value={client.id}>{client.name}</option>)}</select></label><label>Marketplace<select value={form.marketplace_id} onChange={(event) => setForm({ ...form, marketplace_id: event.target.value })} required>{marketplaces.map((marketplace) => <option key={marketplace.id} value={marketplace.amazon_marketplace_id}>{marketplace.name}</option>)}</select></label><label>Amazon Selling Partner ID<input value={manualForm.seller_id} onChange={(event) => setManualForm({ ...manualForm, seller_id: event.target.value })} required placeholder="A1XXXXXXXXXXXXX" /></label><label>Refresh token<textarea rows={3} value={manualForm.refresh_token} onChange={(event) => setManualForm({ ...manualForm, refresh_token: event.target.value })} required placeholder="Atzr|IwEBI..." /><small>Stored encrypted; only used to call the Amazon Selling Partner API for this account.</small></label><div className="modalActions"><button type="button" className="secondary" onClick={() => setOpen(false)}>Cancel</button><button className="primary" disabled={submitting}>{submitting ? "Connecting…" : "Connect account"}</button></div></form>}</Modal><Modal title={connectChannel ? `Connect ${connectChannel.name}` : "Connect"} open={connectChannel !== null} onClose={() => setConnectChannel(null)}>{connectChannel && <form className="formStack" onSubmit={submitConnect}>{hasBothModes(connectChannel) && <div className="rangeGroup modalModeToggle"><button type="button" className={channelMode === "credentials" ? "rangeButton active" : "rangeButton"} onClick={() => setChannelMode("credentials")}>App credentials (self-access)</button><button type="button" className={channelMode === "oauth" ? "rangeButton active" : "rangeButton"} onClick={() => setChannelMode("oauth")}>Authorize (partner app)</button></div>}{connectChannel.status === "needs_configuration" && effectiveMode(connectChannel) === "oauth" && <div className="warningBox"><strong>{connectChannel.name} partner-app credentials are not configured on the server yet.</strong><p>The authorize flow will fail until an administrator sets the {connectChannel.name} app credentials in the server environment. See the setup guide (📖 on the card) for the exact steps.</p></div>}{hasBothModes(connectChannel) && effectiveMode(connectChannel) === "oauth" && <div className="infoBox"><strong>Partner apps only.</strong><p>This consent flow works only for applications registered in the {connectChannel.name} Partner Dashboard. If your app was created from the Seller Dashboard (Developer Access), it is a self-access app — use <strong>App credentials (self-access)</strong> instead, or {connectChannel.name} will show a generic &quot;Something went wrong&quot; error.</p></div>}<label>Client<select value={connectForm.client_id ?? ""} onChange={(event) => setConnectForm({ ...connectForm, client_id: event.target.value })} required><option value="">Select client</option>{clients.map((client) => <option key={client.id} value={client.id}>{client.name}</option>)}</select></label>{effectiveMode(connectChannel) === "oauth" ? <div className="infoBox">You will be redirected to {connectChannel.name} to approve seller access.</div> : connectChannel.credential_fields.map((field) => <label key={field.key}>{field.label}<input type={field.secret ? "password" : "text"} value={connectForm[field.key] ?? ""} onChange={(event) => setConnectForm({ ...connectForm, [field.key]: event.target.value })} required autoComplete="off" /></label>)}{effectiveMode(connectChannel) !== "oauth" && <small>Credentials are stored encrypted and are only used to call the {connectChannel.name} seller API.</small>}<div className="modalActions"><button type="button" className="secondary" onClick={() => setConnectChannel(null)}>Cancel</button><button className="primary" disabled={submitting}>{submitting ? "Connecting…" : effectiveMode(connectChannel) === "oauth" ? `Continue to ${connectChannel.name}` : "Connect account"}</button></div></form>}</Modal><Modal title={docsChannel ? `${docsChannel.name} setup guide` : "Setup guide"} open={docsChannel !== null} onClose={() => setDocsChannel(null)}>{docsChannel && <div className="docsContent"><ol className="checklist">{docsChannel.setup_steps.map((step, index) => <li key={index}>{step}</li>)}</ol><p className="docsLink"><a href={docsChannel.docs_url} target="_blank" rel="noreferrer">Official {docsChannel.name} API documentation ↗</a></p><div className="modalActions"><button type="button" className="secondary" onClick={() => setDocsChannel(null)}>Close</button>{docsChannel.status !== "coming_soon" && <button type="button" className="primary" disabled={!connectable(docsChannel)} onClick={() => { const channel = docsChannel; setDocsChannel(null); openConnect(channel); }}>Connect {docsChannel.name}</button>}</div></div>}</Modal></main></AppShell>;
}
