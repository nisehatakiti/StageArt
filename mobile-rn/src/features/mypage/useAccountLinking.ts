import { useMutation } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';
import { signInWithGoogle } from '@/auth/googleSignIn';

import { addEmailCredential, changePassword, linkGoogleIdentity, requestEmailVerification } from '@/features/auth/api';

/**
 * StageArt Authentication Phase 5's account-linking self-service actions
 * (mypage.tsx). GET /me exposes no "already linked?" status today (no
 * Backend change was made for this - see this Phase's report's disclosed
 * simplification), so these actions are always offered regardless of
 * current state; the Backend's own idempotent-success (Google, already
 * linked to the caller's own account) / 409 Conflict (Email, already
 * set) responses are what actually tell the user whether the action
 * applied - never inferred client-side, and never by matching email
 * addresses (Backend Phase 2 report's "No Automatic Linking" principle).
 */
export function useChangePassword() {
  const { apiClient } = useAuth();

  return useMutation({
    mutationFn: ({ currentPassword, newPassword }: { currentPassword: string; newPassword: string }) =>
      changePassword(apiClient, currentPassword, newPassword),
  });
}

export function useLinkGoogleAccount() {
  const { apiClient } = useAuth();

  return useMutation({
    mutationFn: async () => {
      const idToken = await signInWithGoogle();
      return linkGoogleIdentity(apiClient, idToken);
    },
  });
}

export function useAddEmailCredential() {
  const { apiClient } = useAuth();

  return useMutation({
    mutationFn: ({ email, password }: { email: string; password: string }) => addEmailCredential(apiClient, email, password),
  });
}

export function useRequestEmailVerification() {
  const { apiClient } = useAuth();

  return useMutation({
    mutationFn: () => requestEmailVerification(apiClient),
  });
}
