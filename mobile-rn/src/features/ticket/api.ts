import type { ApiClient } from '@/api/client';
import { publicGet } from '@/api/publicClient';
import type {
  PerformanceTicketAvailability,
  PublicTicketList,
  QuotaAndTicketBackSettings,
  Ticket,
  TicketBackCondition,
  TicketSalesSettings,
} from '@/types/api';

/** GET /productions/{id}/tickets - admin listing (authenticated,
 * membership-gated). Includes ARCHIVED Tickets for history. */
export function fetchTickets(client: ApiClient, productionId: string): Promise<Ticket[]> {
  return client.get<Ticket[]>(`/productions/${productionId}/tickets`);
}

/** GET /productions/{id}/public-tickets - unauthenticated Public Page
 * listing (§9/§34). */
export function fetchPublicTickets(productionId: string): Promise<PublicTicketList> {
  return publicGet<PublicTicketList>(`/productions/${productionId}/public-tickets`);
}

/** POST /productions/{id}/tickets - §4確定事項①: price must be a
 * positive integer greater than zero (0円Ticketは扱わない). */
export function createTicket(
  client: ApiClient,
  productionId: string,
  fields: { name: string; price: number; remarks?: string }
): Promise<Ticket> {
  return client.post<Ticket>(`/productions/${productionId}/tickets`, {
    name: fields.name,
    price: fields.price,
    remarks: fields.remarks,
  });
}

/** PUT /tickets/{id}. */
export function updateTicket(
  client: ApiClient,
  ticketId: string,
  fields: { name: string; price: number; remarks: string | null }
): Promise<Ticket> {
  return client.put<Ticket>(`/tickets/${ticketId}`, {
    name: fields.name,
    price: fields.price,
    remarks: fields.remarks,
  });
}

/** POST /tickets/{id}/archive - "削除" is always a soft archive (§44),
 * never a physical delete. */
export function archiveTicket(client: ApiClient, ticketId: string): Promise<Ticket> {
  return client.post<Ticket>(`/tickets/${ticketId}/archive`);
}

/** GET /productions/{id}/ticket-sales-settings - reads the Production's
 * current publication/sales-window settings (any Production member),
 * so the チケット設定 screen can show what is already saved instead of
 * always starting blank. */
export function fetchTicketSalesSettings(client: ApiClient, productionId: string): Promise<TicketSalesSettings> {
  return client.get<TicketSalesSettings>(`/productions/${productionId}/ticket-sales-settings`);
}

/** GET /productions/{id}/quota-ticket-back-settings - reads the
 * Production's current Quota/Ticket Back settings (any Production
 * member). */
export function fetchQuotaAndTicketBackSettings(client: ApiClient, productionId: string): Promise<QuotaAndTicketBackSettings> {
  return client.get<QuotaAndTicketBackSettings>(`/productions/${productionId}/quota-ticket-back-settings`);
}

/** GET /productions/{id}/performance-ticket-availability - per-Performance
 * "is a Ticket currently purchasable" read model, recomputed from the
 * same publication/sales-window rules CreateReservationUseCase itself
 * enforces (never a new business rule) - lets the チケット管理 screen show
 * which Performances are currently open for sale. */
export function fetchPerformanceTicketAvailability(client: ApiClient, productionId: string): Promise<PerformanceTicketAvailability[]> {
  return client.get<PerformanceTicketAvailability[]>(`/productions/${productionId}/performance-ticket-availability`);
}

/** PUT /productions/{id}/ticket-sales-settings - Chapter 32 §3の
 * チケット設定画面の「公開設定」/「販売設定」セクション。 */
export function updateTicketSalesSettings(
  client: ApiClient,
  productionId: string,
  fields: {
    ticketPublicationAt?: string | null;
    ticketSalesStartAt?: string | null;
    ticketSalesEndRule?: string | null;
    ticketSalesEndParameter?: string | null;
  }
): Promise<TicketSalesSettings> {
  return client.put<TicketSalesSettings>(`/productions/${productionId}/ticket-sales-settings`, {
    ticket_publication_at: fields.ticketPublicationAt,
    ticket_sales_start_at: fields.ticketSalesStartAt,
    ticket_sales_end_rule: fields.ticketSalesEndRule,
    ticket_sales_end_parameter: fields.ticketSalesEndParameter,
  });
}

/** PUT /productions/{id}/quota-ticket-back-settings - Chapter 32 §4の
 * チケットバック／ノルマ設定画面。買取OFF時は`quotaShortfallUnitPrice`を
 * 送っても無視される(Domain側で強制的にnullへ正規化 - §20)。 */
export function updateQuotaAndTicketBackSettings(
  client: ApiClient,
  productionId: string,
  fields: {
    quotaEnabled: boolean;
    quotaCount: number | null;
    quotaBuybackEnabled: boolean;
    quotaShortfallUnitPrice: number | null;
    ticketBackMode: string | null;
    ticketBackConditions: TicketBackCondition[];
  }
): Promise<QuotaAndTicketBackSettings> {
  return client.put<QuotaAndTicketBackSettings>(`/productions/${productionId}/quota-ticket-back-settings`, {
    quota_enabled: fields.quotaEnabled,
    quota_count: fields.quotaCount,
    quota_buyback_enabled: fields.quotaBuybackEnabled,
    quota_shortfall_unit_price: fields.quotaShortfallUnitPrice,
    ticket_back_mode: fields.ticketBackMode,
    ticket_back_conditions: fields.ticketBackConditions,
  });
}
