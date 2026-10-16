/**
 * Options for the user profile's select fields, shared by the edit form and the
 * read-only spouse view so their labels cannot drift apart.
 */
export const genderOptions = [
  { label: "Male", value: "male" },
  { label: "Female", value: "female" },
];

export const appointmentOptions = [
  { label: "Ministerial Servant", value: "ministerial servant" },
  { label: "Elder", value: "elder" },
];

export const servingAsOptions = [
  { label: "Field Missionary", value: "field missionary" },
  { label: "Special Pioneer", value: "special pioneer" },
  { label: "Regular Pioneer", value: "regular pioneer" },
  { label: "Circuit Overseer", value: "circuit overseer" },
  { label: "Bethelite", value: "bethel family member" },
  { label: "Publisher", value: "publisher" },
];

export const maritalStatusOptions = [
  { label: "Single", value: "single" },
  { label: "Married", value: "married" },
  { label: "Separated", value: "separated" },
  { label: "Divorced", value: "divorced" },
  { label: "Widowed", value: "widowed" },
];

/** The option's label for a value, or undefined when the value is not set. */
export const optionLabel = (options: { label: string; value: string }[], value: string | null | undefined) =>
  options.find((option) => option.value === value)?.label;
