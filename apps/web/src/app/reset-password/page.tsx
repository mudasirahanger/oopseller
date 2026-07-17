"use client";

import { ApiError, apiFetch } from "@/lib/api";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";

export default function ResetPasswordPage() {
  const router = useRouter();
  const [token, setToken] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [error, setError] = useState("");
  const [message, setMessage] = useState("");
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    setToken(params.get("token") ?? "");
    setEmail(params.get("email") ?? "");
  }, []);

  async function submit(event: FormEvent) {
    event.preventDefault(); setError(""); setMessage(""); setSubmitting(true);
    try {
      const response = await apiFetch<{ message: string }>("/auth/reset-password", {
        method: "POST",
        body: JSON.stringify({ token, email, password, password_confirmation: passwordConfirmation }),
      }, false);
      setMessage(response.message);
      setTimeout(() => router.replace("/login"), 1800);
    } catch (exception) {
      setError(exception instanceof ApiError ? exception.message : "Unable to reset the password.");
    } finally {
      setSubmitting(false);
    }
  }

  return <main className="authPage"><section className="authCard"><div className="authBrand"><span className="brandMark">O</span><div><strong>OopSeller</strong><small>Amazon Agency OS</small></div></div><p className="eyebrow">Account recovery</p><h1>Reset password</h1><p>Choose a new password for your account.</p><form className="formStack" onSubmit={submit}>{error && <div className="errorBox">{error}</div>}{message && <div className="successBox">{message}</div>}<label>Email<input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required autoComplete="email" /></label><label>New password<input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required minLength={8} autoComplete="new-password" /></label><label>Confirm new password<input type="password" value={passwordConfirmation} onChange={(e) => setPasswordConfirmation(e.target.value)} required minLength={8} autoComplete="new-password" /></label><button className="primary" disabled={submitting || !token}>{submitting ? "Resetting…" : "Reset password"}</button></form><p className="authSwitch"><Link href="/login">Back to sign in</Link></p></section></main>;
}
