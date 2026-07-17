"use client";

import { AppShell } from "@/components/AppShell";
import { EmptyState } from "@/components/EmptyState";
import { PageHeader } from "@/components/PageHeader";
import { StatusPill } from "@/components/StatusPill";
import { apiFetch, ApiError } from "@/lib/api";
import type { Listing, Paginated } from "@/lib/types";
import { FormEvent, useCallback, useEffect, useMemo, useState } from "react";

type Preview = { can_publish: boolean; preview_hash: string; amazon_response: { status?: string; submissionId?: string; issues?: Array<{ code?: string; message?: string; severity?: string }> }; patches: unknown[] };

export default function ListingOptimizerPage() {
  const [listings, setListings] = useState<Listing[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [form, setForm] = useState({ title: "", bullets: "", description: "", backend_terms: "", change_summary: "" });
  const [preview, setPreview] = useState<Preview | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState("");
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const response = await apiFetch<Paginated<Listing>>("/listings?per_page=100");
      setListings(response.data);
      if (!selectedId && response.data[0]) select(response.data[0]);
      setError("");
    } catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to load listings."); }
    finally { setLoading(false); }
  }, [selectedId]);

  useEffect(() => { void load(); }, [load]);
  const selected = useMemo(() => listings.find((listing) => listing.id === selectedId) ?? null, [listings, selectedId]);

  function select(listing: Listing) {
    setSelectedId(listing.id); setPreview(null); setSuccess("");
    setForm({
      title: listing.title ?? "",
      bullets: (listing.bullet_points ?? []).join("\n"),
      description: listing.description ?? "",
      backend_terms: (listing.backend_terms ?? []).join("\n"),
      change_summary: "",
    });
  }

  function payload() {
    return {
      title: form.title || null,
      bullet_points: form.bullets.split("\n").map((item) => item.trim()).filter(Boolean),
      description: form.description || null,
      backend_terms: form.backend_terms.split("\n").map((item) => item.trim()).filter(Boolean),
      change_summary: form.change_summary || null,
    };
  }

  async function save(event: FormEvent) {
    event.preventDefault(); if (!selected) return; setBusy("save"); setError("");
    try { await apiFetch(`/listings/${selected.id}`, { method: "PUT", body: JSON.stringify(payload()) }); setSuccess("Draft saved in OopSeller. Nothing was sent to Amazon."); setPreview(null); await load(); }
    catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to save listing."); }
    finally { setBusy(""); }
  }

  async function refresh() {
    if (!selected) return; setBusy("refresh"); setError("");
    try { await apiFetch(`/listings/${selected.id}/refresh-amazon`, { method: "POST", body: JSON.stringify({}) }); setSuccess("Latest seller listing imported from Amazon."); await load(); }
    catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to refresh listing."); }
    finally { setBusy(""); }
  }

  async function validateAmazon() {
    if (!selected) return; setBusy("preview"); setError(""); setPreview(null);
    try { const response = await apiFetch<{ data: Preview }>(`/listings/${selected.id}/amazon/preview`, { method: "POST", body: JSON.stringify(payload()) }); setPreview(response.data); setSuccess(response.data.can_publish ? "Amazon validation passed. You can now publish this exact version." : "Amazon returned validation errors. Nothing was published."); }
    catch (exception) { setError(exception instanceof ApiError ? exception.message : "Amazon validation failed."); }
    finally { setBusy(""); }
  }

  async function publishAmazon() {
    if (!selected || !preview?.can_publish) return;
    if (!window.confirm("Publish this validated listing update to Amazon?")) return;
    setBusy("publish"); setError("");
    try { await apiFetch(`/listings/${selected.id}/amazon/publish`, { method: "POST", body: JSON.stringify({ preview_hash: preview.preview_hash, confirm: true, change_summary: form.change_summary || null }) }); setSuccess("Amazon accepted the listing update. Refresh later to verify processing status and issues."); setPreview(null); await load(); }
    catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to publish to Amazon."); }
    finally { setBusy(""); }
  }

  return <AppShell><main className="main"><PageHeader eyebrow="Listing operations" title="Listing optimizer" description="Edit locally, validate with Amazon, and publish only after a successful validation preview." actions={selected && <button className="secondary" onClick={refresh} disabled={busy !== "" || !selected.channel_account_id || !selected.seller_sku}>{busy === "refresh" ? "Refreshing…" : "Refresh from Amazon"}</button>} />{error && <div className="errorBox">{error}</div>}{success && <div className="successBox">{success}</div>}{loading ? <div className="panel loadingPanel">Loading listings…</div> : listings.length === 0 ? <section className="panel"><EmptyState title="No listings available" description="Add a product with a marketplace, or import seller listings from a connected Amazon account." /></section> : <section className="optimizerLayout"><aside className="panel listingPicker"><div className="panelHeader"><div><h2>Listings</h2><p>{listings.length} managed listing{listings.length === 1 ? "" : "s"}</p></div></div>{listings.map((listing) => <button className={selectedId === listing.id ? "listingChoice activeChoice" : "listingChoice"} onClick={() => select(listing)} key={listing.id}><strong>{listing.product?.name ?? listing.title ?? "Untitled listing"}</strong><small>{listing.product?.asin} · {listing.seller_sku || "No seller SKU"}</small><span><StatusPill value={listing.status} /></span></button>)}</aside>{selected && <form className="panel optimizerEditor" onSubmit={save}><div className="panelHeader"><div><h2>{selected.product?.name}</h2><p>{selected.product?.asin} · {selected.marketplace_id} · {selected.seller_sku || "No seller SKU"}</p></div>{selected.channel_account_id ? <span className="pill active">Amazon connected</span> : <span className="pill">Local only</span>}</div>{selected.amazon_issues && selected.amazon_issues.length > 0 && <div className="issueBox"><strong>Current Amazon issues</strong>{selected.amazon_issues.map((issue, index) => <p key={`${issue.code}-${index}`}><span>{issue.severity || "ISSUE"}</span> {issue.message || issue.code}</p>)}</div>}<label>Title<textarea rows={3} value={form.title} onChange={(event) => { setForm({ ...form, title: event.target.value }); setPreview(null); }} /></label><label>Bullet points <small>One bullet per line</small><textarea rows={8} value={form.bullets} onChange={(event) => { setForm({ ...form, bullets: event.target.value }); setPreview(null); }} /></label><label>Description<textarea rows={8} value={form.description} onChange={(event) => { setForm({ ...form, description: event.target.value }); setPreview(null); }} /></label><label>Backend search terms <small>One term group per line</small><textarea rows={5} value={form.backend_terms} onChange={(event) => { setForm({ ...form, backend_terms: event.target.value }); setPreview(null); }} /></label><label>Change summary<input value={form.change_summary} onChange={(event) => setForm({ ...form, change_summary: event.target.value })} placeholder="Why this version is being changed" /></label><div className="editorActions"><button className="secondary" disabled={busy !== ""}>{busy === "save" ? "Saving…" : "Save local draft"}</button><button type="button" className="secondary" onClick={validateAmazon} disabled={busy !== "" || !selected.channel_account_id || !selected.seller_sku || !selected.product?.product_type}>{busy === "preview" ? "Validating…" : "Validate with Amazon"}</button><button type="button" className="primary" onClick={publishAmazon} disabled={busy !== "" || !preview?.can_publish}>{busy === "publish" ? "Publishing…" : "Publish validated version"}</button></div>{selected.channel_account_id && (!selected.seller_sku || !selected.product?.product_type) && <div className="warningBox">Amazon publishing requires a seller SKU and Amazon product type. Refresh or re-import this listing first.</div>}{preview && <div className={preview.can_publish ? "previewBox successPreview" : "previewBox errorPreview"}><strong>Amazon validation: {preview.can_publish ? "Passed" : "Blocked"}</strong><p>Status: {preview.amazon_response.status ?? "Response received"} {preview.amazon_response.submissionId ? `· Submission ${preview.amazon_response.submissionId}` : ""}</p>{(preview.amazon_response.issues ?? []).length === 0 ? <p>No validation issues returned.</p> : (preview.amazon_response.issues ?? []).map((issue, index) => <p key={`${issue.code}-${index}`}><b>{issue.severity}</b> {issue.message || issue.code}</p>)}</div>}</form>}</section>}</main></AppShell>;
}
