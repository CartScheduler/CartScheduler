<template></template>
<!-- <script setup> -->
<!-- import {computed, h, ref} from 'vue'; -->
<!-- import {useForm} from '@inertiajs/vue3'; -->
<!-- import { -->
<!--    FlexRender, -->
<!--    getCoreRowModel, -->
<!--    getFilteredRowModel, -->
<!--    getPaginationRowModel, -->
<!--    getSortedRowModel, -->
<!--    useVueTable, -->
<!-- } from '@tanstack/vue-table'; -->
<!-- import {ChevronDown} from 'lucide-vue-next'; -->
<!-- import AppLayout from '@/Layouts/AppLayout.vue'; -->
<!-- import {Table, TableBody, TableCell, TableHead, TableHeader, TableRow} from '@/Components/ui/table'; -->
<!-- import {Button} from '@/Components/ui/button'; -->
<!-- import {Input} from '@/Components/ui/input'; -->
<!-- import { -->
<!--    DropdownMenu, -->
<!--    DropdownMenuCheckboxItem, -->
<!--    DropdownMenuContent, -->
<!--    DropdownMenuTrigger -->
<!-- } from '@/Components/ui/dropdown-menu'; -->
<!-- import UserDetailPopover from '@/Components/Admin/UserDetailPopover.vue'; -->
<!-- import InputDateRange from "@/Components/FormFields/InputDateRange.vue"; -->

<!-- const props = defineProps({ -->
<!--    data: { -->
<!--        type: Array, -->
<!--        required: true -->
<!--    }, -->
<!--    meta: { -->
<!--        type: Object, -->
<!--        required: true -->
<!--    } -->
<!-- }); -->

<!-- const form = useForm({ -->
<!--    startDate: props.meta.start_date || null, -->
<!--    endDate: props.meta.end_date || null, -->
<!-- }); -->

<!-- const rangeModel = computed({ -->
<!--    get: () => ({startDate: form.startDate, endDate: form.endDate}), -->
<!--    set: (value) => { -->
<!--        form.startDate = value.startDate; -->
<!--        form.endDate = value.endDate; -->
<!--    } -->
<!-- }) -->

<!-- // Global filter -->
<!-- const globalFilter = ref(''); -->

<!-- // Column definitions -->
<!-- const columns = [ -->
<!--    { -->
<!--        accessorKey: 'uid', -->
<!--        header: 'User ID', -->
<!--        enableSorting: true, -->
<!--        enableHiding: true, -->
<!--    }, -->
<!--    { -->
<!--        accessorKey: 'name', -->
<!--        header: 'Name', -->
<!--        enableSorting: true, -->
<!--        enableHiding: false, -->
<!--        cell: ({row}) => { -->
<!--            const user = row.original; -->
<!--            return h(UserDetailPopover, { -->
<!--                userId: user.uid, -->
<!--                triggerText: user.name -->
<!--            }); -->
<!--        } -->
<!--    }, -->
<!--    { -->
<!--        accessorKey: 'email', -->
<!--        header: 'Email', -->
<!--        enableSorting: true, -->
<!--        enableHiding: true, -->
<!--    }, -->
<!--    { -->
<!--        accessorKey: 'mobile_phone', -->
<!--        header: 'Mobile', -->
<!--        enableSorting: true, -->
<!--        enableHiding: true, -->
<!--    }, -->
<!--    { -->
<!--        accessorKey: 'gender', -->
<!--        header: 'Gender', -->
<!--        enableSorting: true, -->
<!--        enableHiding: true, -->
<!--    }, -->
<!--    { -->
<!--        accessorKey: 'account_confirmed', -->
<!--        header: 'Account Confirmed', -->
<!--        enableSorting: true, -->
<!--        enableHiding: true, -->
<!--    }, -->
<!--    { -->
<!--        accessorKey: 'shift_count', -->
<!--        header: 'Shift Count', -->
<!--        enableSorting: true, -->
<!--        enableHiding: true, -->
<!--    }, -->
<!--    { -->
<!--        accessorKey: 'availability_set', -->
<!--        header: 'Availability Set', -->
<!--        enableSorting: true, -->
<!--        enableHiding: true, -->
<!--    }, -->
<!--    { -->
<!--        accessorKey: 'availability_monday', -->
<!--        header: 'Monday', -->
<!--        enableSorting: true, -->
<!--        enableHiding: true, -->
<!--    }, -->
<!--    { -->
<!--        accessorKey: 'availability_tuesday', -->
<!--        header: 'Tuesday', -->
<!--        enableSorting: true, -->
<!--        enableHiding: true, -->
<!--    }, -->
<!--    { -->
<!--        accessorKey: 'availability_wednesday', -->
<!--        header: 'Wednesday', -->
<!--        enableSorting: true, -->
<!--        enableHiding: true, -->
<!--    }, -->
<!--    { -->
<!--        accessorKey: 'availability_thursday', -->
<!--        header: 'Thursday', -->
<!--        enableSorting: true, -->
<!--        enableHiding: true, -->
<!--    }, -->
<!--    { -->
<!--        accessorKey: 'availability_friday', -->
<!--        header: 'Friday', -->
<!--        enableSorting: true, -->
<!--        enableHiding: true, -->
<!--    }, -->
<!--    { -->
<!--        accessorKey: 'availability_saturday', -->
<!--        header: 'Saturday', -->
<!--        enableSorting: true, -->
<!--        enableHiding: true, -->
<!--    }, -->
<!--    { -->
<!--        accessorKey: 'availability_sunday', -->
<!--        header: 'Sunday', -->
<!--        enableSorting: true, -->
<!--        enableHiding: true, -->
<!--    }, -->
<!--    { -->
<!--        accessorKey: 'last_updated_availability', -->
<!--        header: 'Last Updated', -->
<!--        enableSorting: true, -->
<!--        enableHiding: true, -->
<!--        cell: ({row}) => { -->
<!--            const date = row.original.last_updated_availability; -->
<!--            if (!date) return 'Never'; -->
<!--            return new Date(date).toLocaleDateString(); -->
<!--        } -->
<!--    }, -->
<!-- ]; -->

