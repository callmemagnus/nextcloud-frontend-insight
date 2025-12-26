<!--
SPDX-FileCopyrightText: 2025 Magnus Anderssen <magnus@magooweb.com>
SPDX-License-Identifier: AGPL-3.0-or-later
-->
<script lang="ts">
    import EventTable from "./EventTable.svelte";
    import Indicators from "./Indicators.svelte";
    import AdminActions from "./AdminActions.svelte";
    const t = EventTable;
</script>

<div class="container">
    <Indicators />
    <EventTable />
    <AdminActions />
</div>

<style>
    .container {
        padding: 20px 0;
    }
</style>
