"use client";

import { apiFetch, authStorage, clearAuth, saveAuth } from "@/lib/api";
import type { Organization, User } from "@/lib/types";
import { createContext, useCallback, useContext, useEffect, useState } from "react";

interface AuthContextValue {
  user: User | null;
  organization: Organization | null;
  loading: boolean;
  login(email: string, password: string): Promise<void>;
  register(input: { name: string; email: string; password: string; password_confirmation: string; organization_name: string }): Promise<void>;
  logout(): Promise<void>;
  hydrate(): Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [organization, setOrganization] = useState<Organization | null>(null);
  const [loading, setLoading] = useState(true);

  const hydrate = useCallback(async () => {
    const { token } = authStorage();
    if (!token) {
      setLoading(false);
      return;
    }
    try {
      const response = await apiFetch<{ data: User }>("/auth/me");
      setUser(response.data);
      setOrganization(response.data.organizations?.find((item) => item.id === response.data.current_organization_id) ?? null);
    } catch {
      clearAuth();
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { void hydrate(); }, [hydrate]);

  const login = async (email: string, password: string) => {
    const response = await apiFetch<{ data: { user: User; token: string } }>("/auth/login", {
      method: "POST",
      body: JSON.stringify({ email, password }),
    }, false);
    saveAuth(response.data.token, response.data.user.current_organization_id);
    setUser(response.data.user);
    setOrganization((response.data.user as User & { current_organization?: Organization }).current_organization ?? null);
    await hydrate();
  };

  const register = async (input: { name: string; email: string; password: string; password_confirmation: string; organization_name: string }) => {
    const response = await apiFetch<{ data: { user: User; organization: Organization; token: string } }>("/auth/register", {
      method: "POST",
      body: JSON.stringify(input),
    }, false);
    saveAuth(response.data.token, response.data.organization.id);
    setUser(response.data.user);
    setOrganization(response.data.organization);
  };

  const logout = async () => {
    try { await apiFetch("/auth/logout", { method: "POST" }); } catch { /* clear locally regardless */ }
    clearAuth();
    setUser(null);
    setOrganization(null);
  };

  return <AuthContext.Provider value={{ user, organization, loading, login, register, logout, hydrate }}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) throw new Error("useAuth must be used inside AuthProvider");
  return context;
}
