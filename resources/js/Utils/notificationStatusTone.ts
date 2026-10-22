export type StatusTone = "success" | "danger" | "warning" | "info" | "neutral";

/**
 * The colour for a notification log status. A stalled send is reported as failed.
 */
export default function notificationStatusTone(status: App.Enums.NotificationStatus): StatusTone {
  switch (status) {
    case "sent":
      return "success";
    case "failed":
      return "danger";
    case "queued":
    case "retrying":
      return "warning";
    case "sending":
      return "info";
    default:
      return "neutral";
  }
}

/**
 * Text classes for a status shown as plain text. Success uses the normal text colour.
 */
export const statusToneTextClass: Record<StatusTone, string> = {
  success: "",
  danger: "text-red-600 dark:text-red-400",
  warning: "text-amber-700 dark:text-amber-300",
  info: "text-sky-700 dark:text-sky-300",
  neutral: "text-gray-500 dark:text-gray-400",
};
