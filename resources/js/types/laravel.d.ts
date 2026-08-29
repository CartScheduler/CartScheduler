declare namespace App.Data {
  export type AdminUserData = {
    id: number;
    name: string;
  };
  export type AvailabilityData = {
    day_monday?: Array<App.Enums.AvailabilityHours>;
    day_tuesday?: Array<App.Enums.AvailabilityHours>;
    day_wednesday?: Array<App.Enums.AvailabilityHours>;
    day_thursday?: Array<App.Enums.AvailabilityHours>;
    day_friday?: Array<App.Enums.AvailabilityHours>;
    day_saturday?: Array<App.Enums.AvailabilityHours>;
    day_sunday?: Array<App.Enums.AvailabilityHours>;
    num_mondays: number;
    num_tuesdays: number;
    num_wednesdays: number;
    num_thursdays: number;
    num_fridays: number;
    num_saturdays: number;
    num_sundays: number;
    comments?: string;
  };
  export type AvailableShiftMetaData = {
    volunteer_count: number;
    max_allowed: number;
    has_availability: boolean;
  };
  export type AvailableShiftsData = {
    shifts: { [date: string]: { [shift_id: number]: App.Data.UserShiftData[] } };
    freeShifts: { [date: string]: App.Data.AvailableShiftMetaData };
    locations: Array<App.Data.LocationData>;
    maxDateReservation: string;
  };
  export type ExtendedUserData = {
    id: number;
    name: string;
    gender?: "male" | "female" | undefined;
    mobile_phone?: string;
    email?: string;
    marital_status?: App.Enums.MaritalStatus;
    appointment?: App.Enums.Appointment;
    serving_as?: App.Enums.ServingAs;
    responsible_brother?: string;
    birth_year?: number;
    shift_id?: number;
    shift_date?: string;
    last_shift_date?: string;
    last_shift_start_time?: string;
    last_location_name?: string;
    num_sundays?: number;
    num_mondays?: number;
    num_tuesdays?: number;
    num_wednesdays?: number;
    num_thursdays?: number;
    num_fridays?: number;
    num_saturdays?: number;
    filled_sundays?: number;
    filled_mondays?: number;
    filled_tuesdays?: number;
    filled_wednesdays?: number;
    filled_thursdays?: number;
    filled_fridays?: number;
    filled_saturdays?: number;
    availability_comments?: string;
  };
  export type FilledShiftData = {
    date: IsoDate;
    shifts_filled: number;
    shifts_available: number;
  };
  export type IdAndNameData = {
    id: number;
    name: string;
  };
  export type LocationAdminData = {
    id?: number;
    name: string;
    description?: string;
    clean_description?: string;
    min_volunteers?: number;
    max_volunteers?: number;
    requires_brother: boolean;
    latitude?: number;
    longitude?: number;
    is_enabled: boolean;
    sort_order?: number;
    shifts: Array<App.Data.ShiftAdminData>;
  };
  export type LocationChoiceData = {
    id: number;
    name: string;
  };
  export type LocationData = {
    id: number;
    name: string;
    description?: string;
    min_volunteers: number;
    max_volunteers: number;
    requires_brother: boolean;
    shifts?: Array<App.Data.ShiftData>;
  };
  export type OutstandingReportsData = {
    shift_id: number;
    shift_date: IsoDate;
    start_time: TwentyFourHourTime;
    end_time: TwentyFourHourTime;
    requires_brother?: number;
    location_name?: string;
  };
  export type ReportMetadataData = {
    shift_id: number;
    shift_time: string;
    location_id: number;
    location_name: string;
    submitted_by_id: number;
    submitted_by_name: string;
    submitted_by_email: string;
    submitted_by_phone: string;
    associates: { id:number;name:string };
  };
  export type ReportTagData = {
    id?: number;
    name: string;
    order_column?: number;
  };
  export type ReportsData = {
    id: number;
    shift_date?: string;
    placements_count?: number;
    videos_count?: number;
    requests_count?: number;
    comments?: string;
    shift_was_cancelled: boolean;
    tags: Array<{ id: number; name: { [lang: string]: string }; slug: { [lang: string]: string } }>;
    metadata?: App.Data.ReportMetadataData;
  };
  export type ShiftAdminData = {
    id?: number;
    location_id: number;
    start_time: string;
    end_time: string;
    day_monday: boolean;
    day_tuesday: boolean;
    day_wednesday: boolean;
    day_thursday: boolean;
    day_friday: boolean;
    day_saturday: boolean;
    day_sunday: boolean;
    available_from?: string;
    available_to?: string;
    is_enabled: boolean;
  };
  export type ShiftData = {
    id: number;
    start_time: string;
    end_time: string;
    available_from?: string;
    available_to?: string;
    volunteers?: Array<App.Data.UserData>;
    location?: App.Data.LocationData;
    js_days: [boolean, boolean, boolean, boolean, boolean, boolean, boolean];
  };
  export type SpouseAdminData = {
    id: number;
    name: string;
  };
  export type UserAdminData = {
    id: number;
    name: string;
    email: string;
    role: string;
    gender?: "male" | "female" | undefined;
    mobile_phone?: string;
    year_of_birth?: number;
    appointment?: App.Enums.Appointment;
    serving_as?: App.Enums.ServingAs;
    marital_status?: App.Enums.MaritalStatus;
    responsible_brother?: string;
    vacations?: Array<App.Data.UserVacationData>;
    availability?: App.Data.AvailabilityData;
    spouse?: App.Data.SpouseAdminData;
    selectedLocations?: Array<number>;
    is_enabled: boolean;
    is_unrestricted: boolean;
    has_logged_in: boolean;
  };
  export type UserData = {
    name: string;
    id?: number;
    uuid?: string;
    gender: string;
    mobile_phone: string;
    email?: string;
    shift_id?: number;
    shift_date?: string;
    is_unrestricted?: boolean;
    last_shift_date?: string;
    last_shift_start_time?: TwentyFourHourTime;
  };
  export type UserShiftData = {
    volunteer_id: number;
    location_id: number;
    location_name: string;
    start_time: TwentyFourHourTime;
    end_time: TwentyFourHourTime;
    max_volunteers: number;
    available_from?: string;
    available_to?: string;
  };
  export type UserVacationData = {
    id?: number;
    start_date: string;
    end_date: string;
    description?: string;
  };
}
declare namespace App.Enums {
  export type Appointment = "elder" | "ministerial servant";
  export type AvailabilityHours = 0 | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 | 14 | 15 | 16 | 17 | 18 | 19 | 20 | 21 | 22 | 23;
  export type CacheKey = "total-users" | "total-locations" | "shift-filled-data" | "outstanding-reports";
  export type DBPeriod = "MONTH" | "MONTHS" | "WEEK" | "WEEKS";
  export type MaritalStatus = "single" | "married" | "separated" | "divorced" | "widowed";
  export type Role = "admin" | "user";
  export type ServingAs = "field missionary" | "special pioneer" | "bethel family member" | "circuit overseer" | "regular pioneer" | "publisher";
}
declare namespace App.Settings {
  export type GeneralSettings = { siteName: string;
    currentVersion: string;
    availableVersion: string;
    allowedSettingsUsers: Array<number>;
    systemShiftStartHour: number;
    systemShiftEndHour: number;
    enableUserAvailability: boolean;
    enableUserLocationChoices: boolean;
    enableShiftRemoveConfirm: boolean;
    shiftRemoveConfirmMessage: string;
  };
}
