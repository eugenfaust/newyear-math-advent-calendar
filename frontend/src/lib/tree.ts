export type TreeOrnament = {
  uuid: string;
  name: string;
  imageUrl: string;
  treeSlot: number | null;
  receivedAt: string;
  placedAt: string | null;
  updatedAt: string;
};

export type UserTree = {
  calendarUuid: string;
  year: number;
  completedDays: number;
  totalDays: 31;
  completed: boolean;
  ornaments: TreeOrnament[];
};
