import { ref, toValue } from "vue";
import type { MaybeRefOrGetter } from "vue";

type Options = {
  templateKey: MaybeRefOrGetter<string>;
  subject: MaybeRefOrGetter<string>;
  body: MaybeRefOrGetter<string>;
};

/**
 * Renders an unsaved email template through the server's preview endpoint.
 * Nothing is requested while the subject or body is empty.
 */
export default function useEmailTemplatePreview({ templateKey, subject, body }: Options) {
  const loading = ref(false);
  const error = ref<string | null>(null);
  const previewSubject = ref("");
  const previewHtml = ref("");

  const loadPreview = async () => {
    const currentSubject = toValue(subject);
    const currentBody = toValue(body);

    if (!currentSubject || !currentBody) {
      error.value = null;
      previewSubject.value = "";
      previewHtml.value = "";
      return;
    }

    loading.value = true;
    error.value = null;

    try {
      const response = await axios.post<{ subject: string; html: string }>(route("admin.emails.preview"), {
        key: toValue(templateKey),
        subject: currentSubject,
        body: currentBody,
      });

      previewSubject.value = response.data.subject;
      previewHtml.value = response.data.html;
    } catch {
      error.value = "The email preview could not be generated.";
      previewSubject.value = "";
      previewHtml.value = "";
    } finally {
      loading.value = false;
    }
  };

  return { loading, error, previewSubject, previewHtml, loadPreview };
}
