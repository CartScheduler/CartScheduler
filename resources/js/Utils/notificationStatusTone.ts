import type { StampTone } from "@/Components/StatusStamp.vue";

/**
 * The stamp colour for a notification log status. A stalled send is reported as failed.
 */
export default function notificationStatusTone(status: App.Enums.NotificationStatus): StampTone {
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
