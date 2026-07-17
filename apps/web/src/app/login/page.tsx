"use client";

import { ApiError } from "@/lib/api";
import { useAuth } from "@/components/AuthProvider";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";

export default function LoginPage() {
  const { user, loading, login } = useAuth();
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => { if (!loading && user) router.replace("/"); }, [loading, user, router]);

  async function submit(event: FormEvent) {
    event.preventDefault(); setError(""); setSubmitting(true);
    try { await login(email, password); router.replace("/"); }
    catch (exception) { setError(exception instanceof ApiError ? exception.message : "Unable to sign in."); }
    finally { setSubmitting(false); }
  }

  return <main className="authPage"><section className="authCard"><div className="authBrand"><span className="brandMark">O</span><div><strong>OopSeller</strong><small>Amazon Agency OS</small></div></div><p className="eyebrow">Secure workspace</p><h1>Sign in</h1><p>Access your real client, product, listing, and Amazon integration data.</p><form className="formStack" onSubmit={submit}>{error && <div className="errorBox">{error}</div>}<label>Email<input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="email" /></label><label>Password<input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required autoComplete="current-password" /></label><button className="primary" disabled={submitting}>{submitting ? "Signing in…" : "Sign in"}</button></form><p className="authSwitch">New agency? <Link href="/register">Create an account</Link></p><p className="authSwitch"><Link href="/forgot-password">Forgot your password?</Link></p></section></main>;
}
