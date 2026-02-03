<script setup lang="ts">
import {Link} from '@inertiajs/vue3';
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
import {dashboard} from '@/routes';
import {type NavItem} from '@/types';
import AppLogo from './AppLogo.vue';
import categories from "@/routes/categories";
import products from "@/routes/products";
import orders from "@/routes/orders";

import {LayoutGrid, FolderTree, Package, ShoppingCart, Folder, BookOpen, Banknote} from 'lucide-vue-next';
import transactions from "@/routes/transactions";

const mainNavItems: NavItem[] = [
  {
    title: 'Dashboard',
    href: dashboard(),
    icon: LayoutGrid,
  },
  {
    title: 'Categories',
    href: categories.index(),
    icon: FolderTree,
  },
  {
    title: 'Products',
    href: products.index(),
    icon: Package,
  },
  {
    title: 'Orders',
    href: orders.index(),
    icon: ShoppingCart,
  },
  {
    title: 'Transactions',
    href: transactions.index(),
    icon: Banknote,
  },
];


const footerNavItems: NavItem[] = [

];
</script>

<template>
  <Sidebar collapsible="icon" variant="inset">
    <SidebarHeader>
      <SidebarMenu>
        <SidebarMenuItem>
          <SidebarMenuButton size="lg" as-child>
            <Link :href="dashboard()">
              <AppLogo/>
            </Link>
          </SidebarMenuButton>
        </SidebarMenuItem>
      </SidebarMenu>
    </SidebarHeader>

    <SidebarContent>
      <NavMain :items="mainNavItems"/>
    </SidebarContent>

    <SidebarFooter>
      <NavFooter :items="footerNavItems"/>
      <NavUser/>
    </SidebarFooter>
  </Sidebar>
  <slot/>
</template>