<!-- // Set up the table -->
<!-- const table = useVueTable({ -->
<!--    get data() { -->
<!--        return props.data -->
<!--    }, -->
<!--    get columns() { -->
<!--        return columns -->
<!--    }, -->
<!--    getCoreRowModel: getCoreRowModel(), -->
<!--    getPaginationRowModel: getPaginationRowModel(), -->
<!--    getSortedRowModel: getSortedRowModel(), -->
<!--    getFilteredRowModel: getFilteredRowModel(), -->
<!--    initialState: { -->
<!--        pagination: { -->
<!--            pageSize: 15, -->
<!--        }, -->
<!--    }, -->
<!--    state: { -->
<!--        get globalFilter() { -->
<!--            return globalFilter.value; -->
<!--        }, -->
<!--    }, -->
<!--    onGlobalFilterChange: (value) => { -->
<!--        globalFilter.value = value; -->
<!--    }, -->
<!--    globalFilterFn: (row, columnId, filterValue) => { -->
<!--        const search = filterValue.toLowerCase(); -->

<!--        // Search through name, email, and mobile values -->
<!--        if (row.getValue('name')?.toLowerCase().includes(search)) return true; -->
<!--        if (row.getValue('email')?.toLowerCase().includes(search)) return true; -->
<!--        return !!row.getValue('mobile_phone')?.toLowerCase().includes(search); -->

<!--    }, -->
<!-- }); -->

<!-- // Handle date filter submission -->
<!-- const filterByDate = () => { -->
<!--    window.location = route('admin.reports.users-availability', { -->
<!--        start_date: form.startDate, -->
<!--        end_date: form.endDate, -->
<!--    }); -->
<!-- }; -->
<!-- </script> -->

<!-- <template> -->
<!--    <AppLayout full-width title="User Availability Report"> -->
<!--        <template #header> -->
<!--            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight"> -->
<!--                User Availability Report -->
<!--            </h2> -->
<!--        </template> -->

<!--        <template #page-top> -->
<!--            &lt;!&ndash; Date Filter &ndash;&gt; -->
<!--            <div class="grid grid-cols-1 sm:grid-cols-[auto_auto] justify-start gap-3"> -->
<!--                &lt;!&ndash;            <div class="flex flex-col sm:flex-row items-end sm:justify-start gap-3 mb-6">&ndash;&gt; -->
<!--                <InputDateRange v-model="rangeModel"/> -->
<!--                <PButton @click="filterByDate">Apply Filter</PButton> -->
<!--            </div> -->

