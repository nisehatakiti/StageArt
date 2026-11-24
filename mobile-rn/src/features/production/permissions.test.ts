import type { Production } from '@/types/api';

import {
  canManageJoinKeys,
  canManageParticipants,
  canManageProductionOperations,
  delegateRolesOf,
  isProxyManager,
  productionResponsibilities,
} from './permissions';

const base: Production = {
  id: 'prod-1',
  project_id: 'proj-1',
  name: 'Show',
  title_heading: null,
  status: 'DRAFT',
  slug: null,
  published_at: null,
  primary_manager_person_id: 'person-pm',
  created_at: '',
  updated_at: '',
  is_primary_manager: false,
  delegate_role: null,
};

function as(fields: Partial<Production>): Production {
  return { ...base, ...fields };
}

describe('Production 担当区分 helpers', () => {
  it('PrimaryManager may manage operations, Join Keys and participants', () => {
    const pm = as({ is_primary_manager: true, delegate_roles: [], is_proxy_manager: false, can_manage_production_operations: true });
    expect(canManageProductionOperations(pm)).toBe(true);
    expect(canManageJoinKeys(pm)).toBe(true);
    expect(canManageParticipants(pm)).toBe(true);
    expect(productionResponsibilities(pm)).toEqual(['PRIMARY_MANAGER']);
  });

  it('公演代理人 (both Roles) may manage operations, Join Keys and participants', () => {
    const proxy = as({
      delegate_role: 'REHEARSAL_MANAGER',
      delegate_roles: ['PARTICIPANT_MANAGER', 'REHEARSAL_MANAGER'],
      is_proxy_manager: true,
      can_manage_production_operations: true,
    });
    expect(isProxyManager(proxy)).toBe(true);
    expect(canManageProductionOperations(proxy)).toBe(true);
    expect(canManageJoinKeys(proxy)).toBe(true);
    expect(canManageParticipants(proxy)).toBe(true);
    expect(productionResponsibilities(proxy)).toEqual(['PROXY_MANAGER']);
  });

  it('稽古担当 (REHEARSAL_MANAGER only) gets no Production management', () => {
    const rehearsal = as({ delegate_role: 'REHEARSAL_MANAGER', delegate_roles: ['REHEARSAL_MANAGER'], is_proxy_manager: false, can_manage_production_operations: false });
    expect(canManageProductionOperations(rehearsal)).toBe(false);
    expect(canManageJoinKeys(rehearsal)).toBe(false);
    expect(canManageParticipants(rehearsal)).toBe(false);
    expect(productionResponsibilities(rehearsal)).toEqual(['REHEARSAL_MANAGER']);
  });

  it('会計担当 (ACCOUNTING_MANAGER) gets no Production management', () => {
    const accounting = as({ delegate_role: 'ACCOUNTING_MANAGER', delegate_roles: ['ACCOUNTING_MANAGER'], is_proxy_manager: false, can_manage_production_operations: false });
    expect(canManageProductionOperations(accounting)).toBe(false);
    expect(canManageJoinKeys(accounting)).toBe(false);
    expect(canManageParticipants(accounting)).toBe(false);
    expect(productionResponsibilities(accounting)).toEqual(['ACCOUNTING_MANAGER']);
  });

  it('PARTICIPANT_MANAGER only manages participants, not operations or Join Keys', () => {
    const participant = as({ delegate_role: 'PARTICIPANT_MANAGER', delegate_roles: ['PARTICIPANT_MANAGER'], is_proxy_manager: false, can_manage_production_operations: false });
    expect(canManageProductionOperations(participant)).toBe(false);
    expect(canManageJoinKeys(participant)).toBe(false);
    expect(canManageParticipants(participant)).toBe(true);
  });

  it('recognises 公演代理人 from delegate_roles whatever the legacy delegate_role says', () => {
    // The server-side legacy field holds only the first Role found.
    for (const legacy of ['PARTICIPANT_MANAGER', 'REHEARSAL_MANAGER']) {
      const proxy = as({ delegate_role: legacy, delegate_roles: ['PARTICIPANT_MANAGER', 'REHEARSAL_MANAGER'] });
      expect(isProxyManager(proxy)).toBe(true);
      expect(canManageProductionOperations(proxy)).toBe(true);
      expect(canManageParticipants(proxy)).toBe(true);
    }
  });

  it('trusts the server decision fields over its own derivation', () => {
    const refused = as({ delegate_roles: ['PARTICIPANT_MANAGER', 'REHEARSAL_MANAGER'], is_proxy_manager: false, can_manage_production_operations: false });
    expect(isProxyManager(refused)).toBe(false);
    expect(canManageProductionOperations(refused)).toBe(false);
  });

  it('falls back to the legacy single Role for older responses, never granting operations to one Role', () => {
    const legacyRehearsal = as({ delegate_role: 'REHEARSAL_MANAGER' });
    expect(delegateRolesOf(legacyRehearsal)).toEqual(['REHEARSAL_MANAGER']);
    expect(canManageProductionOperations(legacyRehearsal)).toBe(false);
    const legacyPm = as({ is_primary_manager: true });
    expect(canManageProductionOperations(legacyPm)).toBe(true);
  });

  it('no Production, no permission', () => {
    expect(canManageProductionOperations(undefined)).toBe(false);
    expect(canManageJoinKeys(null)).toBe(false);
    expect(canManageParticipants(undefined)).toBe(false);
    expect(productionResponsibilities(undefined)).toEqual([]);
  });
});
