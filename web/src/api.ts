export const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:18080/api'

export interface User { uuid: string; name: string; email: string; role: string; organization: { uuid: string; name: string; is_demo: boolean; expires_at: string | null } | null }
export interface AuthPayload { token: string; user: User }
export interface SetupStatus { configured: boolean }
export interface DemoStatus { enabled: boolean; candidate_count: number; lifetime_hours: number; at_capacity: boolean }
export interface DemoSessionPayload extends AuthPayload { demo: { offer_uuid: string; expires_at: string } }
export interface ProviderOption {
  key: string; label: string; defaults: { screening: string; scoring: string };
  configured: boolean; credential_configured: boolean;
}
export interface AiMeta { mode: string; providers: ProviderOption[] }
export interface Organization {
  uuid: string; name: string; notification_sender_name: string; notification_reply_to: string;
  default_screening_provider: string; default_screening_model: string | null;
  default_scoring_provider: string; default_scoring_model: string | null;
  screening_workers: number; scoring_workers: number; screening_prompt: string; scoring_prompt: string;
}
export interface TeamMember { uuid: string; name: string; email: string; role: string }
export interface Offer {
  uuid: string; title: string; company: string; location: string | null; description: string; criteria: string[];
  rejection_message?: string; final_rejection_message?: string; screening_provider?: string; screening_model?: string | null;
  scoring_provider?: string; scoring_model?: string | null; status?: string; opens_at?: string | null; closes_at: string | null;
  applications_count?: number; pending_count?: number; shortlisted_count?: number;
  intake_url?: string;
}
export interface Annotation { uuid: string; body: string; created_at: string }
export interface CandidateApplication {
  uuid: string; candidate_name: string; candidate_email: string; cv_original_name: string | null; cv_available: boolean; cv_deleted_at: string | null; cover_letter: string | null; source?: string; external_reference?: string | null;
  status: string; scope_score: number | null; scope_reason: string | null; final_score: number | null;
  score_breakdown: Record<string, number> | null; ai_summary: string | null; candidate_feedback: string | null;
  recruiter_rank: number | null; read_at: string | null; selected_at: string | null; notified_at: string | null; notification_status: 'pending' | 'sent' | 'previewed' | null;
  created_at: string; annotations: Annotation[];
}
export interface TrackingStatus { application_uuid: string; offer: { uuid: string; title: string; company: string }; status: string; submitted_at: string; score: number | null; feedback: string | null }

interface Resource<T> { data: T }

export async function apiRequest<T>(path: string, options: RequestInit = {}, token?: string): Promise<T> {
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')
  if (!(options.body instanceof FormData)) headers.set('Content-Type', 'application/json')
  if (token) headers.set('Authorization', `Bearer ${token}`)
  const response = await fetch(`${API_URL}${path}`, { ...options, headers })
  if (!response.ok) {
    const error = await response.json() as { message?: string }
    throw new Error(error.message ?? `HTTP ${response.status}`)
  }
  if (response.status === 204) return {} as T
  return await response.json() as T
}

export const unwrap = <T>(resource: Resource<T>): T => resource.data
