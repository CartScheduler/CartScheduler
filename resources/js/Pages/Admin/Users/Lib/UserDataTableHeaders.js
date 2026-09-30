/**
 * @deprecated This should be removed after Vue3 Easy Data Table has been removed
 */
// Named, then exported: a JSDoc block sitting directly on `export default` in a
// `.js` file is parsed as an export assignment, and `default` then reads as a
// modifier on it.
const userDataTableHeaders = [
  {
    text: "ID",
    value: "id",
    sortable: true,
    width: "10%",
  },
  {
    text: "Name",
    value: "name",
    sortable: true,
    width: "20%",
  },
  {
    text: "Email",
    value: "email",
    sortable: true,
    width: "20%",
  },
  {
    text: "Phone",
    value: "phone",
    sortable: true,
    width: "20%",
  },
  {
    text: "Gender",
    value: "gender",
    sortable: true,
    width: "20%",
  },
  {
    text: "Active",
    value: "is_enabled",
    sortable: true,
    width: "20%",
  },
  {
    text: "Restricted",
    value: "is_unrestricted",
    sortable: true,
    width: "20%",
  },
  {
    text: "Role",
    value: "role",
    sortable: true,
    width: "20%",
  },
];

export default userDataTableHeaders;
