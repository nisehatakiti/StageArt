import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';
import type { Organization } from '@/types/api';

import {
  assignOrganizationDelegate,
  cancelOrganizationInvitation,
  createOrganizationInvitation,
  deleteOrganizationLogo,
  fetchOrganizationInvitations,
  fetchOrganizationMembers,
  fetchOrganizations,
  resendOrganizationInvitation,
  revokeOrganizationDelegate,
  transferOrganizationOwner,
  updateOrganizationMemberRemarks,
  uploadOrganizationLogo,
} from './api';

export function useOrganizations() {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['organizations'],
    queryFn: () => fetchOrganizations(apiClient),
    enabled: status === 'authenticated',
  });
}

/** StageArt 団体管理UI整理 "メンバー管理": the roster + 代理人を設定/
 * 代表者交代's own member picker. */
export function useOrganizationMembers(organizationId: string | undefined) {
  const { apiClient, status } = useAuth();
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['organization-members', organizationId],
    queryFn: () => fetchOrganizationMembers(apiClient, organizationId as string),
    enabled: status === 'authenticated' && !!organizationId,
  });

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['organization-members', organizationId] });
  }

  const assignDelegate = useMutation({
    mutationFn: (personId: string) => assignOrganizationDelegate(apiClient, organizationId as string, personId),
    onSuccess: invalidate,
  });

  const revokeDelegate = useMutation({
    mutationFn: (personId: string) => revokeOrganizationDelegate(apiClient, organizationId as string, personId),
    onSuccess: invalidate,
  });

  const updateRemarks = useMutation({
    mutationFn: ({ personId, remarks }: { personId: string; remarks: string | null }) =>
      updateOrganizationMemberRemarks(apiClient, organizationId as string, personId, remarks),
    onSuccess: invalidate,
  });

  return { query, assignDelegate, revokeDelegate, updateRemarks };
}

/** StageArt Organization管理UI/UX統一 "メンバー管理": 招待中のメンバー,
 * mirroring Production's own pending-invitations table (list + resend +
 * cancel). */
export function useOrganizationInvitations(organizationId: string | undefined) {
  const { apiClient, status } = useAuth();
  const queryClient = useQueryClient();

  const query = useQuery({
    queryKey: ['organization-invitations', organizationId],
    queryFn: () => fetchOrganizationInvitations(apiClient, organizationId as string),
    enabled: status === 'authenticated' && !!organizationId,
  });

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['organization-invitations', organizationId] });
  }

  const resend = useMutation({
    mutationFn: (invitationId: string) => resendOrganizationInvitation(apiClient, invitationId),
    onSuccess: invalidate,
  });

  const cancel = useMutation({
    mutationFn: (invitationId: string) => cancelOrganizationInvitation(apiClient, invitationId),
    onSuccess: invalidate,
  });

  return { query, resend, cancel };
}

/** StageArt Organization管理UI/UX統一 "団体ロゴ": upload (multipart) /
 * delete, both invalidating the same `organizations` list query every
 * other Organization field mutation already invalidates. */
export function useOrganizationLogo(organizationId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  /**
   * StageArt Organization管理UI/UX統一 "団体ロゴ" race-condition fix:
   * writes the Backend's own response directly into the `organizations`
   * cache instead of only `invalidateQueries`-ing it. A plain invalidate
   * schedules a background refetch that resolves after this callback
   * returns, so a 団体情報編集画面 save fired in that gap would still read
   * the pre-upload `organization.logo_url` from the (still stale) cache
   * and overwrite the just-uploaded logo back to its old value - this
   * closes that window by updating the cache synchronously, in the same
   * tick the upload/delete itself resolves.
   */
  function applyUpdatedOrganization(updated: Organization) {
    queryClient.setQueryData<Organization[]>(['organizations'], (current) =>
      current?.map((organization) => (organization.id === updated.id ? updated : organization))
    );
  }

  const upload = useMutation({
    mutationFn: ({ file, fileName }: { file: Blob; fileName: string }) =>
      uploadOrganizationLogo(apiClient, organizationId as string, file, fileName),
    onSuccess: applyUpdatedOrganization,
  });

  const remove = useMutation({
    mutationFn: () => deleteOrganizationLogo(apiClient, organizationId as string),
    onSuccess: applyUpdatedOrganization,
  });

  return { upload, remove };
}

/** StageArt 団体管理UI整理 "代表者交代": owner-only, demotes the current
 * Owner to MEMBER and promotes the target to OWNER
 * (OwnerTransferUseCase). Invalidates `organizations` (current_person_role
 * changes for the caller) and `organization-members` (both rows change
 * role). */
export function useTransferOrganizationOwner(organizationId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (newOwnerPersonId: string) => transferOrganizationOwner(apiClient, organizationId as string, newOwnerPersonId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['organizations'] });
      queryClient.invalidateQueries({ queryKey: ['organization-members', organizationId] });
    },
  });
}

/** StageArt 団体管理UI整理 "メンバーを追加". */
export function useCreateOrganizationInvitation(organizationId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ name, email, remarks }: { name: string; email: string; remarks?: string }) =>
      createOrganizationInvitation(apiClient, organizationId as string, name, email, remarks),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['organization-members', organizationId] });
      queryClient.invalidateQueries({ queryKey: ['organization-invitations', organizationId] });
    },
  });
}
