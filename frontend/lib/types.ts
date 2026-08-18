export type RmsUser = {
  id?: number;
  username: string;
  role: string;
  roleLabel?: string;
  displayName?: string;
  department?: string | null;
  employeeId?: string;
  email?: string;
};

export type TicketListItem = {
  reference: string;
  title?: string;
  status?: string;
  department?: string;
  submittedBy?: string;
  submittedByName?: string;
  riskLevel?: string;
  updatedAt?: string;
};

export type TicketFiveW1H = {
  what?: string;
  why?: string;
  where?: string;
  when?: string;
  who?: string;
  how?: string;
};

export type TicketComment = {
  id?: string;
  body?: string;
  authorName?: string;
  authorRole?: string;
  at?: string;
};

export type TicketDetail = TicketListItem &
  TicketFiveW1H & {
    location?: string;
    mitigationApproach?: string;
    evidenceCount?: number;
    fiveW1H?: TicketFiveW1H;
    ownership?: { state?: string; ownerName?: string };
    actionPlan?: { summary?: string; steps?: string[]; targetDate?: string };
    personnel?: Array<{ id?: string; name?: string; role?: string }>;
    threadComments?: TicketComment[];
    ai?: Record<string, unknown>;
    submittedBy?: string;
  };

export type TicketDraftPayload = {
  title: string;
  location: string;
  what: string;
  why: string;
  where: string;
  when: string;
  who: string;
  how: string;
  mitigationApproach: string;
  evidenceCount: number;
};

export type NotificationItem = {
  id: string | number;
  title?: string;
  body?: string;
  read?: boolean;
  createdAt?: string;
};

export type DepartmentItem = {
  id: string;
  name: string;
  code?: string;
  description?: string;
  head?: string | null;
  active?: boolean;
  status?: string;
};

export type PositionItem = {
  id: string;
  name: string;
  active?: boolean;
};

export type AdminUserItem = RmsUser & {
  status?: string;
  position?: string;
  active?: boolean;
  builtIn?: boolean;
};

export type AuditLogItem = {
  id?: string;
  at?: string;
  username?: string;
  roleLabel?: string;
  action?: string;
  module?: string;
  description?: string;
  ip?: string;
};

export type SystemSettings = {
  landingTagline?: string;
  landingHeadline?: string;
  organizationName?: string;
  defaultRiskLevels?: string[];
  emailNotifications?: boolean;
  passwordMinLength?: number;
  sessionTimeoutMinutes?: number;
  mfaEnabled?: boolean;
  maxUploadSizeMb?: number;
  allowedFileTypes?: string[];
  maintenanceMode?: boolean;
  backupEnabled?: boolean;
  backupFrequency?: string;
};
