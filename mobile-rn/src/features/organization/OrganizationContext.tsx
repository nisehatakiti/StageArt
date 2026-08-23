import { useQueryClient } from '@tanstack/react-query';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type PropsWithChildren } from 'react';

import { useAuth } from '@/auth/AuthContext';

type OrganizationContextValue = {
  currentOrganizationId: string | null;
  /** Sets the active Organization Context. Invalidates the (unscoped,
   * Membership-scoped) Production/Project queries on an actual switch
   * (previous id was non-null and different), per §13's "旧Organizationの
   * Productionを残さない" - not on the very first auto-select, which has
   * nothing stale to invalidate. */
  selectOrganization: (organizationId: string) => void;
};

const OrganizationContext = createContext<OrganizationContextValue | null>(null);

/**
 * §7: Organization Context is a Client Context only ("Organization
 * Context = Authorization ではない") - purely which Organization the UI
 * is currently browsing. Every Query these Organizations/Productions
 * come from is independently re-authorized server-side on every
 * request; this Context never bypasses that.
 *
 * Deliberately in-memory only (not persisted to SecureStore or any
 * Local Storage) - Organization Context is UI navigation state, not a
 * secret, and §23 explicitly excludes Offline/local persistence from
 * this Phase. Resets on logout so a stale id from a previous account
 * can never leak into a new session.
 */
export function OrganizationProvider({ children }: PropsWithChildren) {
  const { status } = useAuth();
  const queryClient = useQueryClient();
  const [currentOrganizationId, setCurrentOrganizationId] = useState<string | null>(null);
  // Tracks the previous Organization outside of React state so the
  // switch-detection below never needs to call invalidateQueries() from
  // inside a setState updater (calling side-effecting code, which
  // itself triggers other components' setStates, from inside an
  // updater function is unsafe - a real bug this Phase's testing caught:
  // switching Organizations silently stopped re-rendering Home under
  // Jest until this was fixed).
  const previousOrganizationIdRef = useRef<string | null>(null);

  useEffect(() => {
    if (status === 'unauthenticated') {
      // Subscribing to an external system's state (AuthContext's status)
      // and syncing local state in response is exactly the sanctioned
      // case react-hooks/set-state-in-effect calls out ("Subscribe for
      // updates from some external system, calling setState ... when
      // external state changes") - same justified pattern as
      // use-color-scheme.web.ts from Phase 5.1.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setCurrentOrganizationId(null);
      previousOrganizationIdRef.current = null;
    }
  }, [status]);

  const selectOrganization = useCallback(
    (organizationId: string) => {
      const previous = previousOrganizationIdRef.current;
      previousOrganizationIdRef.current = organizationId;
      setCurrentOrganizationId(organizationId);

      if (previous !== null && previous !== organizationId) {
        queryClient.invalidateQueries({ queryKey: ['productions'] });
        queryClient.invalidateQueries({ queryKey: ['projects'] });
      }
    },
    [queryClient]
  );

  const value = useMemo(
    () => ({ currentOrganizationId, selectOrganization }),
    [currentOrganizationId, selectOrganization]
  );

  return <OrganizationContext.Provider value={value}>{children}</OrganizationContext.Provider>;
}

export function useOrganizationContext(): OrganizationContextValue {
  const context = useContext(OrganizationContext);

  if (!context) {
    throw new Error('useOrganizationContext() must be used within an OrganizationProvider.');
  }

  return context;
}
