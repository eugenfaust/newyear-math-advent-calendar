export type TemplateTask = { uuid: string; title: string; isActive: boolean };
export type TemplateDay = { day: number; task: TemplateTask | null };
export type TemplateSummary = {
  uuid: string;
  name: string;
  description: string | null;
  year: number | null;
  isActive: boolean;
  filledDays: number;
  isComplete: boolean;
  createdAt: string;
  updatedAt: string;
};
export type TemplateDetail = TemplateSummary & { days: TemplateDay[] };
export type TemplateList = {
  items: TemplateSummary[];
  pagination: { page: number; perPage: number; total: number; totalPages: number };
};
