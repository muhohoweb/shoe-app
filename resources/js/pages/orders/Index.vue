<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Edit, Trash2, Eye, Package } from 'lucide-vue-next';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { toast } from 'vue-sonner'
import { ref, computed } from 'vue'
import Pagination from '@/components/Pagination.vue'

interface Item {
  id: number
  product_id: number
  size: string | null
  color: string | null
  price: number
  quantity: number
  product?: {
    id: number
    name: string
  }
}

interface Order {
  id: number
  uuid: string
  customer_name: string | null
  mpesa_number: string
  mpesa_code: string | null
  amount: number
  payment_status: 'pending' | 'paid' | 'failed'
  tracking_number: string | null
  town: string
  description: string
  status: 'pending' | 'processing' | 'completed' | 'cancelled'
  items: Item[]
  created_at: string
}

const props = defineProps<{
  orders?: {
    data: Order[]
    links: any[]
  }
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: dashboard().url },
  { title: 'Orders' },
]

const ordersList = computed(() => props.orders?.data || [])
const paginationLinks = computed(() => props.orders?.links || [])

const statusColors = {
  pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
  processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
  completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
  cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
}

const paymentStatusColors = {
  pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
  paid: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
  failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
}

// ─── View Modal ─────────────────────────────────────────────────
const isViewDialogOpen = ref(false)
const viewingOrder = ref<Order | null>(null)

const openViewDialog = (order: Order) => {
  viewingOrder.value = order
  isViewDialogOpen.value = true
}

const closeViewDialog = () => {
  isViewDialogOpen.value = false
  viewingOrder.value = null
}

// ─── Edit Modal ─────────────────────────────────────────────────
const isEditDialogOpen = ref(false)
const editingOrder = ref<Order | null>(null)
const editForm = useForm({
  status: '',
  payment_status: '',
  tracking_number: '',
})

const openEditDialog = (order: Order) => {
  editingOrder.value = order
  editForm.status = order.status
  editForm.payment_status = order.payment_status
  editForm.tracking_number = order.tracking_number || ''
  editForm.clearErrors()
  isEditDialogOpen.value = true
}

const closeEditDialog = () => {
  isEditDialogOpen.value = false
  editingOrder.value = null
  editForm.reset()
  editForm.clearErrors()
}

const handleEditSubmit = () => {
  if (!editingOrder.value) return

  editForm.put(`/orders/${editingOrder.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Order Updated', {
        description: `Order #${editingOrder.value?.uuid.slice(0, 8)} has been updated.`,
      })
      closeEditDialog()
    },
    onError: () => {
      toast.error('Update Failed', {
        description: 'Please check the form for errors.',
      })
    },
  })
}

// ─── Delete Modal ───────────────────────────────────────────────
const isDeleteDialogOpen = ref(false)
const deletingOrder = ref<Order | null>(null)

const openDeleteDialog = (order: Order) => {
  deletingOrder.value = order
  isDeleteDialogOpen.value = true
}

const handleDeleteConfirm = () => {
  if (!deletingOrder.value) return

  router.delete(`/orders/${deletingOrder.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Order Deleted', {
        description: `Order #${deletingOrder.value?.uuid.slice(0, 8)} has been deleted.`,
      })
      isDeleteDialogOpen.value = false
      deletingOrder.value = null
    },
    onError: () => {
      toast.error('Delete Failed', {
        description: 'Unable to delete order. Please try again.',
      })
    },
  })
}

