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
  shiftRemoveConfirmMessage?: string;
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

/**
 * The shared props every Inertia page receives, plus whatever that page adds.
 *
 * `T` is an object rather than `object | unknown[]`: Inertia's own `PageProps`
 * is an index signature, and a union that can be an array does not satisfy it,
 * so `Page<AppPageProps>` had nothing valid to resolve to.
 */
export type AppPageProps<
  T extends Record<string, unknown> = Record<string, unknown>,
> = InertiaProps & Jetstream & T;
