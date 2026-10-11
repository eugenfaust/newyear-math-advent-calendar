export type CalendarDayStatus = "locked" | "available" | "expired" | "completed";

export type CalendarDaySummary = {
  day: number;
  status: CalendarDayStatus;
  availableOn: string;
  openedAt: string | null;
  completedAt: string | null;
};

export type UserCalendar = {
  uuid: string;
  year: number;
  startedAt: string | null;
  completedDays: number;
  availableDays: number;
  totalDays: 31;
  days: CalendarDaySummary[];
};

export type CalendarTask = {
  uuid: string;
  title: string;
  content: string;
  imageUrl: string | null;
  answerType: "NONE";
};

export type CalendarDay = Omit<CalendarDaySummary, "status"> & {
  year: number;
  status: "available" | "completed";
  task: CalendarTask;
};

export type CompletionReward = { uuid: string; name: string; imageUrl: string };
export type CalendarDayCompletion = { completed: true; completedAt: string; reward: CompletionReward };
