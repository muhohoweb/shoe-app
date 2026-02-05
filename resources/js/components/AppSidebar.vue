<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import AppLogo from './AppLogo.vue';
import categories from "@/routes/categories";
import products from "@/routes/products";
import orders from "@/routes/orders";
import jobs from "@/routes/jobs";
import transactions from "@/routes/transactions";
import {LayoutGrid, FolderTree, Package, ShoppingCart, Banknote, Clock} from 'lucide-vue-next';

const mainNavItems: NavItem[] = [
  { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
  { title: 'Categories', href: categories.index(), icon: FolderTree },
  { title: 'Products', href: products.index(), icon: Package },
  { title: 'Orders', href: orders.index(), icon: ShoppingCart },
  { title: 'Transactions', href: transactions.index(), icon: Banknote },
  { title: 'Schedules', href: jobs.index(), icon: Clock },
];

const footerNavItems: NavItem[] = [];
</script>

<template>
  <Sidebar
      collapsible="icon"
      variant="inset"
      class="border-r border-stone-200 bg-gradient-to-b from-stone-50 to-stone-100 dark:border-stone-800 dark:from-stone-900 dark:to-stone-950"
  >
    <SidebarHeader class="border-b border-stone-200/60 dark:border-stone-800/60">
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton size="lg" as-child>
            <Link :href="dashboard()">
              <AppLogo />
            </Link>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
      <NavMain :items="mainNavItems" />
    </SidebarContent>

    <SidebarFooter class="border-t border-stone-200/60 dark:border-stone-800/60">
      <NavFooter :items="footerNavItems" />
      <NavUser />
    </SidebarFooter>
  </Sidebar>
  <slot />
</template>