<!--            &lt;!&ndash; Data Table Controls &ndash;&gt; -->
<!--            <div class="grid grid-cols-1 sm:grid-cols-2 justify-between gap-3"> -->
<!--                <Input -->
<!--                    placeholder="Search users..." -->
<!--                    v-model="globalFilter" -->
<!--                    class="mt-3 w-full sm:w-64" -->
<!--                /> -->

<!--                <DropdownMenu> -->
<!--                    <DropdownMenuTrigger as-child> -->
<!--                        <PButton variant="outline" class="w-auto justify-self-end"> -->
<!--                            Columns -->
<!--                            <ChevronDown/> -->
<!--                        </PButton> -->
<!--                    </DropdownMenuTrigger> -->
<!--                    <DropdownMenuContent align="end"> -->
<!--                        <DropdownMenuCheckboxItem -->
<!--                            v-for="column in table.getAllColumns().filter((column) => column.getCanHide())" -->
<!--                            :key="column.id" -->
<!--                            class="capitalize" -->
<!--                            :checked="column.getIsVisible()" -->
<!--                            @update:checked="(value) => column.toggleVisibility(!!value)"> -->
<!--                            {{ column.id }} -->
<!--                        </DropdownMenuCheckboxItem> -->
<!--                    </DropdownMenuContent> -->
<!--                </DropdownMenu> -->
<!--            </div> -->

<!--        </template> -->

<!--        <div class="overflow-x-scroll"> -->

<!--            &lt;!&ndash; Data Table &ndash;&gt; -->
<!--            <div class="border rounded-md"> -->
<!--                <Table> -->
<!--                    <TableHeader> -->
<!--                        <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id"> -->
<!--                            <TableHead v-for="header in headerGroup.headers" :key="header.id" class="whitespace-nowrap"> -->
<!--                                <div -->
<!--                                    v-if="!header.isPlaceholder" -->
<!--                                    class="flex items-center" -->
<!--                                    :class="{ 'cursor-pointer select-none': header.column.getCanSort() }" -->
<!--                                    @click="header.column.getToggleSortingHandler()"> -->
<!--                                    {{ header.column.columnDef.header }} -->

<!--                                    <span v-if="header.column.getCanSort()" class="ml-1"> -->
<!--                                                  <template v-if="header.column.getIsSorted() === 'asc'">▲</template> -->
<!--                                                  <template -->
<!--                                                      v-else-if="header.column.getIsSorted() === 'desc'">▼</template> -->
<!--                                                  <template v-else>⇕</template> -->
<!--                                                </span> -->
<!--                                </div> -->
<!--                            </TableHead> -->
<!--                        </TableRow> -->
<!--                    </TableHeader> -->
<!--                    <TableBody> -->
<!--                        <template v-if="table.getRowModel().rows?.length"> -->
<!--                            <TableRow -->
<!--                                v-for="row in table.getRowModel().rows" -->
<!--                                :key="row.id" -->
<!--                                :data-state="row.getIsSelected() ? 'selected' : undefined"> -->
<!--                                <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id"> -->
<!--                                    <FlexRender -->
<!--                                        :render="cell.column.columnDef.cell" -->
<!--                                        :props="cell.getContext()" -->
<!--                                    /> -->
<!--                                </TableCell> -->
<!--                            </TableRow> -->
<!--                        </template> -->
<!--                        <template v-else> -->
<!--                            <TableRow> -->
<!--                                <TableCell :colspan="columns.length" class="h-24 text-center"> -->
<!--                                    No results found. -->
<!--                                </TableCell> -->
<!--                            </TableRow> -->
<!--                        </template> -->
<!--                    </TableBody> -->
<!--                </Table> -->
<!--            </div> -->

<!--            &lt;!&ndash; Pagination &ndash;&gt; -->
<!--            <div class="flex items-center justify-end py-4 space-x-2"> -->
<!--                <PButton -->
<!--                    variant="outline" -->
<!--                    size="sm" -->
<!--                    :disabled="!table.getCanPreviousPage()" -->
<!--                    @click="table.previousPage()"> -->
<!--                    Previous -->
<!--                </PButton> -->
<!--                Page {{ table.getState().pagination.pageIndex + 1 }} of {{ table.getPageCount() }} -->
<!--                <PButton -->
<!--                    variant="outline" -->
<!--                    size="sm" -->
<!--                    :disabled="!table.getCanNextPage()" -->
<!--                    @click="table.nextPage()"> -->
<!--                    Next -->
<!--                </PButton> -->
<!--            </div> -->
<!--        </div> -->
<!--    </AppLayout> -->
<!-- </template> -->
