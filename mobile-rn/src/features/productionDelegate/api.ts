import type { ApiClient } from '@/api/client';
import type { ProductionDelegate } from '@/types/api';

/**
 * ProductionDelegate実用化 Phase: the Backend (ProductionDelegateRestController.php)
 * was already fully implemented - this is the first Frontend layer to
 * call it. `/productions/{id}/delegates` for list/create,
 * `/production-delegates/{id}` for update/delete (not nested under
 * production, matching how the Backend actually routes it).
 */
export function fetchProductionDelegates(client: ApiClient, productionId: string): Promise<ProductionDelegate[]> {
  return client.get<ProductionDelegate[]>(`/productions/${productionId}/delegates`);
}

export function createProductionDelegate(
  client: ApiClient,
  productionId: string,
  fields: { personId: string; role: string }
): Promise<ProductionDelegate> {
  return client.post<ProductionDelegate>(`/productions/${productionId}/delegates`, {
    person_id: fields.personId,
    role: fields.role,
  });
}

export function updateProductionDelegate(
  client: ApiClient,
  delegateId: string,
  fields: { role: string; status: string }
): Promise<ProductionDelegate> {
  return client.put<ProductionDelegate>(`/production-delegates/${delegateId}`, {
    role: fields.role,
    status: fields.status,
  });
}

export function deleteProductionDelegate(client: ApiClient, delegateId: string): Promise<void> {
  return client.delete<void>(`/production-delegates/${delegateId}`);
}