const closeDeleteDialog = () => {
  isDeleteDialogOpen.value = false
  deletingOrder.value = null
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-KE', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <Head title="Orders" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
      <div class="relative min-h-[100vh] flex-1 rounded-xl border border-gray-200 bg-white md:min-h-min dark:border-gray-700 dark:bg-gray-800">
        <div class="p-6">
          <div class="mb-4 flex items-center justify-between">
            <h2 class="text-xl font-semibold">Orders</h2>
          </div>

          <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>#</TableHead>
                  <TableHead>Order ID</TableHead>
                  <TableHead>Customer</TableHead>
                  <TableHead>Amount</TableHead>
                  <TableHead>Payment</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Items</TableHead>
                  <TableHead>Date</TableHead>
                  <TableHead class="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow v-if="ordersList.length === 0">
                  <TableCell colspan="9" class="text-center text-gray-500 dark:text-gray-400 h-24">
                    No orders found.
                  </TableCell>
                </TableRow>
                <TableRow v-for="(order, index) in ordersList" :key="order.id">
                  <TableCell class="font-medium">{{ index + 1 }}</TableCell>
                  <TableCell class="font-mono text-sm">{{ order.uuid.slice(0, 8) }}</TableCell>
                  <TableCell>
                    <div>
                      <div class="font-medium">{{ order.customer_name || 'N/A' }}</div>
                      <div class="text-sm text-gray-500 dark:text-gray-400">{{ order.mpesa_number }}</div>
                    </div>
                  </TableCell>
                  <TableCell class="font-medium">KES {{ Number(order.amount).toLocaleString() }}</TableCell>
                  <TableCell>
                    <span
                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                        :class="paymentStatusColors[order.payment_status]"
                    >
                      {{ order.payment_status }}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span
                        class="inline-flex rounded-full px-2 py-1 text-xs font-semibold"
                        :class="statusColors[order.status]"
                    >
                      {{ order.status }}
                    </span>
                  </TableCell>
                  <TableCell>
                    <span class="inline-flex items-center gap-1">
                      <Package class="h-4 w-4 text-gray-400" />
                      {{ order.items?.length || 0 }}
                    </span>
                  </TableCell>
                  <TableCell class="text-sm text-gray-500 dark:text-gray-400">
                    {{ formatDate(order.created_at) }}
                  </TableCell>
                  <TableCell class="text-right">
                    <div class="flex items-center justify-end gap-2">
                      <button
                          @click="openViewDialog(order)"
                          class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                      >
                        <Eye class="h-4 w-4" />
                        View
                      </button>
                      <button
                          @click="openEditDialog(order)"
                          class="inline-flex items-center gap-1 rounded-md bg-blue-100 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800"
                      >
                        <Edit class="h-4 w-4" />
                        Edit
                      </button>
                      <button
                          @click="openDeleteDialog(order)"
                          class="inline-flex items-center gap-1 rounded-md bg-red-100 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-200 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800"
                      >
                        <Trash2 class="h-4 w-4" />
                        Delete
                      </button>
                    </div>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </div>

          <div class="mt-4 flex items-center justify-between">
            <div class="text-sm text-gray-500 dark:text-gray-400">
              Showing {{ ordersList.length }} orders on this page
            </div>
            <Pagination :links="paginationLinks" />
          </div>
        </div>
      </div>
    </div>

    <!-- ─── View Order Dialog ───────────────────────────────────── -->
    <Dialog v-model:open="isViewDialogOpen">
      <DialogContent class="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Order Details</DialogTitle>
          <DialogDescription v-if="viewingOrder">
            Order #{{ viewingOrder.uuid.slice(0, 8) }}
          </DialogDescription>
        </DialogHeader>

        <div v-if="viewingOrder" class="grid gap-4 py-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <Label class="text-muted-foreground">Customer</Label>
              <p class="font-medium">{{ viewingOrder.customer_name || 'N/A' }}</p>
            </div>
            <div>
              <Label class="text-muted-foreground">M-Pesa Number</Label>
              <p class="font-medium">{{ viewingOrder.mpesa_number }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <Label class="text-muted-foreground">Amount</Label>
              <p class="font-medium">KES {{ Number(viewingOrder.amount).toLocaleString() }}</p>
            </div>
            <div>
              <Label class="text-muted-foreground">M-Pesa Code</Label>
              <p class="font-medium font-mono">{{ viewingOrder.mpesa_code || 'N/A' }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <Label class="text-muted-foreground">Payment Status</Label>
              <span
                  class="inline-flex rounded-full px-2 py-1 text-xs font-semibold mt-1"
                  :class="paymentStatusColors[viewingOrder.payment_status]"
              >
                {{ viewingOrder.payment_status }}
              </span>
            </div>
            <div>
              <Label class="text-muted-foreground">Order Status</Label>
              <span
                  class="inline-flex rounded-full px-2 py-1 text-xs font-semibold mt-1"
                  :class="statusColors[viewingOrder.status]"
              >
                {{ viewingOrder.status }}
              </span>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <Label class="text-muted-foreground">Town</Label>
              <p class="font-medium">{{ viewingOrder.town }}</p>
            </div>
            <div>
              <Label class="text-muted-foreground">Tracking Number</Label>
              <p class="font-medium font-mono">{{ viewingOrder.tracking_number || 'N/A' }}</p>
            </div>
          </div>

          <div>
            <Label class="text-muted-foreground">Description</Label>
            <p class="text-sm mt-1">{{ viewingOrder.description }}</p>
          </div>

          <!-- Order Items -->
          <div v-if="viewingOrder.items && viewingOrder.items.length > 0">
            <Label class="text-muted-foreground mb-2 block">Items ({{ viewingOrder.items.length }})</Label>
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Product</TableHead>
                    <TableHead>Size</TableHead>
                    <TableHead>Color</TableHead>
                    <TableHead>Qty</TableHead>
                    <TableHead>Price</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="item in viewingOrder.items" :key="item.id">
                    <TableCell>{{ item.product?.name || 'N/A' }}</TableCell>
                    <TableCell>{{ item.size || '-' }}</TableCell>
                    <TableCell>{{ item.color || '-' }}</TableCell>
                    <TableCell>{{ item.quantity }}</TableCell>
                    <TableCell>KES {{ Number(item.price).toLocaleString() }}</TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" @click="closeViewDialog">Close</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- ─── Edit Order Dialog ───────────────────────────────────── -->
    <Dialog v-model:open="isEditDialogOpen">
      <DialogContent class="sm:max-w-[450px]" @interact-outside="(e) => e.preventDefault()">
        <DialogHeader>
          <DialogTitle>Update Order</DialogTitle>
          <DialogDescription v-if="editingOrder">
            Order #{{ editingOrder.uuid.slice(0, 8) }}
          </DialogDescription>
        </DialogHeader>

        <form @submit.prevent="handleEditSubmit">
          <div class="grid gap-4 py-4">
            <div class="grid gap-2">
              <Label for="edit-status">Order Status</Label>
              <Select v-model="editForm.status" required>
                <SelectTrigger id="edit-status">
                  <SelectValue placeholder="Select status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="pending">Pending</SelectItem>
                  <SelectItem value="processing">Processing</SelectItem>
                  <SelectItem value="completed">Completed</SelectItem>
                  <SelectItem value="cancelled">Cancelled</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="grid gap-2">
              <Label for="edit-payment-status">Payment Status</Label>
              <Select v-model="editForm.payment_status" required>
                <SelectTrigger id="edit-payment-status">
                  <SelectValue placeholder="Select payment status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="pending">Pending</SelectItem>
                  <SelectItem value="paid">Paid</SelectItem>
                  <SelectItem value="failed">Failed</SelectItem>
                </SelectContent>
              </Select>
            </div>

            <div class="grid gap-2">
              <Label for="edit-tracking">Tracking Number</Label>
              <Input
                  id="edit-tracking"
                  v-model="editForm.tracking_number"
                  placeholder="Enter tracking number"
              />
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" @click="closeEditDialog">Cancel</Button>
            <Button type="submit" :disabled="editForm.processing">Save Changes</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- ─── Delete Order Alert Dialog ───────────────────────────── -->
    <AlertDialog v-model:open="isDeleteDialogOpen">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
          <AlertDialogDescription>
            This will permanently delete order
            <span v-if="deletingOrder" class="font-semibold font-mono">#{{ deletingOrder.uuid.slice(0, 8) }}</span>
            and all its items.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel @click="closeDeleteDialog">Cancel</AlertDialogCancel>
          <AlertDialogAction @click="handleDeleteConfirm" class="bg-red-600 hover:bg-red-700">
            Delete
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </AppLayout>
</template>