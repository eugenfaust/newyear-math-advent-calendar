import NextAuth from "next-auth";
import Credentials from "next-auth/providers/credentials";

type ApiUser = {
  uuid: string;
  login: string;
  displayName: string;
  roles: string[];
};

type LoginResponse = {
  accessToken: string;
  expiresAt: string;
  user: ApiUser;
};

declare module "next-auth" {
  interface User {
    uuid: string;
    login: string;
    roles: string[];
    accessToken: string;
    expiresAt: string;
  }

  interface Session {
    user: {
      uuid: string;
      login: string;
      displayName: string;
      roles: string[];
    };
  }
}

type AuthTokenFields = {
  uuid?: string;
  login?: string;
  displayName?: string;
  roles?: string[];
  accessToken?: string;
  expiresAt?: string;
};

export const { handlers, auth, signIn, signOut } = NextAuth({
  pages: { signIn: "/login" },
  session: { strategy: "jwt", maxAge: 12 * 60 * 60 },
  providers: [
    Credentials({
      credentials: { login: {}, password: {} },
      async authorize(credentials) {
        const login = credentials.login;
        const password = credentials.password;
        if (typeof login !== "string" || typeof password !== "string" || !login || !password) {
          return null;
        }
        const baseUrl = process.env.INTERNAL_API_URL;
        if (!baseUrl) throw new Error("Backend URL is not configured.");
        const response = await fetch(`${baseUrl}/auth/login`, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ login, password }),
          cache: "no-store",
        });
        if (response.status === 401) return null;
        if (!response.ok) throw new Error("Authentication service is unavailable.");
        const result = (await response.json()) as LoginResponse;
        return {
          id: result.user.uuid,
          name: result.user.displayName,
          uuid: result.user.uuid,
          login: result.user.login,
          roles: result.user.roles,
          accessToken: result.accessToken,
          expiresAt: result.expiresAt,
        };
      },
    }),
  ],
  callbacks: {
    async jwt({ token, user }) {
      const authToken = token as typeof token & AuthTokenFields;
      if (user) {
        authToken.uuid = user.uuid;
        authToken.login = user.login;
        authToken.displayName = user.name ?? "";
        authToken.roles = user.roles;
        authToken.accessToken = user.accessToken;
        authToken.expiresAt = user.expiresAt;
      } else {
        const baseUrl = process.env.INTERNAL_API_URL;
        if (!baseUrl || !authToken.accessToken || !authToken.expiresAt || Date.parse(authToken.expiresAt) <= Date.now()) {
          return null;
        }
        try {
          const response = await fetch(`${baseUrl}/auth/me`, {
            headers: { Authorization: `Bearer ${authToken.accessToken}` },
            cache: "no-store",
          });
          if (!response.ok) return null;
          const currentUser = (await response.json()) as ApiUser;
          authToken.uuid = currentUser.uuid;
          authToken.login = currentUser.login;
          authToken.displayName = currentUser.displayName;
          authToken.roles = currentUser.roles;
        } catch {
          return null;
        }
      }
      return authToken;
    },
    session({ session, token }) {
      const authToken = token as typeof token & AuthTokenFields;
      return {
        expires: session.expires,
        user: {
          uuid: authToken.uuid ?? "",
          login: authToken.login ?? "",
          displayName: authToken.displayName ?? "",
          roles: authToken.roles ?? [],
        },
      };
    },
  },
  events: {
    async signOut(message) {
      if (!("token" in message) || typeof message.token?.accessToken !== "string") return;
      const baseUrl = process.env.INTERNAL_API_URL;
      if (!baseUrl) return;
      try {
        await fetch(`${baseUrl}/auth/logout`, {
          method: "POST",
          headers: { Authorization: `Bearer ${message.token.accessToken}` },
          cache: "no-store",
        });
      } catch {
        // Session cookie is still cleared; backend token expires independently.
      }
    },
  },
});
