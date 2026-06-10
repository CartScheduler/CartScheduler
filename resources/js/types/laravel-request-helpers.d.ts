import "vite/client";
import type { ToastSeverity } from "@/Composables/useToast";

export type AuthUser = {
  id: number;
  uuid: string;
  name: string;
  email: string;
  gender: string;
};

export type InertiaProps = {
  pagePermissions: {
    canAdmin?: true;
    canEditSettings?: true;
  };
  shiftAvailability: {
    timezone: string;
    duration: number;
    period: App.Enums.DBPeriod;
    releasedDaily: boolean;
    weekDayRelease: "SUN" | "MON" | "TUE" | "WED" | "THU" | "FRI" | "SAT";
    systemShiftStartHour: number;
    systemShiftEndHour: number;
  };
  hasUpdate?: boolean;
  enableUserAvailability?: boolean;
  needsToUpdateAvailability?: boolean;
  enableUserLocationChoices?: boolean;
  isUnrestricted?: true;
  auth: {
    user: AuthUser;
  }
};

export interface Flash {
  title?: string;
  message?: string | undefined;
  position?: "top" | "bottom" | "center";
  /* @deprecated - Use message instead */
  banner?: string;
  /* @deprecated - Only use for 'success' messages */
  bannerStyle?: ToastSeverity extends string ? ToastSeverity : undefined;
}

export interface JetstreamProps {
  flash: Flash;
  canCreateTeams: boolean;
  canUpdateProfileInformation: boolean;
  canUpdatePassword: boolean;
  canManageTwoFactorAuthentication: boolean;
  hasAccountDeletionFeatures: boolean;
  hasApiFeatures: boolean;
  hasTeamFeatures: boolean;
  managesProfilePhotos: boolean;
  hasEmailVerification: boolean;
}


export interface Jetstream {
  jetstream: JetstreamProps;
}

export type AppPageProps<
  T extends Record<string, unknown> | unknown[] = Record<string, unknown> | unknown[],
> = InertiaProps & Jetstream & FlashMessage & T;
